# Installation, configuration and operations

## Requirements

- PHP 8.4+ (developed on 8.5) with the usual Laravel extensions, `gd` (clearance PDF images) and `intl`
- MySQL 8 (development) — SQLite is used by the tests
- Node 22 + npm (Vite, Tailwind 4); Chromium for the browser tests (`npx playwright install --with-deps chromium`)
- A queue worker and the Laravel scheduler (both are needed for pulls and email)

## First install

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate     # then set DB_* (database fec_erp)
php artisan migrate --force
php artisan db:seed                                   # roles, permissions, departments, courses, sample academics
php artisan storage:link
```

Development with everything running (server, queue worker, logs, Vite): `composer run dev`.
Demo data with a login for every role: `php artisan seed:demo --fresh` (**drops all tables**; refuses in production without `--force`; never run against real data).

Production checklist: `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, `php artisan config:cache route:cache view:cache event:cache`, a process manager for `php artisan queue:work`, and a cron entry `* * * * * php /path/artisan schedule:run`.

## Demo logins (after `seed:demo`; password `password`)

| Role | Email |
|---|---|
| Super admin | superadmin@fec.test |
| Administration office | office@fec.test |
| Head of institution | head@fec.test |
| Principal | principal@fec.test |
| Department head (CSE / EEE) | head.cse@fec.test / head.eee@fec.test |
| Hall provost (BJH / SKH) | provost.bjh@fec.test / provost.skh@fec.test |
| Librarian | librarian@fec.test |
| Teacher | teacher@fec.test |
| Students | student.eligible@fec.test, student.nonresident@fec.test, student.libraryloan@fec.test, student.unfinished@fec.test, student.incomplete@fec.test |
| Teacher with 2FA (test fixture) | mfa.teacher@fec.test (TOTP secret `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP`) |

## Environment settings

| Setting | Default | Purpose |
|---|---|---|
| `DB_*` | MySQL `fec_erp` | Database |
| `QUEUE_CONNECTION` | `database` | Queue for pulls, emails, outbox |
| `SESSION_DRIVER` | `database` | Sessions (plus the server-side `user_sessions` records) |
| `MAIL_*` | `MAIL_MAILER=log` | Mail transport |
| `NOTIFICATION_DRIVER` | `log` | `log` writes emails to the log, **`mail` sends them** |
| `NOTIFICATIONS_ENABLED` | `true` | Master switch of the email pipeline (the bell stays on) |
| `NOTIFICATION_DIGEST_TIME` | `07:30` | When digests are sent |
| `RESULT_PORTAL_ENABLED` | `true` | Result portal sync on/off |
| `RESULT_PORTAL_URL` | `https://cmc.du.ac.bd` | Portal base URL |
| `RESULT_PORTAL_DELAY_MS` | `2000` | Pause between portal requests |
| `RESULT_PORTAL_REPLACE_POLICY` | `always` | `always` replaces a grade with the newest attempt, `better` keeps the better one |
| `RESULT_PORTAL_SHADOW` | `true` | Shadow mode: detect and confirm publications but pull / email nothing until *Run now* |
| `RESULT_PORTAL_CHECK_TIME` | `06:00` | Daily check time |
| `ANTHROPIC_API_KEY`, `ASSISTANT_PROVIDER`, `ASSISTANT_MODEL` | provider `claude` | In-app assistant (without a key it falls back to feature search) |
| `MCP_MAX_INTEGRATIONS`, `MCP_RATE_LIMIT` | 5, 120/min | AI integration limits |
| `SECURITY_SESSION_MINUTES`, `SECURITY_SESSION_REMEMBER_MINUTES` | 720, 43200 | Session inactivity expiry |
| `GRADING_RETAKE_POLICY`, `GRADING_FAILED_COUNTS` | `best`, `true` | CGPA rules |
| `CLEARANCE_REMIND_AFTER_DAYS`, `CLEARANCE_ESCALATE_AFTER_DAYS` | 3, 7 | Clearance reminders |

More settings live in `config/` (`security.php`, `profile.php`, `grading.php`, `clearance.php`, `mcp_access.php`, `mcp_clients.php`, `assistant.php`, `result_portal.php`, `notifications.php`, `notification_events.php`, `erp.php`, `feature_index.php`).

## Scheduled jobs

