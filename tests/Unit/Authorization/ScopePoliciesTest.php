<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Hall;
use App\Models\Notice;
use App\Models\Student;
use App\Policies\Scopes\ScopePolicies;
use App\Support\Authorization\ResourceScope;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();

    $this->policies = app(ScopePolicies::class);
    $this->hallA = Hall::query()->where('code', T::HALL_A)->firstOrFail();
    $this->hallB = Hall::query()->where('code', T::HALL_B)->firstOrFail();
    $this->cse = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();
    $this->eee = Department::query()->where('code', T::DEPT_EEE)->firstOrFail();
});

/**
 * Scope of a student living in a hall (residencies arrive with the clearance module).
 */
function residentScope(Student $student, Hall $hall): ResourceScope
{
    return $student->authorizationScope()->merge(ResourceScope::forHall($hall->id));
}

function studentOf(string $email): Student
{
    return datasetUser($email)->student;
}

describe('hall provost', function () {
    it('may act on students of their own hall only', function () {
        $student = studentOf(T::STUDENT_ELIGIBLE);
        $provostA = datasetUser(T::PROVOST_A);
        $provostB = datasetUser(T::PROVOST_B);

        expect($this->policies->canActOnStudentClearance($provostA, residentScope($student, $this->hallA)))->toBeTrue()
            ->and($this->policies->canActOnStudentClearance($provostA, residentScope($student, $this->hallB)))->toBeFalse()
            ->and($this->policies->canActOnStudentClearance($provostB, residentScope($student, $this->hallA)))->toBeFalse()
            ->and($this->policies->canActOnStudentClearance($provostB, residentScope($student, $this->hallB)))->toBeTrue();
    });

    it('cannot act on a non-residential student', function () {
        $student = studentOf(T::STUDENT_NON_RESIDENT);

        expect($this->policies->canActOnStudentClearance(datasetUser(T::PROVOST_A), $student->authorizationScope()))->toBeFalse();
    });

    it('manages residents of their own hall only', function () {
        expect($this->policies->canManageHallResident(datasetUser(T::PROVOST_A), $this->hallA))->toBeTrue()
            ->and($this->policies->canManageHallResident(datasetUser(T::PROVOST_A), $this->hallB->id))->toBeFalse();
    });
});

describe('department head', function () {
    it('may act on students of their own department only', function () {
        $cseStudent = residentScope(studentOf(T::STUDENT_ELIGIBLE), $this->hallA);
        $eeeStudent = residentScope(studentOf(T::STUDENT_LIBRARY_LOAN), $this->hallB);

        expect($this->policies->canActOnStudentClearance(datasetUser(T::DEPT_HEAD_CSE), $cseStudent))->toBeTrue()
            ->and($this->policies->canActOnStudentClearance(datasetUser(T::DEPT_HEAD_CSE), $eeeStudent))->toBeFalse()
            ->and($this->policies->canActOnStudentClearance(datasetUser(T::DEPT_HEAD_EEE), $eeeStudent))->toBeTrue()
            ->and($this->policies->canActOnStudentClearance(datasetUser(T::DEPT_HEAD_EEE), $cseStudent))->toBeFalse();
    });

    it('manages courses of their own department only', function () {
        $cseCourse = Course::query()->where('code', 'CSE-1201')->firstOrFail();
        $eeeCourse = Course::query()->where('code', 'EEE-1201')->firstOrFail();
        $head = datasetUser(T::DEPT_HEAD_CSE);

        expect($this->policies->canManageCourse($head, $cseCourse))->toBeTrue()
            ->and($this->policies->canManageCourse($head, $eeeCourse))->toBeFalse()
            ->and($this->policies->canAssignCourseTeacher($head, $cseCourse))->toBeTrue()
            ->and($this->policies->canAssignCourseTeacher($head, $eeeCourse))->toBeFalse()
            ->and($this->policies->canCreateCourseIn($head, $this->cse))->toBeTrue()
            ->and($this->policies->canCreateCourseIn($head, $this->eee))->toBeFalse()
            ->and($this->policies->canApproveResultsFor($head, $cseCourse))->toBeTrue()
            ->and($this->policies->canApproveResultsFor($head, $eeeCourse))->toBeFalse();
    });

    it('views students of their own department only', function () {
        $head = datasetUser(T::DEPT_HEAD_CSE);

        expect($this->policies->canViewStudent($head, studentOf(T::STUDENT_ELIGIBLE)))->toBeTrue()
            ->and($this->policies->canViewStudent($head, studentOf(T::STUDENT_LIBRARY_LOAN)))->toBeFalse();
    });

    it('also keeps the teacher permissions of their second role, scoped to their own courses', function () {
        $head = datasetUser(T::DEPT_HEAD_CSE);

        expect($this->policies->canEnterMarksFor($head, Course::query()->where('code', 'CSE-1201')->firstOrFail()))->toBeTrue()
            ->and($this->policies->canEnterMarksFor($head, Course::query()->where('code', 'CSE-1101')->firstOrFail()))->toBeFalse();
    });
});

