<?php

namespace App\Services\Academic;

use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\NotFoundException;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Course × semester × section (spec §4.2 `course_offering_create`).
 */
class CourseOfferingService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    /**
     * Offerings the actor may list (department-scoped for department heads).
     *
     * @return Builder<CourseOffering>
     */
    public function query(User $actor): Builder
    {
        $this->authorizer->authorize($actor, 'course_offering:list');

        $query = CourseOffering::query()->with(['course', 'semester', 'teacher.user']);

        return $this->authorizer->scopeFor($actor, 'course_offering:list')->constrain($query, [
            'department' => fn (Builder $q, array $ids) => $q->whereHas('course', fn (Builder $course) => $course->whereIn('department_id', $ids)),
            'course' => fn (Builder $q, array $ids) => $q->whereIn('course_id', $ids),
        ]);
    }

    public function create(User $actor, int $courseId, int $semesterId, string $section = 'A', ?int $teacherId = null): CourseOffering
    {
        $course = Course::query()->find($courseId) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'course']));
        Semester::query()->find($semesterId) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'semester']));

        $this->authorizer->authorize($actor, 'course_offering:create', $course);

        if ($teacherId !== null) {
            Teacher::query()->find($teacherId) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'teacher']));
        }

        if (CourseOffering::query()->where(['course_id' => $courseId, 'semester_id' => $semesterId, 'section' => $section])->exists()) {
            throw new ConflictException('This course is already offered in that semester and section.');
        }

        return $this->audit->as($actor, fn (): CourseOffering => DB::transaction(fn (): CourseOffering => CourseOffering::query()->create([
            'course_id' => $courseId,
            'semester_id' => $semesterId,
            'section' => $section,
            'teacher_id' => $teacherId,
        ])));
    }

    public function get(User $actor, int $id): CourseOffering
    {
        $offering = CourseOffering::query()->with('course')->find($id) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'course offering']));

        $this->authorizer->authorize($actor, 'course_offering:list', $offering);

        return $offering;
    }
}
