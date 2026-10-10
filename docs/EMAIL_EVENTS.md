# Email notification events — audit and matrix

Status: **implemented** (see "Operations" at the end). Found by reading the code (services, notifications, observers, scheduler,
audit actions), not by guessing. "Exists" = something already fires today (in-app bell + a log line, because
`ExternalMessenger` is bound to `LogMessenger`); "New" = the operation exists but nothing notifies; "Planned" = belongs to
the result-portal work.

## What the audit found

- Every existing notification already extends `InAppNotification`, whose `ExternalChannel` hands title/body/url to the
  bound `ExternalMessenger`. **That is the one hook point to reuse.** Today the messenger only writes to the log and
  `MAIL_MAILER=log`, so no real email leaves the system.
- 13 notifications exist today: new device, password changed, 2FA locked out, 2FA reset by admin, 6 MCP
  (created, revoked, stopped all, expiring, new IP, …) and the clearance set (submitted, resubmitted, waiting, approved,
  rejected, ready, collected, reminder, escalation).
- Many operations only write the audit log (2FA enabled/disabled, sessions revoked, role scopes changed, MCP switches,
  course teachers assigned, routine generated) or nothing at all (result submit/approve/publish, enrollment, hall
  residency, dues, library loans, notices, user (de)activation, student create/update, exam duties).
- Filament-only CRUD (staff, teachers, halls, notices, exam duties) bypasses services, so those events need model
  observers; service-backed ones fire from the service.
- Infrastructure present: `jobs`, `failed_jobs`, `notifications` tables, `QUEUE_CONNECTION=database`, a scheduler with 3
  daily commands (+ result portal check planned).
- **Gaps to flag:** there is **no password-reset flow** (Filament `passwordReset()` is not enabled), so a "welcome"
  email can never contain a password and currently has nothing to link to for setting one. There is no email-address
  verification flow either.

## Defaults principle

| Rule | Meaning |
|---|---|
| **ON** | Security-critical, or a state change the affected person must know about |
| **OFF (optional)** | Noisy, administrative convenience, or low value |
| **Digest** | High volume or many recipients: one summary mail per recipient per day (07:30) instead of one mail per event |
| **Immediate** | Time-sensitive or security: queued at once |

Never in any email: passwords, tokens (MCP, session, trusted-device), TOTP secrets and recovery codes, full IP history,
national ID / birth registration, phone numbers, file paths. Grades are **not** included by default (a login link is);
an admin may opt in per event after the preview.

## Matrix

Columns: **Event key** · when / status · recipient · default · mode · email contains · sensitive → excluded.

### A. Account and security

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `security.new_device_login` | First login from an unseen browser · **Exists** | The user | ON | Immediate | Device label, time, coarse IP, link to Devices | Session id, cookie |
| `security.password_changed` | Password changed · **Exists** | The user | ON | Immediate | Time, count of devices signed out, link to Devices | Password |
| `security.two_factor_locked_out` | 5 wrong codes · **Exists** | The user | ON | Immediate | Lock minutes, advice | Codes |
| `security.two_factor_reset_by_admin` | Admin resets 2FA · **Exists** | The user (+ super admins optional) | ON | Immediate | Admin's reason, what to do next | Secret, recovery codes |
| `security.two_factor_enabled` | 2FA turned on · audit only → **New** | The user | ON | Immediate | Time, device | Secret |
| `security.two_factor_disabled` | 2FA turned off · event exists, no mail → **New** | The user | ON | Immediate | Time, "integrations were stopped" | — |
| `security.recovery_code_used` | A recovery code consumed · audit only → **New** | The user | ON | Immediate | Time, codes left (count) | The codes |
| `security.recovery_codes_regenerated` | New set generated · audit only → **New** | The user | ON | Immediate | Time | The codes |
| `security.sessions_revoked_by_admin` | Admin logs a user out (one/all) · audit only → **New** | The user | ON | Immediate | Time, count, admin's reason | — |
| `security.account_deactivated` / `reactivated` | `UserService` · **New** | The user | ON | Immediate | Who to contact | — |
| `security.role_changed` | Role assigned/revoked, scopes changed · audit only → **New** | The user; super admins digest | ON / OFF | Immediate / Digest | Role names, scope names (department/hall) | Permissions list |
| `security.email_changed` | User email edited · **New** | Old **and** new address | ON | Immediate | New address (masked on the old mail), time | — |
| `user.account_created` | Admin creates a user · **New** | The new user | ON | Immediate | Sign-in URL, role, who created | **Password (never)** |
| `mcp.integration_created` / `revoked` / `stopped_all` | AI integration events · **Exists** | The owner | ON | Immediate | Name, client, access level, reason (revocations) | Token |
| `mcp.integration_expiring` | 7 days before expiry · **Exists** | The owner | ON | Digest | Names, dates | Token |
| `mcp.new_ip` | Integration used from a new IP · **Exists** | The owner | ON | Immediate | Integration name, coarse IP | Token |
| `mcp.global_switch` / `role_switch` / `two_factor.policy_changed` / `rbac.permission_matrix_updated` | Config changed · audit only → **New** | Super admins | OFF | Digest | What changed, by whom | Secrets |

