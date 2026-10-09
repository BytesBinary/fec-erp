<?php

namespace App\Services\Users;

use App\Enums\RoleKey;
use App\Enums\ScopeType;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Department;
use App\Models\Hall;
use App\Models\RoleScope;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Role assignment with scopes (e.g. department_head of department 5).
 * Attaching/detaching roles is audited by the spatie event listener; scope
 * changes are audited here.
 */
class RoleService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    /**
     * @return Collection<int, Role>
     */
    public function list(User $actor): Collection
    {
        $this->authorizer->authorize($actor, 'role:list');

        return Role::query()->where('guard_name', 'web')->withCount('users')->orderBy('name')->get();
    }

    /**
     * Roles of a user with their scope ids.
     *
     * @return list<array{role: string, scope_type: string, scope_ids: list<int>}>
     */
    public function rolesOf(User $actor, User $user): array
    {
        if (! $actor->is($user)) {
            $this->authorizer->authorize($actor, 'user:view', $user);
        }

        $user->load(['roles', 'roleScopes']);

        return $user->roles->map(fn (Role $role): array => [
            'role' => $role->name,
            'scope_type' => (RoleKey::tryFrom($role->name)?->defaultScope() ?? ScopeType::Global)->value,
            'scope_ids' => $user->roleScopes->where('role_id', $role->id)->pluck('scope_id')->map(fn ($id): int => (int) $id)->values()->all(),
        ])->values()->all();
    }

    /**
     * Give a user a role, optionally pinned to scope ids (department ids for
     * department_head, hall ids for hall_provost, extra course ids for teacher).
     *
     * @param  list<int>  $scopeIds
     */
    public function assign(User $actor, User $user, string $roleName, array $scopeIds = []): User
    {
        $this->authorizer->authorize($actor, 'role:assign', $user);

        $role = $this->findRole($roleName);
        $this->guardSuperAdmin($actor, $role);

        $scopeType = RoleKey::tryFrom($role->name)?->defaultScope() ?? ScopeType::Global;
        $scopeIds = $this->validateScopeIds($role, $scopeType, $scopeIds);

        return $this->audit->as($actor, fn (): User => DB::transaction(function () use ($user, $role, $scopeType, $scopeIds): User {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $this->syncScopes($user, $role, $scopeType, $scopeIds);

            return $user->refresh()->load(['roles', 'roleScopes']);
        }));
    }

    public function revoke(User $actor, User $user, string $roleName): User
    {
        $this->authorizer->authorize($actor, 'role:revoke', $user);

        $role = $this->findRole($roleName);
        $this->guardSuperAdmin($actor, $role);

        if ($actor->is($user) && $role->name === RoleKey::SuperAdmin->value) {
            throw new ForbiddenException('You cannot remove your own super admin role.');
        }

        return $this->audit->as($actor, fn (): User => DB::transaction(function () use ($user, $role): User {
            $this->syncScopes($user, $role, ScopeType::Global, []);
            $user->removeRole($role);

            return $user->refresh()->load(['roles', 'roleScopes']);
        }));
    }

    protected function findRole(string $roleName): Role
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'role']), ['entity' => 'role', 'id' => $roleName]);
        }

        return $role;
    }

    protected function guardSuperAdmin(User $actor, Role $role): void
    {
        if ($role->name === RoleKey::SuperAdmin->value && ! $this->authorizer->isSuperAdmin($actor)) {
            throw new ForbiddenException('Only a super admin can grant or revoke the super admin role.', ['permission' => 'role:assign']);
        }
    }

    /**
     * @param  list<int>  $scopeIds
     * @return list<int>
     */
    protected function validateScopeIds(Role $role, ScopeType $scopeType, array $scopeIds): array
    {
        $scopeIds = array_values(array_unique(array_map('intval', $scopeIds)));

        $table = match ($scopeType) {
            ScopeType::Department => (new Department)->getTable(),
            ScopeType::Hall => (new Hall)->getTable(),
            ScopeType::Course => 'courses',
            default => null,
        };

        if ($table === null) {
            if ($scopeIds !== []) {
                throw new ValidationException(__('erp.errors.validation'), [
                    'errors' => ['scope_ids' => ["The {$role->name} role is not scoped."]],
                ]);
            }

            return [];
        }

        if ($scopeIds === [] && RoleKey::from($role->name)->requiresExplicitScope()) {
            throw new ValidationException(__('erp.errors.validation'), [
                'errors' => ['scope_ids' => ["The {$role->name} role needs at least one {$scopeType->value}."]],
            ]);
        }

        $existing = DB::table($table)->whereIn('id', $scopeIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $missing = array_values(array_diff($scopeIds, $existing));

        if ($missing !== []) {
            throw new ValidationException(__('erp.errors.validation'), [
                'errors' => ['scope_ids' => ["Unknown {$scopeType->value} id(s): ".implode(', ', $missing)]],
            ]);
        }

        return $scopeIds;
    }

    /**
     * @param  list<int>  $scopeIds
     */
    protected function syncScopes(User $user, Role $role, ScopeType $scopeType, array $scopeIds): void
    {
        $current = RoleScope::query()->where('user_id', $user->id)->where('role_id', $role->id)->get();
        $before = $current->pluck('scope_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

        $current->reject(fn (RoleScope $scope): bool => $scope->scope_type === $scopeType && in_array($scope->scope_id, $scopeIds, true))
            ->each(fn (RoleScope $scope) => $scope->delete());

        foreach ($scopeIds as $scopeId) {
            RoleScope::query()->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeId,
            ]);
        }

        $after = $scopeIds;
        sort($after);

        if ($before !== $after) {
            $this->audit->record('user.role_scopes_changed', $user,
                ['role' => $role->name, 'scope_ids' => $before],
                ['role' => $role->name, 'scope_type' => $scopeType->value, 'scope_ids' => $after],
            );
        }

        $user->unsetRelation('roleScopes');
    }
}
