<?php

use App\Enums\AttemptType;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Filament\Pages\Results\MyResults;
use App\Filament\Pages\Results\ResultEntry;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Academic\CourseOfferingService;
use App\Services\Academic\EnrollmentService;
use App\Services\Results\GradingScaleService;
use App\Services\Results\ResultService;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

function student(string $email): Student
{
    return Student::query()->where('user_id', datasetUser($email)->id)->firstOrFail();
}

it('maps marks to letter and grade point on the UGC scale', function (float $marks, string $letter, float $point) {
    expect(app(GradingScaleService::class)->gradeFor($marks))->toBe(['letter' => $letter, 'point' => $point]);
})->with([
    [100, 'A+', 4.0], [80, 'A+', 4.0], [79.99, 'A', 3.75], [75, 'A', 3.75], [74.99, 'A-', 3.5], [70, 'A-', 3.5],
    [65, 'B+', 3.25], [60, 'B', 3.0], [55, 'B-', 2.75], [50, 'C+', 2.5], [45, 'C', 2.25], [40, 'D', 2.0], [39.99, 'F', 0.0], [0, 'F', 0.0],
]);

it('rejects marks outside 0 to 100', function (float $marks) {
    app(GradingScaleService::class)->gradeFor($marks);
})->throws(ValidationException::class)->with([-1, 100.5]);

it('matches the hand calculated gpa and cgpa of every seeded student', function (string $email, array $expected) {
    $transcript = app(ResultService::class)->transcript(datasetUser($email), student($email));

    expect($transcript['cgpa_display'])->toBe($expected['cgpa'])
        ->and($transcript['earned_credits'])->toBe($expected['earned_credits'])
        ->and(collect($transcript['semesters'])->pluck('gpa_display', 'code')->all())->toBe($expected['semesters']);
})->with(fn () => collect(T::EXPECTED_RESULTS)->map(fn (array $expected, string $email): array => [$email, $expected])->all());

it('counts the retake, not the failed attempt, and ignores the unpublished improvement', function () {
    $email = T::STUDENT_ELIGIBLE;
    $transcript = app(ResultService::class)->transcript(datasetUser($email), student($email));

    $courses = collect($transcript['semesters'])->flatMap(fn (array $semester): array => $semester['courses']);

    expect($courses->where('code', 'CSE-1202')->pluck('letter')->all())->toBe(['F', 'B+'])
        ->and($courses->where('code', 'CSE-1201')->count())->toBe(1)
        ->and(collect($transcript['semesters'])->pluck('code')->all())->toBe(['SP2024', 'FA2024', 'SP2025']);
});

it('never exposes unpublished results to the student', function () {
    $transcript = app(ResultService::class)->transcript(datasetUser(T::STUDENT_UNFINISHED), student(T::STUDENT_UNFINISHED));

    expect(collect($transcript['semesters'])->pluck('code')->all())->toBe(['SP2024'])
        ->and(collect($transcript['semesters'])->flatMap(fn (array $s): array => $s['courses'])->pluck('code')->all())->not->toContain('CSE-1201');
});

it('lets a student read only their own results', function () {
    app(ResultService::class)->transcript(datasetUser(T::STUDENT_ELIGIBLE), student(T::STUDENT_NON_RESIDENT));
})->throws(ForbiddenException::class);

it('lets a department head read results of their own department only', function () {
    $service = app(ResultService::class);

    expect($service->cgpa(datasetUser(T::DEPT_HEAD_CSE), student(T::STUDENT_ELIGIBLE))->display())->toBe('3.56');
    expect(fn () => $service->cgpa(datasetUser(T::DEPT_HEAD_EEE), student(T::STUDENT_ELIGIBLE)))->toThrow(ForbiddenException::class);
});

it('shows the result page with the cgpa and hides the unpublished semester', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));

    $this->get('/results')->assertOk()
        ->assertSeeText('3.56')
        ->assertSeeText('Spring 2024')
        ->assertSeeText('Spring 2025')
        ->assertDontSeeText('Fall 2025');

    Livewire::test(MyResults::class)->assertSee('3.56');
});

it('renders the printable result sheet with the same cgpa', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/results/print')->assertOk()->assertSee('Result sheet')->assertSee('3.56')->assertDontSee('Fall 2025');
});

it('keeps the result menu away from staff accounts', function () {
    $this->actingAs(datasetUser(T::TEACHER));

    expect(MyResults::canAccess())->toBeFalse();
});

