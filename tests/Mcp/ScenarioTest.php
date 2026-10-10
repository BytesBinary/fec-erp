<?php

use App\Models\ClearanceRequest;
use App\Models\Course;
use App\Models\Result;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
});

function mcpAs(string $email, string $access = 'full'): McpClient
{
    ['token' => $token] = mcpIntegrationFor(datasetUser($email), $access);

    return new McpClient(test(), $token);
}

describe('profile gate', function () {
    it('blocks every tool except me_* for a student with an incomplete profile', function () {
        $client = mcpAs(T::STUDENT_INCOMPLETE);

        foreach (['result_get_cgpa', 'clearance_check_eligibility', 'clearance_apply', 'student_list_my_courses', 'transcript_get', 'course_list'] as $tool) {
            expect($client->errorCode($tool))->toBe('PROFILE_INCOMPLETE', $tool);
        }

        foreach (['me_get_profile', 'me_list_permissions', 'me_list_sessions'] as $tool) {
            expect($client->call($tool)['isError'])->toBeFalse($tool);
        }
    });

    it('lets the incomplete student complete the profile through me_update_profile and then proceed', function () {
        $client = mcpAs(T::STUDENT_INCOMPLETE);

        $profile = $client->call('me_get_profile');
        expect($profile['payload']['data']['progress']['percent'])->toBe(0)
            ->and($profile['payload']['data']['missing'])->toHaveKey('photo');

        $update = $client->call('me_update_profile', [
            'full_name_certificate' => 'Arif Chowdhury', 'father_name' => 'Rahim Chowdhury', 'mother_name' => 'Karima Chowdhury', 'date_of_birth' => '2002-05-20',
            'phone' => '01712345678', 'email' => 'arif@example.com', 'present_address' => '12 Park Road, Dhaka', 'permanent_address' => 'Village Kuti, Cumilla',
            'guardian_phone' => '01812345678', 'blood_group' => 'O+', 'nid_or_birth_reg' => '2002123456789', 'is_residential' => false, 'emergency_contact_phone' => '01912345678',
        ]);

        expect($update['isError'])->toBeFalse()->and($update['payload']['summary'])->toContain('missing');

        App\Models\StudentProfile::query()->update(['photo_path' => 'student-photos/x.png']);

        expect($client->errorCode('result_get_cgpa'))->toBeNull();
    });

    it('lets staff update their own name and email only', function () {
        $client = mcpAs(T::TEACHER);

        expect($client->call('me_update_profile', ['name' => 'Tanvir A. Ahmed'])['isError'])->toBeFalse()
            ->and(datasetUser(T::TEACHER)->name)->toBe('Tanvir A. Ahmed')
            ->and($client->errorCode('me_update_profile', []))->toBe('VALIDATION_ERROR');
    });
});

