<?php

namespace App\Services\Users;

use App\Enums\RoleKey;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The role → permission matrix as data. Reads and writes use spec permission
 * names (`course:create`); aliased names are stored as their Shield
 * permission (`Create:Course`). Changes are audited by the spatie listener.
 */
class PermissionMatrixService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected PermissionCatalog $catalog,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array<string, list<string>> role name → spec permission names
     */
    public function get(User $actor): array
    {
        $this->authorizer->authorize($actor, 'permission_matrix:view');

        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [
                $role->name => $this->specNames($role->permissions->pluck('name')->all()),
            ])
            ->all();
    }

    /**
     * Grant and/or revoke permissions of one role.
     *
     * @param  list<string>  $grant
     * @param  list<string>  $revoke
     * @return list<string> the role's permissions afterwards
     */
    public function update(User $actor, string $roleName, array $grant = [], array $revoke = []): array
    {
        $this->authorizer->authorize($actor, 'permission_matrix:update');

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'role']), ['entity' => 'role', 'id' => $roleName]);
        }

        if ($role->name === RoleKey::SuperAdmin->value) {
            throw new InvalidStateException('The super admin role always has every permission.');
        }

        $unknown = array_values(array_filter([...$grant, ...$revoke], fn (string $name): bool => ! $this->catalog->isKnown($name)));

        if ($unknown !== []) {
            throw new ValidationException(__('erp.errors.validation'), [
                'errors' => ['permissions' => ['Unknown permission(s): '.implode(', ', $unknown)]],
            ]);
        }

        $this->audit->as($actor, fn () => DB::transaction(function () use ($role, $grant, $revoke): void {
            $toGrant = array_map(fn (string $name): Permission => Permission::findOrCreate($this->catalog->physicalName($name), 'web'), $grant);

            if ($toGrant !== []) {
                $role->givePermissionTo($toGrant);
            }

            foreach ($revoke as $name) {
                $physical = $this->catalog->physicalName($name);

                if ($role->hasPermissionTo($physical)) {
                    $role->revokePermissionTo($physical);
                }
            }
        }));

        return $this->specNames($role->refresh()->permissions->pluck('name')->all());
    }

    /**
     * Physical permission names → spec names. Several spec names may share
     * one Shield permission (course:update and course:archive); all are listed.
     * Shield permissions with no spec alias are returned unchanged.
     *
     * @param  list<string>  $physicalNames
     * @return list<string>
     */
    protected function specNames(array $physicalNames): array
    {
        $reverse = [];

        foreach ($this->catalog->aliases() as $spec => $physical) {
            $reverse[$physical][] = $spec;
        }

        $names = [];

        foreach ($physicalNames as $physical) {
            foreach ($reverse[$physical] ?? [$physical] as $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }
}
