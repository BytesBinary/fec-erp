<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\ClearanceRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\ClearanceStageService;
use App\Services\Clearance\HashChain;

/**
 * Online clearance (spec §8): student, approver and administration-office tools.
 */
final class ClearanceTools
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(ClearanceRequest $request, bool $withTimeline = true): array
    {
        $request->loadMissing(['student.user', 'student.department', 'currentStage']);

        $data = [
            'id' => $request->id,
            'request_no' => $request->request_no,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'current_stage' => $request->currentStage?->key,
            'student' => ['id' => $request->student->id, 'name' => $request->student->user->name, 'roll_number' => $request->student->roll_number, 'department' => $request->student->department?->code],
            'version' => $request->version,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'ready_at' => $request->ready_at?->toIso8601String(),
            'collected_at' => $request->collected_at?->toIso8601String(),
        ];

        if ($withTimeline) {
            $data['timeline'] = array_map(fn (array $row): array => [
                'stage' => $row['stage']->key, 'label' => $row['stage']->label, 'status' => $row['status'], 'approver' => $row['approver'],
                'at' => $row['at']?->toIso8601String(), 'remarks' => $row['remarks'],
            ], app(ClearanceService::class)->timeline($request));
        }

        return $data;
    }

    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $service = fn (): ClearanceService => app(ClearanceService::class);
        $requestParam = fn (): Param => Param::integer('request_id', 'The clearance request id.', required: true);
        $preview = fn (User $actor, array $args): array => self::describe($service()->get($actor, (int) $args['request_id']));

        return [
            ToolDefinition::make('clearance_check_eligibility', 'Check clearance eligibility', 'Check whether a student can apply for clearance, with the exact reasons when not (incomplete profile, missing credits, results awaiting publication, an active request, inactive account). Students omit student_id.')
                ->permission('clearance:view')
                ->params(Param::integer('student_id', 'Student id (staff only).'))
                ->covers(ClearanceService::class.'::checkEligibility')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $result = $service()->checkEligibility($actor, isset($args['student_id']) ? Student::query()->findOrFail($args['student_id']) : null);

                    return ['summary' => $result->eligible() ? 'Eligible to apply.' : 'Not eligible: '.implode(' ', array_column($result->reasons, 'message')), 'data' => $result->toArray()];
                }),

            ToolDefinition::make('clearance_apply', 'Apply for clearance', 'Student applies for clearance. Checks eligibility, creates request CLR-YYYY-NNNNNN and starts the approval chain (hall, library, department, head). Name, parents and date of birth lock afterwards.')
                ->permission('clearance:apply')
                ->creates()
                ->covers(ClearanceService::class.'::apply')
                ->handler(function (User $actor) use ($service): array {
                    $request = $service()->apply($actor);

                    return ['summary' => "Applied: {$request->request_no}.", 'data' => self::describe($request), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_get_my_status', 'My clearance status', 'Get the signed-in student\'s newest clearance request with its stage-by-stage timeline, approvers and rejection remarks.')
                ->permission('clearance:apply')
                ->covers(ClearanceService::class.'::mine', ClearanceService::class.'::timeline')
                ->handler(function (User $actor) use ($service): array {
                    $request = $service()->mine($actor);

                    return $request === null ? ['summary' => 'You have not applied for clearance.', 'data' => null] : ['summary' => "{$request->request_no}: {$request->status->label()}.", 'data' => self::describe($request)];
                }),

            ToolDefinition::make('clearance_resubmit', 'Resubmit clearance', 'After a rejection, the student fixes the problem and resubmits. The request resumes at the stage that rejected it; earlier approvals stay valid.')
                ->permission('clearance:apply')
                ->write()
                ->covers(ClearanceService::class.'::resubmit')
                ->handler(function (User $actor) use ($service): array {
                    $mine = $service()->mine($actor);
                    $request = $service()->resubmit($actor, $mine?->id ?? 0);

                    return ['summary' => 'Resubmitted; waiting at '.($request->currentStage?->label ?? 'the next stage').'.', 'data' => self::describe($request), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_cancel', 'Cancel clearance', 'Cancel a clearance request: the student before the first approval, or super admin with a reason. Needs confirm=true.')
                ->permission('clearance:cancel')
                ->params(Param::integer('request_id', 'Request id; students may omit it.'), Param::string('reason', 'Reason (required for super admin).'))
                ->destructive(fn (User $actor, array $args): array => isset($args['request_id']) ? self::describe($service()->get($actor, (int) $args['request_id']), false) : ['request' => 'your newest request'])
                ->covers(ClearanceService::class.'::cancel')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $id = $args['request_id'] ?? $service()->mine($actor)?->id ?? 0;
                    $request = $service()->cancel($actor, (int) $id, $args['reason'] ?? null);

                    return ['summary' => "Cancelled {$request->request_no}.", 'data' => self::describe($request, false), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_list_pending_for_me', 'Clearances waiting for me', 'List clearance requests waiting for the signed-in approver\'s decision (their role at the current stage, within their hall/department scope).')
                ->permission('clearance:approve')
                ->covers(ClearanceService::class.'::pendingFor')
                ->handler(function (User $actor) use ($service): array {
                    $rows = $service()->pendingFor($actor);

                    return ['summary' => $rows->count().' request(s) waiting for you.', 'data' => $rows->map(fn (ClearanceRequest $request): array => self::describe($request, false))->all()];
                }),

            ToolDefinition::make('clearance_get', 'Get clearance', 'Get one clearance request with its timeline. Approvers also see the student\'s dues through hall_dues_get / library_dues_get.')
                ->permission('clearance:view')
                ->params($requestParam())
                ->covers(ClearanceService::class.'::get')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $request = $service()->get($actor, (int) $args['request_id']);

                    return ['summary' => "{$request->request_no}: {$request->status->label()}.", 'data' => self::describe($request)];
                }),

            ToolDefinition::make('clearance_approve', 'Approve clearance', 'Approve the clearance at the current stage. Only the approver of that stage (matching hall/department) may do it, and they need a signature image uploaded. Needs confirm=true; without it you get a preview of the request.')
                ->permission('clearance:approve')
                ->params($requestParam(), Param::string('remarks', 'Optional remarks printed in the timeline.'), Param::integer('expected_version', 'Version from clearance_get; protects against two approvers acting at once.'))
                ->destructive($preview)
                ->covers(ClearanceService::class.'::approve')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $request = $service()->approve($actor, (int) $args['request_id'], $args['remarks'] ?? null, isset($args['expected_version']) ? (int) $args['expected_version'] : null);

                    return ['summary' => "Approved; request is now {$request->status->label()}.", 'data' => self::describe($request), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_reject', 'Reject clearance', 'Reject the clearance at the current stage with a reason the student will see. Needs confirm=true.')
                ->permission('clearance:reject')
                ->params($requestParam(), Param::string('reason', 'Why it is rejected (required).', true), Param::integer('expected_version', 'Version from clearance_get.'))
                ->destructive($preview)
                ->covers(ClearanceService::class.'::reject')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $request = $service()->reject($actor, (int) $args['request_id'], $args['reason'], isset($args['expected_version']) ? (int) $args['expected_version'] : null);

                    return ['summary' => 'Rejected; the student was notified.', 'data' => self::describe($request), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_search', 'Search clearances', 'Administration office: search clearances by q (student roll, name or request number), department_id, session (e.g. 2021-2022) or status. Default status is ready_for_collection; pass status=all for every status.')
                ->permission('clearance:search')
                ->params(Param::string('q', 'Roll number, name or request number.'), Param::integer('department_id', 'Department id.'), Param::string('session', 'Session, e.g. 2021-2022.'), Param::string('status', 'A status value or "all".'), Param::integer('limit', 'Max rows, up to 100.'))
                ->covers(ClearanceService::class.'::search')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $rows = $service()->search($actor, array_diff_key($args, ['limit' => 1]), (int) ($args['limit'] ?? 50));

                    return ['summary' => $rows->count().' clearance(s) found.', 'data' => $rows->map(fn (ClearanceRequest $request): array => self::describe($request, false))->all()];
                }),

            ToolDefinition::make('clearance_print', 'Print clearance', 'Record that the office printed a fully approved clearance (first print moves it to PRINTED; reprints are marked DUPLICATE). Returns the print and PDF URLs to open in the browser. Needs confirm=true.')
                ->permission('clearance:print')
                ->params($requestParam())
                ->destructive($preview)
                ->covers(ClearanceService::class.'::recordPrint')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $print = $service()->recordPrint($actor, (int) $args['request_id'], 'html');

                    return ['summary' => $print->is_duplicate ? 'Reprint recorded (DUPLICATE).' : 'Print recorded.', 'data' => ['print_id' => $print->id, 'duplicate' => $print->is_duplicate, 'print_url' => route('clearance.print', ['clearanceRequest' => $args['request_id'], 'print' => $print->id]), 'pdf_url' => route('clearance.pdf', ['clearanceRequest' => $args['request_id'], 'print' => $print->id])], 'entity' => ['ClearanceRequest', $args['request_id']]];
                }),

            ToolDefinition::make('clearance_mark_collected', 'Mark clearance collected', 'Record the hand-over of the printed, Principal-signed and sealed clearance. Say whether the student\'s ID was verified. Needs confirm=true.')
                ->permission('clearance:mark_collected')
                ->params($requestParam(), Param::boolean('id_verified', 'True if the student showed their ID.', true))
                ->destructive($preview)
                ->covers(ClearanceService::class.'::markCollected')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $request = $service()->markCollected($actor, (int) $args['request_id'], (bool) $args['id_verified']);

                    return ['summary' => 'Marked as collected.', 'data' => self::describe($request, false), 'entity' => ['ClearanceRequest', $request->id]];
                }),

            ToolDefinition::make('clearance_verify_integrity', 'Verify clearance integrity', 'Recompute the tamper-evidence hash chain of a clearance and report whether it is intact.')
                ->permission('clearance:view')
                ->params($requestParam())
                ->covers(HashChain::class.'::verify')
                ->handler(function (User $actor, array $args) use ($service): array {
                    $result = app(HashChain::class)->verify($service()->get($actor, (int) $args['request_id']));

                    return ['summary' => $result['intact'] ? 'The approval chain is intact.' : 'WARNING: the approval chain was altered.', 'data' => $result];
                }),

            ToolDefinition::make('clearance_stage_config_get', 'Clearance stage config', 'Get the configured clearance stages in order (key, approver role, scope rule, skippable, active).')
                ->permission('clearance_stage:manage')
                ->covers(ClearanceStageService::class.'::all')
                ->handler(fn (User $actor): array => ['summary' => 'Clearance stages.', 'data' => app(ClearanceStageService::class)->all($actor)->map(fn ($stage): array => ['id' => $stage->id, 'key' => $stage->key, 'order' => $stage->order, 'role' => $stage->approverRole->name, 'scope_rule' => $stage->scope_rule, 'skippable' => $stage->skippable, 'active' => $stage->active])->all()]),

            ToolDefinition::make('clearance_stage_config_update', 'Update clearance stage', 'Toggle a stage\'s active or skippable flag, or move it up/down in the chain. Needs confirm=true because it changes how every future clearance runs.')
                ->permission('clearance_stage:manage')
                ->params(Param::integer('id', 'Stage id.', true), Param::enum('action', 'toggle_active, toggle_skippable, move_up or move_down.', ['toggle_active', 'toggle_skippable', 'move_up', 'move_down'], true))
                ->destructive(fn (User $actor, array $args): array => ['stage_id' => $args['id'], 'action' => $args['action']])
                ->covers(ClearanceStageService::class.'::toggle', ClearanceStageService::class.'::move')
                ->handler(function (User $actor, array $args): array {
                    $stages = app(ClearanceStageService::class);

                    match ($args['action']) {
                        'toggle_active' => $stages->toggle($actor, (int) $args['id'], 'active'),
                        'toggle_skippable' => $stages->toggle($actor, (int) $args['id'], 'skippable'),
                        'move_up' => $stages->move($actor, (int) $args['id'], -1),
                        'move_down' => $stages->move($actor, (int) $args['id'], 1),
                    };

                    return ['summary' => 'Stage updated.', 'data' => null];
                }),
        ];
    }
}
