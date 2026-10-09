<?php

namespace App\Listeners;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Audits role assignments and permission-matrix edits, wherever they come
 * from (services, the Shield Roles page, tinker…).
 */
class RecordRoleAndPermissionChanges
{
    public function __construct(protected AuditLogger $audit) {}

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->audit->record($this->prefix($event->model).'.role_assigned', $event->model, null, [
            'roles' => $this->names($event->rolesOrIds, Role::class),
        ]);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->audit->record($this->prefix($event->model).'.role_revoked', $event->model, [
            'roles' => $this->names($event->rolesOrIds, Role::class),
        ]);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->audit->record($this->prefix($event->model).'.permission_granted', $event->model, null, [
            'permissions' => $this->names($event->permissionsOrIds, Permission::class),
        ]);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->audit->record($this->prefix($event->model).'.permission_revoked', $event->model, [
            'permissions' => $this->names($event->permissionsOrIds, Permission::class),
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            RoleAttachedEvent::class => 'handleRoleAttached',
            RoleDetachedEvent::class => 'handleRoleDetached',
            PermissionAttachedEvent::class => 'handlePermissionAttached',
            PermissionDetachedEvent::class => 'handlePermissionDetached',
        ];
    }

    protected function prefix(Model $model): string
    {
        return $model instanceof Role ? 'role' : 'user';
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    protected function names(mixed $modelsOrIds, string $modelClass): array
    {
        $items = $modelsOrIds instanceof Collection ? $modelsOrIds->all() : (is_array($modelsOrIds) ? $modelsOrIds : [$modelsOrIds]);

        $models = collect($items)->filter(fn (mixed $item): bool => $item instanceof Model);
        $ids = collect($items)->reject(fn (mixed $item): bool => $item instanceof Model)->filter()->values();

        if ($ids->isNotEmpty()) {
            $models = $models->merge($modelClass::query()->whereKey($ids->all())->get());
        }

        return $models->pluck('name')->unique()->sort()->values()->all();
    }
}
