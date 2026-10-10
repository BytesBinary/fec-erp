# FEC ERP — complete feature list

A college ERP built on **Laravel 12, Filament 5 (admin panel), Livewire 4, Pest 4**, with an AI layer (MCP server and in-app assistant), official result sync from the university portal and a full email notification system. This document lists **every feature** of the application, grouped by module, with where to find it, who can use it and which screenshot shows it. Screenshots are generated locally into `docs/screenshots/` (not tracked by git, see `docs/README.md`).

## At a glance

| | |
|---|---|
| Roles | 9 (super admin, administration office, head of institution, principal, department head, hall provost, librarian, teacher, student) + 4 legacy roles |
| Menu items | 50 (resources, pages and reports) in 8 groups, filtered per role |
| MCP tools | 130 (59 read-only, 71 write, 18 destructive with dry-run) |
| Email events | ~70, each configurable from the admin panel |
| Scheduled jobs | 8 daily / per-minute jobs |
| Database tables | 72 (migrations) |
| Automated tests | Unit 86 · Feature 495 · MCP 82 · Browser (real browser, 10 files) 31 — all green in `.autopilot/verify.sh` |
| Public pages | `/login`, `/verify/clearance/{code}` (certificate verification) |

---

## 1. Platform and everyday use

| Feature | Details |
|---|---|
| **Admin panel at `/`** | Filament panel with sidebar navigation, collapsible on desktop, grouped as People · Routine · Manage Exams · Academic · Clearance · Campus · Settings · My Account · Security & Access. Each user sees only the menu items their role may use. |
| **Role dashboards** | Widgets: Welcome, *Clearance waiting for me* (count per approver), Stats overview (students, teachers, staff, departments, exam duties), Quick actions (add student / teacher / staff, new exam duty, manage routine), Recent students, Recent exam duties. Shield-guarded: only roles with the widget permission see them. |
| **Themes and dark mode** | 6 colour presets (Forest & Ochre, Ocean Blue, Royal Plum, Crimson Clay, Midnight Indigo, Emerald), per user, plus light / dark mode. |
| **Institution settings** | Institution name and logo used in the header and on printed documents. |
| **Accessible by design** | Axe accessibility checks run in the test suite on the new screens (contrast, labels, ARIA). |
| **Print and export** | Result sheet, clearance certificate (HTML + PDF), routine / exam duty / credit reports as PDF and Excel. |
| **Notification bell** | In-app notifications for every important event (also mirrored to email, see section 12). |

Screenshots: `*__dashboard`, `super_admin__appearance`, `super_admin__institution_settings`.

## 2. People and organisation

| Feature | Where | Who |
|---|---|---|
| **Students** — create, edit, search, filter by department / program / batch / semester; fields: name, email, roll, **registration number**, department, program, batch, **admission year**, current semester, phone | People → Students | admin office, super admin; department head (own department, read) |
| **Teachers** and **Staff** — records linked to a user account and a designation | People → Teachers / Staff | admin office, super admin |
| **Designations** — separate lists for teacher and staff designations | Settings → Designations | super admin |
| **Users** — accounts of every kind, activate / deactivate, assign roles with scope, sessions tab | Security & Access → Users | super admin |
| **Departments, Programs, Semesters** (one active semester) | Academic | super admin; read for others |
| **Batches** — cohort per department with session (e.g. 2022-2023), batch overview and per-batch detail with semester promotion | Academic → Batches | admin office, department head |
| **Courses** — catalog with code, credits, type (theory / lab), semester number, archive / unarchive, assign teachers | Academic → Courses | department head (own department), super admin |
| **Course offerings and enrollment** — a course offered in a semester, enroll / bulk-enroll / drop students, regular / retake / improvement attempts | via enrollment screens, MCP tools | admin office, department head |
| **Welcome emails** — a new student or staff account triggers a welcome email (never a password) | automatic | — |

Screenshots: `super_admin__students`, `__student_create`, `__teachers`, `__staff`, `__departments`, `__programs`, `__semesters`, `__courses`, `__batches`, `__batch_overview`, `__users`.

## 3. Student profile gate

A student must complete the profile before using the system; every page redirects (and every API / MCP call answers `PROFILE_INCOMPLETE`) until it is complete.

