<?php

namespace App\Services\Clearance;

use App\Exceptions\Domain\ValidationException;
use App\Models\StaffSignature;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Each approver uploads one signature image (PNG with transparency). Approval
 * is impossible without it (spec §8.5).
 */
class StaffSignatureService
{
    public const DISK = 'local';

    public const MAX_BYTES = 512 * 1024;

    public function __construct(protected AuditLogger $audit) {}

    public function has(User $user): bool
    {
        $signature = StaffSignature::query()->find($user->getKey());

        return $signature !== null && Storage::disk(self::DISK)->exists($signature->image_path);
    }

    public function contents(User $user): ?string
    {
        $signature = StaffSignature::query()->find($user->getKey());

        return $signature === null ? null : Storage::disk(self::DISK)->get($signature->image_path);
    }

    /**
     * Stores PNG bytes as the user's signature.
     */
    public function store(User $user, string $png): StaffSignature
    {
        if (strlen($png) > self::MAX_BYTES) {
            throw new ValidationException(__('erp.clearance.signature_too_large'));
        }

        if (! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new ValidationException(__('erp.clearance.signature_png_only'));
        }

        $path = "signatures/{$user->getKey()}.png";
        Storage::disk(self::DISK)->put($path, $png);

        return $this->audit->as($user, fn (): StaffSignature => StaffSignature::query()->updateOrCreate(['user_id' => $user->getKey()], ['image_path' => $path]));
    }

    /**
     * Copies the current signature into the clearance's own folder so that
     * later changes never alter an old clearance.
     *
     * @return array{path: string, sha256: string}
     */
    public function snapshot(User $user, int $requestId, string $stageKey): array
    {
        $bytes = $this->contents($user) ?? throw new ValidationException(__('erp.clearance.signature_missing'));
        $path = "clearance-signatures/{$requestId}/{$stageKey}-".now()->format('YmdHisv').'.png';

        Storage::disk(self::DISK)->put($path, $bytes);

        return ['path' => $path, 'sha256' => hash('sha256', $bytes)];
    }
}
