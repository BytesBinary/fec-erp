<?php

namespace App\Services\Clearance;

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\ClearanceApproval;
use App\Models\ClearanceEvent;
use App\Models\ClearanceRequest;
use App\Models\ClearanceStage;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileService;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\ResourceScope;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The clearance state machine (spec §8.4). Every change of status goes through
 * {@see self::transition()}, which validates the current state, applies the
 * optimistic lock (`version`) and writes the `clearance_events` row.
 */
class ClearanceService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected AuditLogger $audit,
        protected EligibilityChecker $eligibility,
        protected StageResolver $stages,
        protected ApproverResolver $approvers,
        protected HashChain $chain,
        protected StaffSignatureService $signatures,
        protected RequestNumberGenerator $numbers,
        protected ClearanceNotifier $notifier,
        protected ProfileService $profiles,
    ) {}

    /**
     * Eligibility of the acting student (or of `$student` for staff).
     */
    public function checkEligibility(User $actor, ?Student $student = null): EligibilityResult
    {
        $student ??= $this->studentOf($actor);

        if ($student->user_id === $actor->getKey()) {
            $this->authorizer->authorize($actor, 'clearance:apply', ResourceScope::forOwner($actor->getKey()));
        } else {
            $this->authorizer->authorize($actor, 'clearance:view', $student);
        }

        return $this->eligibility->check($student);
    }

    /**
     * The student applies for their own clearance.
     */
    public function apply(User $actor): ClearanceRequest
    {
        $this->authorizer->authorize($actor, 'clearance:apply', ResourceScope::forOwner($actor->getKey()));

        $student = $this->studentOf($actor);
        $result = $this->eligibility->check($student);

        if (! $result->eligible()) {
            throw new InvalidStateException(__('erp.clearance.not_eligible'), ['reasons' => $result->reasons]);
        }

        $request = $this->audit->as($actor, fn (): ClearanceRequest => DB::transaction(function () use ($student, $actor): ClearanceRequest {
            $request = ClearanceRequest::query()->create([
                'request_no' => $this->numbers->next(),
                'verify_code' => $this->numbers->verifyCode(),
                'student_id' => $student->getKey(),
                'status' => ClearanceStatus::Submitted,
                'submitted_at' => now(),
                'version' => 1,
            ]);

            $this->event($request, null, ClearanceStatus::Submitted, $actor, 'Submitted by student');
            $this->profiles->lockFieldsForClearance($student);

            return $this->moveToNextStage($request, null, $actor, 'Submitted');
        }));

        $this->audit->record('clearance.submitted', $request, null, ['request_no' => $request->request_no], $actor);
        $this->notifier->submitted($request->fresh(['student.user', 'currentStage.approverRole']));

        return $request;
    }

    /**
     * The acting student's newest request.
     */
    public function mine(User $actor): ?ClearanceRequest
    {
        $student = $this->profiles->studentOf($actor);

        if ($student === null) {
            return null;
        }

        $this->authorizer->authorize($actor, 'clearance:view', ResourceScope::forOwner($actor->getKey()));

        return ClearanceRequest::query()->where('student_id', $student->getKey())->latest('id')->first();
    }

    public function get(User $actor, ClearanceRequest|int $request): ClearanceRequest
    {
        $model = $request instanceof ClearanceRequest ? $request : (ClearanceRequest::query()->find($request) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'clearance request'])));

        $this->authorizer->authorize($actor, 'clearance:view', $model);

        return $model->load(['student.user', 'student.department', 'student.program', 'currentStage.approverRole']);
    }

    /**
     * Requests waiting for the acting user's decision (their role at the
     * current stage and their scope covering the student).
     *
     * @return Collection<int, ClearanceRequest>
     */
    public function pendingFor(User $actor): Collection
    {
        $this->authorizer->authorize($actor, 'clearance:view');

        $roleIds = $actor->roles()->pluck('roles.id');

        return ClearanceRequest::query()
            ->where('status', ClearanceStatus::Pending->value)
            ->whereHas('currentStage', fn (Builder $query) => $query->whereIn('approver_role_id', $roleIds))
            ->with(['student.user', 'student.department', 'student.program', 'currentStage.approverRole'])
            ->orderBy('submitted_at')
            ->get()
            ->filter(fn (ClearanceRequest $request): bool => $this->approvers->canAct($actor, $request))
            ->values();
    }

    public function approve(User $actor, ClearanceRequest|int $request, ?string $remarks = null, ?int $expectedVersion = null): ClearanceRequest
    {
        $result = $this->audit->as($actor, fn (): ClearanceRequest => DB::transaction(function () use ($actor, $request, $remarks, $expectedVersion): ClearanceRequest {
            $fresh = $this->lock($request, $expectedVersion);
            $stage = $this->assertCanDecide($actor, $fresh);

            $snapshot = $this->signatures->snapshot($actor, $fresh->getKey(), $stage->key);
            $this->recordApproval($fresh, $stage, 'approved', $actor, $remarks, $snapshot);

            return $this->moveToNextStage($fresh, $stage, $actor, $remarks, $expectedVersion);
        }));

        $this->audit->record('clearance.approved', $result, null, ['stage' => $result->approvals()->reorder('id', 'desc')->where('decision', 'approved')->first()?->stage_id], $actor);

        if ($result->status === ClearanceStatus::ReadyForCollection) {
            $this->notifier->approved($result, $result->approvals()->reorder('id', 'desc')->where('decision', 'approved')->firstOrFail()->stage);
            $this->notifier->ready($result);
        } else {
            $this->notifier->approved($result, $result->approvals()->reorder('id', 'desc')->where('decision', 'approved')->firstOrFail()->stage);
        }

        return $result;
    }

    public function reject(User $actor, ClearanceRequest|int $request, string $reason, ?int $expectedVersion = null): ClearanceRequest
    {
        if (trim($reason) === '') {
            throw new ValidationException(__('erp.clearance.reject_reason_required'));
        }

        $result = $this->audit->as($actor, fn (): ClearanceRequest => DB::transaction(function () use ($actor, $request, $reason, $expectedVersion): ClearanceRequest {
            $fresh = $this->lock($request, $expectedVersion);
            $stage = $this->assertCanDecide($actor, $fresh);

            $this->recordApproval($fresh, $stage, 'rejected', $actor, $reason, null);

            return $this->transition($fresh, ClearanceStatus::Rejected, $actor, ['chain_hash' => $this->chain->lastHash($fresh)], $expectedVersion, "Rejected at {$stage->label}: {$reason}");
        }));

        $stage = $result->currentStage;
        $this->audit->record('clearance.rejected', $result, null, ['stage' => $stage->key, 'reason' => $reason], $actor);
        $this->notifier->rejected($result->load('student.user'), $stage, $reason);

        return $result;
    }

    /**
     * After a rejection the student resubmits; the request resumes at the
     * stage that rejected it and earlier approvals stay valid (spec §8.1).
     */
    public function resubmit(User $actor, ClearanceRequest|int $request, ?int $expectedVersion = null): ClearanceRequest
    {
        $result = $this->audit->as($actor, fn (): ClearanceRequest => DB::transaction(function () use ($actor, $request, $expectedVersion): ClearanceRequest {
            $fresh = $this->lock($request, $expectedVersion);
            $this->assertOwner($actor, $fresh);

            if ($fresh->status !== ClearanceStatus::Rejected) {
                throw new InvalidStateException(__('erp.clearance.not_rejected'));
            }

            return $this->transition($fresh, ClearanceStatus::Pending, $actor, [], $expectedVersion, 'Resubmitted by student');
        }));

        $this->audit->record('clearance.resubmitted', $result, null, null, $actor);
        $this->notifier->submitted($result->load(['student.user', 'currentStage.approverRole']), resubmitted: true);

        return $result;
    }

    /**
     * Student before the first approval, or super admin with a reason.
     */
    public function cancel(User $actor, ClearanceRequest|int $request, ?string $reason = null): ClearanceRequest
    {
        $result = $this->audit->as($actor, fn (): ClearanceRequest => DB::transaction(function () use ($actor, $request, $reason): ClearanceRequest {
            $fresh = $this->lock($request, null);

            if ($fresh->student->user_id === $actor->getKey()) {
                $this->authorizer->authorize($actor, 'clearance:cancel', ResourceScope::forOwner($actor->getKey()));

                if ($fresh->approvals()->where('decision', 'approved')->exists()) {
                    throw new InvalidStateException(__('erp.clearance.cannot_cancel_after_approval'));
                }
            } else {
                $this->authorizer->authorize($actor, 'clearance:cancel', $fresh);

                if (trim((string) $reason) === '') {
                    throw new ValidationException(__('erp.clearance.cancel_reason_required'));
                }
            }

            return $this->transition($fresh, ClearanceStatus::Cancelled, $actor, ['cancelled_at' => now(), 'cancel_reason' => $reason, 'current_stage_id' => null], null, $reason ?? 'Cancelled by student');
        }));

        $this->audit->record('clearance.cancelled', $result, null, ['reason' => $reason], $actor);

        return $result;
    }

    /**
     * Stage-by-stage timeline for the student / approver views: the latest
     * decision per stage, plus the stages still ahead.
     *
     * @return list<array{stage: ClearanceStage, status: string, approver: ?string, designation: ?string, at: ?\Carbon\CarbonInterface, remarks: ?string}>
     */
    public function timeline(ClearanceRequest $request): array
    {
        $approvals = $request->approvals()->with('stage')->get()->groupBy('stage_id')->map(fn (Collection $rows): ClearanceApproval => $rows->last());
        $stageIds = $approvals->keys()->merge($this->stages->chain()->pluck('id'))->unique();
        $stages = ClearanceStage::query()->whereIn('id', $stageIds)->orderBy('order')->orderBy('id')->get();

        return $stages->map(function (ClearanceStage $stage) use ($approvals, $request): array {
            $approval = $approvals->get($stage->getKey());

            $status = match (true) {
                $approval?->decision === 'approved' => 'approved',
                $approval?->decision === 'skipped' => 'skipped',
                $approval?->decision === 'rejected' && $request->status === ClearanceStatus::Rejected && $request->current_stage_id === $stage->getKey() => 'rejected',
                $request->current_stage_id === $stage->getKey() && $request->status === ClearanceStatus::Pending => 'pending',
                default => 'waiting',
            };

            return [
                'stage' => $stage,
                'status' => $status,
                'approver' => $approval?->approver_name,
                'designation' => $approval?->approver_designation,
                'at' => $approval?->decided_at,
                'remarks' => $approval?->remarks,
            ];
        })->values()->all();
    }

    /**
     * Fresh row, optionally checked against the version the caller saw.
     */
    protected function lock(ClearanceRequest|int $request, ?int $expectedVersion): ClearanceRequest
    {
        $id = $request instanceof ClearanceRequest ? $request->getKey() : $request;

        $fresh = ClearanceRequest::query()->lockForUpdate()->with(['student.user', 'currentStage.approverRole'])->find($id)
            ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'clearance request']));

        if ($expectedVersion !== null && $expectedVersion !== $fresh->version) {
            throw new ConflictException(__('erp.errors.conflict'), ['expected_version' => $expectedVersion, 'current_version' => $fresh->version]);
        }

        return $fresh;
    }

    protected function assertCanDecide(User $actor, ClearanceRequest $request): ClearanceStage
    {
        if ($request->status !== ClearanceStatus::Pending || $request->currentStage === null) {
            throw new InvalidStateException(__('erp.clearance.not_pending', ['status' => $request->status->label()]));
        }

        if (! $this->approvers->canAct($actor, $request)) {
            throw new ForbiddenException(__('erp.clearance.not_your_stage', ['stage' => $request->currentStage->label]), ['stage' => $request->currentStage->key]);
        }

        return $request->currentStage;
    }

    protected function assertOwner(User $actor, ClearanceRequest $request): void
    {
        if ($request->student->user_id !== $actor->getKey()) {
            throw new ForbiddenException(__('erp.errors.forbidden', ['permission' => 'clearance:apply']));
        }
    }

    protected function studentOf(User $actor): Student
    {
        return $this->profiles->studentOf($actor) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'student']));
    }

    /**
     * Moves the request to the first non-skipped stage after `$after`
     * (recording skipped stages), or to READY_FOR_COLLECTION when none is left.
     */
    protected function moveToNextStage(ClearanceRequest $request, ?ClearanceStage $after, User $actor, ?string $note, ?int $expectedVersion = null): ClearanceRequest
    {
        $next = null;

        foreach ($this->stages->stagesAfter($after) as $stage) {
            if ($this->stages->shouldSkip($stage, $request->student)) {
                $this->recordApproval($request, $stage, 'skipped', null, __('erp.clearance.skipped_note'), null);

                continue;
            }

            $next = $stage;
            break;
        }

        $changes = ['current_stage_id' => $next?->getKey(), 'chain_hash' => $this->chain->lastHash($request)];

        if ($next === null) {
            return $this->transition($request, ClearanceStatus::ReadyForCollection, $actor, $changes + ['ready_at' => now()], $expectedVersion, 'All stages approved');
        }

        return $this->transition($request, ClearanceStatus::Pending, $actor, $changes, $expectedVersion, $note);
    }

    /**
     * The only place a request's status changes.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function transition(ClearanceRequest $request, ClearanceStatus $to, ?User $actor, array $changes = [], ?int $expectedVersion = null, ?string $note = null): ClearanceRequest
    {
        $current = ClearanceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

        if ($expectedVersion !== null && $expectedVersion !== $current->version) {
            throw new ConflictException(__('erp.errors.conflict'), ['expected_version' => $expectedVersion, 'current_version' => $current->version]);
        }

        if (! $current->status->canTransitionTo($to)) {
            throw new InvalidStateException(__('erp.clearance.invalid_transition', ['from' => $current->status->label(), 'to' => $to->label()]), ['from' => $current->status->value, 'to' => $to->value]);
        }

        $changes['status'] = $to->value;
        $changes['version'] = $current->version + 1;
        $changes['updated_at'] = now();

        $updated = ClearanceRequest::query()->whereKey($current->getKey())->where('version', $current->version)->update($changes);

        if ($updated !== 1) {
            throw new ConflictException(__('erp.errors.conflict'), ['current_version' => $current->version]);
        }

        $this->event($current, $current->status, $to, $actor, $note);

        return ClearanceRequest::query()->with(['student.user', 'currentStage.approverRole'])->findOrFail($current->getKey());
    }

    protected function event(ClearanceRequest $request, ?ClearanceStatus $from, ClearanceStatus $to, ?User $actor, ?string $note): void
    {
        ClearanceEvent::query()->create([
            'clearance_request_id' => $request->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'channel' => RequestContext::current()->channel()->value,
            'note' => $note,
        ]);
    }

    /**
     * Appends a decision to the hash chain.
     *
     * @param  array{path: string, sha256: string}|null  $snapshot
     */
    protected function recordApproval(ClearanceRequest $request, ClearanceStage $stage, string $decision, ?User $actor, ?string $remarks, ?array $snapshot): ClearanceApproval
    {
        $decidedAt = now()->startOfSecond();
        $previous = $this->chain->lastHash($request);

        $data = [
            'stage_id' => $stage->getKey(),
            'decision' => $decision,
            'approver_user_id' => $actor?->getKey(),
            'approver_name' => $actor?->name,
            'approver_designation' => $actor === null ? null : $this->designationOf($actor, $stage),
            'signature_sha256' => $snapshot['sha256'] ?? null,
            'remarks' => $remarks,
            'decided_at' => $decidedAt->toIso8601String(),
        ];

        return ClearanceApproval::query()->create([
            'clearance_request_id' => $request->getKey(),
            'stage_id' => $stage->getKey(),
            'decision' => $decision,
            'approver_user_id' => $data['approver_user_id'],
            'approver_name' => $data['approver_name'],
            'approver_designation' => $data['approver_designation'],
            'signature_snapshot_path' => $snapshot['path'] ?? null,
            'signature_sha256' => $data['signature_sha256'],
            'remarks' => $remarks,
            'ip' => RequestContext::current()->ip(),
            'decided_at' => $decidedAt,
            'prev_hash' => $previous,
            'hash' => $this->chain->hashFor($previous, $request, $data),
        ]);
    }

    protected function designationOf(User $actor, ClearanceStage $stage): string
    {
        $designation = $actor->teacher?->designation?->name ?? $actor->staff?->designation?->name;

        return $designation !== null ? "{$designation}, {$stage->label}" : $stage->label;
    }
}
