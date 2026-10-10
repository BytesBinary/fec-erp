<?php

/*
|--------------------------------------------------------------------------
| ERP domain configuration
|--------------------------------------------------------------------------
|
| RBAC: the permission catalog (`resource:action`), the aliases that map
| spec permission names onto the Filament Shield permissions that already
| guard the legacy resources, and the DEFAULT role → permission matrix.
|
| The matrix below is only the seed. The live mapping is data in the
| `role_has_permissions` table; super admin edits it from the Roles page
| (Shield) or through PermissionMatrixService. Re-running PermissionSeeder
| only ADDS missing grants, it never removes grants made by an admin.
|
*/

$shieldResources = [
    'department' => 'Department',
    'designation' => 'Designation',
    'batch' => 'Batch',
    'course' => 'Course',
    'teacher' => 'Teacher',
    'staff' => 'Staff',
    'student' => 'Student',
    'exam_type' => 'ExamType',
    'exam_hall' => 'ExamHall',
    'exam_duty' => 'ExamDuty',
];

$shieldActions = [
    'list' => 'ViewAny',
    'view' => 'View',
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'restore' => 'Restore',
    'force_delete' => 'ForceDelete',
    'delete_any' => 'DeleteAny',
    'restore_any' => 'RestoreAny',
    'force_delete_any' => 'ForceDeleteAny',
    'replicate' => 'Replicate',
    'reorder' => 'Reorder',
];

$aliases = [];

foreach ($shieldResources as $resource => $model) {
    foreach ($shieldActions as $action => $shieldAction) {
        $aliases["{$resource}:{$action}"] = "{$shieldAction}:{$model}";
    }
}

$aliases['course:archive'] = 'Update:Course';
$aliases['course:assign_teacher'] = 'View:AssignTeachers';

