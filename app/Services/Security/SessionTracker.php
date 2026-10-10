<?php

namespace App\Services\Security;

use App\Models\KnownDevice;
use App\Models\User;
use App\Models\UserSession;
use App\Notifications\NewDeviceLogin;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Server-side session records (spec §3A.1). A record is created on the first
 * authenticated request of a browser session and validated on every later one,
 * so a revoked session is rejected on its very next request.
 */
class SessionTracker
{
    public function __construct(
        protected DeviceParser $devices,
        protected TrustedDeviceService $trust,
        protected AuditLogger $audit,
    ) {}

    public function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    public function find(Request $request): ?UserSession
    {
        $store = $request->hasSession() ? $request->session() : session()->driver();

        if (! $store->isStarted()) {
            return null;
        }

        return UserSession::query()
            ->where('session_hash', $this->hash($store->getId()))
            ->first();
    }

    /**
     * Creates the record for this browser session and raises the new-device
     * alert when the browser/OS combination was never seen for this user.
     */
    public function begin(User $user, Request $request, bool $remember = false): UserSession
    {
        $parsed = $this->devices->parse($request->userAgent());
        $minutes = $remember ? config('security.sessions.remember_minutes') : config('security.sessions.inactivity_minutes');

        $session = UserSession::query()->create([
            'user_id' => $user->getKey(),
            'session_hash' => $this->hash($request->session()->getId()),
            'device_label' => $parsed['label'],
            'device_type' => $parsed['type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'ip' => $request->ip(),
            'remember' => $remember,
            'last_active_at' => now(),
            'expires_at' => now()->addMinutes((int) $minutes),
        ]);

        $fingerprint = $this->devices->fingerprint($parsed['browser'], $parsed['os'], $parsed['type']);

        $device = KnownDevice::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'fingerprint_hash' => $fingerprint],
            ['label' => $parsed['label'], 'first_seen_at' => now()],
        );

        if ($device->wasRecentlyCreated) {
            $user->notify(new NewDeviceLogin($parsed['label'], (string) $request->ip()));
        }

        $this->trust->adoptFromCookie($user, $session, $request);

        return $session;
    }

    /**
     * Slides the inactivity window forward, writing at most once per interval.
     */
    public function touch(UserSession $session): void
    {
        $interval = (int) config('security.sessions.touch_interval_seconds');

        if ($session->last_active_at !== null && $session->last_active_at->gt(now()->subSeconds($interval))) {
            return;
        }

        $minutes = $session->remember ? config('security.sessions.remember_minutes') : config('security.sessions.inactivity_minutes');

        $session->forceFill([
            'last_active_at' => now(),
            'expires_at' => now()->addMinutes((int) $minutes),
        ])->save();
    }

    public function revoke(UserSession $session, ?User $by = null, string $reason = 'logged_out'): void
    {
        if ($session->isRevoked()) {
            return;
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $by?->getKey(),
            'revoked_reason' => $reason,
        ])->save();

        $this->trust->revokeForSession($session);
    }

    /**
     * Revokes every active session of `$user` except the one with `$exceptHash`.
     *
     * @return int number of sessions revoked
     */
    public function revokeOthers(User $user, ?string $exceptHash, ?User $by = null, string $reason = 'logged_out_others'): int
    {
        $sessions = UserSession::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->when($exceptHash !== null, fn ($query) => $query->where('session_hash', '!=', $exceptHash))
            ->get();

        foreach ($sessions as $session) {
            $this->revoke($session, $by, $reason);
        }

        return $sessions->count();
    }

    public function revokeAll(User $user, ?User $by = null, string $reason = 'revoked'): int
    {
        return $this->revokeOthers($user, null, $by, $reason);
    }

    /**
     * Active sessions, newest activity first.
     *
     * @return Collection<int, UserSession>
     */
    public function activeFor(User $user): Collection
    {
        return UserSession::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->orderByDesc('last_active_at')
            ->get();
    }

    /**
     * Deletes revoked / expired records older than the audit retention period.
     */
    public function prune(): int
    {
        $cutoff = now()->subDays((int) config('security.sessions.retention_days'));

        return UserSession::query()
            ->where(fn ($query) => $query->where('revoked_at', '<', $cutoff)->orWhere('expires_at', '<', $cutoff))
            ->delete();
    }
}
