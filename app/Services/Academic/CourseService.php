<?php

namespace App\Services\Academic;

use App\Enums\CourseType;
use App\Exceptions\Domain\ValidationException;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\CrudService;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Course>
 */
class CourseService extends CrudService
{
    public function __construct(Authorizer $authorizer, protected AuditLogger $audit)
    {
        parent::__construct($authorizer);
    }

    protected function modelClass(): string
    {
        return Course::class;
    }

    protected function resourceKey(): string
    {
        return 'course';
    }

    protected function rules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'semester_number' => ['required', 'integer', 'between:1,8'],
            'type' => ['required', Rule::enum(CourseType::class)],
            'code' => ['required', 'string', 'max:30'],
            'version' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'credit_hours' => ['required', 'numeric', 'min:0', 'max:6'],
            'weekly_classes' => ['nullable', 'integer', 'between:1,7'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Course codes are unique per explicit version (`courses_code_version_unique`;
     * like the database, several rows with a null "latest" version are allowed).
     */
    protected function validate(array $data, ?Model $record): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        $validated = parent::validate($data, $record);

        $version = array_key_exists('version', $validated) ? $validated['version'] : $record?->version;

        $duplicate = $version !== null && Course::query()
            ->where('code', $validated['code'] ?? $record?->code)
            ->where('version', $version)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();

        if ($duplicate) {
            throw new ValidationException(__('erp.errors.validation'), [
                'errors' => ['code' => ['A course with this code and version already exists.']],
            ]);
        }

        return $validated;
    }

    protected function scopeColumns(): array
    {
        return ['department' => 'department_id', 'course' => 'id'];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'semester_number', 'type', 'is_active'];
    }

    /**
     * Archive (deactivate) a course without deleting it.
     */
    public function archive(User $actor, Course|int $course): Course
    {
        $course = $this->resolve($course);

        $this->authorizer->authorize($actor, $this->permission('archive'), $course);

        $this->write($actor, fn () => $course->update(['is_active' => false]));

        return $course->refresh();
    }

    /**
     * Replace the teachers assigned to a course.
     *
     * @param  list<int>  $teacherIds
     */
    public function assignTeachers(User $actor, Course|int $course, array $teacherIds): Course
    {
        $course = $this->resolve($course);

        $this->authorizer->authorize($actor, $this->permission('assign_teacher'), $course);

        $teacherIds = array_values(array_unique(array_map('intval', $teacherIds)));
        $known = Teacher::query()->whereKey($teacherIds)->pluck('id')->all();
        $unknown = array_values(array_diff($teacherIds, $known));

        if ($unknown !== []) {
            throw new ValidationException(__('erp.errors.validation'), [
                'errors' => ['teacher_ids' => ['Unknown teacher id(s): '.implode(', ', $unknown)]],
            ]);
        }

        return $this->write($actor, function () use ($course, $teacherIds): Course {
            $before = $course->teachers()->pluck('teachers.id')->sort()->values()->all();

            $course->teachers()->sync($teacherIds);

            $after = $course->teachers()->pluck('teachers.id')->sort()->values()->all();

            if ($before !== $after) {
                $this->audit->record('course.teachers_assigned', $course, ['teacher_ids' => $before], ['teacher_ids' => $after]);

                Teacher::query()->whereKey(array_diff($after, $before))->with('user')->each(
                    fn (Teacher $teacher) => app(\App\Services\Notifications\NotificationEvents::class)->emit('course.teachers_assigned', ['course' => $course->code.' '.$course->name], 'teach:'.$course->getKey().':'.$teacher->getKey(), $teacher->user),
                );
            }

            return $course->load('teachers');
        });
    }
}