- 16 profile fields (name as on certificate, parents, date of birth, phone, email, addresses, **photo** 35:45, guardian, blood group, NID / birth registration, hall, emergency contact …).
- **Which fields are required is configurable** by the super admin (Settings → Required profile fields); adding a required field gates students again.
- Progress bar and a precise list of what is missing; per-field validation; residential students must pick a hall.
- After a clearance request is submitted, **name, parents and date of birth lock** (the office can still edit).
- Reminder email for unfinished profiles (optional, weekly).

Screenshots: `student_incomplete__profile_gate`, `student__profile`, `super_admin__profile_fields`.

## 4. Results, CGPA and transcripts

| Feature | Details |
|---|---|
| **Marks entry** | Teachers enter marks for the roster of their own course (Academic → Result entry). Letter grade and grade point are computed from the live grading scale. |
| **Approval workflow** | draft → **submitted** (teacher; all students need marks) → **approved** (department head) → **published** (authorised publisher). Marks lock after submission. Students see only *published* results — in the panel, in print, over MCP and in the assistant. |
| **Grading scale** | Editable bands (seeded with the Bangladesh UGC scale: A+ 80-100 → 4.00 … F 0-39.99 → 0.00) at Settings → Grading scale. |
| **CGPA engine** | Pure, tested calculator: best attempt of a repeated course counts (configurable `best` / `latest`), a failed course counts as 0.00 with its credits, half-up rounding to 2 decimals, per-semester GPA and cumulative CGPA. |
| **Student result page** | Published semesters with every course, grade and point, semester GPA, CGPA, earned credits, and a printable **result sheet** (`/results/print`). |
| **Retakes and improvements** | Enrollments with attempt type; the transcript and CGPA use the same attempt ordering. |
| **Publish preview** | Dry-run of what publishing a semester would change (MCP). |
| **Emails** | Students are told when their semester is published (grades are not in the email); department heads get a digest of results waiting for approval. |

Screenshots: `student__results`, `student__results_print`, `teacher__result_entry`, `super_admin__grading_scale`.

## 5. Official results from the university portal (DU exam portal)

