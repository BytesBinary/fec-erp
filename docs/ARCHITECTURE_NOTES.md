# Architecture Notes (Phase 0 — Discovery)

Written 2026-10-10 on branch `autopilot/erp-features` (base commit `fc2be259`).
Every later phase must follow the conventions recorded here. Decisions taken
while writing this document are in [`docs/DECISIONS.md`](DECISIONS.md).

---

## 1. Stack

| Item | Value |
|---|---|
| Language | PHP 8.5 (composer constraint `^8.2`; production CI builds with PHP 8.4) |
| Framework | Laravel 12 (streamlined structure: `bootstrap/app.php`, `bootstrap/providers.php`, no `Http/Kernel.php`) |
| Admin UI | **Filament 5** single panel `erp` mounted at `/` (`app/Providers/Filament/ErpPanelProvider.php`), Livewire 4, Alpine, Tailwind 4 (Vite theme `resources/css/filament/erp/theme.css`) |
| RBAC package | `spatie/laravel-permission` 8.3 driven by `bezhansalleh/filament-shield` 4.3 |
| PDF | `barryvdh/laravel-dompdf` 3.1 (dompdf 3.1.4) — already used by routine/exam reports |
| 2FA / QR libs already installed (via Filament) | `pragmarx/google2fa` 9, `pragmarx/google2fa-qrcode` 3, `chillerlan/php-qrcode` 5 (all production deps) |
| MCP | `laravel/mcp` v0.5.9 present as a **dev** transitive dependency of `laravel/boost` — must be promoted to `require` in Phase 5 (deploy uses `composer install --no-dev`) |
| Package managers | Composer 2, npm (Node 22) |
| Lint | Laravel Pint (`vendor/bin/pint`), default Laravel preset (no `pint.json`) |
| Static analysis | none (no PHPStan/Larastan) |
| Tests | Pest 4 on PHPUnit 12; **Pest browser plugin (Playwright) added in Phase 0** |

## 2. Database, ORM, migrations

- Production / dev: **MySQL/MariaDB** (`.env`: `DB_CONNECTION=mysql`, local `fec_erp`). The developer's dev
  server (`composer run dev`) may be running against it — **never** point tests at it.
- Tests: SQLite `:memory:` (phpunit.xml). `verify.sh` migrates/rolls back/re-migrates/seeds a throw-away
  `database/verify.sqlite`. All new migrations and seeders **must be DB-agnostic** (no raw MySQL SQL; use
  `Schema::disableForeignKeyConstraints()` etc.). Phase 0 fixed the one MySQL-only statement in `CourseSeeder`.
- ORM: Eloquent; casts via `casts()` method; `SoftDeletes` on most domain models; factories exist for
  Batch, Course, Department, Designation, RoutineSlot, Staff, Student, Teacher, TimeSlot, User.
- Migrations: Laravel migrations in `database/migrations`; the last commit squashed ALTERs into their base
  create migrations. From now on: **only add new migrations, each with a working `down()`** (verify.sh runs
  `migrate:reset`).

### Existing entities (vs. the spec's list)