### B. Students and profile

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `student.added` | Student created (panel, MCP, assistant) · **New** | The student | ON | Immediate | Welcome, department, batch, sign-in URL | Password |
| `student.added_admin_copy` | same | Department head | OFF | Digest | Name, roll, batch | — |
| `student.updated` | Department/batch/semester/registration number changed · **New** | The student; admins for registration change | OFF; ON for registration change | Digest | Changed field names | Old/new ID numbers in full |
| `profile.incomplete_reminder` | Profile gate unmet for N days · **New (scheduled)** | The student | OFF | Digest | Missing field names | Field values |
| `profile.completed` | Profile finished · **New** | The student | OFF | Immediate | Confirmation | — |
| `profile.required_fields_changed` | New required field gates students · audit → **New** | Affected students | OFF | Digest | Which field | — |
| `profile.locked_after_clearance` | Name/parents/DOB locked · **New** | The student | ON | Immediate | Which fields lock | Values |
| `hall.assigned` / `vacated` | `HallResidencyService` · **New** | The student; provost copy OFF | ON | Immediate | Hall, room | — |
| `enrollment.enrolled` / `dropped` | `EnrollmentService` · **New** | The student | OFF / ON | Digest / Immediate | Course code and title | — |

### C. Results

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `result.submitted` | Teacher submits an offering · **New** | Department head | ON | Digest | Course, count entered | Marks |
| `result.approved` | Dept head approves · **New** | Publishers (super admin / configured role) | ON | Digest | Course, semester | Marks |
| `result.semester_published` | `publishSemester` · **New** | Students of that semester | ON | Immediate | Semester, link to Results | **Grades, GPA** (opt-in) |
| `grading_scale.changed` | Scale replaced · **New** | Super admins | OFF | Immediate | Who, when | — |
| `portal.exam_catalog_updated` | New exam ids on the portal · **Planned** | Admin office, super admins | ON | Digest | Exam names, department | — |
| `portal.publication_detected` / `confirmed` | Daily check · **Planned** | Admin office, super admins | ON | Immediate | Exam, department, student count | — |
| `student.result_pulled` | Pull saved · **Planned** | The student | ON | Immediate | Exam, semester, year, link | Grades (opt-in) |
| `student.result_pull_failed` | Pull failed after retries · **Planned** | Admin office, dept head | ON (admins) / OFF (student) | Digest | Student name, reason | Registration number |
| `student.grade_changed` | Retake / improved / declined · **Planned** | The student | ON | Immediate | Course code, kind of change | Grade values (opt-in) |
| `system.portal_health_failed` | Portal down / page unrecognised · **Planned** | Super admins | ON | Immediate, throttled 1/6 h | Reason, last good check | — |

