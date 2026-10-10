# Presentation kit

A ready outline for presenting the whole application. Each slide lists the message, the points to say and the screenshot to use (files in `docs/screenshots/<role>/<name>.png`; see `README.md` for how they are generated). A 10-slide version and a live-demo script follow.

## The story in one sentence

*One system where every person sees exactly their part of the college — and where results, clearance, security and email are automatic, auditable and AI-ready.*

## Full deck (24 slides)

| # | Slide | Say this | Screenshot |
|---|---|---|---|
| 1 | **Title** — FEC ERP | People · Results · Clearance · Security · AI, in one panel | `public/login` |
| 2 | **Why** | Results live on another site, clearance is paper and signatures, access is ad hoc, nothing is auditable | — |
| 3 | **What it is** | Laravel 12 + Filament 5 panel, 9 roles, 50 screens, 130 AI tools, ~70 email events, 72 tables, 690+ tests | `super_admin/dashboard` |
| 4 | **Who sees what** | One central authorizer: roles + scope (department, hall, own courses, self). Same rules for web, MCP and assistant | `super_admin/roles` |
| 5 | **Dashboards and look** | Role-specific dashboards, 6 themes, dark mode | `super_admin/appearance`, `student/dashboard`, `department_head/dashboard` |
| 6 | **People and organisation** | Students (registration no., admission year), teachers, staff, departments, programs, batches, semesters, courses | `super_admin/students`, `super_admin/student_create`, `super_admin/batch_overview` |
| 7 | **Student profile gate** | 16 configurable fields + photo; everything is locked until complete; fields lock after clearance is requested | `student_incomplete/profile_gate`, `student/profile`, `super_admin/profile_fields` |
| 8 | **Results and CGPA** | draft → submitted → approved → published; students only see published; best attempt counts; editable grading scale | `teacher/result_entry`, `student/results`, `super_admin/grading_scale` |
| 9 | **Results from the university portal** | Add a student → results are pulled automatically; the parser reads real pages; only the relevant exam years are asked (2022 → 2022-2026) | `student/results` (portal section) |
| 10 | **Tracking every pull** | Success / Failed / Waiting / Skipped with the reason, retry, department-scoped | `super_admin/student_results`, `department_head/student_results` |
| 11 | **Retake and improvement** | Old grades kept, new grade replaces it and is marked Retake / Improved / Declined with the previous grade | `student/results` |
| 12 | **Knowing when results are published** | Daily list check, probe students confirm, shadow mode, late-result re-checks, health alerts | `super_admin/portal_monitor` |
| 13 | **Clearance journey** | Eligibility rules, apply, hall → library → department → head, reject / resubmit, collected | `student/clearance_apply`, `student_clearance_waiting/my_clearance`, `student_clearance_rejected/my_clearance`, `student_clearance_ready/my_clearance` |
| 14 | **Clearance for approvers** | Waiting-for-me queue with dues and loans; scoped to hall / department | `hall_provost/clearance_pending`, `librarian/clearance_pending`, `department_head/clearance_pending`, `head_of_institution/clearance_pending` |
| 15 | **Proof you can trust** | Signatures, QR verification page, SHA-256 hash chain, DUPLICATE watermark, print log | `admin_office/clearance_print`, `public/clearance_verify`, `admin_office/clearance_desk` |
| 16 | **Halls, library, notices** | Residency, dues, loans and fines feed clearance; scoped notices | `hall_provost/hall_residents`, `librarian/library_loans`, `super_admin/notices` |
| 17 | **Routine and exams** | Auto-generate routines, assign teachers, master / individual / credit / exam-duty reports with PDF and Excel | `super_admin/routine`, `super_admin/master_routine`, `super_admin/exam_duty_report` |
| 18 | **Account security** | Revocable sessions, new-device alert, TOTP 2FA, recovery codes, trusted devices, mandatory 2FA per role | `two_factor/challenge`, `student/devices`, `super_admin/two_factor_policy` |
| 19 | **Audit** | Every write: who, which channel (web / MCP / assistant / system), before / after | `super_admin/audit_log` |
| 20 | **AI-ready: MCP server** | 130 tools, role-filtered, dry-run + confirm, idempotency; users create tokens with 2FA; admins oversee | `two_factor/ai_wizard`, `super_admin/mcp_integrations` |
| 21 | **In-app assistant** | Finds features with deep links, performs actions only after a confirmation card, masks personal data | `student/assistant` |
| 22 | **Email notifications** | ~70 events, rules, templates, digests, history, retries, privacy guard, no code change needed | `super_admin/email_notifications`, `super_admin/email_templates`, `super_admin/email_deliveries` |
| 23 | **Reliable and tested** | Outbox, queue retries, scheduler with failure alerts; Unit 86 · Feature 495 · MCP 82 · Browser 31; accessibility checks; CI | — |
| 24 | **What is next** | Password-reset flow, scope picker for custom roles, coverage report, official data feed from the university | — |

