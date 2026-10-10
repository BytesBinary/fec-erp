<?php

namespace App\Services\Security;

use App\Models\MfaRolePolicy;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Spatie\Permission\Models\Role;

/**
 * Per-role "2FA required" switches (spec §3A.2). Off for every role by default.
 */
class MfaPolicy
{
    public function __construct(protected AuditLogger $audit) {}

    public function isRequiredFor(User $user): bool
    {
        return MfaRolePolicy::query()
            ->where('required', true)
            ->whereIn('role_id', $user->roles()->pluck('roles.id'))
            ->exists();
    }

    public function isRoleRequired(Role $role): bool
    {
        return MfaRolePolicy::query()->whereKey($role->getKey())->where('required', true)->exists();
    }

    public function setRoleRequired(Role $role, bool $required): void
    {
        MfaRolePolicy::query()->updateOrCreate(['role_id' => $role->getKey()], ['required' => $required]);

        $this->audit->record('two_factor.policy_changed', $role, null, ['role' => $role->name, 'required' => $required]);
    }
}
