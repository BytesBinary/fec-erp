<?php

namespace App\Support\Authorization;

use App\Enums\RoleKey;
use App\Enums\ScopeType;
use App\Exceptions\Domain\ForbiddenException;
use App\Models\RoleScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single place where "may this user do X (to this record)?" is decided.
 * Used by Filament policies (web), the MCP server and the assistant.
 *
 * A permission is granted when ANY role of the user carries it (the union of
 * roles) AND — when a record is given — that role's scope covers the record:
 *
 *  - global roles (super_admin, admin_office, librarian, legacy roles…) cover everything;
 *  - department_head / hall_provost cover the departments / halls pinned in `role_scopes`;
 *  - teacher covers the courses assigned in `course_teacher` (plus explicit course scopes);
 *  - student covers only records it owns.
 *
 * Read permissions listed in `erp.rbac.unscoped_permissions` ignore scope.
 * Inactive or deleted users are denied everything.
 */
class Authorizer
{
    public function __construct(
        protected PermissionCatalog $catalog,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * @throws ForbiddenException
     */
    public function authorize(User $user, string $permission, Model|ResourceScope|null $resource = null): void
    {
        if (! $this->isActive($user)) {
            throw new ForbiddenException(__('erp.errors.inactive'), ['permission' => $permission]);
        }

        $grants = $this->grantingRoles($user, $permission);

        if ($grants->isEmpty()) {
            throw new ForbiddenException(
                __('erp.errors.forbidden', ['permission' => $permission]),
                ['permission' => $permission, 'allowed_roles' => $this->rolesHolding($permission)],
            );
        }

        if (! $this->scopeAllows($user, $permission, $grants, $resource)) {
            throw new ForbiddenException(
                __('erp.errors.forbidden_scope', ['permission' => $permission]),
                ['permission' => $permission],
            );
        }
    }

    public function allows(User $user, string $permission, Model|ResourceScope|null $resource = null): bool
    {
        try {
            $this->authorize($user, $permission, $resource);
        } catch (ForbiddenException) {
            return false;
        }

        return true;
    }

    public function denies(User $user, string $permission, Model|ResourceScope|null $resource = null): bool
    {
        return ! $this->allows($user, $permission, $resource);
    }

    /**
     * Everything the user can reach with a permission, for filtering lists.
     */
    public function scopeFor(User $user, string $permission): AccessScope
    {
        if (! $this->isActive($user)) {
            return AccessScope::denied();
        }

        if ($this->isSuperAdmin($user)) {
            return AccessScope::everything();
        }

        $grants = $this->grantingRoles($user, $permission);

        if ($grants->isEmpty()) {
            return AccessScope::denied();
        }

        if ($this->catalog->isUnscoped($permission)) {
            return AccessScope::everything();
        }

        $departmentIds = $hallIds = $courseIds = [];
        $selfUserId = null;

        foreach ($grants as $grant) {
            $scopeType = $this->scopeTypeOf($grant);

            if ($scopeType === ScopeType::Global) {
                return AccessScope::everything();
            }

            match ($scopeType) {
                ScopeType::Department => $departmentIds = [...$departmentIds, ...$this->scopeIds($user, $grant, ScopeType::Department)],
                ScopeType::Hall => $hallIds = [...$hallIds, ...$this->scopeIds($user, $grant, ScopeType::Hall)],
                ScopeType::Course => $courseIds = [...$courseIds, ...$this->courseIds($user, $grant)],
                ScopeType::Self => $selfUserId = $user->id,
            };
        }

        return new AccessScope(
            granted: true,
            departmentIds: array_values(array_unique($departmentIds)),
            hallIds: array_values(array_unique($hallIds)),
            courseIds: array_values(array_unique($courseIds)),
            selfUserId: $selfUserId,
        );
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->roles->contains('name', $this->superAdminRole());
    }

    /**
     * Role keys that currently hold a permission, for "ask a … to do this" hints.
     *
     * @return list<string>
     */
    public function rolesHolding(string $permission): array
    {
        $stored = $this->storedPermission($permission);

        $roles = $stored?->roles->pluck('name')->all() ?? [];

        return array_values(array_unique([$this->superAdminRole(), ...$roles]));
    }

    /**
     * Roles of the user that carry the permission. A directly assigned user
     * permission counts as a global grant (represented by `null`).
     *
     * @return Collection<int, Role|null>
     */
    protected function grantingRoles(User $user, string $permission): Collection
    {
        if ($this->isSuperAdmin($user)) {
            return collect([null]);
        }

        $stored = $this->storedPermission($permission);

        if ($stored === null) {
            return collect();
        }

        $roleIds = $stored->roles->pluck('id');

        $grants = $user->roles->filter(fn (Role $role): bool => $roleIds->contains($role->getKey()))->values();

        if ($user->permissions->contains('name', $stored->name)) {
            $grants->push(null);
        }

        return $grants;
    }

    /**
     * @param  Collection<int, Role|null>  $grants
     */
    protected function scopeAllows(User $user, string $permission, Collection $grants, Model|ResourceScope|null $resource): bool
    {
        if ($resource === null || $this->catalog->isUnscoped($permission)) {
            return true;
        }

        $resourceScope = $this->resourceScopeOf($resource);

        foreach ($grants as $grant) {
            $allowed = match ($this->scopeTypeOf($grant)) {
                ScopeType::Global => true,
                ScopeType::Department => $this->intersects($this->scopeIds($user, $grant, ScopeType::Department), $resourceScope->departmentIds),
                ScopeType::Hall => $this->intersects($this->scopeIds($user, $grant, ScopeType::Hall), $resourceScope->hallIds),
                ScopeType::Course => $this->intersects($this->courseIds($user, $grant), $resourceScope->courseIds),
                ScopeType::Self => in_array($user->id, $resourceScope->ownerUserIds, true),
            };

            if ($allowed) {
                return true;
            }
        }

        return false;
    }

    protected function resourceScopeOf(Model|ResourceScope $resource): ResourceScope
    {
        if ($resource instanceof ResourceScope) {
            return $resource;
        }

        if ($resource instanceof HasAuthorizationScope) {
            return $resource->authorizationScope();
        }

        return ResourceScope::none();
    }

    protected function scopeTypeOf(?Role $role): ScopeType
    {
        if ($role === null) {
            return ScopeType::Global;
        }

        return RoleKey::tryFrom($role->name)?->defaultScope() ?? ScopeType::Global;
    }

    /**
     * @return list<int>
     */
    protected function scopeIds(User $user, Role $role, ScopeType $type): array
    {
        return $user->roleScopes
            ->filter(fn (RoleScope $scope): bool => $scope->role_id === $role->getKey() && $scope->scope_type === $type)
            ->pluck('scope_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * A teacher's course scope: courses assigned through `course_teacher`
     * plus any explicit course scope rows.
     *
     * @return list<int>
     */
    protected function courseIds(User $user, Role $role): array
    {
        $assigned = $user->teacher?->courses()->pluck('courses.id')->map(fn (mixed $id): int => (int) $id)->all() ?? [];

        return array_values(array_unique([...$assigned, ...$this->scopeIds($user, $role, ScopeType::Course)]));
    }

    /**
     * @param  list<int>  $granted
     * @param  list<int>  $required
     */
    protected function intersects(array $granted, array $required): bool
    {
        return array_intersect($granted, $required) !== [];
    }

    protected function isActive(User $user): bool
    {
        return $user->is_active !== false && ! $user->trashed();
    }

    protected function storedPermission(string $permission): ?\Spatie\Permission\Contracts\Permission
    {
        return $this->registrar
            ->getPermissions(['name' => $this->catalog->physicalName($permission), 'guard_name' => 'web'], true)
            ->first();
    }

    protected function superAdminRole(): string
    {
        return config('filament-shield.super_admin.name', RoleKey::SuperAdmin->value);
    }
}
