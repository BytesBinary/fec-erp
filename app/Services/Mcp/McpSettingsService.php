<?php

namespace App\Services\Mcp;

use App\Models\McpRoleAccess;
use App\Models\McpSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Spatie\Permission\Models\Role;

/**
 * Global MCP switches (spec §4.5): on/off, per-role access, integration limit,
 * and whether "never expires" is allowed.
 */
class McpSettingsService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    public function settings(): McpSetting
    {
        return McpSetting::current();
    }

    public function globallyEnabled(): bool
    {
        return $this->settings()->global_enabled;
    }

    public function roleEnabled(Role $role): bool
    {
        return McpRoleAccess::query()->whereKey($role->getKey())->value('enabled') ?? true;
    }

    /**
     * MCP is allowed for a user when at least one of their roles is enabled.
     */
    public function enabledForUser(User $user): bool
    {
        if (! $this->globallyEnabled()) {
            return false;
        }

        $roles = $user->roles()->get();

        return $roles->isEmpty() ? false : $roles->contains(fn (Role $role): bool => $this->roleEnabled($role));
    }

    public function setGlobal(User $actor, bool $enabled): void
    {
        $this->authorizer->authorize($actor, 'mcp:configure');
        $this->audit->as($actor, fn () => $this->settings()->update(['global_enabled' => $enabled]));
        $this->audit->record('mcp.global_switch', $this->settings(), null, ['enabled' => $enabled], $actor);
        app(\App\Services\Notifications\NotificationEvents::class)->emit('mcp.settings_changed', ['summary' => 'MCP was switched '.($enabled ? 'on' : 'off').' for everyone', 'actor' => $actor->name, 'link' => url('/security/mcp-integrations')], null);
    }

    public function setRole(User $actor, Role $role, bool $enabled): void
    {
        $this->authorizer->authorize($actor, 'mcp:configure');
        McpRoleAccess::query()->updateOrCreate(['role_id' => $role->getKey()], ['enabled' => $enabled]);
        $this->audit->record('mcp.role_switch', $role, null, ['role' => $role->name, 'enabled' => $enabled], $actor);
        app(\App\Services\Notifications\NotificationEvents::class)->emit('mcp.settings_changed', ['summary' => 'MCP was switched '.($enabled ? 'on' : 'off').' for role '.$role->name, 'actor' => $actor->name, 'link' => url('/security/mcp-integrations')], null);
    }

    public function configure(User $actor, ?int $maxIntegrations = null, ?bool $allowNeverExpire = null): McpSetting
    {
        $this->authorizer->authorize($actor, 'mcp:configure');

        $changes = array_filter(['max_integrations_per_user' => $maxIntegrations, 'allow_never_expire' => $allowNeverExpire], fn (mixed $value): bool => $value !== null);

        $this->audit->as($actor, fn () => $this->settings()->update($changes));

        return $this->settings()->refresh();
    }
}