### D. Clearance

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `clearance.submitted` / `resubmitted` | Student applies · **Exists** | The student | ON | Immediate | Request no. | — |
| `clearance.waiting` | Request reaches a stage · **Exists** | That stage's approvers | ON | **Digest** (one mail per day listing requests) | Counts, link to Pending | Student contact data |
| `clearance.approved` | A stage approves · **Exists** | The student | ON | Immediate | Stage, request no. | Approver personal data |
| `clearance.rejected` | A stage rejects · **Exists** | The student | ON | Immediate | Stage, the reviewer's remark | — |
| `clearance.ready_for_collection` | All stages done · **Exists** | The student | ON | Immediate | Collection instructions | — |
| `clearance.collected` | Marked collected · **Exists** | The student | ON | Immediate | Request no., date | — |
| `clearance.cancelled` | Cancelled · audit only → **New** | Student; current approver | ON | Immediate | Request no. | — |
| `clearance.printed` / `reprinted` | Print recorded · audit only → **New** | Admin office | OFF | Digest | Request no., duplicate flag | — |
| `clearance.ready_digest` | Daily summary of clearances ready for the office · **Exists** | Admin office | ON | Digest | Count | — |
| `clearance.reminder` | Pending > N days · **Exists** | Current approvers | ON | Digest | Student, days waiting | — |
| `clearance.escalation` | Pending > escalation days · **Exists** | Super admins | ON | Immediate | Request, stage, days | — |
| `clearance.integrity_failed` | Hash-chain verification fails · **New** | Super admins | ON | Immediate | Request no., break position | — |
| `clearance.stage_config_changed` | Stages toggled/moved · **New** | Super admins | OFF | Digest | What changed | — |

### E. Halls, library, dues

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `hall.due_recorded` / `due_settled` | `HallResidencyService` · **New** | The student | ON | Immediate | Description, amount, link | — |
| `library.loan_issued` / `returned` | `LibraryService` · **New** | The student | OFF | Digest | Title, due date | — |
| `library.loan_due_soon` | 2 days before due · **New (scheduled)** | The student | ON | Digest | Title, due date | — |
| `library.loan_overdue` / `fine_recorded` / `fine_settled` | **New** | The student | ON | Immediate | Title, fine amount | — |

### F. Notices and academic operations

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `notice.published` | `NoticeService::create` (audience: all / department / hall) · **New** | Matching students/staff | OFF | Digest | Title, link | Full body of restricted audiences |
| `course.teachers_assigned` | Audit exists · **New mail** | The teacher | ON | Immediate | Course, semester | — |
| `routine.generated` | Routine generated · audit only → **New** | Department staff | OFF | Digest | Semester, department | — |
| `semester.activated` | `SemesterService::setActive` · **New** | Admin office | OFF | Immediate | Semester | — |

### G. System and operations

| Event key | When / status | Recipient | Default | Mode | Contains | Excluded |
|---|---|---|---|---|---|---|
| `system.scheduled_task_failed` | A scheduled command exits non-zero · **New** (`onFailure` hooks) | Super admins | ON | Immediate, throttled | Command, time, short error | Stack trace |
| `system.queue_job_failed` | Job lands in `failed_jobs` · **New** | Super admins | ON | Digest (hourly) | Job class, count, first error line | Payload (contains PII) |
| `system.email_delivery_failing` | N consecutive delivery failures · **New** | Super admins | ON | Immediate, throttled | Failure count, last error | — |
| `system.mcp_denied_spike` | Many denied tool calls · **New** | Super admins | OFF | Digest | Counts per user/role | Arguments |

## Deliberately **no** email

Successful logins, TOTP success, every MCP/assistant tool call, assistant messages and confirmations, read-only
operations, clearance verify page hits, and hash-chain verification **success** — high volume, no action needed; they
stay in the audit log.

## Architecture (extends what exists)

1. **Registry** `config/notification_events.php`: one entry per event key — category, label, default enabled, default
   mode, default recipient rules, allowed placeholders, sensitivity notes. Seeds the DB.
2. **Emit** through one dispatcher, `NotificationEvents::emit($key, $subject, $context, $dedupeKey)`, called from
   services (service-backed) or model observers (Filament-only CRUD). Existing `InAppNotification`s gain an
   `eventKey()`; the in-app bell stays always on.
3. **Outbox** `outbox_events` written **in the same DB transaction** as the change. A dispatcher job (every minute and
   after commit) turns rows into deliveries, so a crash cannot lose an event.