describe('workflow', function () {
    beforeEach(function () {
        $this->semester = Semester::query()->where('code', 'FA2025')->firstOrFail();
        $this->course = Course::query()->where('code', 'CSE-1101')->firstOrFail();
        $this->teacher = datasetUser(T::TEACHER);
        $this->headCse = datasetUser(T::DEPT_HEAD_CSE);
        $this->admin = datasetUser(T::SUPER_ADMIN);

        $this->offering = app(CourseOfferingService::class)->create($this->headCse, $this->course->id, $this->semester->id, 'B', $this->teacher->teacher->id);
        $this->enrollments = collect([T::STUDENT_ELIGIBLE, T::STUDENT_NON_RESIDENT])->map(
            fn (string $email) => app(EnrollmentService::class)->enroll(datasetUser(T::ADMIN_OFFICE), student($email), $this->offering, AttemptType::Improvement)
        );
    });

    it('takes marks from draft to published through teacher, department head and publisher', function () {
        $service = app(ResultService::class);

        foreach ($this->enrollments as $enrollment) {
            $service->enterMarks($this->teacher, $enrollment, 88);
        }

        expect(Result::query()->where('status', 'draft')->count())->toBe(2);
        expect($service->submitOffering($this->teacher, $this->offering))->toBe(2);
        expect($service->approveOffering($this->headCse, $this->offering))->toBe(2);

        $this->enrollments->each(fn (Enrollment $e) => expect($e->result()->first()->status->value)->toBe('approved'));

        expect($service->previewPublish($this->admin, $this->semester)['results'])->toBeGreaterThanOrEqual(2);
        expect($service->publishSemester($this->admin, $this->semester))->toBeGreaterThanOrEqual(2);

        expect($this->enrollments->first()->result()->first()->status->value)->toBe('published');
    });

    it('does not show approved results to the student until they are published', function () {
        $service = app(ResultService::class);
        $before = $service->cgpa(datasetUser(T::STUDENT_NON_RESIDENT), student(T::STUDENT_NON_RESIDENT))->display();

        $enrollment = $this->enrollments->last();
        $service->enterMarks($this->teacher, $enrollment, 20);
        $service->enterMarks($this->teacher, $this->enrollments->first(), 20);
        $service->submitOffering($this->teacher, $this->offering);
        $service->approveOffering($this->headCse, $this->offering);

        expect($service->cgpa(datasetUser(T::STUDENT_NON_RESIDENT), student(T::STUDENT_NON_RESIDENT))->display())->toBe($before);
    });

    it('refuses to submit until every enrolled student has marks', function () {
        app(ResultService::class)->enterMarks($this->teacher, $this->enrollments->first(), 70);
        app(ResultService::class)->submitOffering($this->teacher, $this->offering);
    })->throws(ValidationException::class);

    it('locks marks after submission', function () {
        $service = app(ResultService::class);
        $this->enrollments->each(fn (Enrollment $e) => $service->enterMarks($this->teacher, $e, 70));
        $service->submitOffering($this->teacher, $this->offering);

        $service->enterMarks($this->teacher, $this->enrollments->first(), 99);
    })->throws(InvalidStateException::class);

    it('only lets the course teacher enter marks and only the owning department head approve', function () {
        $service = app(ResultService::class);

        expect(fn () => $service->enterMarks(datasetUser(T::LIBRARIAN), $this->enrollments->first(), 70))->toThrow(ForbiddenException::class);

        $this->enrollments->each(fn (Enrollment $e) => $service->enterMarks($this->teacher, $e, 70));
        $service->submitOffering($this->teacher, $this->offering);

        expect(fn () => $service->approveOffering(datasetUser(T::DEPT_HEAD_EEE), $this->offering))->toThrow(ForbiddenException::class)
            ->and(fn () => $service->approveOffering($this->teacher, $this->offering))->toThrow(ForbiddenException::class);
    });

    it('only lets an authorized publisher publish', function () {
        app(ResultService::class)->publishSemester($this->headCse, $this->semester);
    })->throws(ForbiddenException::class);

    it('lets the result entry page save marks for the teacher', function () {
        $this->actingAs($this->teacher);

        Livewire::test(ResultEntry::class)
            ->set('offeringId', $this->offering->id)
            ->set("marks.{$this->enrollments->first()->id}", '91')
            ->call('saveMarks');

        expect($this->enrollments->first()->result()->first()->letter)->toBe('A+');
    });
});

it('detects duplicate offerings and enrollments', function () {
    $offering = CourseOffering::query()->firstOrFail();

    expect(fn () => app(CourseOfferingService::class)->create(datasetUser(T::SUPER_ADMIN), $offering->course_id, $offering->semester_id, $offering->section))
        ->toThrow(App\Exceptions\Domain\ConflictException::class);

    $enrollment = Enrollment::query()->firstOrFail();
    expect(fn () => app(EnrollmentService::class)->enroll(datasetUser(T::ADMIN_OFFICE), $enrollment->student, $enrollment->offering))
        ->toThrow(App\Exceptions\Domain\ConflictException::class);
});

it('lists the courses of the acting student only', function () {
    $mine = app(EnrollmentService::class)->myCourses(datasetUser(T::STUDENT_ELIGIBLE));

    expect($mine->pluck('student_id')->unique()->all())->toBe([student(T::STUDENT_ELIGIBLE)->id]);
});
