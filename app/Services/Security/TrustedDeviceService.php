<?php

namespace App\Services\Security;

use App\Models\KnownDevice;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Trust this device for 30 days" (spec §3A.2). The browser keeps a random
 * token in an encrypted cookie; only its hash is stored. Revoking the session
 * that granted trust removes the token, so the next login challenges again.
 */
class TrustedDeviceService
{
    public function cookieName(): string
    {
        return (string) config('security.two_factor.trust_cookie');
    }

    public function enabled(): bool
    {
        return (bool) config('security.two_factor.trust_device_enabled');
    }

    /**
     * Grants trust to the browser behind `$session` and queues the cookie.
     */
    public function trust(User $user, UserSession $session): void
    {
        if (! $this->enabled()) {
            return;
        }

        $days = (int) config('security.two_factor.trust_device_days');
        $token = Str::random(48);
        $until = now()->addDays($days);

        KnownDevice::query()
            ->firstOrCreate(
                ['user_id' => $user->getKey(), 'fingerprint_hash' => app(DeviceParser::class)->fingerprint($session->browser, $session->os, $session->device_type)],
                ['label' => $session->device_label, 'first_seen_at' => now()],
            )
            ->forceFill(['trusted_token_hash' => hash('sha256', $token), 'trusted_until' => $until])
            ->save();

        $session->forceFill(['trusted_until' => $until, 'trusted_token_hash' => hash('sha256', $token)])->save();

        Cookie::queue($this->cookieName(), $token, $days * 24 * 60, null, null, null, true, false, 'lax');
    }

    /**
     * When a new session starts in a browser that still holds a valid trust
     * cookie, the session inherits the trust and skips the 2FA challenge.
     */
    public function adoptFromCookie(User $user, UserSession $session, Request $request): void
    {
        $token = $request->cookie($this->cookieName());

        if (! is_string($token) || $token === '' || ! $this->enabled()) {
            return;
        }

        $hash = hash('sha256', $token);

        $device = KnownDevice::query()
            ->where('user_id', $user->getKey())
            ->where('trusted_token_hash', $hash)
            ->where('trusted_until', '>', now())
            ->first();

        if ($device === null) {
            return;
        }

        $session->forceFill([
            'trusted_until' => $device->trusted_until,
            'trusted_token_hash' => $hash,
            'mfa_passed_at' => now(),
        ])->save();
    }

    public function revokeForSession(UserSession $session): void
    {
        if ($session->trusted_token_hash === null) {
            return;
        }

        KnownDevice::query()
            ->where('user_id', $session->user_id)
            ->where('trusted_token_hash', $session->trusted_token_hash)
            ->update(['trusted_token_hash' => null, 'trusted_until' => null]);

        $session->forceFill(['trusted_until' => null, 'trusted_token_hash' => null])->save();
    }

    public function revokeAllFor(User $user): void
    {
        KnownDevice::query()->where('user_id', $user->getKey())->update(['trusted_token_hash' => null, 'trusted_until' => null]);
        UserSession::query()->where('user_id', $user->getKey())->update(['trusted_until' => null, 'trusted_token_hash' => null]);
    }
}