describe('results', function () {
    it('returns the seeded CGPA to the student and never leaks unpublished results', function () {
        $client = mcpAs(T::STUDENT_ELIGIBLE);

        $cgpa = $client->call('result_get_cgpa');
        expect($cgpa['payload']['data']['cgpa_display'])->toBe(T::EXPECTED_RESULTS[T::STUDENT_ELIGIBLE]['cgpa']);

        $semesters = $client->call('result_get_semester')['payload']['data']['semesters'];
        expect(collect($semesters)->pluck('code')->all())->toBe(['SP2024', 'FA2024', 'SP2025']);

        $encoded = json_encode($client->call('transcript_get')['payload']);
        expect($encoded)->not->toContain('FA2025');
    });

    it('keeps students out of other students\' results', function () {
        $client = mcpAs(T::STUDENT_ELIGIBLE);
        $other = studentFor(T::STUDENT_NON_RESIDENT);

        expect($client->errorCode('result_get_cgpa', ['student_id' => $other->id]))->toBe('FORBIDDEN')
            ->and($client->errorCode('transcript_get', ['student_id' => $other->id]))->toBe('FORBIDDEN');
    });

    it('runs the whole results workflow through tools: enter, submit, approve, publish', function () {
        $teacher = mcpAs(T::TEACHER);
        $head = mcpAs(T::DEPT_HEAD_CSE);
        $admin = mcpAs(T::SUPER_ADMIN);

        $course = Course::query()->where('code', 'CSE-1101')->firstOrFail();
        $semester = App\Models\Semester::query()->where('code', 'FA2025')->firstOrFail();

        $offering = $admin->call('course_offering_create', ['course_id' => $course->id, 'semester_id' => $semester->id, 'section' => 'Z', 'teacher_id' => datasetUser(T::TEACHER)->teacher->id, 'idempotencyKey' => 'off-z'])['payload']['data'];
        $enrollment = $admin->call('enrollment_create', ['student_id' => studentFor(T::STUDENT_ELIGIBLE)->id, 'course_offering_id' => $offering['id'], 'attempt_type' => 'improvement'])['payload']['data'];

        $marks = $teacher->call('result_enter_marks', ['enrollment_id' => $enrollment['id'], 'marks' => 88]);
        expect($marks['payload']['data']['letter'])->toBe('A+');

        expect($teacher->call('result_submit', ['course_offering_id' => $offering['id']])['payload']['data']['count'])->toBe(1);
        expect($teacher->errorCode('result_approve', ['course_offering_id' => $offering['id'], 'confirm' => true]))->toBe('FORBIDDEN');

        $preview = $head->call('result_approve', ['course_offering_id' => $offering['id']]);
        expect($preview['payload']['dry_run'])->toBeTrue();
        expect($head->call('result_approve', ['course_offering_id' => $offering['id'], 'confirm' => true])['payload']['data']['count'])->toBe(1);

        $publish = $admin->call('result_publish', ['semester_id' => $semester->id]);
        expect($publish['payload']['dry_run'])->toBeTrue();
        expect($admin->call('result_publish', ['semester_id' => $semester->id, 'confirm' => true])['payload']['data']['published'])->toBeGreaterThanOrEqual(1)
            ->and(Result::query()->where('enrollment_id', $enrollment['id'])->first()->status->value)->toBe('published');
    });

    it('lets a teacher enter marks only for their own course', function () {
        $client = mcpAs(T::TEACHER);
        $enrollment = App\Models\Enrollment::query()->whereHas('offering.course', fn ($q) => $q->where('code', 'CSE-1201'))->firstOrFail();

        expect($client->errorCode('result_enter_marks', ['enrollment_id' => $enrollment->id, 'marks' => 50]))->toBe('FORBIDDEN');
    });
});

describe('clearance over MCP', function () {
    it('runs the whole clearance chain through tools, each actor limited to their stage', function () {
        $student = mcpAs(T::STUDENT_ELIGIBLE);
        $provost = mcpAs(T::PROVOST_A);
        $wrongProvost = mcpAs(T::PROVOST_B);
        $librarian = mcpAs(T::LIBRARIAN);
        $head = mcpAs(T::DEPT_HEAD_CSE);
        $institution = mcpAs(T::HEAD_OF_INSTITUTION);
        $office = mcpAs(T::ADMIN_OFFICE);

        expect($student->call('clearance_check_eligibility')['payload']['data']['eligible'])->toBeTrue();

        $applied = $student->call('clearance_apply', ['idempotencyKey' => 'apply-1'])['payload']['data'];
        $id = $applied['id'];
        expect($applied['current_stage'])->toBe('hall')->and($student->call('clearance_apply', ['idempotencyKey' => 'apply-1'])['payload']['replayed'])->toBeTrue();

        expect($wrongProvost->call('clearance_list_pending_for_me')['payload']['data'])->toBe([])
            ->and($wrongProvost->errorCode('clearance_get', ['request_id' => $id]))->toBe('FORBIDDEN')
            ->and($wrongProvost->errorCode('clearance_approve', ['request_id' => $id, 'confirm' => true]))->toBe('FORBIDDEN')
            ->and($librarian->errorCode('clearance_approve', ['request_id' => $id, 'confirm' => true]))->toBe('FORBIDDEN');

        $pending = $provost->call('clearance_list_pending_for_me')['payload']['data'];
        expect($pending)->toHaveCount(1)->and($pending[0]['id'])->toBe($id);

        $preview = $provost->call('clearance_approve', ['request_id' => $id]);
        expect($preview['payload']['dry_run'])->toBeTrue()->and(ClearanceRequest::query()->find($id)->currentStage->key)->toBe('hall');

        $provost->call('clearance_approve', ['request_id' => $id, 'confirm' => true, 'remarks' => 'ok']);
        $librarian->call('clearance_approve', ['request_id' => $id, 'confirm' => true]);
        $head->call('clearance_approve', ['request_id' => $id, 'confirm' => true]);
        $final = $institution->call('clearance_approve', ['request_id' => $id, 'confirm' => true]);

        expect($final['payload']['data']['status'])->toBe('ready_for_collection');

        expect($student->call('clearance_get_my_status')['payload']['data']['status'])->toBe('ready_for_collection');
        expect($office->call('clearance_search')['payload']['data'])->toHaveCount(1);

        expect($office->call('clearance_print', ['request_id' => $id])['payload']['dry_run'])->toBeTrue();
        $print = $office->call('clearance_print', ['request_id' => $id, 'confirm' => true])['payload']['data'];
        expect($print['print_url'])->toContain("/clearance/{$id}/print");

        $collected = $office->call('clearance_mark_collected', ['request_id' => $id, 'id_verified' => true, 'confirm' => true]);
        expect($collected['payload']['data']['status'])->toBe('collected');

        expect($office->call('clearance_verify_integrity', ['request_id' => $id])['payload']['data']['intact'])->toBeTrue();
    });

    it('lets a read-only approver look at requests but not decide', function () {
        applyForClearance();
        $readOnly = mcpAs(T::PROVOST_A, 'read_only');

        expect($readOnly->call('clearance_list_pending_for_me')['isError'])->toBeFalse()
            ->and($readOnly->errorCode('clearance_approve', ['request_id' => 1, 'confirm' => true]))->toBe('FORBIDDEN');
    });

    it('hides unreachable students from a hall provost over MCP', function () {
        $request = applyForClearance(T::STUDENT_LIBRARY_LOAN);
        $provostA = mcpAs(T::PROVOST_A);

        expect($provostA->errorCode('clearance_get', ['request_id' => $request->id]))->toBe('FORBIDDEN')
            ->and($provostA->errorCode('hall_dues_get', ['student_id' => studentFor(T::STUDENT_LIBRARY_LOAN)->id]))->toBe('FORBIDDEN');
    });

    it('shows the approver the dues of their stage', function () {
        $loan = studentFor(T::STUDENT_LIBRARY_LOAN);
        $librarian = mcpAs(T::LIBRARIAN);

        $dues = $librarian->call('library_dues_get', ['student_id' => $loan->id])['payload']['data'];

        expect($dues['outstanding_loans'])->toBe(1)->and($dues['unpaid_fines'])->toEqual(120);
    });
});

