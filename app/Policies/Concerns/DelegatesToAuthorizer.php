<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Filament (Shield) policy methods answered by the central Authorizer, so the
 * web UI enforces the same permission + scope rules as MCP and the assistant.
 * `course:update` resolves to the Shield permission `Update:Course` through
 * config('erp.rbac.aliases'), so existing role grants keep working.
 */
trait DelegatesToAuthorizer
{
    /**
     * The `resource` part of `resource:action`, e.g. "course".
     */
    abstract protected function resourceKey(): string;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'list');
    }

    public function view(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'view', $record);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'create');
    }

    public function update(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'update', $record);
    }

    public function delete(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'delete', $record);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'delete_any');
    }

    public function restore(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'restore', $record);
    }

    public function forceDelete(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'force_delete', $record);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'force_delete_any');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'restore_any');
    }

    public function replicate(AuthUser $authUser, Model $record): bool
    {
        return $this->allows($authUser, 'replicate', $record);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'reorder');
    }

    protected function allows(AuthUser $authUser, string $action, ?Model $record = null): bool
    {
        return $authUser instanceof User
            && app(Authorizer::class)->allows($authUser, $this->resourceKey().':'.$action, $record);
    }
}
