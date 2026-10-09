<?php

namespace App\Services\Audit;

use App\Enums\Channel;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes audit log rows for every write, from any channel. Model CRUD is
 * captured automatically by the {@see \App\Models\Concerns\Auditable} trait;
 * services call {@see self::record()} for writes that are not a single model
 * change (role assignment, permission matrix edits, pivot syncs, …).
 */
class AuditLogger
{
    protected bool $paused = false;

    public function __construct(protected RequestContext $context) {}

    /**
     * @param  Model|array{0: string, 1: int|string|null}|null  $entity  A model or an [entity_type, entity_id] pair.
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, Model|array|null $entity = null, ?array $before = null, ?array $after = null, ?User $actor = null): ?AuditLog
    {
        if ($this->paused) {
            return null;
        }

        $actor ??= $this->currentActor();
        $channel = $this->context->channel();

        if ($actor === null && $this->isSystemContext($channel) && ! config('erp.audit.record_system_writes_without_actor')) {
            return null;
        }

        [$entityType, $entityId] = $this->entityReference($entity);

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'channel' => $channel,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'before' => $before === null ? null : $this->redact($before),
            'after' => $after === null ? null : $this->redact($after),
            'ip' => $this->context->ip(),
            'user_agent' => $this->context->userAgent(),
            'integration_id' => $this->context->integrationId(),
            'created_at' => now(),
        ]);
    }

    /**
     * Run a callback without writing audit rows (bulk imports, seeders).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $previous = $this->paused;
        $this->paused = true;

        try {
            return $callback();
        } finally {
            $this->paused = $previous;
        }
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function redact(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(config('erp.audit.redacted_attributes', [])));
    }

    public static function entityTypeOf(Model $model): string
    {
        return class_basename($model);
    }

    /**
     * @param  Model|array{0: string, 1: int|string|null}|null  $entity
     * @return array{0: string|null, 1: int|string|null}
     */
    protected function entityReference(Model|array|null $entity): array
    {
        if ($entity instanceof Model) {
            return [self::entityTypeOf($entity), $entity->getKey()];
        }

        return [$entity[0] ?? null, $entity[1] ?? null];
    }

    /**
     * Console commands, seeders and queued jobs (anything outside an HTTP
     * request, including APP_ENV=testing artisan runs).
     */
    protected function isSystemContext(Channel $channel): bool
    {
        return $channel === Channel::System || app()->runningInConsole();
    }

    protected function currentActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