describe('admin tools', function () {
    it('searches the audit log by channel and integration', function () {
        $admin = mcpAs(T::SUPER_ADMIN);
        $admin->call('department_create', ['name' => 'Law', 'code' => 'LAW']);

        $logs = $admin->call('audit_log_search', ['channel' => 'mcp', 'action' => 'department.created'])['payload']['data']['items'];

        expect($logs)->toHaveCount(1)->and($logs[0]['channel'])->toBe('mcp');
    });

    it('lets only super admin read the permission matrix', function () {
        expect(mcpAs(T::SUPER_ADMIN)->call('permission_matrix_get')['isError'])->toBeFalse()
            ->and(mcpAs(T::TEACHER)->errorCode('permission_matrix_get'))->toBe('FORBIDDEN');
    });

    it('assigns and revokes a role with scope', function () {
        $admin = mcpAs(T::SUPER_ADMIN);
        $user = datasetUser(T::LIBRARIAN);
        $department = App\Models\Department::query()->where('code', T::DEPT_EEE)->firstOrFail();

        $assigned = $admin->call('role_assign', ['user_id' => $user->id, 'role' => 'department_head', 'scope_ids' => [$department->id]]);
        expect($assigned['isError'])->toBeFalse()->and($user->fresh()->hasRole('department_head'))->toBeTrue();

        expect($admin->call('role_revoke', ['user_id' => $user->id, 'role' => 'department_head', 'confirm' => true])['isError'])->toBeFalse()
            ->and($user->fresh()->hasRole('department_head'))->toBeFalse();
    });

    it('lets a department head manage only courses of their own department', function () {
        $head = mcpAs(T::DEPT_HEAD_CSE);
        $eee = App\Models\Department::query()->where('code', T::DEPT_EEE)->firstOrFail();
        $cse = App\Models\Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

        $args = ['semester_number' => 4, 'type' => 'theory', 'code' => 'XX-4101', 'name' => 'Seminar', 'credit_hours' => 2];

        expect($head->errorCode('course_create', $args + ['department_id' => $eee->id]))->toBe('FORBIDDEN')
            ->and($head->call('course_create', $args + ['department_id' => $cse->id])['isError'])->toBeFalse();
    });
});
