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
 * config/erp.php. Idempotent and additive: it creates missing permissions and
 * roles and adds missing grants, but never revokes a grant an admin made.
 */
class PermissionSeeder extends Seeder
{
    public function run(PermissionCatalog $catalog): void
    {
        foreach (array_keys($catalog->nativePermissions()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (array_unique(array_values($catalog->aliases())) as $physical) {
            Permission::findOrCreate($physical, 'web');
        }

        foreach (RoleKey::values() as $roleKey) {
            Role::findOrCreate($roleKey, 'web');
        }

        foreach ($catalog->defaultMatrix() as $roleKey => $permissions) {
            $role = Role::findByName($roleKey, 'web');

            $missing = collect($permissions)
                ->map(fn (string $permission): string => $catalog->physicalName($permission))
                ->unique()
                ->reject(fn (string $physical): bool => $role->hasPermissionTo($physical))
                ->values()
                ->all();

            if ($missing !== []) {
                $role->givePermissionTo($missing);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
