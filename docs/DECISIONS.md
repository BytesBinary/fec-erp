# Decisions

Autopilot run: decisions are made without waiting for the product owner (see
`autopilot/AUTOPILOT_RULES.md` §1–2). Each entry: decision, alternatives, why, how to change it.

---

## Pre-made product decisions (AUTOPILOT_RULES §2) — answers to spec §12

| # (§12) | Topic | Decision | Where it will be configurable |
|---|---|---|---|
| 1 | Stack | Keep the existing stack: Laravel 12 + Filament 5 + Livewire 4 + Pest 4, MySQL/MariaDB in prod, SQLite in tests. | — |
| 2 | Head vs Principal | Different people. `head_of_institution` approves digitally (last online stage). `principal` only signs paper. | `clearance_stages` table |
| 3 | Eligibility | Profile complete + all final results published + account active + no other active request. | `config/clearance.php` |
| 4 | Retakes / improvements | Best attempt counts; one attempt per course in CGPA; a failed course with no passing attempt counts as 0.00 with its credits. | `config/grading.php` (`retake_policy`, `fail_counts_in_denominator`) |
| 5 | Grading scale / rounding | Bangladesh UGC uniform scale (80+ A+ 4.00 … <40 F 0.00), stored as DB config; compute at full precision, display 2 decimals, round half up. | `grading_scales` table, `config/grading.php` |
| 6 | Rejection | Resume at the rejecting stage; earlier approvals stay valid. | `config/clearance.php` |
| 7 | Extra stages | None by default; stage table supports more. | `clearance_stages` |
| 8 | Notifications | In-app (database) always. Mail only when enabled by env; SMS via a pluggable interface with a log-only driver. Reminder threshold default 3 days. | `config/erp.php` notifications section |
| 9 | Approval security | No OTP/password re-entry. Approver must be logged in and have a signature image. | `config/clearance.php` |
| 10 | AI provider / data | Claude API via `ANTHROPIC_API_KEY` + `ASSISTANT_MODEL`; mask NID and phone numbers before sending; no key → feature-search fallback; tests use FakeProvider. | `config/assistant.php` |
| 11 | Language | English UI, strings in `lang/en/*.php` (i18n-ready). | `lang/` |
| 12 | 2FA policy | TOTP; optional by default, required for MCP; super admin can enforce per role (off by default); "trust this device" 30 days. | `mfa_role_policies`, `config/security.php` |
| 13 | MCP limits | Default expiry 90 days (30/90/180/365); "never" disabled unless super admin allows; max 5 active integrations per user; step-up 2FA for each new integration. | `mcp_settings` |
| — | Sessions | Inactivity expiry 30 days with remember-me, 12 hours without; password change logs out other devices. | `config/security.php` |
| — | Missing modules | Build minimal halls (assignment + dues) and library (loans + fines). | — |

---

## D-001 — MCP implementation: `laravel/mcp`
- **Decision:** Use `laravel/mcp` (Laravel's official MCP server package, already in the lockfile as a dev
  dependency of Boost) and promote it to a production `require` in Phase 5.
