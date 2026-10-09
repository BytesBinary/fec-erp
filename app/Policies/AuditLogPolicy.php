<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * The audit log is append-only: it can be read (`audit_log:view`) but never
 * created, edited or deleted through the UI.
 */
class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser instanceof User && app(Authorizer::class)->allows($authUser, 'audit_log:view');
    }

    public function view(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $this->viewAny($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