describe('teacher', function () {
    it('enters marks only for assigned courses', function () {
        $teacher = datasetUser(T::TEACHER);

        expect($this->policies->canEnterMarksFor($teacher, Course::query()->where('code', 'CSE-1101')->firstOrFail()))->toBeTrue()
            ->and($this->policies->canEnterMarksFor($teacher, Course::query()->where('code', 'CSE-1201')->firstOrFail()))->toBeFalse()
            ->and($this->policies->canManageCourse($teacher, Course::query()->where('code', 'CSE-1101')->firstOrFail()))->toBeFalse();
    });
});

describe('student', function () {
    it('sees only their own record and results', function () {
        $me = datasetUser(T::STUDENT_ELIGIBLE);

        expect($this->policies->canViewStudent($me, $me->student))->toBeTrue()
            ->and($this->policies->canViewResultsOf($me, $me->student))->toBeTrue()
            ->and($this->policies->canViewStudent($me, studentOf(T::STUDENT_UNFINISHED)))->toBeFalse()
            ->and($this->policies->canViewResultsOf($me, studentOf(T::STUDENT_UNFINISHED)))->toBeFalse();
    });

    it('cannot approve clearances', function () {
        $me = datasetUser(T::STUDENT_ELIGIBLE);

        expect($this->policies->canActOnStudentClearance($me, $me->student->authorizationScope()))->toBeFalse();
    });
});

describe('global approvers', function () {
    it('lets the librarian and the head of institution act on any student', function (string $email) {
        $scope = residentScope(studentOf(T::STUDENT_LIBRARY_LOAN), $this->hallB);

        expect($this->policies->canActOnStudentClearance(datasetUser($email), $scope))->toBeTrue();
    })->with([T::LIBRARIAN, T::HEAD_OF_INSTITUTION, T::SUPER_ADMIN]);

    it('does not let read-only roles approve', function (string $email) {
        $scope = studentOf(T::STUDENT_ELIGIBLE)->authorizationScope();

        expect($this->policies->canActOnStudentClearance(datasetUser($email), $scope))->toBeFalse();
    })->with([T::PRINCIPAL, T::ADMIN_OFFICE, T::TEACHER]);
});

describe('notices', function () {
    it('scopes who may publish department, hall and global notices', function () {
        $head = datasetUser(T::DEPT_HEAD_CSE);
        $provost = datasetUser(T::PROVOST_A);

        expect($this->policies->canPublishNotice($head, departmentId: $this->cse->id))->toBeTrue()
            ->and($this->policies->canPublishNotice($head, departmentId: $this->eee->id))->toBeFalse()
            ->and($this->policies->canPublishNotice($head))->toBeFalse()
            ->and($this->policies->canPublishNotice($provost, hallId: $this->hallA->id))->toBeTrue()
            ->and($this->policies->canPublishNotice($provost, hallId: $this->hallB->id))->toBeFalse()
            ->and($this->policies->canPublishNotice(datasetUser(T::HEAD_OF_INSTITUTION)))->toBeTrue()
            ->and($this->policies->canPublishNotice(datasetUser(T::LIBRARIAN)))->toBeFalse();
    });

    it('lets a department head manage notices of their department only', function () {
        $ownNotice = Notice::factory()->create(['department_id' => $this->cse->id]);
        $otherNotice = Notice::factory()->create(['department_id' => $this->eee->id]);

        expect($this->policies->canManageNotice(datasetUser(T::DEPT_HEAD_CSE), $ownNotice))->toBeTrue()
            ->and($this->policies->canManageNotice(datasetUser(T::DEPT_HEAD_CSE), $otherNotice))->toBeFalse();
    });
});

describe('list scopes', function () {
    it('filters list queries to the actor scope', function () {
        $query = fn (string $email) => authorizer()
            ->scopeFor(datasetUser($email), 'student:list')
            ->constrain(Student::query(), ['department' => 'department_id', 'self' => 'user_id'])
            ->pluck('roll_number')
            ->sort()
            ->values()
            ->all();

        expect($query(T::DEPT_HEAD_CSE))->toBe(['CSE-21-001', 'CSE-21-002', 'CSE-21-003', 'CSE-21-004'])
            ->and($query(T::DEPT_HEAD_EEE))->toBe(['EEE-21-001'])
            ->and($query(T::STUDENT_ELIGIBLE))->toBe([])
            ->and($query(T::ADMIN_OFFICE))->toHaveCount(5)
            ->and($query(T::TEACHER))->toBe([]);
    });

    it('limits a student to their own record', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);
        $scope = authorizer()->scopeFor($student, 'student:view');

        expect($scope->selfUserId)->toBe($student->id)
            ->and($scope->constrain(Student::query(), ['self' => 'user_id'])->pluck('roll_number')->all())->toBe(['CSE-21-003']);
    });

    it('reports the concrete scope ids', function () {
        $scope = authorizer()->scopeFor(datasetUser(T::PROVOST_B), 'hall:assign_student');

        expect($scope->granted)->toBeTrue()
            ->and($scope->global)->toBeFalse()
            ->and($scope->hallIds)->toBe([$this->hallB->id]);
    });
});
