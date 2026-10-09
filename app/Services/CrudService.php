<?php

namespace App\Services;

use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Base for simple domain services: every public method takes the acting user,
 * authorizes through the central Authorizer (permission + scope), validates,
 * and writes inside a transaction. Model writes are audited automatically by
 * the Auditable trait. MCP tools, the assistant and Filament pages all call
 * these methods instead of touching models directly.
 *
 * @template TModel of Model
 */
abstract class CrudService
{
    public const MAX_PAGE_SIZE = 100;

    public function __construct(protected Authorizer $authorizer) {}

    /**
     * @return class-string<TModel>
     */
    abstract protected function modelClass(): string;

    /**
     * The `resource` part of the `resource:action` permissions.
     */
    abstract protected function resourceKey(): string;

    /**
     * Validation rules. `$record` is null on create.
     *
     * @param  TModel|null  $record
     * @return array<string, mixed>
     */
    abstract protected function rules(?Model $record): array;

    /**
     * Scope type → column used to filter list queries for scoped roles.
     *
     * @return array<string, string|\Closure>
     */
    protected function scopeColumns(): array
    {
        return [];
    }

    /**
     * Columns searched by the `search` filter of {@see self::paginate()}.
     *
     * @return list<string>
     */
    protected function searchColumns(): array
    {
        return [];
    }

    /**
     * Query of the records the actor may list.
     *
     * @return Builder<TModel>
     */
    public function query(User $actor): Builder
    {
        $this->authorizer->authorize($actor, $this->permission('list'));

        $query = $this->modelClass()::query();

        return $this->authorizer->scopeFor($actor, $this->permission('list'))->constrain($query, $this->scopeColumns());
    }

    /**
     * Cursor-paginated list (limit ≤ 100), optionally filtered by `search` and
     * by exact-match columns listed in {@see self::filterableColumns()}.
     *
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, TModel>
     */
    public function paginate(User $actor, array $filters = [], int $limit = 25, ?string $cursor = null): CursorPaginator
    {
        $query = $this->query($actor);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '' && $this->searchColumns() !== []) {
            $query->where(function (Builder $searchQuery) use ($search): void {
                foreach ($this->searchColumns() as $column) {
                    $searchQuery->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ($this->filterableColumns() as $column) {
            if (array_key_exists($column, $filters) && $filters[$column] !== null) {
                $query->where($column, $filters[$column]);
            }
        }

        $limit = max(1, min(self::MAX_PAGE_SIZE, $limit));

        return $query->orderBy($query->getModel()->getQualifiedKeyName())->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }

    /**
     * @return TModel
     */
    public function get(User $actor, Model|int|string $record): Model
    {
        $model = $this->resolve($record);

        $this->authorizer->authorize($actor, $this->permission('view'), $model);

        return $model;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(User $actor, array $data): Model
    {
        $this->authorizer->authorize($actor, $this->permission('create'), $this->newInstance($data));

        $validated = $this->validate($data, null);

        return $this->write($actor, fn (): Model => $this->performCreate($actor, $validated));
    }

    /**
     * @param  TModel|int|string  $record
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(User $actor, Model|int|string $record, array $data): Model
    {
        $model = $this->resolve($record);

        $this->authorizer->authorize($actor, $this->permission('update'), $model);

        $validated = $this->validate($data, $model);

        $this->authorizer->authorize($actor, $this->permission('update'), (clone $model)->fill($validated));

        return $this->write($actor, fn (): Model => $this->performUpdate($actor, $model, $validated));
    }

    /**
     * @param  TModel|int|string  $record
     */
    public function delete(User $actor, Model|int|string $record): void
    {
        $model = $this->resolve($record);

        $this->authorizer->authorize($actor, $this->permission('delete'), $model);

        $this->write($actor, fn () => $model->delete());
    }

    /**
     * @param  TModel|int|string  $record
     * @return TModel
     */
    public function restore(User $actor, Model|int|string $record): Model
    {
        $model = $this->resolve($record, withTrashed: true);

        $this->authorizer->authorize($actor, $this->permission('restore'), $model);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $this->write($actor, fn () => $model->restore());
        }

        return $model;
    }

    /**
     * Run a write in a transaction, attributing audit rows to the actor.
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function write(User $actor, \Closure $callback): mixed
    {
        return app(AuditLogger::class)->as($actor, fn (): mixed => DB::transaction($callback));
    }

    public function permission(string $action): string
    {
        return $this->resourceKey().':'.$action;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    protected function performCreate(User $actor, array $data): Model
    {
        return $this->modelClass()::query()->create($data);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    protected function performUpdate(User $actor, Model $model, array $data): Model
    {
        $model->update($data);

        return $model->refresh();
    }

    /**
     * @return list<string>
     */
    protected function filterableColumns(): array
    {
        return [];
    }

    /**
     * Unsaved instance used to authorize a create against the target scope
     * (e.g. the department a new course will belong to).
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    protected function newInstance(array $data): Model
    {
        $class = $this->modelClass();

        return (new $class)->forceFill(array_intersect_key($data, array_flip((new $class)->getFillable())));
    }

    /**
     * @param  TModel|null  $record
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?Model $record): array
    {
        $rules = $this->rules($record);

        if ($record !== null) {
            $rules = array_map(
                fn (mixed $rule): mixed => is_array($rule) ? ['sometimes', ...$rule] : 'sometimes|'.$rule,
                $rules,
            );
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException(__('erp.errors.validation'), ['errors' => $validator->errors()->toArray()]);
        }

        return $validator->validated();
    }

    /**
     * @param  TModel|int|string  $record
     * @return TModel
     */
    protected function resolve(Model|int|string $record, bool $withTrashed = false): Model
    {
        if ($record instanceof Model) {
            return $record;
        }

        $query = $this->modelClass()::query();

        if ($withTrashed && in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true)) {
            $query->withTrashed();
        }

        $model = $query->find($record);

        if ($model === null) {
            throw new NotFoundException(__('erp.errors.not_found', ['entity' => str_replace('_', ' ', $this->resourceKey())]), [
                'entity' => $this->resourceKey(),
                'id' => $record,
            ]);
        }

        return $model;
    }
}