Results are published on a different website (the university's controller-of-examinations portal). The ERP pulls them automatically, saves them and tracks every pull.

| Feature | Details |
|---|---|
| **Auto-pull when a student is added** | Adding a student from the panel, an MCP tool or the assistant queues a background pull. A changed registration number pulls again. |
| **Student results screen** (Academic → Student results) | Every pull as *Success / Failed / Waiting / Skipped* with the reason, status filters, retry, **Retry all failed**. A department head only sees their own department. |
| **Real parser** | Built from real portal pages: header table, subjects by position, outcome (Promoted / Imp.), GPA, CGPA, backlog subjects; messy course codes normalised; an F with a blank point = 0.00; anything unknown fails loudly (nothing wrong is stored). |
| **Admission-year window** | A student is only checked against exams from the admission year to +6 years, never beyond the current year (2022 → 2022..2026), and not against other batches' exams: ~8 requests instead of 74. |
| **Retake / improvement tracking** | Old grades are kept; the newest attempt becomes current and is marked **Retake** (previous grade F), **Improved** or **Declined** with the previous grade — visible to the student and in MCP. A setting switches to "keep the better grade". |
| **Exam catalog** | The portal's exam list for CSE, EEE and Civil is saved (by year, kind, semester) — *First-time sync* button. |
| **Daily publication detection** | Every day (06:00) the saved list is compared with the portal; a new exam id is a candidate; **probe students** (best CGPA for regular exams, students with failing grades for improvement exams) confirm the results are really visible; then all eligible students are pulled. Portal errors or unknown pages never count as "not published". |
| **Late results** | Students with no result yet are re-checked after 3 and 7 days, then closed as "not in this exam". |
| **Shadow mode** | Default on: publications are detected and confirmed but nothing is pulled or emailed until an admin presses **Run now**. |
| **Portal monitor** (Academic → Portal monitor) | Last and next check, exams by department and year, detected publications with *Run now*, probe results, recent runs, student-pull counts, *First-time sync* and *Check for new results* buttons. |
| **Stored evidence** | Per student and exam: roll, outcome, GPA, CGPA, backlog, publication date and the raw page (private disk) so a parser fix can re-read it without asking the portal again. |
| **Politeness** | 2-second pacing, one student at a time, no enumeration of registration numbers. |
| **MCP + emails** | 4 + 4 MCP tools; emails for new exams, publications, a student's result, grade changes, failures and portal problems. |

Screenshots: `super_admin__portal_monitor`, `super_admin__student_results`, `admin_office__student_results`, `student__results` (with Retake badges).

## 6. Clearance (graduation / leaving certificate workflow)

| Feature | Details |
|---|---|
| **Eligibility check** | Profile complete, account active, no other active request, published results earn the program's required credits, no unresolved failed course (D-018). The student sees the exact reasons. |
| **Apply** | One click creates request `CLR-YYYY-NNNNNN` and starts the chain. |
| **Configurable approval chain** | Stages (default **hall → library → department → head**): order, approver role, scope rule, active, skippable — editable at Settings → Clearance stages. Non-residents skip the hall stage. |
| **Scoped approvers** | An approver may only act if they hold the stage's role **and** that role's scope covers the student (hall provost of the student's hall, head of the student's department …) (D-019). |
| **Approve / reject with remarks, resubmit, cancel** | Rejection returns the request to the student with the reason; they fix it and resubmit. Optimistic version lock prevents double actions. |
| **Waiting-for-me queue** | Each approver sees requests at their stage, with dues / loans of that stage visible (hall dues, library loans and fines). |
| **Clearance desk** | Administration office: search / filter all requests, print, reprint, hand over. |
| **Certificate print and PDF** | Print view with institution header, student photo, approver names and **signatures**, QR code; reprints carry a **DUPLICATE** watermark unless the super admin overrides; every print is logged. |
| **Public verification** | `/verify/clearance/{code}` shows whether a certificate is genuine (QR points here) without login. |
| **Tamper evidence** | Every approval is part of a **SHA-256 hash chain** (including the signature image hash); verification detects any edit; a break alerts the super admins. |
| **Hand-over** | "Collected" is recorded with an ID-verified flag. |
| **Reminders and escalation** | After 3 days a waiting stage's approvers are reminded; after 7 days the super admins are told; the office gets a digest of requests ready. |
| **Notifications** | Student: submitted, approved, rejected, ready, collected, cancelled; approvers: waiting; admins: escalation, integrity failure. |

Screenshots: `student__clearance_apply`, `student_clearance_waiting__my_clearance`, `student_clearance_rejected__my_clearance`, `student_clearance_ready__my_clearance`, `*__clearance_pending`, `admin_office__clearance_desk`, `admin_office__clearance_request`, `admin_office__clearance_print`, `public__clearance_verify`, `super_admin__clearance_stages`, `hall_provost__clearance_pending`, `librarian__clearance_pending`, `department_head__clearance_pending`, `head_of_institution__clearance_pending`.

## 7. Halls, library and notices

| Feature | Details |
|---|---|
| **Halls** | Hall records; hall residency per student (hall, room, from / to). |
| **Hall residents** (Campus → Hall residents) | Provost assigns / vacates students, records and settles **dues**; the student is emailed. |
| **Library loans** (Campus → Library loans) | Librarian issues books with due dates, records returns with fines, settles fines. Emails: due soon, overdue, fine recorded / settled. |
| **Dues and fines feed clearance** | Open dues / fines show on the approver's screen and block the stage. |
| **Notices** | Publish notices for everyone, a department or a hall (department / hall notices need that scope); drafts and scheduled publication; optional email digest. |

Screenshots: `hall_provost__hall_residents`, `librarian__library_loans`, `super_admin__halls`, `super_admin__notices`.

## 8. Routine and exam management (original modules)

| Feature | Details |
|---|---|
| **Routine** | Weekly class routine per batch with period slots; manage slots with dropdown assignment; **auto-generate** a routine from course credits and teacher availability; clear slots. |
| **Assign teachers** | Assign teachers to courses, with **auto-assign**. |
| **Reports** | Master routine, individual (teacher) routine, credit count report, exam duty report — each with on-screen view, **PDF** and **Excel** download. |
| **Exam types / halls / duties** | Maintain exam types and halls; create exam duties; view and download duty details. |
| **Quick actions** | Dashboard shortcuts for the most common office tasks. |

Screenshots: `super_admin__routine`, `__master_routine`, `__individual_routine`, `__assign_teachers`, `__credit_count_report`, `__exam_types`, `__exam_halls`, `__exam_duties`, `__exam_duty_report`.

## 9. Account security

| Feature | Details |
|---|---|
| **Server-side sessions** | Every login is a record you can see and revoke: device, browser, IP, last activity. 12 hours inactivity (30 days with *remember me*). A revoked session is rejected on its very next request — redirect for pages, 401 for JSON, 419 for Livewire (so an open page reloads to login). |
| **Devices page** (My Account → Devices) | See this and other devices, **log out another device**, log out all others, optionally stop AI integrations too. |
| **New-device alert** | Email + bell when an unseen browser signs in. |
| **Two-factor authentication (TOTP)** | QR code + manual key; confirm with a code; works with any authenticator app; secret stored encrypted; replay protection; ±1 step drift; **lockout** after 5 wrong codes for 15 minutes with notification. |
| **Recovery codes** | 10 single-use codes (hashed), warning when fewer than 3 are left, regenerate with a current code. |
| **Trusted devices** | Skip the challenge for 30 days on a device; trust is dropped when that session is logged out. |
| **Mandatory 2FA per role** | Super admin policy page: roles that must use 2FA are forced through setup after login. |
| **Password change** | Signs out every other device and tells the user how many. |
| **Admin tools** | Super admin: view a user's sessions, revoke one or all with a reason, **reset 2FA** (user signed out everywhere and notified). All audited. |
| **Middleware chain** | session tracking → authentication → 2FA challenge → role 2FA setup → profile gate, applied to pages **and** every Livewire update. |

Screenshots: `two_factor__challenge`, `two_factor__enabled`, `student__devices`, `student__two_factor`, `super_admin__two_factor_policy`, `super_admin__devices`.

## 10. Access control, roles and audit

| Feature | Details |
|---|---|
| **One central authorizer** | Every screen, MCP tool and assistant action calls the same permission + scope check. |
| **Roles & permissions screen** | Create and edit roles and permissions in the UI (Security & Access → Roles & permissions); changes apply immediately. |
| **Scoped roles** | department_head → departments, hall_provost → halls, teacher → own courses, student → self; set when assigning the role. |
| **Permission matrix** | Edit what each role can do (screen and MCP tool); defaults are seeded and never overwritten. |
| **Audit log** | Append-only log of every write: actor, **channel** (web / MCP / assistant / system), entity, before / after; searchable by actor, channel, action, entity, date. Secrets are never stored. |
| **Row-level safety** | Students cannot read other students' data or unpublished results; hash-chained clearance; revoked sessions rejected instantly. |

Screenshots: `super_admin__roles`, `super_admin__users`, `super_admin__audit_log`.

## 11. AI: MCP server and in-app assistant

### MCP server (`POST /mcp`, stdio)
- **130 tools** across 12 domains (full catalog: `docs/MCP_TOOLS.md`), role-filtered, with strict schemas, structured output, pagination, **dry-run + `confirm=true`** for destructive tools, **idempotency keys** for safe retries, audit with channel `mcp`.
- Anything a user can do in the panel can be done through a tool, with the same permission and scope. A **parity test** fails if a new service method is neither a tool nor listed as excluded.
- Resources and prompts for AI clients.

### AI integrations (My Account → AI Integrations, setup wizard)
- Per-user **integration tokens** (`erpmcp_…`, stored as SHA-256, shown once), expiry 30 / 90 / 180 / 365 days (never-expire only if the admin allows), max 5 per user, **read-only** or full access.
- **2FA is mandatory** for creating a token and enforced on every request.
- Ready-to-paste connection snippets for Claude Desktop, Claude Code, Cursor, VS Code and other clients.
- Instant **Stop** / stop all; expiry warnings 7 days ahead; new-IP alert; disabling 2FA stops all integrations.
- **Admin oversight** (Security & Access → MCP integrations): every user's integrations, revoke with reason, global on/off, per-role on/off, usage overview (calls and denied calls per day).

### In-app assistant (floating widget on every page)
- Answers "where is …?" with the menu path and a **deep link**, using a generated **feature index** of all 50 menu items.
- Can **perform actions** through the same MCP tools — every write shows a **confirmation card** first; destructive ones preview the dry-run.
- Privacy: national ID and phone numbers are masked before anything reaches the model; tool results are wrapped as data (prompt-injection defence); per-user rate limit.
- Works without an API key (falls back to feature search); uses Claude when `ANTHROPIC_API_KEY` is set.

Screenshots: `student__assistant`, `two_factor__ai_integrations`, `two_factor__ai_wizard`, `super_admin__mcp_integrations`.

## 12. Email notifications

| Feature | Details |
|---|---|
| **~70 events** in 8 categories (account & security, students & profile, results, result portal, clearance, halls & library, notices & academic, system) — full matrix in `docs/EMAIL_EVENTS.md`. |
| **Event rules screen** (Security & Access → Email notifications) | Per event: on / off, immediate or daily digest, recipients (the person, department head of their own department, hall provost, any role, extra addresses), template; **Preview** with sample values; **Send test to me**; reset to defaults. |
| **Templates** | Write extra subjects and bodies; only the event's own placeholders are allowed; credential-like text is rejected. |
| **Delivery history** (Email deliveries) | Every email: sent, queued, held (digest), failed (with error), skipped (no address), blocked; filters; **Retry** and **Retry all failed**. |
| **Reliable by design** | Events are saved with the change (transactional outbox), then processed by a queue with retries and back-off; a unique key stops duplicates when jobs retry or scheduled scans run twice; recovery after a crash is automatic. |
| **Privacy** | Never contains passwords, tokens, 2FA secrets, recovery codes, national ID or grades (students sign in to see them); a guard blocks credential-shaped text. |
| **Digests** | High-volume events are combined into one email per person per day. |
| **Time-based scans** | Library due soon / overdue, profile reminders, spike of denied AI tool calls. |
| **System alerts** | Failed scheduled tasks, failed queue jobs, failing email delivery, portal not working. |
| **MCP** | 12 tools for the same operations. |

Screenshots: `super_admin__email_notifications`, `__email_templates`, `__email_deliveries`, `admin_office__email_deliveries`.

## 13. Operations, quality and tooling

| Feature | Details |
|---|---|
| **Scheduler** | `mcp:notify-expiring` 08:00 · `clearance:remind-pending` 08:30 · `sessions:prune` 03:00 · `portal:check` 06:00 · `portal:recheck-pending` 07:00 · `notifications:process-outbox` every minute · `notifications:send-digests` 07:30 · `notifications:scan` 08:15. Failures raise an alert. |
| **Artisan commands** | `seed:test`, `seed:demo` (refuses in production), `portal:sync-catalog`, `portal:check`, `portal:recheck-pending`, `notifications:*`, `mcp:start`. |
| **Seeders** | Deterministic test dataset (one user per role, 5 named students in known states, 2FA user, MCP token) and an "alive" demo dataset (≈35 students, results, notices, loans, dues, clearances in every state). |
| **Verification script** | `.autopilot/verify.sh`: dependencies → build → migrate + seed on a throw-away database → Pint → Unit → Feature → MCP → Browser; used by CI (`.github/workflows/tests.yml`). |
| **Browser (E2E) tests** | Real-browser journeys: login, profile gate and results, clearance end-to-end, devices, 2FA, MCP integrations, assistant, accessibility. |
| **Documentation** | Architecture notes, 27 recorded decisions, independent review, MCP guide, this catalog. |

---

## Must-not-miss highlights (for the presentation)

1. **One authorization layer for everything** — web, MCP and the assistant obey the same roles and scopes.
2. **Student journey end to end** — profile gate → published results with CGPA → clearance with live timeline → verified certificate.
3. **Clearance with proof** — signatures, QR verification page, SHA-256 hash chain, DUPLICATE watermark.
4. **Official results sync** — automatic pull when a student is added, daily publication detection, retake / improvement tracking, success / failed tracking.
5. **AI-ready** — 130-tool MCP server and an in-app assistant that asks for confirmation before it writes.
6. **Security first** — server-side sessions you can revoke, TOTP 2FA with recovery codes, trusted devices, mandatory 2FA per role, instant revocation even on open pages.
7. **Admin-configurable without code** — roles & permissions, required profile fields, grading scale, clearance stages, email rules and templates, themes.
8. **Email system** — ~70 events, rules, templates, digests, delivery history, retries, privacy guard.
9. **Everything is audited** — who did what, through which channel.
10. **Tested** — 690+ automated tests including real-browser journeys and accessibility checks.
