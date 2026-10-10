<?php

namespace App\Services\Security;

use App\Exceptions\Domain\ValidationException;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;

/**
 * Super-admin tools for other users' sessions and 2FA (spec §3A.1, §3A.2).
 * Every call is authorized through the central {@see Authorizer} and audited.
 */
class UserSecurityService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected SessionTracker $sessions,
        protected TwoFactorService $twoFactor,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, UserSession>
     */
    public function sessionsOf(User $actor, User $user): Collection
    {
        $this->authorizer->authorize($actor, 'session:view_any');

        return $this->sessions->activeFor($user);
    }

    public function revokeSession(User $actor, UserSession $session, string $reason): void
    {
        $this->authorizer->authorize($actor, 'session:revoke');
        $this->requireReason($reason);

        $this->sessions->revoke($session, $actor, $reason);
        $this->audit->record('session.revoked_by_admin', $session->user, null, ['session_id' => $session->getKey(), 'reason' => $reason], $actor);
    }

    public function revokeAllSessions(User $actor, User $user, string $reason): int
    {
        $this->authorizer->authorize($actor, 'session:revoke');
        $this->requireReason($reason);

        $count = $this->sessions->revokeAll($user, $actor, $reason);
        $this->audit->record('session.revoked_all_by_admin', $user, null, ['count' => $count, 'reason' => $reason], $actor);

        return $count;
    }

    public function resetTwoFactor(User $actor, User $user, string $reason): void
    {
        $this->authorizer->authorize($actor, 'two_factor:reset');
        $this->requireReason($reason);

        $this->twoFactor->adminReset($user, $actor, $reason);
    }

    protected function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new ValidationException('A reason is required.');
        }
    }
}
