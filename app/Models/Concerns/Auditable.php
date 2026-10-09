<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records created / updated / deleted / restored events of the model in the
 * audit log, with before/after snapshots (secrets redacted).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            static::auditLogger()->record(static::auditAction($model, 'created'), $model, null, $model->attributesToArray());
        });

        static::updated(function (Model $model): void {
            $changes = static::auditableChanges($model);

            if ($changes === []) {
                return;
            }

            $before = array_intersect_key($model->getRawOriginal(), $changes);

            static::auditLogger()->record(static::auditAction($model, 'updated'), $model, $before, $changes);
        });

        static::deleted(function (Model $model): void {
            $forced = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

            static::auditLogger()->record(
                static::auditAction($model, $forced ? 'force_deleted' : 'deleted'),
                $model,
                $model->getRawOriginal(),
                null,
            );
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                static::auditLogger()->record(static::auditAction($model, 'restored'), $model, null, $model->attributesToArray());
            });
        }
    }

    /**
     * Changed attributes worth auditing: timestamps and redacted secrets
     * alone (e.g. a remember-token refresh on logout) are not a write.
     *
     * @return array<string, mixed>
     */
    protected static function auditableChanges(Model $model): array
    {
        $ignored = [$model->getUpdatedAtColumn(), ...config('erp.audit.redacted_attributes', [])];

        $changes = array_diff_key($model->getChanges(), array_flip(array_filter($ignored)));

        if ($changes === [] && array_intersect_key($model->getChanges(), array_flip(['password'])) !== []) {
            return ['password_changed' => true];
        }

        return $changes;
    }

    protected static function auditAction(Model $model, string $event): string
    {
        return Str::snake(class_basename($model)).'.'.$event;
    }

    protected static function auditLogger(): AuditLogger
    {
        return app(AuditLogger::class);
    }
}
