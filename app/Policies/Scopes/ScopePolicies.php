<?php

namespace App\Policies\Scopes;

use App\Models\ClearanceRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hall;
use App\Models\Notice;
use App\Models\Student;
use App\Models\User;
use App\Services\Clearance\ApproverResolver;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\ResourceScope;

/**
 * Named, scope-aware policy functions for the questions the domain asks most
 * often. Each is a thin, readable wrapper around {@see Authorizer}, so all of
 * them share the same rules (role union + per-role scope). Clearance-specific
 * functions (canApproveClearance, …) are added with the clearance module.
 */
class ScopePolicies
{
    public function __construct(protected Authorizer $authorizer) {}

    public function canManageCourse(User $user, Course $course): bool
    {
        return $this->authorizer->allows($user, 'course:update', $course);
    }

    public function canCreateCourseIn(User $user, Department $department): bool
    {
        return $this->authorizer->allows($user, 'course:create', $department);
    }

    public function canAssignCourseTeacher(User $user, Course $course): bool
    {
        return $this->authorizer->allows($user, 'course:assign_teacher', $course);
    }

    public function canViewStudent(User $user, Student $student): bool
    {
        return $this->authorizer->allows($user, 'student:view', $student);
    }

    public function canViewResultsOf(User $user, Student $student): bool
    {
        return $this->authorizer->allows($user, 'result:view', $student);
    }

    public function canApproveResultsFor(User $user, Course $course): bool
    {
        return $this->authorizer->allows($user, 'result:approve', $course);
    }

    public function canEnterMarksFor(User $user, Course $course): bool
    {
        return $this->authorizer->allows($user, 'result:enter_marks', $course);
    }

    /**
     * Provost of a hall may act on that hall's residents only.
     */
    public function canManageHallResident(User $user, Hall|int $hall): bool
    {
        $hallId = $hall instanceof Hall ? $hall->id : $hall;

        return $this->authorizer->allows($user, 'hall:assign_student', ResourceScope::forHall($hallId));
    }

    public function canPublishNotice(User $user, ?int $departmentId = null, ?int $hallId = null): bool
    {
        if ($departmentId === null && $hallId === null) {
            return $this->authorizer->scopeFor($user, 'notice:create')->global;
        }

        $scope = ResourceScope::forDepartment($departmentId)->merge(ResourceScope::forHall($hallId));

        return $this->authorizer->allows($user, 'notice:create', $scope);
    }

    public function canManageNotice(User $user, Notice $notice): bool
    {
        return $this->authorizer->allows($user, 'notice:update', $notice);
    }

    /**
     * The approver must hold the role of the request's current stage and that
     * role's scope (hall / department) must cover the student.
     */
    public function canApproveClearance(User $user, ClearanceRequest $request): bool
    {
        return app(ApproverResolver::class)->canAct($user, $request);
    }

    /**
     * Approving a clearance stage for a student: the approver's role scope
     * must cover the student's department / hall (clearance stage matching
     * is layered on top of this in the clearance module).
     */
    public function canActOnStudentClearance(User $user, ResourceScope $studentScope): bool
    {
        return $this->authorizer->allows($user, 'clearance:approve', $studentScope);
    }
}
