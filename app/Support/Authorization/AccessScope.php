<?php

namespace App\Support\Authorization;

use App\Enums\ScopeType;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The union of everything a user may reach with one permission. Used by
 * services to filter list queries the same way the Authorizer checks
 * single records.
 */
final class AccessScope
{
    /**
     * @param  list<int>  $departmentIds
     * @param  list<int>  $hallIds
     * @param  list<int>  $courseIds
     */
    public function __construct(
        public readonly bool $granted,
        public readonly bool $global = false,
        public readonly array $departmentIds = [],
        public readonly array $hallIds = [],
        public readonly array $courseIds = [],
        public readonly ?int $selfUserId = null,
    ) {}

    public static function denied(): self
    {
        return new self(granted: false);
    }

    public static function everything(): self
    {
        return new self(granted: true, global: true);
    }

    /**
     * Constrain a query to the records inside this scope. `$columns` maps a
     * scope type to the column holding that id (e.g. ['department' =>
     * 'department_id', 'self' => 'user_id']) or to a closure that receives
     * the query and the allowed ids.
     *
     * @param  array<string, string|\Closure(Builder, list<int>): void>  $columns
     */
    public function constrain(Builder $query, array $columns): Builder
    {
        if ($this->global) {
            return $query;
        }

        if (! $this->granted) {
            return $query->whereRaw('1 = 0');
        }

        $filters = array_filter([
            ScopeType::Department->value => $this->departmentIds,
            ScopeType::Hall->value => $this->hallIds,
            ScopeType::Course->value => $this->courseIds,
            ScopeType::Self->value => $this->selfUserId === null ? [] : [$this->selfUserId],
        ]);

        $applicable = array_intersect_key($filters, $columns);

        if ($applicable === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($applicable, $columns): void {
            foreach ($applicable as $type => $ids) {
                $column = $columns[$type];

                if ($column instanceof \Closure) {
                    $scoped->orWhere(fn (Builder $nested) => $column($nested, $ids));

                    continue;
                }

                $scoped->orWhereIn($column, $ids);
            }
        });
    }
}
