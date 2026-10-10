# Data model

72 tables created by the migrations in `database/migrations`. Primary keys are `id`; foreign keys end in `_id`. Models live in `app/Models`, enums in `app/Enums`. The column lists come from a fresh migration of the project, so they are exact.


## People and organisation

### `users`

Every account (students, teachers, staff, admins).

<details><summary>Columns (12)</summary>

`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `theme`, `theme_primary_color`, `deleted_at`, `created_at`, `updated_at`, `is_active`

</details>

### `students`

Student record: department, program, batch, admission year, roll, registration number, current semester.

<details><summary>Columns (13)</summary>

`id`, `user_id`, `department_id`, `batch_id`, `roll_number`, `registration_number`, `current_semester`, `phone`, `deleted_at`, `created_at`, `updated_at`, `program_id`, `admission_year`

</details>

### `student_profiles`

Profile gate data: names, address, blood group, photo, hall, completion time, locked fields.

<details><summary>Columns (22)</summary>

`id`, `student_id`, `full_name_certificate`, `father_name`, `mother_name`, `date_of_birth`, `email`, `present_address`, `permanent_address`, `photo_path`, `guardian_name`, `guardian_phone`, `blood_group`, `nid_or_birth_reg`, `is_residential`, `hall_id`, `emergency_contact_name`, `emergency_contact_phone`, `profile_completed_at`, `locked_fields`, `created_at`, `updated_at`

</details>

### `teachers`

Teacher record linked to a user and a designation.

<details><summary>Columns (11)</summary>

`id`, `user_id`, `department_id`, `employee_id`, `designation_id`, `short_name`, `joining_date`, `phone`, `deleted_at`, `created_at`, `updated_at`

</details>

### `staff`

Non-teaching staff record.

<details><summary>Columns (10)</summary>

`id`, `user_id`, `department_id`, `designation_id`, `employee_id`, `joining_date`, `phone`, `deleted_at`, `created_at`, `updated_at`

</details>

### `staff_signatures`

Approver signature images (stored privately) used on clearance certificates.

<details><summary>Columns (4)</summary>

`user_id`, `image_path`, `created_at`, `updated_at`

</details>

### `departments`

Departments (CSE, EEE, CE).

<details><summary>Columns (8)</summary>

`id`, `name`, `code`, `description`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `programs`

Programs of a department.

<details><summary>Columns (10)</summary>

`id`, `department_id`, `name`, `code`, `required_credits`, `total_semesters`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `batches`

Student batches with session (for example 2022-2023) and current semester.

<details><summary>Columns (10)</summary>

`id`, `department_id`, `batch_number`, `session`, `current_semester`, `is_active`, `is_archived`, `deleted_at`, `created_at`, `updated_at`

</details>

### `designations`

Designations of teachers and staff.

<details><summary>Columns (8)</summary>

`id`, `name`, `short_name`, `type`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `institution_settings`

Institution name, logo and print settings.

<details><summary>Columns (14)</summary>

`id`, `institution_name`, `short_name`, `logo_path`, `address`, `phone`, `email`, `website`, `principal_name`, `principal_title`, `principal_signature_path`, `enable_supervisor`, `created_at`, `updated_at`

</details>


## Access control and audit

### `roles`

Roles (spatie).

<details><summary>Columns (5)</summary>

`id`, `name`, `guard_name`, `created_at`, `updated_at`

</details>

### `permissions`

Permissions (spatie, Shield).

<details><summary>Columns (5)</summary>

`id`, `name`, `guard_name`, `created_at`, `updated_at`

</details>

### `model_has_roles`

User to role.

<details><summary>Columns (3)</summary>

`role_id`, `model_type`, `model_id`

</details>

### `role_has_permissions`

Role to permission.

<details><summary>Columns (2)</summary>

`permission_id`, `role_id`

</details>

### `model_has_permissions`

Direct user permissions.

<details><summary>Columns (3)</summary>

`permission_id`, `model_type`, `model_id`

</details>

### `role_scopes`

Pins a user's role to a department, hall or course.

<details><summary>Columns (7)</summary>

`id`, `user_id`, `role_id`, `scope_type`, `scope_id`, `created_at`, `updated_at`

</details>

### `audit_logs`

Append-only log of every write with actor, channel (web / mcp / assistant / system), before and after.

<details><summary>Columns (12)</summary>

`id`, `actor_user_id`, `channel`, `action`, `entity_type`, `entity_id`, `before`, `after`, `ip`, `user_agent`, `integration_id`, `created_at`

</details>

### `idempotency_keys`

MCP idempotency keys so retried writes do not repeat.

<details><summary>Columns (8)</summary>

`id`, `user_id`, `tool`, `key`, `request_hash`, `response`, `created_at`, `updated_at`

</details>


## Account security

### `user_sessions`

Server-side record of every login session (revocable).

<details><summary>Columns (20)</summary>

`id`, `user_id`, `session_hash`, `device_label`, `device_type`, `browser`, `os`, `ip`, `location`, `remember`, `mfa_passed_at`, `last_active_at`, `expires_at`, `trusted_until`, `revoked_at`, `revoked_by`, `revoked_reason`, `trusted_token_hash`, `created_at`, `updated_at`

</details>

### `known_devices`

Devices seen, for new-device alerts and trusted devices.

<details><summary>Columns (9)</summary>

`id`, `user_id`, `fingerprint_hash`, `label`, `trusted_token_hash`, `trusted_until`, `first_seen_at`, `created_at`, `updated_at`

</details>

### `user_mfa`

TOTP secret (encrypted), last used step, failed attempts, lockout.

<details><summary>Columns (8)</summary>

`user_id`, `totp_secret_encrypted`, `enabled_at`, `last_used_step`, `failed_attempts`, `locked_until`, `created_at`, `updated_at`

</details>

### `mfa_recovery_codes`

Hashed single-use recovery codes.

<details><summary>Columns (5)</summary>

`id`, `user_id`, `code_hash`, `used_at`, `created_at`

</details>

### `mfa_role_policies`

Roles for which two-factor is mandatory.

<details><summary>Columns (4)</summary>

`role_id`, `required`, `created_at`, `updated_at`

</details>

### `sessions`

Framework sessions.

<details><summary>Columns (6)</summary>

`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`

</details>

### `password_reset_tokens`

Framework table (no reset flow is enabled yet).

<details><summary>Columns (3)</summary>

`email`, `token`, `created_at`

</details>


## Academics and results

### `semesters`

Semesters with start and end dates; one is active.

<details><summary>Columns (9)</summary>

`id`, `name`, `code`, `starts_on`, `ends_on`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `courses`

Course catalog (code, name, credits, type, department, semester number).

<details><summary>Columns (14)</summary>

`id`, `department_id`, `semester_number`, `type`, `code`, `version`, `name`, `credit_hours`, `weekly_classes`, `description`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `course_teacher`

Course to teacher.

<details><summary>Columns (2)</summary>

`course_id`, `teacher_id`

</details>

### `course_offerings`

A course offered in a semester (section, teacher).

<details><summary>Columns (7)</summary>

`id`, `course_id`, `semester_id`, `section`, `teacher_id`, `created_at`, `updated_at`

</details>

### `enrollments`

Student to offering with attempt type (regular / retake / improvement) and status.

<details><summary>Columns (7)</summary>

`id`, `student_id`, `course_offering_id`, `attempt_type`, `status`, `created_at`, `updated_at`

</details>

### `results`

Marks, letter and grade point per enrollment with the draft, submitted, approved, published workflow.

<details><summary>Columns (14)</summary>

`id`, `enrollment_id`, `marks`, `letter`, `grade_point`, `status`, `entered_by`, `approved_by`, `published_by`, `submitted_at`, `approved_at`, `published_at`, `created_at`, `updated_at`

</details>

### `grading_scales`

Live grade bands (editable); seeded with the UGC scale.

<details><summary>Columns (8)</summary>

`id`, `min_mark`, `max_mark`, `letter`, `grade_point`, `active_from`, `created_at`, `updated_at`

</details>

### `routine_slots`

Weekly class routine slots.

<details><summary>Columns (12)</summary>

`id`, `department_id`, `batch_id`, `semester_number`, `day_of_week`, `time_slot_id`, `course_id`, `teacher_id`, `slot_group_id`, `is_lab_continuation`, `created_at`, `updated_at`

</details>

### `time_slots`

Period times.

<details><summary>Columns (9)</summary>

`id`, `name`, `start_time`, `end_time`, `type`, `sort_order`, `deleted_at`, `created_at`, `updated_at`

</details>

### `exam_types`

Exam types.

<details><summary>Columns (5)</summary>

`id`, `type`, `deleted_at`, `created_at`, `updated_at`

</details>

### `exam_halls`

Exam halls.

<details><summary>Columns (5)</summary>

`id`, `name`, `deleted_at`, `created_at`, `updated_at`

</details>

### `exam_duties`

Exam duty entries.

<details><summary>Columns (13)</summary>

`id`, `start_time`, `end_time`, `exam_type_id`, `exam_name`, `semester`, `batch`, `department`, `exam_year`, `duty_details`, `deleted_at`, `created_at`, `updated_at`

</details>


## Clearance

### `clearance_requests`

One request per student attempt: status, version lock, request number, verify code, hash chain head.

<details><summary>Columns (19)</summary>

`id`, `request_no`, `verify_code`, `student_id`, `status`, `current_stage_id`, `submitted_at`, `ready_at`, `printed_at`, `collected_at`, `collected_by`, `id_verified`, `cancelled_at`, `cancel_reason`, `version`, `chain_hash`, `last_reminded_at`, `created_at`, `updated_at`

</details>

### `clearance_stages`

Configurable approval stages (hall, library, department, head): order, approver role, scope rule, active, skippable.

<details><summary>Columns (10)</summary>

`id`, `key`, `label`, `order`, `approver_role_id`, `scope_rule`, `skippable`, `active`, `created_at`, `updated_at`

</details>

### `clearance_approvals`

Each stage decision with approver snapshot, signature hash and chain hash.

<details><summary>Columns (17)</summary>

`id`, `clearance_request_id`, `stage_id`, `decision`, `approver_user_id`, `approver_name`, `approver_designation`, `signature_snapshot_path`, `signature_sha256`, `remarks`, `ip`, `decided_at`, `prev_hash`, `hash`, `superseded`, `created_at`, `updated_at`

</details>

### `clearance_events`

Timeline of status changes.

<details><summary>Columns (8)</summary>

`id`, `clearance_request_id`, `from_status`, `to_status`, `actor_user_id`, `channel`, `note`, `created_at`

</details>

### `clearance_prints`

Every print and reprint with the duplicate flag.

<details><summary>Columns (8)</summary>

`id`, `clearance_request_id`, `printed_by`, `printed_at`, `is_duplicate`, `format`, `created_at`, `updated_at`

</details>


## Halls, library and notices

### `halls`

Halls.

<details><summary>Columns (9)</summary>

`id`, `name`, `code`, `gender`, `capacity`, `is_active`, `deleted_at`, `created_at`, `updated_at`

</details>

### `hall_residencies`

Which student lives in which hall and room, from and to.

<details><summary>Columns (8)</summary>

`id`, `student_id`, `hall_id`, `room`, `assigned_on`, `ended_on`, `created_at`, `updated_at`

</details>

### `hall_dues`

Dues owed to a hall.

<details><summary>Columns (8)</summary>

`id`, `student_id`, `hall_id`, `description`, `amount`, `settled_at`, `created_at`, `updated_at`

</details>

### `library_loans`

Books issued, due dates, returns and fines.

<details><summary>Columns (11)</summary>

`id`, `student_id`, `book_title`, `accession_no`, `issued_on`, `due_on`, `returned_on`, `fine_amount`, `fine_settled_at`, `created_at`, `updated_at`

</details>

### `notices`

Notices for everyone, a department or a hall.

<details><summary>Columns (11)</summary>

`id`, `title`, `body`, `audience`, `department_id`, `hall_id`, `created_by`, `published_at`, `deleted_at`, `created_at`, `updated_at`

</details>


## Profile gate

### `profile_required_fields`

Which profile fields are required or active (super admin editable).

<details><summary>Columns (6)</summary>

`id`, `field_key`, `required`, `active`, `created_at`, `updated_at`

</details>


## MCP and assistant

### `mcp_integrations`

AI integrations: hashed token, client, access level, expiry, last use.

<details><summary>Columns (18)</summary>

`id`, `user_id`, `name`, `client_type`, `access_level`, `token_hash`, `token_prefix`, `expires_at`, `first_connected_at`, `last_used_at`, `last_used_ip`, `known_ips`, `expiry_notified_at`, `revoked_at`, `revoked_by`, `revoked_reason`, `created_at`, `updated_at`

</details>

### `mcp_settings`

Global MCP switches and limits.

<details><summary>Columns (6)</summary>

`id`, `global_enabled`, `max_integrations_per_user`, `allow_never_expire`, `created_at`, `updated_at`

</details>

### `mcp_role_access`

MCP on or off per role.

<details><summary>Columns (4)</summary>

`role_id`, `enabled`, `created_at`, `updated_at`

</details>

### `assistant_conversations`

Assistant conversations per user.

<details><summary>Columns (4)</summary>

`id`, `user_id`, `created_at`, `updated_at`

</details>

### `assistant_messages`

Messages, tool calls and confirmation cards.

<details><summary>Columns (6)</summary>

`id`, `conversation_id`, `role`, `content`, `tool_calls`, `created_at`

</details>


## Result portal

### `result_pulls`

Every attempt to pull a student's official results: status, reason, counts, trigger.

<details><summary>Columns (19)</summary>

`id`, `student_id`, `requested_by`, `trigger`, `status`, `attempts`, `exams_checked`, `results_found`, `results_changed`, `portal_exam_id`, `portal_publication_id`, `pending_checks`, `message`, `next_check_at`, `queued_at`, `started_at`, `finished_at`, `created_at`, `updated_at`

</details>

### `portal_results`

Course grades from the portal with retake / improved / declined marks and history.

<details><summary>Columns (18)</summary>

`id`, `student_id`, `result_pull_id`, `portal_exam_id`, `exam_title`, `exam_kind`, `course_code`, `course_title`, `credits`, `letter`, `grade_point`, `is_current`, `change_type`, `previous_letter`, `previous_grade_point`, `fetched_at`, `created_at`, `updated_at`

</details>

### `portal_exam_results`

Per student and exam: roll, outcome, GPA, CGPA, backlog, raw page reference.

<details><summary>Columns (19)</summary>

`id`, `student_id`, `result_pull_id`, `portal_exam_id`, `exam_title`, `exam_kind`, `exam_year`, `exam_roll`, `class_roll`, `published_on`, `outcome`, `gpa`, `cgpa`, `backlog_codes`, `raw_page_path`, `raw_page_hash`, `fetched_at`, `created_at`, `updated_at`

</details>

### `portal_exams`

Saved exam list of the portal (CSE, EEE, Civil) with kind, semester and exam year.

<details><summary>Columns (17)</summary>

`id`, `portal_exam_id`, `program_id`, `title`, `kind`, `semester`, `exam_year`, `session_tag`, `status`, `first_seen_at`, `last_seen_at`, `confirmed_at`, `published_on`, `last_checked_at`, `check_count`, `created_at`, `updated_at`

</details>

### `portal_publications`

A detected result publication (detected, awaiting, shadow, confirmed).

<details><summary>Columns (14)</summary>

`id`, `portal_exam_id`, `program_id`, `status`, `mode`, `detected_at`, `confirmed_at`, `last_checked_at`, `next_check_at`, `check_count`, `students_total`, `notes`, `created_at`, `updated_at`

</details>

### `portal_probes`

Probe lookups that confirmed or denied a publication.

<details><summary>Columns (8)</summary>

`id`, `portal_exam_id`, `student_id`, `outcome`, `message`, `checked_at`, `created_at`, `updated_at`

</details>

### `portal_check_runs`

Every daily check and catalog sync run.

<details><summary>Columns (11)</summary>

`id`, `kind`, `status`, `exams_total`, `new_exams`, `publications_confirmed`, `message`, `started_at`, `finished_at`, `created_at`, `updated_at`

</details>


## Email notifications

### `notification_rules`

Per event: on or off, mode, recipients, template.

<details><summary>Columns (9)</summary>

`id`, `event_key`, `category`, `enabled`, `mode`, `recipients`, `email_template_id`, `created_at`, `updated_at`

</details>

### `email_templates`

Admin-written subjects and bodies.

<details><summary>Columns (7)</summary>

`id`, `event_key`, `name`, `subject`, `body`, `created_at`, `updated_at`

</details>

### `outbox_events`

Events saved in the same transaction as the change, so none is lost.

<details><summary>Columns (13)</summary>

`id`, `event_key`, `dedupe_key`, `context`, `affected_user_id`, `department_id`, `hall_id`, `occurred_at`, `processed_at`, `attempts`, `last_error`, `created_at`, `updated_at`

</details>

### `email_deliveries`

Every email with status, attempts, error and a unique dedupe key.

<details><summary>Columns (19)</summary>

`id`, `outbox_event_id`, `event_key`, `recipient_user_id`, `recipient_email`, `subject`, `body`, `url`, `status`, `mode`, `attempts`, `last_error`, `dedupe_key`, `is_test`, `digest_delivery_id`, `queued_at`, `sent_at`, `created_at`, `updated_at`

</details>

### `notifications`

In-app (bell) notifications.

<details><summary>Columns (8)</summary>

`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`

</details>


## Framework

### `cache`

Cache store.

<details><summary>Columns (3)</summary>

`key`, `value`, `expiration`

</details>

### `cache_locks`

Cache locks.

<details><summary>Columns (3)</summary>

`key`, `owner`, `expiration`

</details>

### `jobs`

Queue jobs.

<details><summary>Columns (7)</summary>

`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`

</details>

### `job_batches`

Queue batches.

<details><summary>Columns (10)</summary>

`id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at`

</details>

### `failed_jobs`

Failed queue jobs.

<details><summary>Columns (7)</summary>

`id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at`

</details>

### `migrations`

Migration history.

<details><summary>Columns (3)</summary>

`id`, `migration`, `batch`

</details>

