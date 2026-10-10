<?php

namespace App\Services\Academic;

use App\Enums\AttemptType;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    /**
     * Enrollments of one student the actor may see (own, or in scope).
     *
     * @return Collection<int, Enrollment>
     */
    public function listFor(User $actor, Student $student): Collection
    {
        $this->authorizer->authorize($actor, 'enrollment:list', $student);

        return Enrollment::query()
            ->with(['offering.course', 'offering.semester'])
            ->where('student_id', $student->getKey())
            ->where('status', 'enrolled')
            ->get();
    }

    /**
     * The acting student's current courses (`student_list_my_courses`).
     *
     * @return Collection<int, Enrollment>
     */
    public function myCourses(User $actor): Collection
    {
        $student = Student::query()->where('user_id', $actor->getKey())->first() ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'student']));

        return $this->listFor($actor, $student);
    }

    /**
     * @return Builder<Enrollment>
     */
    public function query(User $actor): Builder
    {
        $this->authorizer->authorize($actor, 'enrollment:list');

        return $this->authorizer->scopeFor($actor, 'enrollment:list')->constrain(Enrollment::query()->with(['student.user', 'offering.course']), [
            'department' => fn (Builder $q, array $ids) => $q->whereHas('student', fn (Builder $s) => $s->whereIn('department_id', $ids)),
            'self' => fn (Builder $q, array $ids) => $q->whereHas('student', fn (Builder $s) => $s->whereIn('user_id', $ids)),
            'course' => fn (Builder $q, array $ids) => $q->whereHas('offering', fn (Builder $o) => $o->whereIn('course_id', $ids)),
        ]);
    }

    public function enroll(User $actor, Student $student, CourseOffering $offering, AttemptType $attempt = AttemptType::Regular): Enrollment
    {
        $this->authorizer->authorize($actor, 'enrollment:create', $student);

        if (Enrollment::query()->where(['student_id' => $student->getKey(), 'course_offering_id' => $offering->getKey()])->exists()) {
            throw new ConflictException('The student is already enrolled in this offering.');
        }

        return $this->audit->as($actor, fn (): Enrollment => Enrollment::query()->create([
            'student_id' => $student->getKey(),
            'course_offering_id' => $offering->getKey(),
            'attempt_type' => $attempt,
            'status' => 'enrolled',
        ]));
    }

    /**
     * Enrolls many students in one offering (all-or-nothing).
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, Enrollment>
     */
    public function bulkEnroll(User $actor, CourseOffering $offering, array $studentIds, AttemptType $attempt = AttemptType::Regular): Collection
    {
        $this->authorizer->authorize($actor, 'enrollment:bulk_create');

        $students = Student::query()->whereIn('id', $studentIds)->get();

        foreach ($students as $student) {
            $this->authorizer->authorize($actor, 'enrollment:bulk_create', $student);
        }

        return $this->audit->as($actor, fn (): Collection => DB::transaction(fn (): Collection => $students->map(
            fn (Student $student): Enrollment => $this->enroll($actor, $student, $offering, $attempt)
        )));
    }

    public function drop(User $actor, Enrollment $enrollment): Enrollment
    {
        $this->authorizer->authorize($actor, 'enrollment:drop', $enrollment->student);

        if ($enrollment->result !== null && $enrollment->result->marks !== null) {
            throw new InvalidStateException('An enrollment with entered marks cannot be dropped.');
        }

        $this->audit->as($actor, fn () => $enrollment->update(['status' => 'dropped']));

        return $enrollment;
    }
}