4. **Rules** `notification_rules` (event key, enabled, mode, recipients, template). Recipient kinds: affected user,
   department head of the affected department, hall provost, role(s), named users. Department boundaries are enforced
   when resolving.
5. **Templates** `email_templates` (subject/body with whitelisted placeholders only, versioned) with preview and
   "send test to me".
6. **Deliveries** `email_deliveries` (unique dedupe key, status queued/sent/failed/skipped, attempts, last error);
   `SendEmailDelivery` job with retries and backoff; `MailMessenger` replaces `LogMessenger` when a mailer is
   configured (dev stays on `log`). Digests are built from queued rows per recipient.
7. **Admin UI** "Email notifications" under Security & Access: events grouped by category with enable switch, mode,
   recipients and template; a Deliveries tab with filters, failures and retry. Permissions:
   `notification_rule:manage` (super admin), `email_delivery:view` / `retry` (super admin, admin office; department
   heads see their own department's deliveries).
8. **Safety**: placeholder whitelist (no raw model dumps), a test that scans every rendered template for secret
   patterns, dedupe on `event + recipient + subject + fingerprint`, per-recipient rate cap.
9. **Tests**: emission per event, recipient resolution incl. department scoping, rules on/off, template rendering and
   redaction, dedupe on retried jobs and repeated scheduled runs, failure → retry → success, outbox recovery after a
   simulated crash, digest assembly, MCP/permission checks for the admin screens.

## Operations (as built)

**Where things are**

| What | Where |
|---|---|
| Event registry (defaults) | `config/notification_events.php` |
| Pipeline settings | `config/notifications.php`, `.env`: `NOTIFICATION_DRIVER` (`log` = write to the log, `mail` = send through `MAIL_*`), `NOTIFICATIONS_ENABLED`, `NOTIFICATION_DIGEST_TIME` |
| Emit an event | `app(NotificationEvents::class)->emit($key, $context, $dedupeKey, $user, $departmentId, $hallId, $extraEmails)` |
| Admin screens | Security & Access → **Email notifications**, **Email templates**, **Email deliveries** |
| MCP tools | `notification_rule_list/update/reset`, `notification_preview`, `notification_test_send`, `email_template_*`, `email_delivery_list/retry/retry_failed` |
| Permissions | `notification_rule:view/manage`, `email_template:manage`, `email_delivery:view/retry` (super admin all; admin office view + retry) |

**Turning real email on:** set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, then `NOTIFICATION_DRIVER=mail`. Run a queue worker (`php artisan queue:work`, included in `composer run dev`) and the scheduler (`php artisan schedule:work` or the cron entry). Use **Send test to me** on any event first.

**How it flows:** module → `emit()` → `outbox_events` row (same DB transaction as the change) → `ProcessOutbox` job (also every minute) → rule check → recipients → rendered text → `email_deliveries` (unique dedupe key) → `SendEmailDelivery` (5 tries, backoff 1/5/15/60 min) → mail. Digest events wait in `held` and go out as one email per recipient at `NOTIFICATION_DIGEST_TIME`.

**Behaviour worth knowing**
- A switched-off event writes nothing at all (so turning it on later is not retroactive).
- Seeders and `phpunit.xml` switch the whole system off (`NOTIFICATIONS_ENABLED=false`); demo users are never emailed.
- No email is sent to someone without a valid address: it is recorded as **skipped** with the reason.
- An email whose text looks like a token or password is **blocked**, not sent.
- Delivery is at-least-once: a crash between sending and saving "sent" could repeat one email.

**Recovery**
- App crashed after saving a change: the event is in the outbox; `php artisan notifications:process-outbox` (scheduled every minute) turns it into emails. Nothing to do.
- Mail provider down: deliveries retry on their own, then show as **failed**; fix the provider, then **Retry all failed** (or the MCP tool). Three failures within an hour raise one "Emails are failing" event.
- Stuck outbox rows (after 5 attempts they stop): see `outbox_events.last_error`; fix the cause and set `attempts` to 0.
- Missing rule rows after adding events: `php artisan notifications:sync-rules` (never overwrites edits).
