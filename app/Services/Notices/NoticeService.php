<?php

namespace App\Services\Notices;

use App\Exceptions\Domain\ForbiddenException;
use App\Models\Notice;
use App\Models\User;
use App\Policies\Scopes\ScopePolicies;
use App\Services\CrudService;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Notices for everyone, one department or one hall. Global notices need a
 * global notice:create grant; department/hall notices need the matching scope.
 *
 * @extends CrudService<Notice>
 */
class NoticeService extends CrudService
{
    public function __construct(Authorizer $authorizer, protected ScopePolicies $policies)
    {
        parent::__construct($authorizer);
    }

    protected function modelClass(): string
    {
        return Notice::class;
    }

    protected function resourceKey(): string
    {
        return 'notice';
    }

    protected function rules(?Model $record): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in(Notice::AUDIENCES)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'hall_id' => ['nullable', 'integer', Rule::exists('halls', 'id')],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['title', 'body'];
    }

    protected function filterableColumns(): array
    {
        return ['audience', 'department_id', 'hall_id'];
    }

    /**
     * Published notices visible to the actor; managers also see their drafts.
     *
     * @return Builder<Notice>
     */
    public function query(User $actor): Builder
    {
        $query = parent::query($actor);

        if ($this->authorizer->allows($actor, 'notice:update')) {
            return $query;
        }

        return $query->published();
    }

    public function create(User $actor, array $data): Model
    {
        if (! $this->policies->canPublishNotice($actor, $data['department_id'] ?? null, $data['hall_id'] ?? null)) {
            throw new ForbiddenException(__('erp.errors.forbidden_scope', ['permission' => 'notice:create']), ['permission' => 'notice:create']);
        }

        return parent::create($actor, $data);
    }

    protected function performCreate(User $actor, array $data): Model
    {
        return Notice::query()->create([
            'audience' => 'all',
            ...$data,
            'created_by' => $actor->id,
        ]);
    }
}
