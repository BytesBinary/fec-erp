<?php

/*
|--------------------------------------------------------------------------
| Email notification events (docs/EMAIL_EVENTS.md)
|--------------------------------------------------------------------------
|
| The registry of every event that can send an email: its category, whether
| it is on by default, immediate or in the daily digest, who gets it, and the
| default subject/body with the placeholders it may use. Admins change
| enabled / mode / recipients / template per event on the "Email
| notifications" screen; those choices live in the `notification_rules`
| table and win over these defaults. Nothing here is a secret: passwords,
| tokens, 2FA secrets and recovery codes are never placeholders.
|
| Recipient kinds: affected_user, department_head, hall_provost,
| context_emails (extra addresses named by the event), role:<role_key>.
| Always available placeholders: {app}, {recipient_name}, {link}.
|
*/

$e = fn (string $category, string $label, bool $enabled, string $mode, array $recipients, string $subject, string $body, array $placeholders = [], array $sample = [], string $note = ''): array => [
    'category' => $category,
    'label' => $label,
    'enabled' => $enabled,
    'mode' => $mode,
    'recipients' => $recipients,
    'subject' => $subject,
    'body' => $body,
    'placeholders' => $placeholders,
    'sample' => $sample,
    'sensitive' => $note,
];

$bridge = ['title', 'body'];
$bridgeSample = ['title' => 'Title of the notice', 'body' => 'The text that is shown in the notification bell.'];
$bridgeBody = "{title}\n\n{body}\n\nOpen: {link}";

