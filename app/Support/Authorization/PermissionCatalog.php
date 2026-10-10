<?php

namespace App\Support\Authorization;

/**
 * Read access to the permission catalog in config/erp.php.
 */
class PermissionCatalog
{
    /**
     * The permission name actually stored in the database for a spec
     * permission (resolves Shield aliases such as course:create → Create:Course).
     */
    public function physicalName(string $permission): string
    {
        return config("erp.rbac.aliases.{$permission}") ?? $permission;
    }

    /**
     * @return array<string, string>
     */
    public function nativePermissions(): array
    {
        return config('erp.rbac.permissions', []);
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        return config('erp.rbac.aliases', []);
    }

    /**
     * Every spec permission name (native + aliased).
     *
     * @return list<string>
     */
    public function allPermissionNames(): array
    {
        return array_values(array_unique([
            ...array_keys($this->nativePermissions()),
            ...array_keys($this->aliases()),
        ]));
    }

    public function isKnown(string $permission): bool
    {
        return array_key_exists($permission, $this->nativePermissions())
            || array_key_exists($permission, $this->aliases());
    }

    public function isUnscoped(string $permission): bool
    {
        return in_array($permission, config('erp.rbac.unscoped_permissions', []), true);
    }

    /**
     * Default role → spec permission matrix.
     *
     * @return array<string, list<string>>
     */
    public function defaultMatrix(): array
    {
        return config('erp.rbac.matrix', []);
    }
}
