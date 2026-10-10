<?php

namespace App\Services\Clearance;

use App\Models\ClearanceApproval;
use App\Models\ClearanceRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Tamper-evidence for a clearance (spec §8.5): every approval row carries
 * SHA-256(prev_hash + request data + its own data), so changing any approval
 * (or the request identity) breaks every hash after it.
 */
class HashChain
{
    public function genesis(ClearanceRequest $request): string
    {
        return hash('sha256', implode('|', ['genesis', $request->request_no, $request->student_id]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hashFor(string $previousHash, ClearanceRequest $request, array $data): string
    {
        return hash('sha256', $previousHash.'|'.json_encode([
            'request_no' => $request->request_no,
            'student_id' => $request->student_id,
            'stage_id' => $data['stage_id'],
            'decision' => $data['decision'],
            'approver_user_id' => $data['approver_user_id'],
            'approver_name' => $data['approver_name'],
            'approver_designation' => $data['approver_designation'],
            'signature_sha256' => $data['signature_sha256'],
            'remarks' => $data['remarks'],
            'decided_at' => $data['decided_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function lastHash(ClearanceRequest $request): string
    {
        return ClearanceApproval::query()->where('clearance_request_id', $request->getKey())->orderByDesc('id')->value('hash') ?? $this->genesis($request);
    }

    /**
     * Recomputes the whole chain.
     *
     * @return array{intact: bool, broken_at: ?int, checked: int}
     */
    public function verify(ClearanceRequest $request): array
    {
        $previous = $this->genesis($request);
        $checked = 0;

        foreach ($request->approvals()->get() as $approval) {
            $expected = $this->hashFor($previous, $request, [
                'stage_id' => $approval->stage_id,
                'decision' => $approval->decision,
                'approver_user_id' => $approval->approver_user_id,
                'approver_name' => $approval->approver_name,
                'approver_designation' => $approval->approver_designation,
                'signature_sha256' => $approval->signature_sha256,
                'remarks' => $approval->remarks,
                'decided_at' => $approval->decided_at->toIso8601String(),
            ]);

            if ($approval->prev_hash !== $previous || $approval->hash !== $expected) {
                return ['intact' => false, 'broken_at' => $approval->getKey(), 'checked' => $checked];
            }

            if ($approval->signature_snapshot_path !== null && ! $this->signatureMatches($approval)) {
                return ['intact' => false, 'broken_at' => $approval->getKey(), 'checked' => $checked];
            }

            $previous = $approval->hash;
            $checked++;
        }

        if ($request->chain_hash !== null && $request->chain_hash !== $previous) {
            return ['intact' => false, 'broken_at' => null, 'checked' => $checked];
        }

        return ['intact' => true, 'broken_at' => null, 'checked' => $checked];
    }

    /**
     * The stored signature image must still be the one that was signed off.
     */
    protected function signatureMatches(ClearanceApproval $approval): bool
    {
        $disk = Storage::disk(StaffSignatureService::DISK);

        return $disk->exists($approval->signature_snapshot_path)
            && hash('sha256', (string) $disk->get($approval->signature_snapshot_path)) === $approval->signature_sha256;
    }
}
