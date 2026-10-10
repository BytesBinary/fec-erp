<?php

/*
|--------------------------------------------------------------------------
| Feature index metadata (spec §5.2)
|--------------------------------------------------------------------------
|
| The index itself is generated from the Filament panel (menu, URLs,
| accessibility). This file only adds what the registry cannot know: a
| description, search keywords, how-to steps and the permission used to
| name the roles that can use a feature. `tests/Feature/Assistant/FeatureIndexTest`
| fails when a menu item has no entry here.
|
*/

return [

    'features' => [
        'Filament\\Pages\\Dashboard' => [
            'description' => 'Your dashboard with the widgets for your role.',
            'keywords' => ['home', 'dashboard', 'overview', 'start'],
            'permission' => null,
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Appearance' => [
            'description' => 'Choose your colour theme and accent colour.',
            'keywords' => ['theme', 'colour', 'color', 'appearance', 'dark', 'accent', 'style'],
            'permission' => null,
            'steps' => [],
        ],
        'App\\Filament\\Pages\\AssignTeachers' => [
            'description' => 'Assign teachers to courses.',
            'keywords' => ['assign', 'teacher', 'course', 'teaching'],
            'permission' => 'course:assign_teacher',
            'steps' => ['Open Academic → Assign Teachers.', 'Pick a course and choose its teachers.', 'Save.'],
        ],
        'App\\Filament\\Pages\\BatchOverview' => [
            'description' => 'See every batch with its students and progress.',
            'keywords' => ['batch', 'batches', 'overview', 'students', 'session'],
            'permission' => 'batch:list',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\CreditCountReport' => [
            'description' => 'Report of credit hours per teacher.',
            'keywords' => ['credit', 'credits', 'count', 'report', 'teacher', 'load'],
            'permission' => 'teacher:list',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\ExamDutyReport' => [
            'description' => 'Report of exam invigilation duties.',
            'keywords' => ['exam', 'duty', 'duties', 'report', 'invigilation'],
            'permission' => 'exam_duty:list',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\IndividualRoutineReport' => [
            'description' => 'Class routine of one teacher.',
            'keywords' => ['routine', 'teacher', 'individual', 'schedule', 'timetable'],
            'permission' => 'teacher:list',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\MasterRoutineReport' => [
            'description' => 'The master class routine for all batches.',
            'keywords' => ['master', 'routine', 'timetable', 'schedule', 'classes'],
            'permission' => 'teacher:list',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\InstitutionSettings' => [
            'description' => 'Institution name, logo, address and the principal.',
            'keywords' => ['institution', 'settings', 'logo', 'name', 'address', 'principal', 'school'],
            'permission' => 'security:manage',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Campus\\HallResidents' => [
            'description' => 'Hall residents, room assignment and hall dues (hall provost).',
            'keywords' => ['hall', 'resident', 'residents', 'room', 'dues', 'provost', 'hostel'],
            'permission' => 'hall:assign_student',
            'steps' => ['Open Campus → Hall residents.', 'Enter the roll number, choose the hall and room, Assign.', 'Record dues under "Dues".'],
        ],
        'App\\Filament\\Pages\\Campus\\LibraryLoans' => [
            'description' => 'Issue and return library books, record fines (librarian).',
            'keywords' => ['library', 'book', 'loan', 'fine', 'return', 'librarian'],
            'permission' => 'library_loans:manage',
            'steps' => ['Open Campus → Library loans.', 'Issue a book by roll number, or mark returned / fine paid.'],
        ],
        'App\\Filament\\Pages\\Clearance\\Apply' => [
            'description' => 'Apply for your clearance; see whether you are eligible and why not.',
            'keywords' => ['clearance', 'apply', 'certificate', 'graduation', 'eligible', 'eligibility'],
            'permission' => 'clearance:apply',
            'steps' => ['Open Clearance → Apply.', 'Check the eligibility result.', 'Submit the request.'],
        ],
        'App\\Filament\\Pages\\Clearance\\MyClearance' => [
            'description' => 'Follow your clearance request stage by stage; resubmit after a rejection.',
            'keywords' => ['clearance', 'status', 'progress', 'timeline', 'resubmit', 'collected', 'ready'],
            'permission' => 'clearance:apply',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Clearance\\PendingApprovals' => [
            'description' => 'Clearance requests waiting for your approval.',
            'keywords' => ['clearance', 'approve', 'approval', 'pending', 'waiting', 'reject', 'sign'],
            'permission' => 'clearance:approve',
            'steps' => ['Open Clearance → Waiting for me.', 'Review a request, check the dues shown.', 'Approve or reject with a reason.'],
        ],
        'App\\Filament\\Pages\\Clearance\\ClearanceDesk' => [
            'description' => 'Administration office desk: search, print and hand over clearances.',
            'keywords' => ['clearance', 'desk', 'print', 'collect', 'handover', 'office', 'search', 'seal'],
            'permission' => 'clearance:search',
            'steps' => ['Open Clearance → Clearance desk.', 'Search by student ID.', 'Open the request, Print, then Mark collected.'],
        ],
        'App\\Filament\\Pages\\Clearance\\ClearanceStages' => [
            'description' => 'Configure the clearance approval chain.',
            'keywords' => ['clearance', 'stages', 'chain', 'order', 'skip', 'approver'],
            'permission' => 'clearance_stage:manage',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Profile\\CompleteProfile' => [
            'description' => 'Complete or update your student profile.',
            'keywords' => ['profile', 'complete', 'photo', 'address', 'blood', 'nid', 'guardian', 'details'],
            'permission' => 'profile:update_own',
            'steps' => ['Open Settings → My profile.', 'Fill all required fields and upload a passport-size photo.', 'Save.'],
        ],
        'App\\Filament\\Pages\\Results\\MyResults' => [
            'description' => 'Your semester results and CGPA.',
            'keywords' => ['result', 'results', 'cgpa', 'gpa', 'grade', 'marks', 'transcript', 'semester'],
            'permission' => 'profile:update_own',
            'steps' => ['Open Result in the menu.', 'Pick a semester block or print the result sheet.'],
        ],
        'App\\Filament\\Pages\\Results\\ResultEntry' => [
            'description' => 'Enter marks, submit, approve and publish results.',
            'keywords' => ['result', 'marks', 'enter', 'grading', 'submit', 'approve', 'publish', 'teacher'],
            'permission' => 'result:enter_marks',
            'steps' => ['Open Academic → Result entry.', 'Choose the course offering, type marks, Save.', 'Submit to the department head.'],
        ],
        'App\\Filament\\Pages\\Security\\Devices' => [
            'description' => 'See where you are logged in and log other devices out.',
            'keywords' => ['devices', 'sessions', 'logout', 'log out', 'security', 'login', 'browsers'],
            'permission' => null,
            'steps' => ['Open Settings → Devices.', 'Press Log out next to a device, or "Log out all other devices".'],
        ],
        'App\\Filament\\Pages\\Security\\TwoFactorSettings' => [
            'description' => 'Turn two-factor authentication on or off; recovery codes.',
            'keywords' => ['2fa', 'two factor', 'two-factor', 'authenticator', 'totp', 'recovery', 'security', 'mfa'],
            'permission' => null,
            'steps' => ['Open Settings → Two-factor authentication.', 'Set up, scan the QR code, enter the code, save recovery codes.'],
        ],
        'App\\Filament\\Pages\\Security\\TwoFactorPolicy' => [
            'description' => 'Make two-factor authentication mandatory per role.',
            'keywords' => ['2fa', 'policy', 'mandatory', 'required', 'roles', 'security'],
            'permission' => 'security:manage',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Security\\McpOversight' => [
            'description' => 'All AI integrations: revoke, switches, usage.',
            'keywords' => ['mcp', 'ai', 'integrations', 'oversight', 'revoke', 'admin', 'tokens'],
            'permission' => 'mcp_integration:manage_all',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Settings\\AiIntegrations' => [
            'description' => 'Connect AI clients (MCP), see and stop your integrations.',
            'keywords' => ['ai', 'mcp', 'integration', 'claude', 'cursor', 'token', 'connect', 'agent', 'assistant'],
            'permission' => null,
            'steps' => ['Turn on two-factor authentication first.', 'Open Settings → AI Integrations → Connect an AI client.', 'Follow the five steps.'],
        ],
        'App\\Filament\\Pages\\Settings\\GradingScalePage' => [
            'description' => 'Edit the grading scale (marks, letters, grade points).',
            'keywords' => ['grading', 'scale', 'grade', 'points', 'letters'],
            'permission' => 'grading_scale:manage',
            'steps' => [],
        ],
        'App\\Filament\\Pages\\Settings\\MySignature' => [
            'description' => 'Upload the signature image printed on clearances you approve.',
            'keywords' => ['signature', 'sign', 'image', 'clearance', 'approver'],
            'permission' => 'clearance:approve',
            'steps' => ['Open Settings → My signature.', 'Upload a PNG with a transparent background.'],
        ],
        'App\\Filament\\Pages\\Settings\\ProfileFields' => [
            'description' => 'Choose which student profile fields are required.',
            'keywords' => ['profile', 'fields', 'required', 'student', 'configure'],
            'permission' => 'profile_field:manage',
            'steps' => [],
        ],
        'App\\Filament\\Resources\\ResultPulls\\ResultPullResource' => [
            'description' => 'See which students\' official results were pulled from the university exam portal, which succeeded or failed (with the reason), and retry the failed ones.',
            'keywords' => ['student results', 'pull', 'portal', 'official result', 'failed', 'retry', 'sync', 'exam portal'],
            'permission' => 'result_pull:list',
            'steps' => ['Open Student results in the Academic menu.', 'Use the Failed tab to see which students could not be pulled.', 'Click Retry on a row, or Retry all failed.'],
        ],

        'App\\Filament\\Pages\\Portal\\PortalMonitor' => [
            'description' => 'Monitor the university result portal: the saved exam list, the daily check for newly published results, confirmed publications and the student pulls they started.',
            'keywords' => ['portal', 'result portal', 'exam list', 'publication', 'new results', 'sync', 'check', 'monitor', 'shadow'],
            'permission' => 'portal_monitor:view',
            'steps' => ['Open Portal monitor in the Academic menu.', 'First time: press First-time sync to save the exam list.', 'Press Check for new results to look for newly published exams.', 'Press Run now on a confirmed publication to pull the students.'],
        ],
        'App\\Filament\\Resources\\NotificationRules\\NotificationRuleResource' => [
            'description' => 'Choose which events send an email, to whom (the person, department head, roles), immediately or in the daily digest, and with which template. Preview a message and send yourself a test.',
            'keywords' => ['email', 'emails', 'notification', 'notifications', 'event rules', 'mail', 'digest', 'recipients', 'template', 'smtp'],
            'permission' => 'notification_rule:view',
            'steps' => ['Open Email notifications under Security & Access.', 'Find the event, press Configure, and set on/off, when, recipients and template.', 'Use Preview, then Send test to me before turning an event on.'],
        ],
        'App\\Filament\\Resources\\EmailTemplates\\EmailTemplateResource' => [
            'description' => 'Write extra email subjects and bodies for an event, using only that event\'s placeholders.',
            'keywords' => ['email template', 'templates', 'subject', 'body', 'placeholder', 'wording'],
            'permission' => 'notification_rule:view',
            'steps' => [],
        ],
        'App\\Filament\\Resources\\EmailDeliveries\\EmailDeliveryResource' => [
            'description' => 'Every email the system queued: sent, failed (with the error), held for the digest, skipped or blocked, with a retry.',
            'keywords' => ['email deliveries', 'sent emails', 'failed emails', 'retry email', 'delivery history', 'bounced'],
            'permission' => 'email_delivery:view',
            'steps' => ['Open Email deliveries under Security & Access.', 'Use the Failed tab to see what did not go out, then Retry or Retry all failed.'],
        ],
        'App\\Filament\\Resources\\AuditLogs\\AuditLogResource' => [
            'description' => 'Who changed what, from web, MCP or the assistant.',
            'keywords' => ['audit', 'log', 'history', 'changes', 'who', 'activity'],
            'permission' => 'audit_log:view',
            'steps' => [],
        ],
        'App\\Filament\\Resources\\Batches\\BatchResource' => [
            'description' => 'Batches (student cohorts) of each department.',
            'keywords' => ['batch', 'batches', 'cohort', 'session', 'year'],
            'permission' => 'batch:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a batch.',
                'keywords' => ['batch', 'new batch', 'add batch', 'create batch'],
                'permission' => 'batch:create',
                'steps' => ['Open Academic → Batches → New.', 'Choose the department, number and session.'],
            ],
        ],
        'App\\Filament\\Resources\\Courses\\CourseResource' => [
            'description' => 'Courses with code, credits and department.',
            'keywords' => ['course', 'courses', 'subject', 'credit', 'syllabus'],
            'permission' => 'course:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a course.',
                'keywords' => ['course', 'create course', 'add course', 'new course', 'subject'],
                'permission' => 'course:create',
                'steps' => ['Open Academic → Courses → New.', 'Fill department, semester, type, code, name and credit hours.', 'Save.'],
            ],
        ],
        'App\\Filament\\Resources\\Departments\\DepartmentResource' => [
            'description' => 'Departments of the institution.',
            'keywords' => ['department', 'departments', 'faculty'],
            'permission' => 'department:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a department.',
                'keywords' => ['department', 'create department', 'add department', 'new department'],
                'permission' => 'department:create',
                'steps' => ['Open Academic → Departments → New.'],
            ],
        ],
        'App\\Filament\\Resources\\Designations\\DesignationResource' => [
            'description' => 'Designations of teachers and staff.',
            'keywords' => ['designation', 'designations', 'title', 'rank', 'position'],
            'permission' => 'designation:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a designation.',
                'keywords' => ['designation', 'create designation', 'new designation'],
                'permission' => 'designation:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\ExamDuties\\ExamDutyResource' => [
            'description' => 'Exam duty assignments.',
            'keywords' => ['exam', 'duty', 'invigilation', 'assign'],
            'permission' => 'exam_duty:list',
            'steps' => [],
            'create' => [
                'description' => 'Create an exam duty.',
                'keywords' => ['exam duty', 'create duty', 'new duty'],
                'permission' => 'exam_duty:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\ExamHalls\\ExamHallResource' => [
            'description' => 'Exam rooms.',
            'keywords' => ['exam hall', 'room', 'rooms', 'exam'],
            'permission' => 'exam_hall:list',
            'steps' => [],
            'create' => [
                'description' => 'Create an exam room.',
                'keywords' => ['exam hall', 'new room', 'create room'],
                'permission' => 'exam_hall:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\ExamTypes\\ExamTypeResource' => [
            'description' => 'Types of exams.',
            'keywords' => ['exam type', 'types', 'midterm', 'final'],
            'permission' => 'exam_type:list',
            'steps' => [],
            'create' => [
                'description' => 'Create an exam type.',
                'keywords' => ['exam type', 'create exam type'],
                'permission' => 'exam_type:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Halls\\HallResource' => [
            'description' => 'Residential halls (hostels).',
            'keywords' => ['hall', 'halls', 'hostel', 'dormitory'],
            'permission' => 'hall:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a hall.',
                'keywords' => ['hall', 'create hall', 'new hall', 'hostel'],
                'permission' => 'hall:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Notices\\NoticeResource' => [
            'description' => 'Notices for everyone, a department or a hall.',
            'keywords' => ['notice', 'notices', 'announcement', 'news'],
            'permission' => 'notice:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a notice.',
                'keywords' => ['notice', 'create notice', 'announcement', 'post', 'new notice'],
                'permission' => 'notice:create',
                'steps' => ['Open Academic → Notices → New.', 'Choose the audience, write title and body.'],
            ],
        ],
        'App\\Filament\\Resources\\Programs\\ProgramResource' => [
            'description' => 'Study programs and required credits.',
            'keywords' => ['program', 'programs', 'degree', 'bsc', 'credits'],
            'permission' => 'program:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a program.',
                'keywords' => ['program', 'create program', 'new program', 'degree'],
                'permission' => 'program:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Routine\\RoutineResource' => [
            'description' => 'Class routines.',
            'keywords' => ['routine', 'timetable', 'schedule', 'classes', 'generate'],
            'permission' => 'teacher:list',
            'steps' => [],
        ],
        'App\\Filament\\Resources\\Semesters\\SemesterResource' => [
            'description' => 'Semesters and which one is active.',
            'keywords' => ['semester', 'semesters', 'term', 'active', 'calendar'],
            'permission' => 'semester:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a semester.',
                'keywords' => ['semester', 'create semester', 'new semester', 'term'],
                'permission' => 'semester:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Staff\\StaffResource' => [
            'description' => 'Staff members.',
            'keywords' => ['staff', 'employee', 'office', 'librarian'],
            'permission' => 'staff:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a staff member.',
                'keywords' => ['staff', 'new staff', 'add staff', 'create staff'],
                'permission' => 'staff:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Students\\StudentResource' => [
            'description' => 'Students.',
            'keywords' => ['student', 'students', 'roll', 'registration', 'enrolled'],
            'permission' => 'student:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a student.',
                'keywords' => ['student', 'new student', 'add student', 'create student', 'admission'],
                'permission' => 'student:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Teachers\\TeacherResource' => [
            'description' => 'Teachers.',
            'keywords' => ['teacher', 'teachers', 'faculty', 'lecturer', 'professor'],
            'permission' => 'teacher:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a teacher.',
                'keywords' => ['teacher', 'new teacher', 'add teacher', 'create teacher'],
                'permission' => 'teacher:create',
                'steps' => [],
            ],
        ],
        'App\\Filament\\Resources\\Users\\UserResource' => [
            'description' => 'Login accounts, roles and scopes; sessions and 2FA reset.',
            'keywords' => ['user', 'users', 'account', 'role', 'roles', 'permission', 'login', 'assign', 'deactivate'],
            'permission' => 'user:list',
            'steps' => [],
            'create' => [
                'description' => 'Create a login account.',
                'keywords' => ['user', 'create user', 'new user', 'add user', 'account'],
                'permission' => 'user:create',
                'steps' => ['Open Settings → Users → New.', 'Enter name, email and a password, then assign a role from the user page.'],
            ],
        ],
        'BezhanSalleh\\FilamentShield\\Resources\\Roles\\RoleResource' => [
            'description' => 'Roles and their permissions.',
            'keywords' => ['role', 'roles', 'permission', 'permissions', 'matrix', 'shield'],
            'permission' => 'permission_matrix:view',
            'steps' => [],
        ],
    ],

    /*
     | Navigation items intentionally left out of the index.
     */
    'ignore' => [],

];
