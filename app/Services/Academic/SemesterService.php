<?php

namespace App\Services\Academic;

use App\Models\Semester;
use App\Models\User;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Semester>
 */
class SemesterService extends CrudService
{
    protected function modelClass(): string
    {
        return Semester::class;
    }

    protected function resourceKey(): string
    {
        return 'semester';
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('semesters', 'code')->ignore($record?->getKey())],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function filterableColumns(): array
    {
        return ['is_active'];
    }

    /**
     * Make one semester the active term (all others become inactive).
     */
    public function setActive(User $actor, Semester|int $semester): Semester
    {
        $semester = $this->resolve($semester);

        $this->authorizer->authorize($actor, $this->permission('activate'), $semester);

        return DB::transaction(function () use ($semester): Semester {
            Semester::query()
                ->where('is_active', true)
                ->whereKeyNot($semester->getKey())
                ->get()
                ->each(fn (Semester $other) => $other->update(['is_active' => false]));

            $semester->update(['is_active' => true]);

            return $semester->refresh();
        });
    }
}
