<?php

namespace App\Services\Results;

use App\Enums\ResultStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Grading\GpaCalculator;
use App\Support\Grading\GpaSummary;
use App\Support\Grading\GradedCourse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Result workflow (draft → submitted → approved → published) and the
 * published-only student views (spec §7). Reads for students always go
 * through {@see Result::scopePublished()}; unpublished rows are only
 * reachable by the staff workflow methods.
 */
class ResultService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected AuditLogger $audit,
        protected GradingScaleService $scale,
    ) {}

    /**
     * Published results of one student grouped by semester, with GPAs and CGPA.
     *
     * @return array{
     *     semesters: list<array{semester_id: int, name: string, code: string, courses: list<array<string, mixed>>, gpa: ?float, gpa_display: string, credits: float, earned_credits: float}>,
     *     cgpa: ?float,
     *     cgpa_display: string,
     *     counted_credits: float,
     *     earned_credits: float
     * }
     */
    public function transcript(User $actor, Student $student): array
    {
        $this->authorizer->authorize($actor, 'result:view', $student);

        $rows = Result::query()
            ->published()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->getKey())->where('status', 'enrolled'))
            ->with(['enrollment.offering.course', 'enrollment.offering.semester'])
            ->get()
            ->sortBy(fn (Result $result): string => $this->attemptSortKey($result))
            ->values();

        $passAbove = (float) config('grading.pass_above');
        $order = 0;
        $all = [];
        $bySemester = [];

        foreach ($rows as $result) {
            $offering = $result->enrollment->offering;
            $graded = new GradedCourse($offering->course_id, (float) $offering->course->credit_hours, (float) $result->grade_point, ++$order);
            $all[] = $graded;

            $bySemester[$offering->semester_id]['semester'] = $offering->semester;
            $bySemester[$offering->semester_id]['graded'][] = $graded;
            $bySemester[$offering->semester_id]['courses'][] = [
                'course_id' => $offering->course_id,
                'code' => $offering->course->code,
                'name' => $offering->course->name,
                'credits' => (float) $offering->course->credit_hours,
                'marks' => $result->marks,
                'letter' => $result->letter,
                'grade_point' => $result->grade_point,
                'attempt' => $result->enrollment->attempt_type->value,
            ];
        }

        $semesters = [];

        foreach ($bySemester as $semesterId => $data) {
            $summary = GpaCalculator::semesterGpa($data['graded'], $passAbove);
            $semesters[] = [
                'semester_id' => $semesterId,
                'name' => $data['semester']->name,
                'code' => $data['semester']->code,
                'courses' => $data['courses'],
                'gpa' => $summary->gpa,
                'gpa_display' => $summary->display(),
                'credits' => $summary->countedCredits,
                'earned_credits' => $summary->earnedCredits,
            ];
        }

        $cgpa = $this->cgpaOf($all);

        return [
            'semesters' => $semesters,
            'cgpa' => $cgpa->gpa,
            'cgpa_display' => $cgpa->display(),
            'counted_credits' => $cgpa->countedCredits,
            'earned_credits' => $cgpa->earnedCredits,
        ];
    }

    public function cgpa(User $actor, Student $student): GpaSummary
    {
        $this->authorizer->authorize($actor, 'result:view', $student);

        return $this->cgpaOf($this->publishedGradedCourses($student));
    }

    /**
     * Earned credits and CGPA from published results only, for system checks
     * such as clearance eligibility (no actor authorization).
     */
    public function publishedSummary(Student $student): GpaSummary
    {
        return $this->cgpaOf($this->publishedGradedCourses($student));
    }

    /**
     * Rows of one offering for the staff workflow (all statuses).
     *
     * @return Collection<int, Enrollment>
     */
    public function rosterOf(User $actor, CourseOffering $offering): Collection
    {
        $this->authorizeOffering($actor, 'result:view', $offering);

        return $offering->enrollments()->with(['student.user', 'result'])->where('status', 'enrolled')->get();
    }

    public function enterMarks(User $actor, Enrollment $enrollment, float $marks): Result
    {
        $offering = $enrollment->offering()->with('course')->firstOrFail();
        $this->authorizeOffering($actor, 'result:enter_marks', $offering);

        $result = $enrollment->result()->first();

        if ($result !== null && $result->status !== ResultStatus::Draft) {
            throw new InvalidStateException("Marks can only be changed while the result is a draft (currently {$result->status->value}).");
        }

        $grade = $this->scale->gradeFor($marks);

        return $this->audit->as($actor, fn (): Result => Result::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->getKey()],
            ['marks' => $marks, 'letter' => $grade['letter'], 'grade_point' => $grade['point'], 'status' => ResultStatus::Draft, 'entered_by' => $actor->getKey()],
        ));
    }

    /**
     * Teacher hands the offering's results to the department head. Every
     * enrolled student needs marks first.
     */
    public function submitOffering(User $actor, CourseOffering $offering): int
    {
        $this->authorizeOffering($actor, 'result:submit', $offering);

        $count = $this->transition($actor, $offering, ResultStatus::Draft, ResultStatus::Submitted, requireAll: true);

        app(\App\Services\Notifications\NotificationEvents::class)->emit('result.submitted', ['course' => $offering->course->code.' '.$offering->course->name, 'count' => $count, 'link' => url('/result-entry')], 'result_submit:'.$offering->getKey().':'.now()->format('YmdH'), null, $offering->course->department_id);

        return $count;
    }

    public function approveOffering(User $actor, CourseOffering $offering): int
    {
        $this->authorizeOffering($actor, 'result:approve', $offering);

        $count = $this->transition($actor, $offering, ResultStatus::Submitted, ResultStatus::Approved);

        app(\App\Services\Notifications\NotificationEvents::class)->emit('result.approved', ['course' => $offering->course->code.' '.$offering->course->name, 'link' => url('/results/publish')], 'result_approve:'.$offering->getKey().':'.now()->format('YmdH'));

        return $count;
    }

    /**
     * What publishing a semester would change (the MCP dry-run preview).
     *
     * @return array{semester: string, results: int, offerings: int, students: int}
     */
    public function previewPublish(User $actor, Semester $semester): array
    {
        $this->authorizer->authorize($actor, 'result:publish');

        $query = $this->approvedInSemester($semester);

        return [
            'semester' => $semester->name,
            'results' => (clone $query)->count(),
            'offerings' => (clone $query)->join('enrollments', 'enrollments.id', '=', 'results.enrollment_id')->distinct()->count('enrollments.course_offering_id'),
            'students' => (clone $query)->join('enrollments', 'enrollments.id', '=', 'results.enrollment_id')->distinct()->count('enrollments.student_id'),
        ];
    }

    /**
     * Publishes every approved result of the semester; students see them
     * from now on.
     */
    public function publishSemester(User $actor, Semester $semester): int
    {
        $this->authorizer->authorize($actor, 'result:publish');

        return $this->audit->as($actor, fn (): int => DB::transaction(function () use ($actor, $semester): int {
            $ids = $this->approvedInSemester($semester)->pluck('results.id');

            $studentIds = [];

            Result::query()->whereIn('id', $ids)->with('enrollment')->each(function (Result $result) use ($actor, &$studentIds): void {
                $result->update(['status' => ResultStatus::Published, 'published_at' => now(), 'published_by' => $actor->getKey()]);
                $studentIds[$result->enrollment->student_id] = true;
            });

            \App\Models\Student::query()->whereIn('id', array_keys($studentIds))->with('user')->each(function (\App\Models\Student $student) use ($semester): void {
                app(\App\Services\Notifications\NotificationEvents::class)->emit('result.semester_published', ['semester' => $semester->name, 'link' => url('/results')], 'published:'.$semester->getKey().':'.$student->getKey().':'.now()->format('Ymd'), $student->user, $student->department_id);
            });

            return $ids->count();
        }));
    }

    /**
     * @param  list<GradedCourse>  $courses
     */
    protected function cgpaOf(array $courses): GpaSummary
    {
        return GpaCalculator::cgpa(
            $courses,
            (string) config('grading.retake_policy'),
            (bool) config('grading.failed_course_counts'),
            (float) config('grading.pass_above'),
        );
    }

    /**
     * @return list<GradedCourse>
     */
    protected function publishedGradedCourses(Student $student): array
    {
        $order = 0;

        return Result::query()
            ->published()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->getKey())->where('status', 'enrolled'))
            ->with(['enrollment.offering.course', 'enrollment.offering.semester'])
            ->get()
            ->sortBy(fn (Result $result): string => $this->attemptSortKey($result))
            ->values()
            ->map(fn (Result $result): GradedCourse => new GradedCourse(
                $result->enrollment->offering->course_id,
                (float) $result->enrollment->offering->course->credit_hours,
                (float) $result->grade_point,
                ++$order,
            ))
            ->all();
    }

    /**
     * Chronological attempt order (semester start, then result id), shared by
     * the transcript and the CGPA so "latest attempt" means the same in both.
     */
    protected function attemptSortKey(Result $result): string
    {
        return $result->enrollment->offering->semester->starts_on->format('Y-m-d').'|'.str_pad((string) $result->id, 10, '0', STR_PAD_LEFT);
    }

    protected function authorizeOffering(User $actor, string $permission, CourseOffering $offering): void
    {
        $offering->loadMissing('course');
        $this->authorizer->authorize($actor, $permission, $offering);
    }

    protected function approvedInSemester(Semester $semester): \Illuminate\Database\Eloquent\Builder
    {
        return Result::query()
            ->where('results.status', ResultStatus::Approved->value)
            ->whereHas('enrollment.offering', fn ($query) => $query->where('semester_id', $semester->getKey()));
    }

    protected function transition(User $actor, CourseOffering $offering, ResultStatus $from, ResultStatus $to, bool $requireAll = false): int
    {
        return $this->audit->as($actor, fn (): int => DB::transaction(function () use ($actor, $offering, $from, $to, $requireAll): int {
            $enrollments = $offering->enrollments()->where('status', 'enrolled')->with('result')->get();

            if ($enrollments->isEmpty()) {
                throw new NotFoundException('This offering has no enrolled students.');
            }

            if ($requireAll && $enrollments->contains(fn (Enrollment $enrollment): bool => $enrollment->result?->marks === null)) {
                throw new ValidationException('Enter marks for every enrolled student before submitting.');
            }

            $targets = $enrollments->filter(fn (Enrollment $enrollment): bool => $enrollment->result?->status === $from);

            if ($targets->isEmpty()) {
                throw new InvalidStateException("There are no {$from->value} results to move to {$to->value}.");
            }

            foreach ($targets as $enrollment) {
                $changes = ['status' => $to];

                if ($to === ResultStatus::Submitted) {
                    $changes['submitted_at'] = now();
                }

                if ($to === ResultStatus::Approved) {
                    $changes += ['approved_at' => now(), 'approved_by' => $actor->getKey()];
                }

                $enrollment->result->update($changes);
            }

            return $targets->count();
        }));
    }
}
