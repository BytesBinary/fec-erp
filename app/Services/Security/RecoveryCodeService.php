<?php

namespace App\Services\Security;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time 2FA recovery codes. Only keyed hashes are stored; each code works
 * once (the update is conditional on `used_at IS NULL`, so concurrent use
 * cannot spend the same code twice).
 */
class RecoveryCodeService
{
    /**
     * Replaces every existing code with a fresh set and returns the plain
     * codes. This is the only moment they are available.
     *
     * @return list<string>
     */
    public function generate(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            MfaRecoveryCode::query()->where('user_id', $user->getKey())->delete();

            $codes = [];

            for ($i = 0; $i < (int) config('security.two_factor.recovery_code_count'); $i++) {
                $code = Str::lower(Str::random(5).'-'.Str::random(5));
                $codes[] = $code;

                MfaRecoveryCode::query()->create([
                    'user_id' => $user->getKey(),
                    'code_hash' => $this->hash($code),
                ]);
            }

            return $codes;
        });
    }

    /**
     * Spends `$code` if it is an unused code of `$user`.
     */
    public function consume(User $user, string $code): bool
    {
        $normalized = $this->normalize($code);

        if ($normalized === '') {
            return false;
        }

        return MfaRecoveryCode::query()
            ->where('user_id', $user->getKey())
            ->where('code_hash', $this->hash($normalized))
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    public function remaining(User $user): int
    {
        return MfaRecoveryCode::query()->where('user_id', $user->getKey())->whereNull('used_at')->count();
    }

    public function purge(User $user): void
    {
        MfaRecoveryCode::query()->where('user_id', $user->getKey())->delete();
    }

    public function looksLikeRecoveryCode(string $input): bool
    {
        return (bool) preg_match('/^[a-z0-9]{5}-?[a-z0-9]{5}$/i', trim($input));
    }

    protected function normalize(string $code): string
    {
        $clean = Str::lower(preg_replace('/[^a-z0-9]/i', '', $code) ?? '');

        return strlen($clean) === 10 ? substr($clean, 0, 5).'-'.substr($clean, 5) : '';
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
