<?php

namespace App\Services\Clearance;

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Models\ClearanceApproval;
use App\Models\ClearancePrint;
use App\Models\ClearanceRequest;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Facades\Storage;

/**
 * Data for the printed clearance (spec §8.6): header, student, one row per
 * stage with the embedded signature snapshot, QR code, the empty box for the
 * Principal and the seal area.
 */
class ClearancePrintService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected AuditLogger $audit,
        protected QrCodeService $qr,
        protected HashChain $chain,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function documentData(User $actor, ClearanceRequest|int $request, ?ClearancePrint $print = null, bool $preview = false): array
    {
        $model = $request instanceof ClearanceRequest ? $request : ClearanceRequest::query()->find($request) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'clearance request']));
        $model->load(['student.user', 'student.department', 'student.program', 'student.batch', 'student.profile']);

        $this->authorizer->authorize($actor, 'clearance:print', $model);

        if (! in_array($model->status, [ClearanceStatus::ReadyForCollection, ClearanceStatus::Printed, ClearanceStatus::Collected], true)) {
            throw new InvalidStateException(__('erp.clearance.not_ready_to_print'));
        }

        if ($print !== null && $print->clearance_request_id !== $model->getKey()) {
            throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'print']));
        }

        $stages = $model->approvals()->with('stage')->get()
            ->groupBy('stage_id')
            ->map(fn ($rows): ClearanceApproval => $rows->last())
            ->filter(fn (ClearanceApproval $approval): bool => $approval->decision !== 'rejected')
            ->sortBy(fn (ClearanceApproval $approval): string => str_pad((string) $approval->stage->order, 5, '0', STR_PAD_LEFT).$approval->stage_id)
            ->values()
            ->map(fn (ClearanceApproval $approval): array => [
                'stage' => $approval->stage->label,
                'decision' => $approval->decision,
                'name' => $approval->approver_name,
                'designation' => $approval->approver_designation,
                'decided_at' => $approval->decided_at,
                'signature' => $this->signatureUri($approval),
                'remarks' => $approval->remarks,
            ])
            ->all();

        $institution = InstitutionSetting::current();

        return [
            'request' => $model,
            'student' => $model->student,
            'institution' => $institution,
            'logo' => $this->publicFileUri($institution->logo_path),
            'photo' => $this->publicFileUri($model->student->profile?->photo_path),
            'stages' => $stages,
            'qr' => $this->qr->svgDataUri($this->verificationUrl($model)),
            'verification_url' => $this->verificationUrl($model),
            'duplicate' => $print?->is_duplicate ?? false,
            'preview' => $preview || $print === null,
            'issued_on' => ($print?->printed_at ?? $model->ready_at ?? now())->format('d F Y'),
            'integrity' => $this->chain->verify($model),
            'principal_title' => $institution->principal_title ?: 'Principal',
            'principal_name' => $institution->principal_name,
        ];
    }

    public function verificationUrl(ClearanceRequest $request): string
    {
        return route('clearance.verify', ['code' => $request->verify_code]);
    }

    protected function signatureUri(ClearanceApproval $approval): ?string
    {
        if ($approval->signature_snapshot_path === null || ! Storage::disk('local')->exists($approval->signature_snapshot_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($approval->signature_snapshot_path));
    }

    protected function publicFileUri(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($path));
    }

    /**
     * Loads a print record for the document view (desk users only).
     */
    public function printOf(User $actor, ClearanceRequest $request, int $printId): ClearancePrint
    {
        $this->authorizer->authorize($actor, 'clearance:print', $request);

        return ClearancePrint::query()->where('clearance_request_id', $request->getKey())->find($printId)
            ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'print']));
    }

    public function assertMayOverrideDuplicate(User $actor): void
    {
        if (! $this->authorizer->isSuperAdmin($actor)) {
            throw new ForbiddenException(__('erp.clearance.original_reprint_super_admin'));
        }
    }
}