## 10-slide version

1 Title · 2 Problem and goals · 3 Roles and one authorization layer · 4 Student journey (profile → results → clearance) · 5 Results + official portal sync with retake tracking · 6 Clearance with proof (QR, signatures, hash chain) · 7 Security (sessions, 2FA) and audit · 8 AI: MCP + assistant · 9 Email notifications · 10 Quality and roadmap.

## Must-not-miss features (one line each)

1. Single authorization layer shared by screens, AI tools and the assistant.
2. Student profile gate with configurable required fields.
3. Result workflow (draft → published) and a pure, tested CGPA engine with an editable grading scale.
4. **Automatic result pull from the university portal** when a student is added, with a success / failed tracker.
5. Daily **publication detection** with probe students and shadow mode.
6. **Retake / improvement** marking (Retake · Improved · Declined) with history.
7. Admission-year window (about 8 portal requests per student instead of 74).
8. Configurable **clearance chain** with scoped approvers, reminders and escalation.
9. Signed, QR-verifiable certificate with SHA-256 **hash chain** and DUPLICATE reprints.
10. Revocable sessions, TOTP 2FA, recovery codes, trusted devices, mandatory 2FA per role, instant revocation on open pages.
11. Roles & permissions editable in the UI; permission matrix; row-level scope.
12. Full audit log with channel.
13. **MCP server with 130 tools**, integration wizard (2FA required, hashed token, expiry, read-only), admin oversight.
14. In-app **assistant** with confirmation cards and PII masking.
15. **~70 email events** with rules, templates, digests, delivery history, retries and a secret guard.
16. Routine auto-generation and PDF / Excel reports.
17. Hall dues and library fines that feed clearance.
18. Scheduler with failure alerts; outbox that survives crashes.
19. 690+ automated tests incl. real-browser journeys and accessibility checks; CI.
20. Decision log (27 decisions) and independent review.

## Live demo script (about 8 minutes)

Prepare: `php artisan seed:demo --fresh`, `php artisan serve`, open `http://127.0.0.1:8000`. Password for all: `password`.

1. **Student** `student.eligible@fec.test` — Results: CGPA, published semesters, official portal results with a *Retake* badge. Open the assistant (bottom right): "where are my results".
2. **Student** `student.incomplete@fec.test` — every page redirects to the profile; fill it (live validation).
3. **Clearance** — as `student.eligible@fec.test` apply (Clearance → Apply); then `provost.bjh@fec.test` → *Waiting for me* → approve; `librarian@fec.test`, `head.cse@fec.test`, `head@fec.test` in turn; the student sees the timeline update.
4. **Office** `office@fec.test` — Clearance desk → print (note signatures, QR, hash); reprint shows DUPLICATE; scan / open `/verify/clearance/{code}`.
5. **Portal monitor** — super admin `superadmin@fec.test`: Portal monitor and Student results (success / failed / waiting), *Retry*.
6. **Email** — Security & Access → Email notifications: toggle an event, *Preview*, *Send test to me*; Email deliveries.
7. **Security** — My Account → Devices (log out another device); Two-factor policy.
8. **AI** — `mfa.teacher@fec.test` (secret in `OPERATIONS.md`): AI Integrations wizard; show the snippet; then MCP tools list in the client.
9. **Admin** — Roles & permissions (edit a role), Audit log (filter by channel `mcp` / `assistant`).

## Likely questions

- *Can the AI do anything a user cannot?* No — it runs as the user with the same permission and scope; writes need confirmation.
- *Does it scrape the university site?* It looks up only students that exist in the ERP, one request at a time with pauses, and never enumerates registration numbers; an official data feed can replace it (`ResultSource` interface).
- *What if the portal changes?* Pulls fail visibly (nothing wrong is stored), the raw pages are kept, and a parser fix re-reads them.
- *What if email is down?* Events are stored first; retries with back-off; admins see failed emails and retry.
- *Is student data safe in emails?* No passwords, tokens or grades; a guard blocks credential-like text.
- *What is missing?* A password-reset page, a scope picker for custom roles, coverage reporting (see `README.md` → Known gaps).
