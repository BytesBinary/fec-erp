<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Limits a resource's table to the records the signed-in user may list
 * (e.g. a department head only sees students of their own department).
 */
trait ScopesListToActor
{
    /**
     * The `resource:list` permission whose scope filters the table.
     */
    abstract protected static function listPermission(): string;

    /**
     * Scope type → column map, see {@see \App\Support\Authorization\AccessScope::constrain()}.
     *
     * @return array<string, string|\Closure>
     */
    protected static function listScopeColumns(): array
    {
        return ['department' => 'department_id', 'self' => 'user_id'];
    }

    public static function scopeListQuery(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return app(Authorizer::class)
            ->scopeFor($user, static::listPermission())
            ->constrain($query, static::listScopeColumns());
    }
}