| Spec entity | Exists? | Where / notes |
|---|---|---|
| users | ✅ | `users` (name, email, password, theme, theme_primary_color, soft deletes). **No `is_active` flag.** |
| roles / permissions | ✅ | spatie tables `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. **No scope columns.** |
| students | ✅ | `students` (user_id, department_id, batch_id, roll_number, registration_number, current_semester 1-8, phone). No personal/profile fields, no hall. |
| teachers | ✅ | `teachers` (user_id, department_id, designation_id, short_name, employee_id, joining_date, phone) + `course_teacher` pivot |
| staff | ✅ | `staff` (user_id, department_id, designation_id, employee_id, joining_date, phone) |
| departments | ✅ | `departments` (name, code, description, is_active). **No head reference.** |
| programs | ❌ | Departments act as programs implicitly (one B.Sc. per department). No credit requirement stored. |
| batches | ✅ (extra) | `batches` (department_id, batch_number, session "2017-2018", current_semester, is_active, is_archived) |
| courses | ✅ | `courses` (department_id, semester_number 1-8, type theory/lab, code, version, name, credit_hours, weekly_classes, is_active) |
| semesters | ❌ | Only the integer `semester_number` (1-8) on courses/batches/routine. No term entity, no "active semester". |
| enrollments | ❌ | none |
| results / grades | ❌ | none |
| halls / hostels | ❌ | `exam_halls` exist but are **exam rooms**, not residential halls |
| library | ❌ | none |
| notices | ❌ | none |
| institution settings | ✅ | `institution_settings` singleton (`InstitutionSetting::current()`): name, logo, principal name/title/signature path |
| routine / exams | ✅ (extra) | time_slots, routine_slots, exam_types, exam_halls, exam_duties |

## 3. Authentication and roles today

- **Web session auth** only (guard `web`, Eloquent provider). Filament's built-in `->login()` page at
  `/login`, `->profile(EditProfile::class)` at `/profile` (name, email, password change).
- `SESSION_DRIVER=database` → Laravel `sessions` table already stores `user_id`, `ip_address`,
  `user_agent`, `last_activity` per session. Filament panel middleware includes `AuthenticateSession`
  (password-hash binding → other sessions die when password changes via `Auth::logoutOtherDevices`).
- No API guard, no Sanctum, no JWT, no `routes/api.php`.
- Panel access: `User::canAccessPanel()` = user has **any** role.
- Roles (seeded by `RoleSeeder`, guard `web`): `super_admin` (Shield super admin), `panel_user`,
  `Academic Admin`, `Exam Coordinator`, `Routine Coordinator`, `Report Viewer`, and permission-less
  placeholders `Principal`, `Department Head`, `Teacher`, `Student`, `Librarian`, `Hall Provost`.
- Permissions are Shield-generated, format **`Action:Model`** (e.g. `ViewAny:Course`, `View:BatchOverview`).
  Policies in `app/Policies/*Policy.php` are thin `$user->can('Action:Model')` wrappers (Shield-generated).
  `config/filament-shield.php`: super admin enabled, `define_via_gate => false`, `intercept_gate => before`.
- Admin user seeded by `AdminUserSeeder`: `admin@fec.edu.bd` (note: seeder does not assign a role).

## 4. Where business logic lives

- Mostly **inside Filament pages/resources** (e.g. `CreateStudent::handleRecordCreation` creates the User +
  Student; `AssignTeachers`, report pages build data and PDFs inline).
- Only one service: `app/Services/RoutineGeneratorService.php`.
- **Modules lacking a service layer** (needed for MCP/assistant reuse): users/roles, departments, batches,
  courses (+ teacher assignment), teachers, staff, students, designations, exam types/halls/duties,
  institution settings, routine (partially). Phase 1 adds services for the ones the spec's MCP catalog
  needs; the rest are listed in `mcp/EXCLUDED.md` until covered.

## 5. Routing and menu structure

- All UI routes come from the Filament panel (`discoverResources`, `discoverPages`, `discoverWidgets`).
  `routes/web.php` is empty; `routes/console.php` only has `inspire`. Health check at `/up`.
- **Sidebar = Filament navigation**, groups declared in `ErpPanelProvider::panel()->navigationGroups([...])`:
  `People`, `Routine`, `Manage Exams`, `Academic`, `Settings` (+ Shield's role resource).
  Each Resource/Page declares `$navigationGroup`, `$navigationIcon`, `$navigationSort`, `$navigationLabel`.
  Route names follow `filament.erp.resources.{slug}.{index|create|edit|view}` and
  `filament.erp.pages.{slug}`.
- The **feature index (§5)** will be generated from this registry (`Filament::getPanel('erp')` resources +
  pages and their navigation metadata / URLs), enriched by `config/feature_index.php` for keywords/steps.
- Current route list (non-vendor): dashboard `/`, appearance, assign-teachers, batch-detail/{batchNumber},
  batch-overview, batches CRUD, courses CRUD, credit-count-report, departments CRUD, designations CRUD,
  exam-duties CRUD+view, exam-duty-report, exam-halls CRUD+view, exam-types CRUD+view,
  individual-routine-report, institution-settings, login, logout, master-routine-report, profile, routine,
  routine/{record}/manage, shield/roles CRUD, staff CRUD, students CRUD, teachers CRUD.
- Dashboard widgets: Welcome, QuickActions, DashboardStatsOverview, RecentStudents, RecentExamDuties
  (gated with `HasWidgetShield`).

## 6. Tests, seeds, CI

- Before Phase 0: `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` (the latter was **failing**:
  asserted `/` returns 200, but the panel redirects guests to `/login`, and Feature tests had no DB).
  Phase 0 enabled `RefreshDatabase` for Feature tests and corrected the assertion (see DECISIONS D-004).
- **E2E (new):** `pestphp/pest-plugin-browser` + `playwright` (npm) + Chromium headless shell.
  `tests/Browser/**` (suite `Browser`) extends `Tests\BrowserTestCase` (uses `RefreshDatabase`, forces the
  compiled Vite manifest even if `public/hot` exists). Runs in-process — factories, fakes and
  `RefreshDatabase` with in-memory SQLite all work. Smoke test logs in through the real Filament form.
  - Selector tips learned: Filament inputs have ids like `form.email` → use `[id="form.email"]`;
    `press('Sign in')` can hit the heading, so click `button[type=submit]`; after Livewire submits, use
    `->wait(n)` or an assertion that waits before `assertPathIs`.
- Seeders: `DatabaseSeeder` → InstitutionSetting, AdminUser, Role, Department, Designation, TimeSlot,
  Batch, Course, Teacher, CourseTeacher, Staff, Student. No `seed:test` / `seed:demo` yet (Phase 1 / 7).
- CI: only `.github/workflows/deploy.yml` (push to `main` → build → force-push `deploy` branch). **No test CI.**
  A test workflow running `.autopilot/verify.sh` is planned for Phase 7.
- Verification: **`.autopilot/verify.sh`** (deps → assets → migrate/reset/migrate/seed on SQLite → Pint →
  every testsuite in `phpunit.xml`). Static analysis is reported as SKIP (not configured).

## 7. File storage

- Disks: `local` → `storage/app/private`, `public` → `storage/app/public` (+ `public/storage` symlink).
- Existing uploads (institution logo, principal signature) go to the **public** disk via Filament
  `FileUpload`; PDFs read them via `storage_path('app/public/...')`.
- New: student photos and staff signature images go to the **private `local` disk** (personal data) and are
  served through authorized routes; clearance signature **snapshots** are copied to
  `storage/app/private/clearance-signatures/{request}/{approval}.png` and embedded as data URIs in print/PDF.
- Local PHP lacks the `gd` extension (CI has it). dompdf PNG-with-alpha rendering needs GD — see
  DECISIONS D-012 for the workaround (embed via data URIs, test HTML print view; PDF test tolerant).

## 8. Notifications

- None exist beyond Filament flash toasts (`Filament\Notifications\Notification::make()->send()`).
  No `notifications` table, no mail usage (`MAIL_MAILER=log`), no SMS.
- Plan: Laravel Notifications with the **database** channel + Filament `->databaseNotifications()` bell in the
  panel (in-app, always on). A pluggable `App\Notifications\Channels` layer: mail (enabled only when
  `ERP_NOTIFY_MAIL=true`) and an `SmsGateway` interface with a `LogSmsGateway` driver.

## 9. Gaps between spec and codebase (answered autonomously — see DECISIONS.md)

1. Stack is Laravel/Filament/Pest, not TS/Python → use `laravel/mcp` (official Laravel MCP package) and
   Pest browser (Playwright) — D-001, D-002.
2. Role keys vs existing human-named roles → data migration renames six placeholder roles to snake_case keys
   (ids and assignments preserved) — D-005.
3. Permission format: Shield uses `Action:Model`; spec uses `resource:action` → both coexist; aliases — D-006.
4. Scoped roles: spatie has no scope → new `role_scopes` table — D-007.
5. No programs/semesters/enrollments/results/halls/library/notices → build minimal modules — D-008.
6. "Student account active" → add `users.is_active` — D-009.
7. No API/JWT → session auth for web + `/api/assistant/chat`; MCP uses integration bearer tokens — D-010.
8. Profile data lives in a new `student_profiles` table (1:1 student) — D-011.
9. No GD locally — D-012.

---

## 10. Concrete file/module plan for all later phases

Conventions: Laravel `make:*` generators, Filament resources under `app/Filament/Resources/{Plural}/…`
(Schemas/Tables/Pages sub-folders), custom pages under `app/Filament/Pages`, Shield page/widget gating
traits, `casts()` methods, enums TitleCase in `app/Enums`, PHPDoc over inline comments, Pint style.
Domain services in `app/Services/{Domain}/…`; every service write goes through `AuditLogger`.
Config in `config/erp.php` (+ focused files below). UI strings in `lang/en/erp.php` (i18n-ready).

### Phase 1 — RBAC foundation, audit, service layers, seed:test skeleton
- Migrations: `add_is_active_to_users_table`; `rename_placeholder_roles_to_keys` (data, reversible);
  `create_role_scopes_table` (user_id, role_id, scope_type, scope_id); `create_audit_logs_table`
  (actor_user_id, channel, action, entity_type, entity_id, before json, after json, ip, user_agent,
  integration_id nullable, created_at; indexes on actor, entity, channel, created_at);
  `create_programs_table` (department_id, name, code, required_credits); `add_program_id_to_students`;
  `create_semesters_table` (name, code, starts_on, ends_on, is_active); `create_notices_table`.
- `app/Enums/RoleKey.php` (super_admin, admin_office, head_of_institution, principal, department_head,
  hall_provost, librarian, teacher, accountant(unused), student), `app/Enums/Channel.php` (web/mcp/assistant),
  `app/Enums/ScopeType.php` (global, department, hall, course, self).
- `config/erp.php` — permission catalog (`resource:action` → description), default role→permission matrix,
  aliases to Shield permissions, feature flags.
- `app/Support/Authorization/Authorizer.php` — **single** `authorize(User, string $permission, ?Model $resource = null): void`
  (+ `allows()`), throws `App\Exceptions\Domain\ForbiddenException`; `ScopeResolver` (user's departments/halls/courses);
  `app/Policies/Scopes/*` functions such as `canApproveClearance()`. `app/Support/RequestContext.php` holds
  channel / ip / user agent / integration id for the current call (web middleware sets `web`; MCP sets `mcp`;
  assistant sets `assistant`).
- `app/Services/Audit/AuditLogger.php` + `app/Models/AuditLog.php`; Filament `AuditLogResource` (super_admin,
  filters by actor/channel/action/entity/date).
- Domain exceptions with stable codes: `app/Exceptions/Domain/{DomainException, ForbiddenException,
  NotFoundException, ValidationException, ConflictException, InvalidStateException, RateLimitedException,
  ProfileIncompleteException}`.
- Services (thin, authorization + audit + Eloquent): `app/Services/Users/UserService`, `RoleService`,
  `PermissionMatrixService`, `app/Services/Academic/{DepartmentService, ProgramService, SemesterService,
  CourseService, CourseOfferingService}`, `app/Services/Notices/NoticeService`. Existing Filament
  Create/Edit pages for those resources are refactored to call the services (behaviour unchanged).
- Seeders: `PermissionSeeder` (catalog + default matrix, idempotent), updated `RoleSeeder`;
  `database/seeders/Testing/TestSeeder.php` (deterministic skeleton: 1 user per role, 2 dept heads,
  2 provosts …) and console command `app/Console/Commands/SeedTestCommand.php` (`seed:test`).
- Tests: `tests/Unit/Authorization/*` (policy/scope tables), `tests/Feature/Rbac/*`, `tests/Feature/Audit/*`.

### Phase 1S — Account security
- Migrations: `create_user_sessions_table` (spec §9 columns; `session_hash` = sha256 of Laravel session id),
  `create_known_devices_table`, `create_user_mfa_table`, `create_mfa_recovery_codes_table`,
  `create_mfa_role_policies_table`, `create_notifications_table` (Laravel database notifications).
- `app/Services/Security/{SessionTracker, DeviceParser (UA → "Chrome on Windows"), TwoFactorService
  (google2fa, ±1 window, replay via last_used_step, lockout 5/15 min, encrypted secret cast),
  RecoveryCodeService (10 codes, hashed, single use), TrustedDeviceService (signed cookie + DB, 30 days),
  MfaPolicy (per role)}`.
- Middleware (registered in the Filament panel and `bootstrap/app.php`): `TrackUserSession`
  (creates/validates the session record, throttled `last_active_at`, 401/redirect when revoked/expired),
  `EnsureTwoFactorChallengePassed`, `EnforceRoleTwoFactorSetup`.
- Login: custom `app/Filament/Pages/Auth/Login.php` (remember-me lifetime) + `TwoFactorChallenge` page
  (code / recovery code, trust device). Listener on `Login` event → session record + new-device alert.
- Pages: `app/Filament/Pages/Security/Devices.php` ("Settings → Security → Devices"),
  `TwoFactorSettings.php` (step-up password → QR + key → confirm → recovery codes download/print + tick box),
  admin actions on `UserResource` (view/revoke sessions, reset 2FA with reason).
- Notifications: `NewDeviceLogin`, `TwoFactorLockedOut`, `TwoFactorReset`.
- Config: `config/security.php` (session lifetimes 12h / 30d, trust days 30, lockout 5/15, retention 90d).
- Tests: Unit (TOTP, recovery codes, UA parser), Feature (revoked session 401 on web/API/assistant,
  password change), Browser E2E 11, 12, 15.

### Phase 2 — Profile gate, Results/CGPA
- Migrations: `create_student_profiles_table` (spec fields + `photo_path`, `profile_completed_at`,
  `locked_fields` json), `create_profile_required_fields_table`, `create_grading_scales_table`,
  `create_course_offerings_table`, `create_enrollments_table` (student, offering/course, attempt_type
  regular/retake/improvement), `create_results_table` (enrollment, marks, letter, grade_point, status
  draft/submitted/approved/published, published_at).
- `app/Services/Profile/{ProfileService, ProfileCompletionChecker}`, middleware `EnsureProfileComplete`
  (web redirect to `/profile/complete`; JSON/MCP → `PROFILE_INCOMPLETE` except `me_*`).
- `app/Services/Results/{GpaCalculator (pure, static, full precision, round-half-up display), GradingScale,
  ResultService (enter/submit/approve/publish, published-only reads), TranscriptService}`,
  `config/grading.php` (UGC scale seeded to DB, retake policy `best`, fail counts as 0.00).
- Pages: `app/Filament/Pages/Profile/CompleteProfile.php` (progress bar, passport-ratio crop via Filament
  `FileUpload::imageCropAspectRatio('35:45')`), `app/Filament/Pages/Results/MyResults.php` (Result menu,
  CGPA header) + print view route `results/print`. Staff: `ResultEntry` page, `GradingScaleResource`,
  `ProfileRequiredFieldResource`.
- Tests: table-driven `tests/Unit/Results/GpaCalculatorTest.php`, Feature gate tests, E2E 1–2.

### Phase 3 — Clearance core
- Migrations: `create_halls_table`, `create_hall_residencies_table`, `create_hall_dues_table`,
  `create_library_loans_table`, `create_staff_signatures_table`, `create_clearance_stages_table`,
  `create_clearance_requests_table` (version column, verify_code, chain_hash; spec indexes),
  `create_clearance_approvals_table`, `create_clearance_events_table`, `create_clearance_prints_table`.
- `app/Enums/ClearanceStatus.php`; `app/Services/Clearance/{EligibilityChecker, ClearanceService
  (single transition(), optimistic lock → ConflictException), StageResolver (skip logic), HashChain,
  SignatureSnapshotter, RequestNumberGenerator (CLR-YYYY-000123), ClearanceNotifier}`,
  `app/Services/Halls/HallService`, `app/Services/Library/LibraryService`.
- Pages: student `Clearance/Apply`, `Clearance/MyClearance` (timeline, resubmit); approver
  `Clearance/PendingApprovals` (scoped list, filters, approve/reject with remarks, dues panel) + dashboard
  count widget; `ClearanceStageResource` (super_admin); `HallResource`, `LibraryLoanResource` (minimal);
  signature upload on `EditProfile` (PNG, transparent, size limits).
- Tests: Unit state machine / scope / hash chain; Feature optimistic lock; E2E 3 (to READY), 4, 5, 6.

### Phase 4 — Clearance desk, print/PDF, verification
- `app/Filament/Pages/Clearance/ClearanceDesk.php` (search by student ID, name, request no, department,
  session, status; default READY_FOR_COLLECTION), print view route `clearance/{request}/print` (A4 print CSS)
  + `clearance/{request}/pdf` (dompdf), `app/Services/Clearance/{ClearancePrintService, QrCodeService
  (chillerlan SVG)}`, DUPLICATE watermark, mark collected (ID verified).
- Public `routes/web.php`: `GET /verify/clearance/{code}` → `ClearanceVerificationController` (no auth,
  minimal data, chain status). Test-only tamper helper in tests.
- Tests: E2E 3 (complete), 7, 8.

### Phase 5 — MCP server
- `composer require laravel/mcp` (promote to prod), `routes/ai.php`:
  `Mcp::web('/mcp', ErpServer::class)->middleware([AuthenticateMcpIntegration::class, 'throttle:mcp'])`,
  `Mcp::local('erp', ErpServer::class)` (stdio; token from `ERP_MCP_TOKEN`).
- `app/Mcp/Servers/ErpServer.php`; `app/Mcp/Registry/ToolRegistry.php` (single declaration of
  name/title/description/inputSchema/outputSchema/requiredPermission/annotations/handler);
  `app/Mcp/Tools/{Me,Users,Roles,Academic,Enrollment,Results,Halls,Library,Clearance,Notices,Help,Admin}/*Tool.php`
  — each calls a domain service; `app/Mcp/Concerns/{ConfirmsDestructive, Idempotent, Paginates}`;
  resources `erp://me`, `erp://academic-calendar`, `erp://grading-scale`; prompts
  `create_course_wizard`, `publish_semester_results`, `review_pending_clearances`.
- `app/Http/Middleware/AuthenticateMcpIntegration.php` (TOKEN_REVOKED / TOKEN_EXPIRED / MFA_REQUIRED /
  MCP_DISABLED / PROFILE_INCOMPLETE), rate limiter `mcp` per token, audit channel `mcp`.
- Migration: `create_mcp_integrations_table`, `create_mcp_settings_table`, `create_mcp_role_access_table`,
  `create_idempotency_keys_table`.
- `mcp/EXCLUDED.md`; `README_MCP.md`; `tests/Mcp/**` (new `Mcp` testsuite): role×tool matrix fixture
  `tests/Mcp/fixtures/role_tool_matrix.php`, parity test (walks `app/Services/**` public methods), schema
  validation, dry-run tests; E2E 10.

### Phase 5M — MCP access management
- `app/Filament/Pages/Settings/AiIntegrations.php` (2FA prerequisite, 5-step wizard via Filament `Wizard`,
  token shown once, snippets from `config/mcp_clients.php`, polling "Connected ✓"), integrations table
  (stop/rename/activity/stop all), `app/Filament/Pages/Security/McpOversight.php` (super admin: all
  integrations, revoke with reason, global/per-role switches, usage overview).
- `app/Services/Mcp/{IntegrationService, McpSettingsService}`; notifications IntegrationCreated/Revoked/
  ExpiringSoon/NewIp; scheduled command `mcp:notify-expiring`.
- Tests: Feature (§10.3 MCP items), E2E 13, 14.

### Phase 6 — AI assistant
- `routes/web.php`: `POST /api/assistant/chat` (web+auth, SSE via `response()->eventStream()`),
  `GET/DELETE /api/assistant/history`; `app/Http/Controllers/Assistant/ChatController.php`.
- `app/Services/Assistant/{AssistantService, Contracts/AssistantProvider, Providers/ClaudeProvider (Laravel
  HTTP client → Anthropic Messages API, model from ASSISTANT_MODEL), Providers/FakeProvider,
  PiiMasker, SystemPromptBuilder, FeatureIndex (generated from the Filament navigation registry +
  config/feature_index.php)}`; tool execution in-process via the same `ToolRegistry` filtered to the user,
  confirmation cards for writes (dry-run preview → confirm).
- Migrations: `create_assistant_conversations_table`, `create_assistant_messages_table`.
- Widget: Blade/Alpine component injected with `PanelsRenderHook::BODY_END` on every authenticated page.
- Tests: `tests/Feature/Assistant/*` (FakeProvider), feature-index completeness, E2E 9; opt-in live test
  guarded by `ASSISTANT_LIVE_TEST=1`.

### Phase 7 — Hardening
- Scheduled commands `clearance:remind-pending`, `mcp:notify-expiring`, `sessions:prune`; rate limits
  reviewed; accessibility assertions (`assertNoAccessibilityIssues`) on new pages; `DemoSeeder` +
  `seed:demo`; `.github/workflows/tests.yml` running verify.sh; docs (`README_MCP.md`, `MORNING_REPORT.md`).