return [

    'categories' => [
        'security' => 'Account & security',
        'student' => 'Students & profile',
        'result' => 'Results',
        'portal' => 'Result portal',
        'clearance' => 'Clearance',
        'campus' => 'Halls & library',
        'academic' => 'Notices & academic',
        'system' => 'System & operations',
    ],

    'events' => [

        // ── Account & security ─────────────────────────────────────────
        'security.new_device_login' => $e('security', 'New device sign-in', true, 'immediate', ['affected_user'], 'New sign-in to your {app} account',
            "Hello {recipient_name},\n\nYour account was just used from a device we have not seen before.\n\nDevice: {device}\nIP address: {ip}\nTime: {time}\n\nIf this was you, no action is needed. If not, sign out other devices and change your password: {link}",
            ['device', 'ip', 'time'], ['device' => 'Chrome on Linux', 'ip' => '203.0.113.7', 'time' => '10 Oct 2026 09:30'], 'Never contains the session id or cookies.'),
        'security.password_changed' => $e('security', 'Password changed', true, 'immediate', ['affected_user'], 'Your {app} password was changed',
            "Hello {recipient_name},\n\nYour password was changed. {signed_out_devices} other device(s) were signed out.\n\nIf you did not do this, contact the office at once. Devices: {link}",
            ['signed_out_devices'], ['signed_out_devices' => '2'], 'Never contains the password.'),
        'security.two_factor_locked_out' => $e('security', 'Two-factor lock-out', true, 'immediate', ['affected_user'], 'Too many wrong two-factor codes',
            "Hello {recipient_name},\n\nThere were 5 wrong two-factor codes on your account, so sign-in is locked for {minutes} minutes.\n\nIf this was not you, change your password after the lock ends.",
            ['minutes'], ['minutes' => '15'], 'Never contains codes.'),
        'security.two_factor_reset_by_admin' => $e('security', 'Two-factor reset by an administrator', true, 'immediate', ['affected_user'], 'Two-factor authentication was reset on your account',
            "Hello {recipient_name},\n\nAn administrator reset your two-factor authentication.\nReason: {reason}\n\nYou were signed out everywhere. Set up two-factor again after signing in: {link}",
            ['reason'], ['reason' => 'Lost phone'], 'Never contains the secret or recovery codes.'),
        'security.two_factor_enabled' => $e('security', 'Two-factor turned on', true, 'immediate', ['affected_user'], 'Two-factor authentication is now on',
            "Hello {recipient_name},\n\nTwo-factor authentication was turned on for your account at {time}.\n\nIf this was not you, contact the office. Settings: {link}",
            ['time'], ['time' => '10 Oct 2026 09:30']),
        'security.two_factor_disabled' => $e('security', 'Two-factor turned off', true, 'immediate', ['affected_user'], 'Two-factor authentication was turned off',
            "Hello {recipient_name},\n\nTwo-factor authentication was turned off for your account at {time}. Your AI integrations were stopped.\n\nIf this was not you, change your password now: {link}",
            ['time'], ['time' => '10 Oct 2026 09:30']),
        'security.recovery_code_used' => $e('security', 'Recovery code used', true, 'immediate', ['affected_user'], 'A recovery code was used on your account',
            "Hello {recipient_name},\n\nOne of your recovery codes was used to sign in at {time}. {codes_left} code(s) are left.\n\nGenerate new codes if few are left: {link}",
            ['time', 'codes_left'], ['time' => '10 Oct 2026 09:30', 'codes_left' => '7'], 'Never contains the codes.'),
        'security.recovery_codes_regenerated' => $e('security', 'Recovery codes regenerated', true, 'immediate', ['affected_user'], 'New recovery codes were generated',
            "Hello {recipient_name},\n\nA new set of recovery codes was generated for your account at {time}. The old codes no longer work.\n\nIf this was not you, contact the office: {link}",
            ['time'], ['time' => '10 Oct 2026 09:30'], 'Never contains the codes.'),
        'security.sessions_revoked_by_admin' => $e('security', 'Signed out by an administrator', true, 'immediate', ['affected_user'], 'You were signed out of {app}',
            "Hello {recipient_name},\n\nAn administrator signed you out of {count} session(s).\nReason: {reason}\n\nSign in again to continue: {link}",
            ['count', 'reason'], ['count' => '3', 'reason' => 'Security check']),
        'security.account_deactivated' => $e('security', 'Account deactivated', true, 'immediate', ['affected_user'], 'Your {app} account was deactivated',
            "Hello {recipient_name},\n\nYour account was deactivated. You can no longer sign in.\n\nContact the office if you think this is a mistake.", [], []),
        'security.account_reactivated' => $e('security', 'Account reactivated', true, 'immediate', ['affected_user'], 'Your {app} account is active again',
            "Hello {recipient_name},\n\nYour account was reactivated. You can sign in again: {link}", [], []),
        'security.role_changed' => $e('security', 'Roles changed', true, 'immediate', ['affected_user'], 'Your access in {app} changed',
            "Hello {recipient_name},\n\nYour roles were updated.\nRoles now: {roles}\nScope: {scopes}\n\nIf you did not expect this, contact the office.",
            ['roles', 'scopes'], ['roles' => 'Department head', 'scopes' => 'CSE'], 'Lists role names only, never the permission list.'),
        'security.email_changed' => $e('security', 'Email address changed', true, 'immediate', ['affected_user', 'context_emails'], 'The email address of your {app} account changed',
            "Hello {recipient_name},\n\nThe email address of your account was changed to {new_email}.\n\nIf you did not ask for this, contact the office at once.",
            ['new_email'], ['new_email' => 'j***@example.com'], 'The new address is masked.'),
        'user.account_created' => $e('security', 'Account created for you', true, 'immediate', ['affected_user'], 'Your {app} account is ready',
            "Hello {recipient_name},\n\nAn account was created for you with the role: {role}.\n\nSign in here: {link}\n\nThe office will give you your first password separately. It is never sent by email.",
            ['role'], ['role' => 'Teacher'], 'Never contains a password.'),
        'mcp.integration_created' => $e('security', 'AI integration created', true, 'immediate', ['affected_user'], 'A new AI integration was created', $bridgeBody, $bridge, $bridgeSample, 'Never contains the token.'),
        'mcp.integration_revoked' => $e('security', 'AI integration stopped', true, 'immediate', ['affected_user'], 'An AI integration was stopped', $bridgeBody, $bridge, $bridgeSample),
        'mcp.integration_stopped_all' => $e('security', 'All AI integrations stopped', true, 'immediate', ['affected_user'], 'Your AI integrations were stopped', $bridgeBody, $bridge, $bridgeSample),
        'mcp.integration_expiring' => $e('security', 'AI integration expiring', true, 'digest', ['affected_user'], 'An AI integration expires soon', $bridgeBody, $bridge, $bridgeSample),
        'mcp.new_ip' => $e('security', 'AI integration used from a new IP', true, 'immediate', ['affected_user'], 'An AI integration connected from a new IP address', $bridgeBody, $bridge, $bridgeSample),
        'mcp.settings_changed' => $e('security', 'MCP switches or limits changed', false, 'digest', ['role:super_admin'], 'MCP settings changed',
            "{summary}\n\nChanged by {actor}.\n\n{link}", ['summary', 'actor'], ['summary' => 'MCP switched off for role Teacher', 'actor' => 'Super Admin']),
        'security.two_factor_policy_changed' => $e('security', 'Two-factor policy changed', false, 'digest', ['role:super_admin'], 'Two-factor policy changed',
            "{summary}\n\nChanged by {actor}.\n\n{link}", ['summary', 'actor'], ['summary' => 'Two-factor is now required for Teacher', 'actor' => 'Super Admin']),
        'rbac.permission_matrix_updated' => $e('security', 'Role permissions changed', false, 'digest', ['role:super_admin'], 'Role permissions changed',
            "{summary}\n\nChanged by {actor}.\n\n{link}", ['summary', 'actor'], ['summary' => 'Teacher: +2 permission(s), -0', 'actor' => 'Super Admin']),

        // ── Students & profile ─────────────────────────────────────────
        'student.added' => $e('student', 'Student added', true, 'immediate', ['affected_user'], 'Welcome to {app}',
            "Hello {recipient_name},\n\nYou were added as a student of {department}, batch {batch}.\n\nSign in here: {link}\n\nThe office will give you your first password separately. It is never sent by email.",
            ['department', 'batch'], ['department' => 'CSE', 'batch' => 'Batch 12 (2022-2023)'], 'Never contains a password.'),
        'student.added_admin_copy' => $e('student', 'Student added (copy to department head)', false, 'digest', ['department_head'], 'A student was added to your department',
            "{student} (roll {roll}) was added to {department}, batch {batch}.\n\n{link}", ['student', 'roll', 'department', 'batch'], ['student' => 'Arif Chowdhury', 'roll' => '1201', 'department' => 'CSE', 'batch' => 'Batch 12']),
        'student.updated' => $e('student', 'Student record changed', false, 'digest', ['affected_user'], 'Your student record was updated',
            "Hello {recipient_name},\n\nThese details of your student record were changed: {fields}.\n\nOpen: {link}", ['fields'], ['fields' => 'department, batch'], 'Names the changed fields only, not the values.'),
        'profile.incomplete_reminder' => $e('student', 'Profile still incomplete', false, 'digest', ['affected_user'], 'Please complete your profile',
            "Hello {recipient_name},\n\nYour profile still has {missing_count} missing item(s). Some pages stay locked until it is complete.\n\nComplete it here: {link}", ['missing_count'], ['missing_count' => '4']),
        'profile.completed' => $e('student', 'Profile completed', false, 'immediate', ['affected_user'], 'Your profile is complete',
            "Hello {recipient_name},\n\nYour profile is complete. All pages are open: {link}", [], []),
        'profile.required_fields_changed' => $e('student', 'New required profile field', false, 'digest', ['affected_user'], 'A new profile field is required',
            "Hello {recipient_name},\n\nThe office now requires this profile field: {field}. Please fill it in: {link}", ['field'], ['field' => 'Blood group']),
        'profile.locked_after_clearance' => $e('student', 'Profile fields locked', true, 'immediate', ['affected_user'], 'Some profile fields are now locked',
            "Hello {recipient_name},\n\nBecause you applied for clearance, your name, parents' names and date of birth are locked. Ask the administration office for corrections.", [], []),
        'hall.assigned' => $e('student', 'Hall assigned', true, 'immediate', ['affected_user'], 'You were assigned to {hall}',
            "Hello {recipient_name},\n\nYou were assigned to {hall}, room {room}.", ['hall', 'room'], ['hall' => 'Bijoy Ekattor Hall', 'room' => '204']),
        'hall.vacated' => $e('student', 'Hall vacated', true, 'immediate', ['affected_user'], 'Your hall residency ended',
            "Hello {recipient_name},\n\nYour residency in {hall} was ended.", ['hall'], ['hall' => 'Bijoy Ekattor Hall']),
        'enrollment.enrolled' => $e('student', 'Enrolled in a course', false, 'digest', ['affected_user'], 'You were enrolled in {course}',
            "Hello {recipient_name},\n\nYou were enrolled in {course} ({semester}).", ['course', 'semester'], ['course' => 'CSE-3101 Algorithms', 'semester' => 'Spring 2026']),
        'enrollment.dropped' => $e('student', 'Dropped from a course', true, 'immediate', ['affected_user'], 'You were dropped from {course}',
            "Hello {recipient_name},\n\nYou were dropped from {course} ({semester}).", ['course', 'semester'], ['course' => 'CSE-3101 Algorithms', 'semester' => 'Spring 2026']),

        // ── Results ────────────────────────────────────────────────────
        'result.submitted' => $e('result', 'Results submitted for approval', true, 'digest', ['department_head'], 'Results are waiting for your approval',
            "{course}: {count} result(s) were submitted by the teacher.\n\nApprove here: {link}", ['course', 'count'], ['course' => 'CSE-3101 Algorithms', 'count' => '60']),
        'result.approved' => $e('result', 'Results approved', true, 'digest', ['role:super_admin'], 'Results are ready to publish',
            "{course} was approved by the department head and can be published.\n\n{link}", ['course'], ['course' => 'CSE-3101 Algorithms']),
        'result.semester_published' => $e('result', 'Semester results published', true, 'immediate', ['affected_user'], 'Your {semester} results are published',
            "Hello {recipient_name},\n\nThe results of {semester} are published. Sign in to see them: {link}", ['semester'], ['semester' => 'Spring 2026'], 'Grades and GPA are not in the email; the student signs in to see them.'),
        'grading_scale.changed' => $e('result', 'Grading scale changed', false, 'immediate', ['role:super_admin'], 'The grading scale was changed',
            "The grading scale was replaced by {actor}.\n\n{link}", ['actor'], ['actor' => 'Super Admin']),

        // ── Result portal ──────────────────────────────────────────────
        'portal.exam_catalog_updated' => $e('portal', 'New exams on the portal', true, 'digest', ['role:admin_office', 'role:super_admin'], 'New exams appeared on the result portal',
            "{count} new exam(s) were found:\n{exams}\n\n{link}", ['count', 'exams'], ['count' => '1', 'exams' => 'CSE 2nd year 1st Semester Examination of 2024']),
        'portal.publication_detected' => $e('portal', 'Possible result publication', true, 'immediate', ['role:admin_office', 'role:super_admin'], 'A possible new result publication',
            "{exam} appeared on the portal. It is being checked with probe students.\n\n{link}", ['exam'], ['exam' => 'CSE 2nd year 1st Semester Examination of 2024']),
        'portal.publication_confirmed' => $e('portal', 'Result publication confirmed', true, 'immediate', ['role:admin_office', 'role:super_admin'], 'Results were published: {exam}',
            "{exam} is published. Mode: {mode}. Students: {students}.\n\n{link}", ['exam', 'mode', 'students'], ['exam' => 'CSE 2nd year 1st Semester Examination of 2024', 'mode' => 'live', 'students' => '60']),
        'student.result_pulled' => $e('portal', 'Your result was saved', true, 'immediate', ['affected_user'], 'Your result is available: {exam}',
            "Hello {recipient_name},\n\nYour result for {exam} was saved. Sign in to see it: {link}", ['exam'], ['exam' => 'CSE 2nd year 1st Semester Examination of 2024'], 'Grades are not in the email.'),
        'student.result_pull_failed' => $e('portal', 'Result pull failed', true, 'digest', ['role:admin_office', 'department_head'], 'Some student result pulls failed',
            "{student} could not be pulled: {reason}\n\nRetry here: {link}", ['student', 'reason'], ['student' => 'Arif Chowdhury', 'reason' => 'The portal answered with a page the parser does not recognise.'], 'Registration numbers are not included.'),
        'student.grade_changed' => $e('portal', 'Retake / improvement recorded', true, 'immediate', ['affected_user'], 'A retake or improvement result was recorded',
            "Hello {recipient_name},\n\nYour result for {exam} changed these course(s): {courses}.\n\nSee the details: {link}", ['exam', 'courses'], ['exam' => 'CSE 1st year 2nd Semester Improvement Examination of 2024', 'courses' => 'PHY-1203 (retake), MATH-1204 (retake)'], 'Course codes and the kind of change, not the grades.'),
        'system.portal_health_failed' => $e('portal', 'Result portal not working', true, 'immediate', ['role:super_admin'], 'The result portal check failed',
            "{reason}\n\nNo emails or pulls are started until the portal answers correctly again.\n\n{link}", ['reason'], ['reason' => 'The portal could not be reached: HTTP 503']),

        // ── Clearance ──────────────────────────────────────────────────
        'clearance.submitted' => $e('clearance', 'Clearance submitted', true, 'immediate', ['affected_user'], 'Your clearance request was submitted', $bridgeBody, $bridge, $bridgeSample),
        'clearance.resubmitted' => $e('clearance', 'Clearance resubmitted', true, 'immediate', ['affected_user'], 'Your clearance request was resubmitted', $bridgeBody, $bridge, $bridgeSample),
        'clearance.waiting' => $e('clearance', 'Clearance waiting for an approver', true, 'digest', ['affected_user'], 'A clearance request is waiting for you', $bridgeBody, $bridge, $bridgeSample),
        'clearance.approved' => $e('clearance', 'Clearance stage approved', true, 'immediate', ['affected_user'], 'A clearance stage approved your request', $bridgeBody, $bridge, $bridgeSample),
        'clearance.rejected' => $e('clearance', 'Clearance stage rejected', true, 'immediate', ['affected_user'], 'A clearance stage rejected your request', $bridgeBody, $bridge, $bridgeSample),
        'clearance.ready_for_collection' => $e('clearance', 'Clearance ready for collection', true, 'immediate', ['affected_user'], 'Your clearance is ready for collection', $bridgeBody, $bridge, $bridgeSample),
        'clearance.collected' => $e('clearance', 'Clearance collected', true, 'immediate', ['affected_user'], 'Your clearance was collected', $bridgeBody, $bridge, $bridgeSample),
        'clearance.reminder' => $e('clearance', 'Clearance reminder to approvers', true, 'digest', ['affected_user'], 'Clearance requests are waiting', $bridgeBody, $bridge, $bridgeSample),
        'clearance.escalation' => $e('clearance', 'Clearance escalation', true, 'immediate', ['affected_user'], 'A clearance request is overdue', $bridgeBody, $bridge, $bridgeSample),
        'clearance.ready_digest' => $e('clearance', 'Clearances waiting for the office', true, 'digest', ['affected_user'], 'Clearances are ready for collection', $bridgeBody, $bridge, $bridgeSample),
        'clearance.cancelled' => $e('clearance', 'Clearance cancelled', true, 'immediate', ['affected_user'], 'Your clearance request was cancelled',
            "Hello {recipient_name},\n\nClearance request {request_no} was cancelled.\nReason: {reason}\n\n{link}", ['request_no', 'reason'], ['request_no' => 'CLR-2026-000001', 'reason' => 'Applied by mistake']),
        'clearance.printed' => $e('clearance', 'Clearance printed', false, 'digest', ['role:admin_office'], 'A clearance certificate was printed',
            "{request_no} was printed ({kind}).\n\n{link}", ['request_no', 'kind'], ['request_no' => 'CLR-2026-000001', 'kind' => 'duplicate']),
        'clearance.integrity_failed' => $e('clearance', 'Clearance record tampering detected', true, 'immediate', ['role:super_admin'], 'A clearance record failed its integrity check',
            "The hash chain of {request_no} does not verify (break at step {position}). Investigate before it is printed.\n\n{link}", ['request_no', 'position'], ['request_no' => 'CLR-2026-000001', 'position' => '3']),
        'clearance.stage_config_changed' => $e('clearance', 'Clearance stages changed', false, 'digest', ['role:super_admin'], 'The clearance stages were changed',
            "{summary}\n\nChanged by {actor}.", ['summary', 'actor'], ['summary' => 'Library stage switched off', 'actor' => 'Super Admin']),

        // ── Halls & library ────────────────────────────────────────────
        'hall.due_recorded' => $e('campus', 'Hall due recorded', true, 'immediate', ['affected_user'], 'A hall due was recorded',
            "Hello {recipient_name},\n\nA due of {amount} was recorded: {description}. It must be settled before clearance.\n\n{link}", ['description', 'amount'], ['description' => 'Electricity June', 'amount' => '450.00']),
        'hall.due_settled' => $e('campus', 'Hall due settled', true, 'immediate', ['affected_user'], 'A hall due was settled',
            "Hello {recipient_name},\n\nThe due \"{description}\" ({amount}) was marked settled.", ['description', 'amount'], ['description' => 'Electricity June', 'amount' => '450.00']),
        'library.loan_issued' => $e('campus', 'Library book issued', false, 'digest', ['affected_user'], 'A library book was issued to you',
            "Hello {recipient_name},\n\n\"{title}\" was issued to you. Return it by {due}.", ['title', 'due'], ['title' => 'Introduction to Algorithms', 'due' => '25 Oct 2026']),
        'library.loan_returned' => $e('campus', 'Library book returned', false, 'digest', ['affected_user'], 'A library book was returned',
            "Hello {recipient_name},\n\n\"{title}\" was returned.", ['title'], ['title' => 'Introduction to Algorithms']),
        'library.loan_due_soon' => $e('campus', 'Library book due soon', true, 'digest', ['affected_user'], 'A library book is due soon',
            "Hello {recipient_name},\n\n\"{title}\" is due on {due}. Please return it on time to avoid a fine.", ['title', 'due'], ['title' => 'Introduction to Algorithms', 'due' => '25 Oct 2026']),
        'library.loan_overdue' => $e('campus', 'Library book overdue', true, 'immediate', ['affected_user'], 'A library book is overdue',
            "Hello {recipient_name},\n\n\"{title}\" was due on {due}. Please return it; a fine may apply.", ['title', 'due'], ['title' => 'Introduction to Algorithms', 'due' => '10 Oct 2026']),
        'library.fine_recorded' => $e('campus', 'Library fine recorded', true, 'immediate', ['affected_user'], 'A library fine was recorded',
            "Hello {recipient_name},\n\nA fine of {amount} was recorded for \"{title}\". It must be settled before clearance.", ['title', 'amount'], ['title' => 'Introduction to Algorithms', 'amount' => '50.00']),
        'library.fine_settled' => $e('campus', 'Library fine settled', true, 'immediate', ['affected_user'], 'A library fine was settled',
            "Hello {recipient_name},\n\nThe fine for \"{title}\" ({amount}) was marked settled.", ['title', 'amount'], ['title' => 'Introduction to Algorithms', 'amount' => '50.00']),

        // ── Notices & academic ─────────────────────────────────────────
        'notice.published' => $e('academic', 'Notice published', false, 'digest', ['affected_user'], 'New notice: {title}',
            "Hello {recipient_name},\n\nA new notice was published: {title}\n\nRead it: {link}", ['title'], ['title' => 'Exam routine published'], 'Restricted notices are not copied into the email.'),
        'course.teachers_assigned' => $e('academic', 'Teaching assignment', true, 'immediate', ['affected_user'], 'You were assigned to {course}',
            "Hello {recipient_name},\n\nYou were assigned to teach {course}.", ['course'], ['course' => 'CSE-3101 Algorithms']),
        'routine.generated' => $e('academic', 'Routine generated', false, 'digest', ['department_head'], 'A class routine was generated',
            "The routine for {semester} was generated.\n\n{link}", ['semester'], ['semester' => 'Spring 2026']),
        'semester.activated' => $e('academic', 'Active semester changed', false, 'immediate', ['role:admin_office'], 'The active semester changed',
            '{semester} is now the active semester.', ['semester'], ['semester' => 'Spring 2026']),

        // ── System & operations ────────────────────────────────────────
        'system.scheduled_task_failed' => $e('system', 'Scheduled task failed', true, 'immediate', ['role:super_admin'], 'A scheduled task failed: {command}',
            "The scheduled command {command} failed at {time}.\n\nCheck the logs and run it again.", ['command', 'time'], ['command' => 'portal:check', 'time' => '10 Oct 2026 06:00']),
        'system.queue_job_failed' => $e('system', 'Queue job failed', true, 'digest', ['role:super_admin'], 'Background jobs failed',
            "{count} job(s) failed. Latest: {job}.\n\nRetry them with: php artisan queue:retry all", ['count', 'job'], ['count' => '2', 'job' => 'PullStudentResults'], 'The job payload (which can hold personal data) is never included.'),
        'system.email_delivery_failing' => $e('system', 'Emails are failing', true, 'immediate', ['role:super_admin'], 'Email delivery is failing',
            "{count} emails failed in the last hour. Last error: {error}\n\n{link}", ['count', 'error'], ['count' => '5', 'error' => 'Connection refused']),
        'system.mcp_denied_spike' => $e('system', 'Many denied AI tool calls', false, 'digest', ['role:super_admin'], 'Many AI tool calls were denied',
            "{count} AI tool calls were denied in the last 24 hours.\n\n{link}", ['count'], ['count' => '120']),
    ],

];
