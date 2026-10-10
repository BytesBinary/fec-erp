# MCP tool catalog

The ERP exposes **130 tools** through the Model Context Protocol (`POST /mcp`, or stdio with `php artisan mcp:start erp`). 59 are read-only, 71 change data and 18 are destructive (a dry-run preview first, then `confirm=true`).

Every call runs **as the signed-in user** (never as a superuser), is checked by the same authorization layer as the web screens and is written to the audit log with channel `mcp`. `tools/list` only shows the tools the user may call. See `README_MCP.md` for how to connect a client, create a token and the safety rules. The in-app assistant uses the very same tools.

Legend: **R** read-only · **W** writes · **D** destructive (dry-run preview, then `confirm=true`) · parameters in *italics* are required.

## My account

Every signed-in user: own profile, permissions and sessions. (4 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `me_get_profile` | R | any signed-in user | Get the signed-in user's own profile. Students also get profile completion progress and the list of missing or invalid required fields. Use this first to learn who you are acting as. | — |
| `me_update_profile` | W | any signed-in user | Update the signed-in user's own details. Staff can change name and email. Students can change their profile fields (some fields lock after a clearance request). Send only what changes. Passwords, 2FA and photos are web-only. | name, full_name_certificate, father_name, mother_name, date_of_birth, phone, email, present_address, permanent_address, guardian_name, guardian_phone, blood_group, nid_or_birth_reg, is_residential, hall_id, emergency_contact_name, emergency_contact_phone |
| `me_list_permissions` | R | any signed-in user | List the signed-in user's roles, the scope of each role (department, hall or course ids) and every permission they hold. Use it to explain why something is not allowed. | — |
| `me_list_sessions` | R | any signed-in user | List the devices the signed-in user is logged in on (read-only). Logging devices out, 2FA and integrations are web-only actions. | — |

## Users, roles and permissions

Accounts, role assignment with scope, the permission matrix. (11 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `user_list` | R | `user:list` | List the users you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. Roles are assigned with role_assign. | search, filters, limit, cursor |
| `user_get` | R | `user:view` | Get one user by id with all its fields. Use it after user_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `user_create` | W | `user:create` | Create a new user. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. Roles are assigned with role_assign. | *name*, *email*, *password* |
| `user_update` | W | `user:update` | Change fields of an existing user. Send only the fields to change. | *id*, name, email, password |
| `user_deactivate` | D | `user:deactivate` | Deactivate a login account. The user is signed out everywhere and cannot sign in. Reversible with user_reactivate. Needs confirm=true. | *id* |
| `user_reactivate` | W | `user:deactivate` | Reactivate a previously deactivated login account so the user can sign in again. Use after user_deactivate; returns the updated user. | *id* |
| `role_list` | R | `role:list` | List all roles with how many users hold each. Use it to learn valid role keys before role_assign or role_revoke. | — |
| `role_assign` | W | `role:assign` | Give a user a role. department_head needs department_ids, hall_provost needs hall_ids, teacher may take extra course ids. Roles are additive: a user may hold several. | *user_id*, *role*, scope_ids |
| `role_revoke` | D | `role:revoke` | Remove a role from a user; they lose its permissions at once. Needs confirm=true (without it you get a preview). Use role_list first for the role key. | *user_id*, *role* |
| `permission_matrix_get` | R | `permission_matrix:view` | Get which permissions each role holds (role name to permission names). | — |
| `permission_matrix_update` | D | `permission_matrix:update` | Grant and/or revoke permissions of one role. Needs confirm=true. Changing what roles may do affects every user holding the role. | *role*, grant, revoke |

## Academic structure

Departments, programs, semesters, courses, batches, course offerings, grading scale, designations, teacher assignment. (34 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `department_list` | R | `department:list` | List the departments you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `department_get` | R | `department:view` | Get one department by id with all its fields. Use it after department_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `department_create` | W | `department:create` | Create a new department. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *code*, description, is_active |
| `department_update` | W | `department:update` | Change fields of an existing department. Send only the fields to change. | *id*, name, code, description, is_active |
| `program_list` | R | `program:list` | List the programs you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `program_get` | R | `program:view` | Get one program by id with all its fields. Use it after program_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `program_create` | W | `program:create` | Create a new program. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *department_id*, *name*, *code*, *required_credits*, total_semesters, is_active |
| `program_update` | W | `program:update` | Change fields of an existing program. Send only the fields to change. | *id*, department_id, name, code, required_credits, total_semesters, is_active |
| `semester_list` | R | `semester:list` | List the semesters you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `semester_get` | R | `semester:view` | Get one semester by id with all its fields. Use it after semester_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `semester_create` | W | `semester:create` | Create a new semester. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *code*, starts_on, ends_on |
| `semester_update` | W | `semester:update` | Change fields of an existing semester. Send only the fields to change. | *id*, name, code, starts_on, ends_on |
| `semester_set_active` | W | `semester:activate` | Make a semester the active one (only one is active at a time; the previous one is switched off). | *id* |
| `course_list` | R | `course:list` | List the courses you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. Department heads can only manage courses of their own department. | search, filters, limit, cursor |
| `course_get` | R | `course:view` | Get one course by id with all its fields. Use it after course_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `course_create` | W | `course:create` | Create a new course. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. Department heads can only manage courses of their own department. | *department_id*, *semester_number*, *type*, *code*, version, *name*, *credit_hours*, weekly_classes, description, is_active |
| `course_update` | W | `course:update` | Change fields of an existing course. Send only the fields to change. | *id*, department_id, semester_number, type, code, version, name, credit_hours, weekly_classes, description, is_active |
| `course_archive` | D | `course:archive` | Archive (deactivate) a course without deleting it. Needs confirm=true. | *id* |
| `course_assign_teacher` | W | `course:assign_teacher` | Set the teachers of a course (replaces the current assignment). Teacher ids come from teacher_list. | *course_id*, *teacher_ids* |
| `course_offering_list` | R | `course_offering:list` | List course offerings (course x semester x section) you may see. Filter by semester_id or course_id. | semester_id, course_id, limit, cursor |
| `course_offering_get` | R | `course_offering:list` | Get one course offering (a course delivered in a semester and section) by id, with its teacher. Use after course_offering_list. | *id* |
| `course_offering_create` | W | `course_offering:create` | Offer a course in a semester (and section, default A), optionally with its teacher. Fails with CONFLICT if it already exists. | *course_id*, *semester_id*, section, teacher_id |
| `batch_list` | R | `batch:list` | List the batches you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `batch_get` | R | `batch:view` | Get one batch by id with all its fields. Use it after batch_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `batch_create` | W | `batch:create` | Create a new batch. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *department_id*, *batch_number*, *session*, *current_semester*, is_active |
| `batch_update` | W | `batch:update` | Change fields of an existing batch. Send only the fields to change. | *id*, department_id, batch_number, session, current_semester, is_active |
| `designation_list` | R | `designation:list` | List the designations you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `designation_get` | R | `designation:view` | Get one designation by id with all its fields. Use it after designation_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `designation_create` | W | `designation:create` | Create a new designation. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *short_name*, *type*, is_active |
| `designation_update` | W | `designation:update` | Change fields of an existing designation. Send only the fields to change. | *id*, name, short_name, type, is_active |
| `hall_list` | R | `hall:list` | List the halls you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `hall_get` | R | `hall:view` | Get one hall by id with all its fields. Use it after hall_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `hall_create` | W | `hall:create` | Create a new hall. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *code*, gender, capacity, is_active |
| `hall_update` | W | `hall:update` | Change fields of an existing hall. Send only the fields to change. | *id*, name, code, gender, capacity, is_active |

## People

Students, teachers and staff. (12 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `student_list` | R | `student:list` | List the students you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. Search matches name, email, roll and registration number. | search, filters, limit, cursor |
| `student_get` | R | `student:view` | Get one student by id with all its fields. Use it after student_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `student_create` | W | `student:create` | Create a new student. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. Search matches name, email, roll and registration number. | *name*, *email*, *password*, *department_id*, program_id, *batch_id*, admission_year, *roll_number*, *registration_number*, *current_semester*, phone |
| `student_update` | W | `student:update` | Change fields of an existing student. Send only the fields to change. | *id*, name, email, password, department_id, program_id, batch_id, admission_year, roll_number, registration_number, current_semester, phone |
| `teacher_list` | R | `teacher:list` | List the teachers you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `teacher_get` | R | `teacher:view` | Get one teacher by id with all its fields. Use it after teacher_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `teacher_create` | W | `teacher:create` | Create a new teacher. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *email*, *password*, *department_id*, *designation_id*, *employee_id*, short_name, phone, joining_date |
| `teacher_update` | W | `teacher:update` | Change fields of an existing teacher. Send only the fields to change. | *id*, name, email, password, department_id, designation_id, employee_id, short_name, phone, joining_date |
| `staff_list` | R | `staff:list` | List the staff members you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. | search, filters, limit, cursor |
| `staff_get` | R | `staff:view` | Get one staff member by id with all its fields. Use it after staff_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `staff_create` | W | `staff:create` | Create a new staff member. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. | *name*, *email*, *password*, *department_id*, *designation_id*, *employee_id*, phone, joining_date |
| `staff_update` | W | `staff:update` | Change fields of an existing staff member. Send only the fields to change. | *id*, name, email, password, department_id, designation_id, employee_id, phone, joining_date |

## Enrollment

Course enrollment of students. (5 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `enrollment_list` | R | `enrollment:list` | List enrollments you may see. Pass student_id for one student, or course_offering_id for one offering; otherwise lists everything in your scope. | student_id, course_offering_id, limit, cursor |
| `student_list_my_courses` | R | `enrollment:list` | List the signed-in student's current course enrollments with course codes and semesters. | — |
| `enrollment_create` | W | `enrollment:create` | Enroll one student in a course offering (regular, retake or improvement attempt). Fails with CONFLICT if already enrolled. Use enrollment_bulk_create for many students. | *student_id*, *course_offering_id*, attempt_type |
| `enrollment_bulk_create` | D | `enrollment:bulk_create` | Enroll many students in one offering in one all-or-nothing step. Needs confirm=true; without it you get a preview of who would be enrolled. | *course_offering_id*, *student_ids*, attempt_type |
| `enrollment_drop` | D | `enrollment:drop` | Drop a student from a course offering (not possible once marks were entered). Needs confirm=true. | *enrollment_id* |

## Results, transcript and result portal

Marks workflow, CGPA, transcript, official portal results, pulls and the portal monitor. (18 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `result_get_semester` | R | `result:view` | Get published results of one semester (or all) for a student: each course with grade and grade point, the semester GPA and the CGPA. Unpublished results are never shown. | student_id, semester_id |
| `result_get_cgpa` | R | `result:view` | Get a student's CGPA (rounded half up to 2 decimals), counted and earned credits. Based on published results only; the best attempt of a repeated course counts. | student_id |
| `result_pull_list` | R | `result_pull:list` | List the official-result pulls from the exam portal (newest first) with status success / failed / waiting / skipped and the reason for failures, plus the latest status counts. Use it to find which students' results could not be pulled. | status, limit |
| `result_pull_retry` | W | `result_pull:retry` | Queue a new pull for the student of a failed or skipped result pull. | *pull_id* |
| `result_pull_retry_failed` | W | `result_pull:retry` | Queue a new pull for every student whose latest pull failed or was skipped (within your scope). | — |
| `result_portal_get` | R | `result:view` | Get a student's current course grades pulled from the exam portal. A grade that replaced an earlier attempt carries change_type improved / retake / declined and the previous grade. | student_id |
| `portal_monitor_get` | R | `portal_monitor:view` | Status of the university result-portal sync: last and next daily check, exams saved per department and year, detected publications (detected / awaiting / shadow / confirmed), student pull counts, recent probes and runs. | — |
| `portal_catalog_sync` | W | `portal_monitor:manage` | First-time (or manual) sync: fetch the exam lists of CSE, EEE and Civil from the portal and save them. Old exams are stored as known, not as new publications. | — |
| `portal_check_run` | W | `portal_monitor:manage` | Run the daily publication check now: read the exam list, detect exams that are new, and confirm their results with probe students. In shadow mode nothing is pulled until portal_publication_run. | — |
| `portal_publication_run` | W | `portal_monitor:manage` | Queue a result pull for every eligible student of a confirmed (or shadow) publication. | *publication_id* |
| `transcript_get` | R | `transcript:view` | Get the full transcript (all published semesters, GPAs, CGPA, credits). | student_id |
| `result_roster` | R | `result:enter_marks|result:approve|result:publish` | List the students of a course offering with their marks and result status (draft, submitted, approved, published). For teachers, heads and publishers. | *course_offering_id* |
| `result_enter_marks` | W | `result:enter_marks` | Enter or change marks (0-100) for one enrollment while its result is still a draft. The grade is derived from the grading scale. Teachers can only do this for their own courses. | *enrollment_id*, *marks* |
| `result_submit` | W | `result:submit` | Submit all draft results of an offering to the department head. Every enrolled student needs marks first. | *course_offering_id* |
| `result_approve` | D | `result:approve` | Department head approves the submitted results of an offering. Needs confirm=true; without it you get a preview. | *course_offering_id* |
| `result_publish` | D | `result:publish` | Publish every approved result of a semester so students can see them. Cannot be undone. Needs confirm=true; the preview says how many results and students are affected. | *semester_id* |
| `grading_scale_get` | R | `result:view` | Get the grading scale: marks ranges, letters and grade points. | — |
| `grading_scale_replace` | D | `grading_scale:manage` | Replace the whole grading scale. Needs confirm=true. Future marks entry uses the new scale; published grades are not recomputed. | *bands* |

## Halls and library

Hall residency and dues, library loans and fines. (11 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `hall_assign_student` | W | `hall:assign_student` | Move a student into a hall (ends their previous residency). A provost can only use their own hall. | *student_id*, *hall_id*, room |
| `hall_vacate_student` | D | `hall:assign_student` | End a residency (the student leaves the hall). Needs confirm=true. | *residency_id* |
| `hall_residents_list` | R | `hall:assign_student` | List the students currently living in the halls you manage (provosts: their own hall), with room numbers. Returns items and next_cursor. | limit, cursor |
| `hall_dues_get` | R | `hall_dues:view` | List the hall dues of a student (open and settled), used when deciding a clearance. | *student_id*, open_only |
| `hall_due_record` | W | `hall_dues:manage` | Record an amount a student owes the hall (the student must live in the provost's hall). | *student_id*, *description*, *amount* |
| `hall_due_settle` | W | `hall_dues:manage` | Mark a hall due as paid after the student settled it; it then no longer blocks the hall clearance decision. Get due ids from hall_dues_get. | *due_id* |
| `library_loans_list_for_student` | R | `library_loans:view` | List a student's library loans (borrowed books, due and return dates, fines). Set outstanding_only to see only unreturned books. | *student_id*, outstanding_only |
| `library_dues_get` | R | `library_dues:view` | Get a student's unreturned books and unpaid fines (what a librarian checks before approving clearance). | *student_id* |
| `library_loan_issue` | W | `library_loans:manage` | Record that a book was issued to a student with its due date. Pass an idempotencyKey when retrying so the loan is not recorded twice. | *student_id*, *book_title*, *due_on*, accession_no |
| `library_loan_return` | W | `library_loans:manage` | Mark a library loan as returned today, optionally recording a late fine that must then be settled with library_fine_settle. | *loan_id*, fine |
| `library_fine_settle` | W | `library_loans:manage` | Mark a library fine as paid so it no longer blocks the library clearance decision. Get loan ids from library_dues_get. | *loan_id* |

## Clearance

Apply, approve / reject, print, collect, search, verify. (15 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `clearance_check_eligibility` | R | `clearance:view` | Check whether a student can apply for clearance, with the exact reasons when not (incomplete profile, missing credits, results awaiting publication, an active request, inactive account). Students omit student_id. | student_id |
| `clearance_apply` | W | `clearance:apply` | Student applies for clearance. Checks eligibility, creates request CLR-YYYY-NNNNNN and starts the approval chain (hall, library, department, head). Name, parents and date of birth lock afterwards. | — |
| `clearance_get_my_status` | R | `clearance:apply` | Get the signed-in student's newest clearance request with its stage-by-stage timeline, approvers and rejection remarks. | — |
| `clearance_resubmit` | W | `clearance:apply` | After a rejection, the student fixes the problem and resubmits. The request resumes at the stage that rejected it; earlier approvals stay valid. | — |
| `clearance_cancel` | D | `clearance:cancel` | Cancel a clearance request: the student before the first approval, or super admin with a reason. Needs confirm=true. | request_id, reason |
| `clearance_list_pending_for_me` | R | `clearance:approve` | List clearance requests waiting for the signed-in approver's decision (their role at the current stage, within their hall/department scope). | — |
| `clearance_get` | R | `clearance:view` | Get one clearance request with its timeline. Approvers also see the student's dues through hall_dues_get / library_dues_get. | *request_id* |
| `clearance_approve` | D | `clearance:approve` | Approve the clearance at the current stage. Only the approver of that stage (matching hall/department) may do it, and they need a signature image uploaded. Needs confirm=true; without it you get a preview of the request. | *request_id*, remarks, expected_version |
| `clearance_reject` | D | `clearance:reject` | Reject the clearance at the current stage with a reason the student will see. Needs confirm=true. | *request_id*, *reason*, expected_version |
| `clearance_search` | R | `clearance:search` | Administration office: search clearances by q (student roll, name or request number), department_id, session (e.g. 2021-2022) or status. Default status is ready_for_collection; pass status=all for every status. | q, department_id, session, status, limit |
| `clearance_print` | D | `clearance:print` | Record that the office printed a fully approved clearance (first print moves it to PRINTED; reprints are marked DUPLICATE). Returns the print and PDF URLs to open in the browser. Needs confirm=true. | *request_id* |
| `clearance_mark_collected` | D | `clearance:mark_collected` | Record the hand-over of the printed, Principal-signed and sealed clearance. Say whether the student's ID was verified. Needs confirm=true. | *request_id*, *id_verified* |
| `clearance_verify_integrity` | R | `clearance:view` | Recompute the tamper-evidence hash chain of a clearance and report whether it is intact. | *request_id* |
| `clearance_stage_config_get` | R | `clearance_stage:manage` | Get the configured clearance stages in order (key, approver role, scope rule, skippable, active). | — |
| `clearance_stage_config_update` | D | `clearance_stage:manage` | Toggle a stage's active or skippable flag, or move it up/down in the chain. Needs confirm=true because it changes how every future clearance runs. | *id*, *action* |

## Notices

Institution, department and hall notices. (4 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `notice_list` | R | `notice:list` | List the notices you are allowed to see, newest id last. Use it to find ids before get/update or related calls. Supports a search text, exact-match filters and cursor paging (limit up to 100). Returns items and next_cursor. audience is all, department or hall; department and hall notices need the matching scope. | search, filters, limit, cursor |
| `notice_get` | R | `notice:view` | Get one notice by id with all its fields. Use it after notice_list to read a full record; returns the record, or NOT_FOUND / FORBIDDEN when you may not see it. | *id* |
| `notice_create` | W | `notice:create` | Create a new notice. Check the fields below; validation errors name each wrong field. Pass an idempotencyKey when retrying. audience is all, department or hall; department and hall notices need the matching scope. | *title*, *body*, *audience*, department_id, hall_id, published_at |
| `notice_update` | W | `notice:update` | Change fields of an existing notice. Send only the fields to change. | *id*, title, body, audience, department_id, hall_id, published_at |

## Email notifications

Event rules, templates, preview, test and delivery history. (12 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `notification_rule_list` | R | `notification_rule:view` | List every email event grouped by category with its switch (enabled), mode (immediate or digest), recipients, template and the placeholders a template may use. | — |
| `notification_rule_update` | W | `notification_rule:manage` | Enable or disable an email event, set immediate or digest, change recipients (affected_user, department_head, hall_provost, context_emails, role:<name>) or select a template. Super admin only. | *event_key*, enabled, mode, recipients, email_template_id |
| `notification_rule_reset` | W | `notification_rule:manage` | Put an email event back to its default switch, mode, recipients and template. | *event_key* |
| `notification_preview` | R | `notification_rule:view` | Render the email of an event with sample values (optionally a template or a draft subject/body) and show warnings, without sending anything. | *event_key*, email_template_id, subject, body |
| `notification_test_send` | W | `notification_rule:manage` | Send the sample email of an event to the signed-in user only. | *event_key*, email_template_id |
| `email_delivery_list` | R | `email_delivery:view` | List recent emails with status (queued, held, sent, failed, skipped, blocked) and the error of failures, plus counts per status. | status, event_key, limit |
| `email_delivery_retry` | W | `email_delivery:retry` | Queue a failed, skipped or blocked email again. | *delivery_id* |
| `email_delivery_retry_failed` | W | `email_delivery:retry` | Queue every failed email again. | — |
| `email_template_list` | R | `notification_rule:view` | List the custom email templates (subject and body) of one event or all events. | event_key |
| `email_template_create` | W | `email_template:manage` | Create a template for an event. Only that event's placeholders are allowed and text that looks like a credential is rejected. | *event_key*, *name*, *subject*, *body* |
| `email_template_update` | W | `email_template:manage` | Change the name, subject or body of a template. | *template_id*, name, subject, body |
| `email_template_delete` | D | `email_template:manage` | Delete a template; rules that used it go back to the default text. | *template_id* |

## Administration

Audit log search and profile-field configuration. (3 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `audit_log_search` | R | `audit_log:view` | Search the audit log (who changed what, from which channel: web, mcp or assistant). Filters are optional; newest first, cursor paging. | actor_user_id, channel, action, entity_type, entity_id, integration_id, from, to, limit, cursor |
| `profile_field_list` | R | `profile_field:manage` | List which student profile fields are required and active; students missing a required field cannot use the system until they complete their profile. | — |
| `profile_field_configure` | D | `profile_field:manage` | Make a student profile field required/optional or switch it on/off. A newly required field gates students whose profile no longer satisfies the set. Needs confirm=true. | *field_key*, *required*, active |

## Help

Find where a feature lives in the app. (1 tools)

| Tool | Kind | Permission | What it does | Parameters |
|---|---|---|---|---|
| `help_search_features` | R | any signed-in user | Search the application's screens and features by keywords ("create a course", "my results", "clearance desk"). Returns the screens the user may open, each with title, menu path, URL (deep link), required permission, keywords and how-to steps, plus matches the user cannot use together with the roles that can. Never invent a feature that this tool does not return. | *query*, limit |