- **Alternatives:** TypeScript `@modelcontextprotocol/sdk` sidecar (spec's example) — would duplicate auth,
  DB access and services in a second runtime, violating spec §0.2 "reuse the service layer".
- **Why:** Same process, same services, same DB; supports Streamable HTTP (`Mcp::web`) and stdio
  (`Mcp::local`), tool annotations, conditional registration, test helpers.
- **Change:** Replace `routes/ai.php` registrations; tool registry is framework-agnostic metadata.

## D-002 — E2E tooling: Pest 4 browser plugin (Playwright)
- **Decision:** `pestphp/pest-plugin-browser` (dev) + `playwright` npm package + Chromium headless shell.
  Browser tests live in `tests/Browser`, suite `Browser`.
- **Alternatives:** Standalone `@playwright/test` (TypeScript) against `php artisan serve` with a separate
  seeded DB and HTTP test hooks.
- **Why:** The repo already uses Pest 4; the plugin drives real Chromium via Playwright while running the app
  in-process, so tests use factories, `RefreshDatabase`, fakes and can tamper with DB rows directly
  (needed for E2E 7) and call MCP endpoints from PHP (E2E 10/13). One runner, one language.
- **Change:** Tests are plain Pest; switching to `@playwright/test` would need a seeded server + helpers.

## D-003 — Verification uses SQLite, never the dev MySQL database
- **Decision:** `verify.sh` overrides `DB_CONNECTION=sqlite` with a throw-away `database/verify.sqlite`;
  the tests themselves use in-memory SQLite (phpunit.xml).
- **Why:** `.env` points at the developer's local MySQL (`fec_erp`) and a dev server may be running.
  AUTOPILOT §3 forbids touching non-test databases. Consequence: all migrations/seeders must be DB-agnostic.
- **Change:** Set `DB_*` env vars before running verify.sh to target another throw-away DB.

## D-004 — Pre-existing failing test fixed rather than quarantined
- **Decision:** `tests/Feature/ExampleTest.php` (Laravel scaffold) asserted `GET /` → 200, but the Filament
  panel at `/` correctly redirects guests to `/login`, and Feature tests had no database
  (`RefreshDatabase` was commented out in `tests/Pest.php`). Enabled `RefreshDatabase` for Feature tests and
  changed the assertion to "guest is redirected to `/login`, and `/login` returns 200".
- **Why:** The test encoded scaffold behaviour that the app intentionally replaced; the new assertion is
  strictly stronger than a quarantine. Also fixed `CourseSeeder`'s MySQL-only `SET FOREIGN_KEY_CHECKS`
  (now `Schema::disableForeignKeyConstraints()`), so seeding works on every driver.

## D-005 — Role keys: rename the six placeholder roles to snake_case keys
- **Decision (Phase 1):** A new, reversible data migration renames `Principal`, `Department Head`,
  `Teacher`, `Student`, `Librarian`, `Hall Provost` to `principal`, `department_head`, `teacher`,
  `student`, `librarian`, `hall_provost`, and creates `admin_office`, `head_of_institution`
  (and `accountant` is **not** created — no finance module exists). Display labels come from
  `lang/en/roles.php`. `Academic Admin`, `Exam Coordinator`, `Routine Coordinator`, `Report Viewer`,
  `panel_user` stay unchanged. `super_admin` already matches.
- **Alternatives:** Keep human names and map keys via config.
- **Why:** These placeholders have no permissions yet; renaming keeps ids (and any user assignments) intact
  and gives one canonical key across web, MCP, fixtures and seeds.
- **Change:** Edit `RoleKey` enum + the label file; the migration's `down()` restores old names.

## D-006 — Permission strings: `resource:action` for new domain permissions; Shield aliases for legacy
- **Decision:** New permissions use lowercase `resource:action` (`clearance:approve`, `result:publish`, …),
  stored in spatie's `permissions` table and seeded from `config/erp.php`, so super admin edits them in the
  existing Shield Roles UI (custom permissions tab enabled). For resources Shield already manages
  (courses, departments, students, …) the `Authorizer` maps the spec name to the existing Shield
  permission (`course:create` → `Create:Course`) via an alias map, so there is one source of truth.
- **Why:** Avoids two diverging permission sets for the same action while matching the spec's naming.
- **Change:** `config/erp.php` → `permission_aliases`.

## D-007 — Role scopes in a separate `role_scopes` table
- **Decision:** `role_scopes(user_id, role_id, scope_type, scope_id)` alongside spatie's `model_has_roles`.
  Scope types: `department`, `hall`, `course`. A role with no scope rows is global (or self for students).
  Teachers' course scope is derived from `course_teacher`; department heads and provosts get explicit rows.
- **Alternatives:** Add columns to `model_has_roles` (breaks spatie's composite PK semantics); store
  `head_user_id` on departments / `provost_user_id` on halls.
- **Why:** Matches spec §9 `user_roles(..., scope_type, scope_id)`, supports several scopes per role.

## D-008 — Minimal academic modules added
- **Decision:** Add `programs` (one per department, `required_credits`), `semesters` (academic terms, one
  active), `course_offerings` (course × semester × section, teacher), `enrollments` (with `attempt_type`),
  `results`, `notices`, residential `halls` (+ residencies, dues) and `library_loans` (with fines).
  Student result pages group courses by the program semester (`courses.semester_number`, "1st…8th
  semester"), which is how the institution already labels semesters.
- **Why:** Required by results/CGPA, clearance eligibility and the MCP catalog; none exist today.
  `exam_halls` are exam rooms and are not reused for residential halls.

## D-009 — `users.is_active`
- **Decision:** Add `is_active boolean default true` to users. Inactive users cannot log in, use MCP or
  apply for clearance. Soft-deleted users are also treated as inactive.

## D-010 — Authentication channels
- **Decision:** Web + assistant endpoints use the existing session guard (CSRF-protected; the assistant
  endpoint lives at `/api/assistant/chat` but in the `web` middleware group). MCP uses per-user
  integration bearer tokens (hashed, prefixed). No Sanctum/JWT is introduced. OAuth 2.1 (Passport) is the
  documented later phase for MCP.
- **Why:** No API guard exists; adding Sanctum would be a dependency change with no benefit here.

## D-011 — Student profile data in `student_profiles`
- **Decision:** New 1:1 table `student_profiles` (student_id, full_name_certificate, father_name,
  mother_name, date_of_birth, email, present/permanent address, photo_path, guardian contact, blood group,
  nid_or_birth_reg, emergency contact, `profile_completed_at`, `locked_fields` json). Phone stays on
  `students.phone`; residential hall comes from hall residency.
- **Why:** Keeps the existing `students` table and Filament forms untouched.

## D-012 — No GD extension locally
- **Decision:** QR codes are generated as SVG (chillerlan) and embedded as data URIs; signatures are
  embedded as base64 data URIs. The HTML print view is the primary tested artifact; the server-side PDF
  (dompdf) is tested for generation/HTTP 200 and content markers, not image fidelity.
- **Why:** The local PHP build has no `gd`; CI/production (setup-php with `gd`) has it.
- **Change:** Install `php-gd` locally to get full PNG fidelity in dompdf.

## D-013 — Notifications
- **Decision:** Laravel database notifications + Filament `databaseNotifications()` bell (in-app, always).
  Mail channel only when `ERP_NOTIFY_MAIL=true` (mail config exists but the app never sent mail before).
  SMS: `App\Contracts\SmsGateway` with `LogSmsGateway` default driver.

## D-014 — Assistant LLM client
- **Decision:** `ClaudeProvider` calls the Anthropic Messages API through Laravel's HTTP client (no new SDK
  dependency); model from `ASSISTANT_MODEL`, key from `ANTHROPIC_API_KEY` (only in `.env`, placeholders in
  `.env.example`).
- **Change:** Swap the provider binding in `config/assistant.php`.

## D-015 — Phase 1S: sessions, 2FA and notification delivery
- **Decision:** Custom TOTP implementation on `pragmarx/google2fa` (not Filament's built-in MFA) because the
  spec fixes the data model (`user_mfa`, `mfa_recovery_codes`, `mfa_role_policies`), replay protection,
  lockout and trusted devices. Session records (`user_sessions`) are created lazily on the first authenticated
  request and validated by `TrackUserSession` on every request (web, Livewire, JSON). Web pages get a redirect
  to /login, Livewire updates a 419 (page reloads to login), JSON 401 `SESSION_REVOKED`/`SESSION_EXPIRED`.
  Notification delivery: one `ExternalMessenger` interface with a log-only default (replaces the separate
  mail/SMS contracts of D-013 for the security notifications); in-app database notifications always.
- **Also:** Menu path is Settings → Devices / Two-factor authentication / Two-factor policy (flat items in the
  Settings group). Recovery codes: 10 × `xxxxx-xxxxx`, HMAC-SHA256 with the app key. Trusted devices: random
  token in an encrypted cookie, hash stored in `known_devices`; revoking the granting session removes it.
  `SESSION_LIFETIME` must be ≥ 43200 (30 days) for "remember me" to work; the 12 h inactivity limit is
  enforced by `user_sessions.expires_at`.
- **Change:** `config/security.php`.

## D-016 — Browser (E2E) test isolation
- **Decision:** `BrowserTestCase` prepends `Tests\Support\ResetRequestState` and uses file sessions, because
  the Pest browser plugin serves all requests from one process and would otherwise leak the authenticated
  user, session attributes and scoped services between browser contexts (needed for the multi-device E2E tests).

## D-017 — Photo upload in browser tests
- **Decision:** The Pest browser plugin's in-process server drops multipart bodies, so E2E 1 cannot upload a photo through FilePond. The test shows the "Photo is required" message, then stores the file and sets `photo_path` directly and finishes the form through the UI. Photo handling is unit-tested at the service level.
- **Change:** If the plugin gains file support, replace the direct DB update with `attach()`.

## D-018 — Clearance eligibility: "final results published"
- **Decision:** A student is eligible when the profile is complete, the account is active, no other request is active, published results earn at least the program's `required_credits`, and no result is still awaiting publication for a course that has no published pass. An unpublished improvement/retake attempt of an already-passed course does not block.
- **Why:** The seed data (and real life) keep unpublished improvement attempts; blocking on them would make everyone ineligible.
- **Change:** `App\Services\Clearance\EligibilityChecker`.

## D-019 — Clearance approver matching and signatures
- **Decision:** An approver may act only if they hold the stage's approver role *and* that role's own scope covers the student (hall → current residency hall, department → student's department, global → any). Signatures are private PNGs (`local` disk); each approval copies the file into `clearance-signatures/{request}/` and stores its SHA-256, which is part of the hash chain. Skipped stages are recorded as `skipped` rows in the chain.

## D-020 — MCP implementation shape
- **Decision:** `laravel/mcp` with one generic `RegisteredTool` per `ToolDefinition` (109 tools in `app/Mcp/Domains`). `tools/list` filters by the authenticated user and integration access level; `tools/call` bypasses that filter and runs `ToolExecutor`, so guessed names return `FORBIDDEN`. `ToolExecutor` is channel-agnostic and is what the assistant (phase 6) calls. CRUD tool parameters are derived from each service's own validation rules. Parity: `tests/Mcp/ParityTest.php` + `mcp/EXCLUDED.md`.
- **Change:** Add tools in `app/Mcp/Domains/*Tools.php` and regenerate the role × tool fixture.

## D-021 — Client snippets
- **Decision:** Connection snippets live in `config/mcp_clients.php` (one file). The formats (Claude Code `claude mcp add --transport http`, Claude Desktop via `mcp-remote`, Cursor `mcpServers` + `url`/`headers`, VS Code `servers` + `type: http`) are from the clients' public documentation as known on 2026-10-10 and could not be re-fetched during the unattended run; update the file if a client changes format.

## D-022 — Assistant behaviour
- **Decision:** The assistant runs in-process through `ToolExecutor` (channel `assistant`), sees only tools the user may call, and turns every write tool into a confirmation card (`ToolExecutor::preview`, stored as an `assistant_messages` row with role `action`); Confirm executes with `confirm=true` after re-checking permissions. Tool results are masked (NID, phones) and labelled as data. History is one conversation per user (viewable and deletable). Without `ANTHROPIC_API_KEY` or when the API fails the widget answers with plain feature search. Streaming is SSE; `ASSISTANT_STREAM=false` returns the same events in one body (used only by the in-process browser-test server).
- **Change:** `config/assistant.php`, `config/feature_index.php`.

## D-023 — Spec coverage review deviations
- **Decision:** Recorded as deliberate: no geo-IP location (spec only allows it when a local database already exists), no `accountant` role (no finance module), approvers see hall dues and library loans only (no discipline, room-handover or lab-equipment data model exists), no password/OTP re-confirmation on approval (pre-made decision 9), no Bangla UI (decision 11), e-mail/SMS through the pluggable log-only messenger (D-013/D-015). Coverage measurement (>= 90%) is left to CI once a coverage driver is installed.
- **Change:** `config/clearance.php`, `config/security.php`, `config/erp.php`.

## D-024 — Role management UI and Settings navigation (2026-10-10)

- **Decision:** keep the spec RBAC (central `Authorizer` over spatie roles/permissions) and manage it through the existing Shield **Roles** screen (`/shield/roles`, "Security & Access → Roles & permissions"). Roles and permissions live in the same spatie tables, so creating or editing a role there changes behaviour immediately (no cache lag).
- **Why the screen had vanished:** Shield guards the Roles screen, pages and dashboard widgets with permissions (`ViewAny:Role`, `View:DashboardStatsOverview`, …) that a freshly seeded database never created, so even the super admin could not see them. `RoleSeeder` now runs `shield:generate --option=permissions` for the panel and gives every permission to `super_admin`.
- **Limitation:** a role created in the UI has a *global* data scope unless it is listed in `config/erp.php` `rbac.role_scopes` (department / hall / course / self). There is no UI for the scope yet; grant such roles narrowly.
- **Navigation:** the Settings group had 12+ entries. It is split into `Settings` (institution, appearance, designations, profile fields, grading scale, clearance stages), `My Account` (devices, two-factor, AI integrations, signature, complete profile) and `Security & Access` (users, roles, two-factor policy, MCP oversight, audit log).
