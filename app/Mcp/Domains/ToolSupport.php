<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\User;
use App\Services\CrudService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use ReflectionMethod;

/**
 * Shared helpers for the tool declarations: CRUD tool generation from a
 * service's own validation rules (one source of truth), model serialisation
 * and cursor pagination.
 */
final class ToolSupport
{
    public const MAX_LIMIT = 100;

    /**
     * @return array<string, mixed>
     */
    public static function item(Model $model): array
    {
        return $model->toArray();
    }

    /**
     * @param  iterable<Model>  $models
     * @return list<array<string, mixed>>
     */
    public static function items(iterable $models): array
    {
        $out = [];

        foreach ($models as $model) {
            $out[] = self::item($model);
        }

        return $out;
    }

    /**
     * @param  CursorPaginator<int, Model>  $paginator
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string}
     */
    public static function page(CursorPaginator $paginator): array
    {
        return ['items' => self::items($paginator->items()), 'next_cursor' => $paginator->nextCursor()?->encode()];
    }

    /**
     * Cursor-paginates a query ordered by id.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $arguments  limit, cursor
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string}
     */
    public static function paginateQuery(Builder $query, array $arguments): array
    {
        $limit = max(1, min(self::MAX_LIMIT, (int) ($arguments['limit'] ?? 25)));

        return self::page($query->orderBy($query->getModel()->getQualifiedKeyName())->cursorPaginate($limit, ['*'], 'cursor', $arguments['cursor'] ?? null));
    }

    /**
     * @return list<Param>
     */
    public static function paging(): array
    {
        return [
            Param::integer('limit', 'Page size, 1 to 100 (default 25).', example: 25),
            Param::string('cursor', 'Opaque cursor from the previous page\'s next_cursor.'),
        ];
    }

    /**
     * list / get / create / update tools for a {@see CrudService}. Create and
     * update parameters are derived from the service's own validation rules.
     *
     * @param  class-string<CrudService<Model>>  $service
     * @param  list<string>  $only
     * @return list<ToolDefinition>
     */
    public static function crud(string $prefix, string $label, string $service, string $resource, array $only = ['list', 'get', 'create', 'update'], string $hint = ''): array
    {
        $plural = Str::plural($label);
        $tools = [];
        $fields = fn (bool $forUpdate): array => self::paramsFromRules($service, $forUpdate);
        $hint = $hint === '' ? '' : ' '.$hint;

        if (in_array('list', $only, true)) {
            $tools[] = ToolDefinition::make("{$prefix}_list", Str::headline($plural), "List the {$plural} you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor.{$hint}")
                ->permission("{$resource}:list")
                ->params(Param::string('search', "Text to search {$plural} by."), Param::object('filters', 'Exact-match filters as an object, e.g. {"department_id": 3}.'), ...self::paging())
                ->covers("{$service}::paginate")
                ->handler(function (User $actor, array $args) use ($service, $plural): array {
                    $page = self::page(app($service)->paginate($actor, ['search' => $args['search'] ?? null, ...($args['filters'] ?? [])], (int) ($args['limit'] ?? 25), $args['cursor'] ?? null));

                    return ['summary' => count($page['items'])." {$plural} returned".($page['next_cursor'] ? ' (more available: pass next_cursor)' : '').'.', 'data' => $page];
                });
        }

        if (in_array('get', $only, true)) {
            $tools[] = ToolDefinition::make("{$prefix}_get", Str::headline($label), "Get one {$label} by id with all its fields. Use it after {$prefix}_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it.")
                ->permission("{$resource}:view")
                ->params(Param::integer('id', "The {$label} id.", required: true, example: 1))
                ->covers("{$service}::get")
                ->handler(fn (User $actor, array $args): array => ['summary' => "Found the {$label}.", 'data' => self::item(app($service)->get($actor, (int) $args['id'])), 'entity' => [Str::studly($label), (int) $args['id']]]);
        }

        if (in_array('create', $only, true)) {
            $tools[] = ToolDefinition::make("{$prefix}_create", 'Create '.$label, "Create a new {$label}. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying.{$hint}")
                ->permission("{$resource}:create")
                ->params(...$fields(false))
                ->creates()
                ->covers("{$service}::create")
                ->handler(function (User $actor, array $args) use ($service, $label): array {
                    $model = app($service)->create($actor, $args);

                    return ['summary' => "Created the {$label} (id {$model->getKey()}).", 'data' => self::item($model), 'entity' => [Str::studly($label), $model->getKey()]];
                });
        }

        if (in_array('update', $only, true)) {
            $tools[] = ToolDefinition::make("{$prefix}_update", 'Update '.$label, "Change fields of an existing {$label}. Send only the fields to change.")
                ->permission("{$resource}:update")
                ->params(Param::integer('id', "The {$label} id.", required: true), ...$fields(true))
                ->write(idempotent: true)
                ->covers("{$service}::update")
                ->handler(function (User $actor, array $args) use ($service, $label): array {
                    $id = (int) $args['id'];
                    unset($args['id']);
                    $model = app($service)->update($actor, $id, $args);

                    return ['summary' => "Updated the {$label} (id {$id}).", 'data' => self::item($model), 'entity' => [Str::studly($label), $id]];
                });
        }

        return $tools;
    }

    /**
     * @param  class-string<CrudService<Model>>  $service
     * @return list<Param>
     */
    public static function paramsFromRules(string $service, bool $forUpdate): array
    {
        $instance = app($service);
        $rules = (new ReflectionMethod($instance, 'rules'))->invoke($instance, null);
        $params = [];

        foreach ($rules as $name => $fieldRules) {
            $params[] = self::paramFromRule((string) $name, (array) (is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules), $forUpdate);
        }

        return $params;
    }

    /**
     * @param  list<mixed>  $rules
     */
    protected static function paramFromRule(string $name, array $rules, bool $optional): Param
    {
        $strings = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $strings[] = $rule;
            } elseif ($rule instanceof In || $rule instanceof Enum) {
                $strings[] = 'enum-or-list';
            }
        }

        $required = ! $optional && in_array('required', $strings, true);
        $hints = collect($strings)->reject(fn (string $rule): bool => in_array($rule, ['required', 'nullable', 'sometimes', 'enum-or-list'], true))->implode(', ');
        $description = Str::headline($name).($hints !== '' ? " ({$hints})" : '').'.';

        return match (true) {
            in_array('integer', $strings, true) => Param::integer($name, $description, $required),
            in_array('numeric', $strings, true) => Param::number($name, $description, $required),
            in_array('boolean', $strings, true) => Param::boolean($name, $description, $required),
            in_array('array', $strings, true) => Param::array($name, $description, 'integer', $required),
            default => Param::string($name, $description, $required),
        };
    }
}
