<?php

use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hall;
use App\Models\Notice;
use App\Models\Semester;
use App\Models\Teacher;
use App\Services\Academic\CourseService;
use App\Services\Academic\ProgramService;
use App\Services\Academic\SemesterService;
use App\Services\Halls\HallService;
use App\Services\Notices\NoticeService;
use App\Services\People\StudentService;
use App\Services\Users\UserService;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();

    $this->cse = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();
    $this->eee = Department::query()->where('code', T::DEPT_EEE)->firstOrFail();
});

function courseData(int $departmentId, string $code): array
{
    return [
        'department_id' => $departmentId,
        'semester_number' => 3,
        'type' => 'theory',
        'code' => $code,
        'name' => 'Algorithms',
        'credit_hours' => 3,
    ];
}

describe('courses', function () {
    it('lets a department head create courses in their department only', function () {
        $courses = app(CourseService::class);
        $head = datasetUser(T::DEPT_HEAD_CSE);

        $course = $courses->create($head, courseData($this->cse->id, 'cse-2101'));

        expect($course->code)->toBe('CSE-2101')
            ->and(fn () => $courses->create($head, courseData($this->eee->id, 'EEE-2101')))->toThrow(ForbiddenException::class);
    });

    it('validates input and reports field errors', function () {
        try {
            app(CourseService::class)->create(datasetUser(T::SUPER_ADMIN), ['code' => 'X']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            expect($exception->errorCode())->toBe('VALIDATION_ERROR')
                ->and($exception->context['errors'])->toHaveKeys(['department_id', 'name', 'credit_hours']);
        }
    });

    it('rejects a duplicate code for the same explicit version', function () {
        $courses = app(CourseService::class);
        $admin = datasetUser(T::SUPER_ADMIN);

        $courses->create($admin, [...courseData($this->cse->id, 'CSE-3101'), 'version' => 1]);

        expect(fn () => $courses->create($admin, [...courseData($this->cse->id, 'CSE-3101'), 'version' => 1]))
            ->toThrow(ValidationException::class);
    });

    it('returns NOT_FOUND for unknown ids', function () {
        app(CourseService::class)->get(datasetUser(T::SUPER_ADMIN), 999999);
    })->throws(NotFoundException::class);

    it('assigns teachers within scope and audits the change', function () {
        $course = Course::query()->where('code', 'CSE-1202')->firstOrFail();
        $teacher = Teacher::query()->where('employee_id', 'T-CSE-002')->firstOrFail();

        app(CourseService::class)->assignTeachers(datasetUser(T::DEPT_HEAD_CSE), $course, [$teacher->id]);

        expect($course->teachers()->pluck('teachers.id')->all())->toBe([$teacher->id])
            ->and(App\Models\AuditLog::query()->where('action', 'course.teachers_assigned')->exists())->toBeTrue()
            ->and(fn () => app(CourseService::class)->assignTeachers(datasetUser(T::DEPT_HEAD_EEE), $course, []))
            ->toThrow(ForbiddenException::class);
    });

    it('archives a course', function () {
        $course = app(CourseService::class)->archive(datasetUser(T::DEPT_HEAD_CSE), Course::query()->where('code', 'CSE-1201')->firstOrFail());

        expect($course->is_active)->toBeFalse();
    });

    it('paginates with a cursor and caps the page size at 100', function () {
        $courses = app(CourseService::class);
        $admin = datasetUser(T::SUPER_ADMIN);

        $first = $courses->paginate($admin, limit: 4);
        $second = $courses->paginate($admin, limit: 4, cursor: $first->nextCursor()?->encode());

        expect($first->items())->toHaveCount(4)
            ->and($second->items())->toHaveCount(4)
            ->and(collect($first->items())->pluck('id')->intersect(collect($second->items())->pluck('id')))->toBeEmpty()
            ->and($courses->paginate($admin, limit: 500)->perPage())->toBe(100)
            ->and($courses->paginate($admin, ['search' => 'Circuits'])->items())->toHaveCount(2)
            ->and($courses->paginate($admin, ['department_id' => $this->eee->id])->items())->toHaveCount(4);
    });
});

describe('semesters', function () {
    it('keeps exactly one active semester', function () {
        $target = Semester::query()->where('code', 'SP2024')->firstOrFail();

        app(SemesterService::class)->setActive(datasetUser(T::SUPER_ADMIN), $target);

        expect(Semester::query()->where('is_active', true)->pluck('code')->all())->toBe(['SP2024'])
            ->and(fn () => app(SemesterService::class)->setActive(datasetUser(T::DEPT_HEAD_CSE), $target))
            ->toThrow(ForbiddenException::class);
    });
});

describe('programs', function () {
    it('lists programs for every reader but only lets super admin create them', function () {
        $programs = app(ProgramService::class);

        expect($programs->query(datasetUser(T::STUDENT_ELIGIBLE))->count())->toBe(2)
            ->and(fn () => $programs->create(datasetUser(T::DEPT_HEAD_CSE), [
                'department_id' => $this->cse->id, 'name' => 'M.Sc.', 'code' => 'MSC-CSE', 'required_credits' => 36,
            ]))->toThrow(ForbiddenException::class);
    });
});

describe('notices', function () {
    it('scopes notice creation and hides drafts from readers', function () {
        $notices = app(NoticeService::class);
        $head = datasetUser(T::DEPT_HEAD_CSE);

        $notice = $notices->create($head, ['title' => 'Lab closed', 'body' => 'The lab is closed on Friday.', 'audience' => 'students', 'department_id' => $this->cse->id]);
        Notice::factory()->draft()->create(['title' => 'Draft notice']);

        expect($notice->created_by)->toBe($head->id)
            ->and(fn () => $notices->create($head, ['title' => 'Everyone', 'body' => 'x', 'audience' => 'all']))->toThrow(ForbiddenException::class)
            ->and(fn () => $notices->create($head, ['title' => 'EEE', 'body' => 'x', 'audience' => 'all', 'department_id' => $this->eee->id]))->toThrow(ForbiddenException::class)
            ->and($notices->query(datasetUser(T::STUDENT_ELIGIBLE))->pluck('title')->all())->toBe(['Lab closed'])
            ->and($notices->query(datasetUser(T::HEAD_OF_INSTITUTION))->pluck('title')->all())->toContain('Draft notice');
    });
});

describe('halls', function () {
    it('limits a provost to their own hall', function () {
        $hallA = Hall::query()->where('code', T::HALL_A)->firstOrFail();
        $hallB = Hall::query()->where('code', T::HALL_B)->firstOrFail();

        expect(app(HallService::class)->query(datasetUser(T::PROVOST_A))->count())->toBe(2)
            ->and(authorizer()->allows(datasetUser(T::PROVOST_A), 'hall:assign_student', $hallA))->toBeTrue()
            ->and(authorizer()->allows(datasetUser(T::PROVOST_A), 'hall:assign_student', $hallB))->toBeFalse();
    });
});

describe('people', function () {
    it('creates a student with a login account and the student role', function () {
        $student = app(StudentService::class)->create(datasetUser(T::SUPER_ADMIN), [
            'name' => 'New Student',
            'email' => 'new.student@fec.test',
            'password' => 'password123',
            'department_id' => $this->cse->id,
            'batch_id' => App\Models\Batch::query()->where('department_id', $this->cse->id)->value('id'),
            'roll_number' => 'CSE-21-099',
            'registration_number' => 'REG-CSE-21-099',
            'current_semester' => 1,
        ]);

        expect($student->user->email)->toBe('new.student@fec.test')
            ->and($student->user->hasRole('student'))->toBeTrue()
            ->and(Illuminate\Support\Facades\Hash::check('password123', $student->user->password))->toBeTrue();
    });

    it('lets a student view only their own record', function () {
        $students = app(StudentService::class);
        $me = datasetUser(T::STUDENT_ELIGIBLE);

        expect($students->get($me, $me->student)->id)->toBe($me->student->id)
            ->and(fn () => $students->get($me, datasetUser(T::STUDENT_UNFINISHED)->student))->toThrow(ForbiddenException::class);
    });

    it('searches students within scope', function () {
        expect(app(StudentService::class)->search(datasetUser(T::DEPT_HEAD_EEE), 'Tasnim')->count())->toBe(1)
            ->and(app(StudentService::class)->search(datasetUser(T::DEPT_HEAD_EEE), 'Rakib')->count())->toBe(0);
    });
});

describe('users', function () {
    it('deactivates and reactivates accounts, but never your own', function () {
        $users = app(UserService::class);
        $admin = datasetUser(T::SUPER_ADMIN);
        $librarian = datasetUser(T::LIBRARIAN);

        $users->deactivate($admin, $librarian);
        expect(authorizer()->allows($librarian->fresh(), 'library_loans:view'))->toBeFalse();

        $users->reactivate($admin, $librarian);
        expect(authorizer()->allows($librarian->fresh(), 'library_loans:view'))->toBeTrue()
            ->and(fn () => $users->deactivate($admin, $admin))->toThrow(InvalidStateException::class)
            ->and(fn () => $users->deactivate(datasetUser(T::ADMIN_OFFICE), $librarian))->toThrow(ForbiddenException::class);
    });

    it('lets any user update their own name', function () {
        $me = datasetUser(T::STUDENT_ELIGIBLE);

        expect(app(UserService::class)->updateOwnProfile($me, ['name' => 'Rakib H.', 'is_active' => false])->name)->toBe('Rakib H.')
            ->and($me->fresh()->is_active)->toBeTrue();
    });
});