return [

    'rbac' => [

        /*
         | Spec permission name → existing Shield permission name. The
         | Authorizer checks the Shield permission for these, so there is a
         | single source of truth per action.
         */
        'aliases' => $aliases,

        /*
         | Native `resource:action` permissions (stored as-is). Value = label
         | shown in the Roles page "Custom permissions" tab.
         */
        'permissions' => [
            'user:list' => 'List users',
            'user:view' => 'View a user',
            'user:create' => 'Create users',
            'user:update' => 'Update users',
            'user:deactivate' => 'Deactivate / reactivate users',
            'role:list' => 'List roles',
            'role:assign' => 'Assign roles to users',
            'role:revoke' => 'Revoke roles from users',
            'permission_matrix:view' => 'View the permission matrix',
            'permission_matrix:update' => 'Change the permission matrix',

            'program:list' => 'List programs',
            'program:view' => 'View a program',
            'program:create' => 'Create programs',
            'program:update' => 'Update programs',
            'semester:list' => 'List semesters',
            'semester:view' => 'View a semester',
            'semester:create' => 'Create semesters',
            'semester:update' => 'Update semesters',
            'semester:activate' => 'Set the active semester',
            'hall:list' => 'List halls',
            'hall:view' => 'View a hall',
            'hall:create' => 'Create halls',
            'hall:update' => 'Update halls',
            'hall:assign_student' => 'Assign students to halls',
            'hall_dues:view' => 'View hall dues',
            'hall_dues:manage' => 'Record and settle hall dues',
            'library_loans:view' => 'View library loans',
            'library_loans:manage' => 'Issue and return library loans',
            'library_dues:view' => 'View library fines',

            'enrollment:list' => 'List enrollments',
            'enrollment:create' => 'Enroll students',
            'enrollment:bulk_create' => 'Bulk-enroll students',
            'enrollment:drop' => 'Drop enrollments',
            'result:view' => 'View results',
            'result:enter_marks' => 'Enter marks',
            'result:submit' => 'Submit results for approval',
            'result:approve' => 'Approve results',
            'result:publish' => 'Publish results',
            'transcript:view' => 'View transcripts',

            'clearance:apply' => 'Apply for clearance',
            'clearance:view' => 'View clearance requests',
            'clearance:approve' => 'Approve a clearance stage',
            'clearance:reject' => 'Reject a clearance stage',
            'clearance:search' => 'Search clearance requests',
            'clearance:print' => 'Print clearance certificates',
            'clearance:mark_collected' => 'Mark clearance as collected',
            'clearance:cancel' => 'Cancel clearance requests',
            'clearance_stage:manage' => 'Configure clearance stages',

            'notice:list' => 'List notices',
            'notice:view' => 'View a notice',
            'notice:create' => 'Create notices',
            'notice:update' => 'Update notices',
            'notice:delete' => 'Delete notices',

            'audit_log:view' => 'View the audit log',

            'portal_monitor:view' => 'See the result portal monitor (exam list, daily check, publications)',
            'portal_monitor:manage' => 'Sync the exam list, run the daily check and start publication pulls',
            'result_pull:list' => 'See which students\' official results were pulled (success / failed)',
            'result_pull:retry' => 'Retry failed result pulls',

            'profile:update_own' => 'Complete and update own student profile',
            'profile:view' => 'View student profiles',
            'profile:update' => 'Edit student profiles (including locked fields)',
            'profile_field:manage' => 'Configure the required profile fields',
            'grading_scale:manage' => 'Edit the grading scale',
            'course_offering:list' => 'List course offerings',
            'course_offering:create' => 'Create course offerings',
            'course_offering:update' => 'Update course offerings',

            'session:view_any' => 'View any user\'s login sessions',
            'session:revoke' => 'Revoke any user\'s login sessions',
            'two_factor:reset' => 'Reset a user\'s two-factor authentication',
            'security:manage' => 'Manage the security policy (2FA per role)',
            'mcp_integration:manage_all' => 'View and revoke every user\'s AI integrations',
            'mcp:configure' => 'Switch MCP on or off globally and per role',
        ],

        /*
         | Default scope of every permission granted through a role. Roles not
         | listed (including legacy roles such as "Academic Admin") are global.
         */
        'role_scopes' => [
            'department_head' => 'department',
            'hall_provost' => 'hall',
            'teacher' => 'course',
            'student' => 'self',
        ],

        /*
         | Read permissions that are not limited by the role's scope (catalog
         | data every holder may read, e.g. the course list).
         */
        'unscoped_permissions' => [
            'department:list', 'department:view',
            'program:list', 'program:view',
            'semester:list', 'semester:view',
            'course:list', 'course:view',
            'hall:list', 'hall:view',
            'notice:list', 'notice:view',
        ],

        /*
         | Default role → permission matrix (seed only, see header).
         */
        'matrix' => [
            'admin_office' => [
                'result_pull:list', 'result_pull:retry', 'portal_monitor:view', 'portal_monitor:manage',
                'department:list', 'department:view', 'program:list', 'program:view',
                'semester:list', 'semester:view', 'course:list', 'course:view',
                'batch:list', 'batch:view', 'student:list', 'student:view',
                'teacher:list', 'teacher:view', 'user:list', 'user:view',
                'hall:list', 'hall:view', 'result:view', 'transcript:view', 'enrollment:list',
                'clearance:view', 'clearance:search', 'clearance:print', 'clearance:mark_collected',
                'enrollment:create', 'enrollment:bulk_create', 'enrollment:drop', 'profile:view', 'profile:update',
                'course_offering:list', 'course_offering:create',
                'notice:list', 'notice:view',
            ],
            'head_of_institution' => [
                'department:list', 'department:view', 'program:list', 'program:view',
                'semester:list', 'semester:view', 'course:list', 'course:view',
                'student:list', 'student:view', 'teacher:list', 'teacher:view',
                'hall:list', 'hall:view', 'result:view', 'transcript:view',
                'clearance:view', 'clearance:approve', 'clearance:reject',
                'notice:list', 'notice:view', 'notice:create', 'notice:update',
            ],
            'principal' => [
                'department:list', 'department:view', 'program:list', 'program:view',
                'semester:list', 'semester:view', 'course:list', 'course:view',
                'student:list', 'student:view', 'teacher:list', 'teacher:view',
                'result:view', 'clearance:view', 'notice:list', 'notice:view',
            ],
            'department_head' => [
                'result_pull:list', 'result_pull:retry',
                'department:list', 'department:view', 'program:list', 'program:view',
                'semester:list', 'semester:view',
                'course:list', 'course:view', 'course:create', 'course:update', 'course:assign_teacher',
                'student:list', 'student:view', 'teacher:list', 'teacher:view',
                'enrollment:list', 'result:view', 'result:approve',
                'course_offering:list', 'course_offering:create', 'profile:view',
                'clearance:view', 'clearance:approve', 'clearance:reject',
                'notice:list', 'notice:view', 'notice:create', 'notice:update',
            ],
            'hall_provost' => [
                'hall:list', 'hall:view', 'hall:assign_student', 'hall_dues:view', 'hall_dues:manage',
                'student:list', 'student:view',
                'clearance:view', 'clearance:approve', 'clearance:reject',
                'notice:list', 'notice:view', 'notice:create', 'notice:update',
            ],
            'librarian' => [
                'student:list', 'student:view',
                'library_loans:view', 'library_loans:manage', 'library_dues:view',
                'clearance:view', 'clearance:approve', 'clearance:reject',
                'notice:list', 'notice:view',
            ],
            'teacher' => [
                'department:list', 'department:view', 'program:list', 'program:view',
                'semester:list', 'semester:view', 'course:list', 'course:view',
                'enrollment:list', 'result:view', 'result:enter_marks', 'result:submit', 'course_offering:list',
                'notice:list', 'notice:view',
            ],
            'student' => [
                'program:list', 'program:view', 'semester:list', 'semester:view',
                'course:list', 'course:view', 'student:view',
                'enrollment:list', 'result:view', 'transcript:view', 'profile:update_own',
                'clearance:apply', 'clearance:view', 'clearance:cancel',
                'notice:list', 'notice:view',
            ],
        ],
    ],

    'audit' => [

        /*
         | Record writes made by console commands / seeders with no logged-in
         | actor (channel `system`). Off by default to keep seeding quiet.
         */
        'record_system_writes_without_actor' => env('ERP_AUDIT_SYSTEM_WRITES', false),

        /*
         | Attributes never written to the before/after snapshots.
         */
        'redacted_attributes' => [
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
            'totp_secret', 'totp_secret_encrypted', 'token_hash',
        ],
    ],

];
