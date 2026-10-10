<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalog and the DEFAULT role → permission matrix from
 * config/erp.php. Idempotent and never overrides an admin's edits:
 *
 *  - a role that has no permissions yet receives its full default set;
 *  - a permission created in this run is granted to its default roles;
 *  - everything else (grants an admin added or revoked later) is left alone.
 */
class PermissionSeeder extends Seeder
{
    public function run(PermissionCatalog $catalog): void
    {
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        $wanted = array_unique([...array_keys($catalog->nativePermissions()), ...array_values($catalog->aliases())]);

        $created = array_values(array_diff($wanted, $existing));

        foreach ($created as $name) {
            Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (RoleKey::values() as $roleKey) {
            Role::query()->firstOrCreate(['name' => $roleKey, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($catalog->defaultMatrix() as $roleKey => $permissions) {
            $role = Role::query()->where('name', $roleKey)->where('guard_name', 'web')->firstOrFail();
            $granted = $role->permissions()->pluck('name')->all();

            $missing = collect($permissions)
                ->map(fn (string $permission): string => $catalog->physicalName($permission))
                ->unique()
                ->when($granted !== [], fn ($names) => $names->intersect($created))
                ->diff($granted)
                ->values();

            if ($missing->isNotEmpty()) {
                $role->permissions()->attach(
                    Permission::query()->where('guard_name', 'web')->whereIn('name', $missing)->pluck('id')->all()
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