| Time | Command | What it does |
|---|---|---|
| every minute | `notifications:process-outbox` | Turns pending events into email deliveries (also recovers events left by a crash) |
| 03:00 | `sessions:prune` | Deletes revoked / expired session records older than 90 days |
| 06:00 | `portal:check` | Reads the portal exam list, detects and confirms new result publications |
| 07:00 | `portal:recheck-pending` | Re-checks students who had no result yet |
| 07:30 | `notifications:send-digests` | One digest email per recipient |
| 08:00 | `mcp:notify-expiring` | Tells owners of AI integrations that expire within 7 days |
| 08:15 | `notifications:scan` | Time-based events (library due soon / overdue, profile reminders, denied tool-call spike) |
| 08:30 | `clearance:remind-pending` | Reminds approvers, escalates old requests, digest to the office |

A failure of any scheduled command emails the super admins (`system.scheduled_task_failed`).

## Result portal — first use

1. `php artisan migrate`; make sure a queue worker and the scheduler run.
2. Open **Academic → Portal monitor** and press **First-time sync** once (saves the exam lists of CSE, EEE and Civil; old exams are stored as known).
3. Make sure students have a numeric **registration number**, the right **department**, **batch session** (e.g. `2022-2023`) and, ideally, an **admission year**.
4. Leave **shadow mode** on for the first publications. When the monitor shows a *shadow* publication, check it, then press **Run now**.
5. Watch **Academic → Student results** for failed / waiting pulls; use **Retry**.
6. To pull one student by hand: edit the student (a changed registration number pulls again) or use **Retry** on their pull.

Recovery: everything is kept in the database (pulls, publications, probes, runs). After a restart the worker and scheduler simply continue. If the portal changes its page layout, pulls fail visibly with "layout not recognised" and nothing wrong is stored; the raw pages are kept so the parser can be fixed and pages re-read. To use an official data feed instead of scraping, implement `App\Services\ResultPortal\Contracts\ResultSource` and rebind it in `AppServiceProvider`.

## Email — turning it on

1. Set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`.
2. Set `NOTIFICATION_DRIVER=mail` and restart the queue worker.
3. Open **Security & Access → Email notifications**; for any event press **Preview** and **Send test to me**.
4. Review defaults (security and student-affecting events are on; noisy ones are off) and recipients.
5. Watch **Email deliveries**; **Retry all failed** after fixing a provider problem.

Recovery and design notes: `EMAIL_EVENTS.md` (Operations section).

## AI integrations (MCP)

- Endpoint: `POST /mcp` with `Authorization: Bearer erpmcp_…`; local development: `ERP_MCP_TOKEN=… php artisan mcp:start erp`.
- Users create tokens at **My Account → AI Integrations** (2FA required). Admins oversee all integrations at **Security & Access → MCP integrations** (global on/off, per-role on/off, revoke with reason).
- Full guide: `README_MCP.md`; tool catalog: `MCP_TOOLS.md`.

## Accounts and access

- Roles and permissions are edited at **Security & Access → Roles & permissions**. On a database seeded before this feature, run `php artisan db:seed --class=RoleSeeder` once to create the Shield permissions for the super admin.
- Make two-factor mandatory for a role at **Security & Access → Two-factor policy**.
- A user who lost their phone: **Users → (user) → Sessions / Reset 2FA** (reason required, audited).
- There is **no password-reset page yet**; the office hands over first passwords and resets them in the user screen.

## Tests and CI

```bash
.autopilot/verify.sh            # full run: build, fresh migrate + seed, Pint, Unit, Feature, MCP, Browser
php artisan test --compact      # without the browser tests: use --testsuite=Unit etc.
vendor/bin/pint --dirty --format agent
```

CI (`.github/workflows/tests.yml`) runs the same script. Test data: `php artisan seed:test` (deterministic). The browser tests need Chromium. Browser test output is best written to a file with a timeout (Chrome keeps pipes open).

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Menu item or widget missing for the super admin | Shield permissions not created: `php artisan db:seed --class=RoleSeeder` |
| Emails not arriving | `NOTIFICATION_DRIVER` is still `log`, no queue worker, or provider down → **Email deliveries** shows the error; fix and **Retry all failed** |
| Pulls stay "Waiting" / nothing happens | Queue worker not running, `RESULT_PORTAL_ENABLED=false`, or shadow mode awaiting **Run now** |
| Pull "Skipped" | No registration number, a non-numeric registration number, or the department is not mapped (`config/result_portal.php → department_programs`) |
| Pull "Failed: layout not recognised" | The portal changed its page; send a saved page to the developers (raw pages are in `storage/app/private/portal-pages/`) |
| `Vite manifest not found` | `npm run build` |
| A page stays open after logout and acts weird | Expected: the next click returns 419 and reloads to login |
