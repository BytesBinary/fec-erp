# Independent review

## Summary
Reviewed `989c820c..HEAD` plus the uncommitted hardening left by an earlier review run. The uncommitted changes were inspected, found sound, kept and are covered by tests (`tests/Feature/ReviewHardeningTest.php`, `tests/Feature/Security/LivewireEnforcementTest.php`, `tests/Feature/Clearance/HashChainIntegrityTest.php`, `tests/Browser/DevicesTest.php`). Spot-checks of routes, controllers, token/TOTP/recovery-code storage and secrets found no further open issues.

## Security findings
| # | Where | Severity | Finding | Status |
|---|-------|----------|---------|--------|
| S1 | app/Http/Middleware/{TrackUserSession,EnsureTwoFactorChallengePassed,EnforceRoleTwoFactorSetup,EnsureProfileComplete}.php | High | Livewire requests of a revoked session got `response('', 419)`, which Livewire ignores, letting an open page keep acting. Now `abort(419)`. | fixed |
| S2 | app/Providers/Filament/ErpPanelProvider.php:111 | High | Session/2FA/profile middleware were not persistent on Livewire updates; now `isPersistent: true`. | fixed |
| S3 | app/Mcp/Methods/{CallRegisteredTool,ListVisibleTools}.php, IntegrationService::revalidate | High | Long-lived (stdio) MCP connections kept working after revoke/expiry/2FA removal; every request is now revalidated. | fixed |
| S4 | app/Filament/Pages/Settings/AiIntegrations.php, Clearance/ViewClearance.php | High | Public Livewire properties (`newIntegrationId`, `recordId`, `loadedVersion`, ...) were client-tamperable (IDOR); now `#[Locked]`, queries scoped to owner. | fixed |
| S5 | app/Services/Profile/ProfileService.php:213 | Medium | `photo_path` accepted arbitrary paths (traversal); restricted to the photo directory. | fixed |
| S6 | resources/views/assistant/widget.blade.php | Medium | Assistant link events could carry `javascript:`/foreign URLs; now same-origin only via `safeUrl`. | fixed |
| S7 | app/Services/Clearance/HashChain.php | Medium | Hash chain did not cover the signature image; verification now checks the stored image digest. | fixed |
| S8 | AssistantService::confirm/cancel | Medium | Double confirmation could run a write twice; serialised with a cache lock plus the pending-status check. | fixed |
| S9 | routes/web.php | Info | `/results/print`, `/clearance/*/print|pdf`, `/api/assistant/*` sit behind `erp.secure` and service-level `authorize()`; `/verify` is public by design and exposes minimal data. Tokens, session ids and trusted-device tokens are stored as SHA-256; TOTP secret is `encrypted`; recovery codes hashed. No secrets in tracked files. | accepted |

## Spec coverage gaps
Full walk of `IMPLEMENTATION_SPEC.md` (sections 3-10) against `app/`, `routes/`, `config/` and `tests/`. Status: covered, partial, missing, deviation (documented in `docs/DECISIONS.md`). Test paths are relative to `tests/`.

| Requirement (spec) | Implemented at | Test | Status |
|---|---|---|---|
| Roles incl. multi-role union, snake_case keys (3.1) | Enums/RoleKey, Authorizer, migration rename (D-005) | Unit/Authorization/AuthorizerTest, ScopePoliciesTest, Feature/Rbac/RoleKeyMigrationTest | covered |
| `accountant` role (3.1, "only if fees exist") | none, no finance module | none | deviation (D-005) |
| Permissions as DB data, central `authorize`, scope policies (3.2) | Services/Authorization (Authorizer), app/Policies/Scopes, config/erp.php | AuthorizerTest, ScopePoliciesTest, Rbac/PermissionMatrixServiceTest | covered |
| Audit log on web/mcp/assistant with before/after, ip, UA; super admin view (3.3) | Services/Audit/AuditLogger, AuditLogResource | Feature/Audit/AuditLogTest, Mcp/ToolContractTest | covered |
| Sessions table, device label, last_active once/min, "this device" (3A.1) | Services/Security/SessionTracker, DeviceParser, TrackUserSession | Security/SessionTrackingTest, Unit/Security/DeviceParserTest | covered |
| Revoked session 401 on web/API/assistant next request (3A.1) | TrackUserSession (+ persistent Livewire middleware) | SessionTrackingTest, Security/LivewireEnforcementTest, Assistant/AssistantChatTest | covered |
| Devices page: per-device logout, log out all others + stop MCP option (3A.1) | Pages/Security/Devices | SessionTrackingTest, Mcp/AiIntegrationsPageTest, Browser/DevicesTest (E2E 11) | covered |
| Password change signs out others and tells the user (3A.1) | Observers/UserPasswordObserver, Notifications/PasswordChanged | SessionTrackingTest (revoke + new notification test) | covered (notice gap fixed in this review) |
| New-device alert with link to Devices (3A.1) | Notifications/NewDeviceLogin, SessionTracker | SessionTrackingTest, Browser/DevicesTest | covered |
| Expiry 30d remember / 12h, expired hidden but kept 90d (3A.1) | config/security.php, SessionTracker::prune | SessionTrackingTest, Clearance/ClearanceReminderTest (prune scheduled) | covered |
| Approximate location from offline geo-IP (3A.1, only if a DB already exists) | none, no geo-IP database present | none | deviation (spec allows IP only) |
| Super admin views/revokes any user's sessions (3A.1) | Users/RelationManagers/SessionsRelationManager | Security/AdminSecurityToolsTest | covered |
| TOTP RFC 6238, encrypted secret, +-1 drift, replay, lockout 5/15min (3A.2) | Services/Security/TwoFactorService (pragmarx/google2fa) | Security/TwoFactorServiceTest | covered |
| Setup flow: password step-up, QR + manual key, confirm code, 10 recovery codes, download/print, "saved" tick (3A.2) | Pages/Security/TwoFactorSettings + view | Security/TwoFactorSettingsPageTest, Browser/TwoFactorTest (E2E 12); download/print buttons only in the view, not asserted | partial (buttons untested) |
| Login challenge, recovery code once, trust device 30d (3A.2) | Pages/Auth/TwoFactorChallenge, TrustedDeviceService | Security/TwoFactorLoginTest, Browser/TwoFactorTest | covered |
| Disable needs password+code; regenerate needs code; <3 codes warning (3A.2) | TwoFactorService, RecoveryCodeService | TwoFactorServiceTest, TwoFactorSettingsPageTest | covered |
| Role-enforced 2FA, admin reset with reason (3A.2) | MfaPolicy, Pages/Security/TwoFactorPolicy, UserSecurityService | TwoFactorLoginTest, AdminSecurityToolsTest | covered |
| 2FA lockout notifies user (E2E 15) | TwoFactorService, Notifications/TwoFactorLockedOut | TwoFactorServiceTest, Browser/TwoFactorTest | covered |
| Web-only actions listed in mcp/EXCLUDED.md; `me_list_sessions` exposed (3A.2) | mcp/EXCLUDED.md, Mcp/Domains/MeTools | Mcp/ParityTest, RoleToolMatrixTest | covered |
| MCP on official SDK, Streamable HTTP at /mcp + stdio (4.1) | laravel/mcp (D-001), routes/ai.php, Mcp/Servers/ErpServer | Mcp/SmokeTest (HTTP); stdio revalidation in Feature/ReviewHardeningTest | partial (stdio transport not driven end to end) |
| Integration tokens hashed, expiry, revocable; per-request checks MFA_REQUIRED/TOKEN_REVOKED/TOKEN_EXPIRED/MCP_DISABLED (4.1) | Services/Mcp/IntegrationService, Middleware/AuthenticateMcpIntegration | Mcp/AuthenticationTest, McpOversightTest | covered |
| OAuth 2.1 (design only) (4.1) | documented later phase (D-010) | none | deviation (D-010) |
| Tool registry with schema, annotations, permission; tools/list filtered; tools/call re-checked (4.1) | Mcp/Registry/{ToolDefinition,ToolRegistry,ToolExecutor}, Mcp/Methods | Mcp/ToolContractTest, RoleToolMatrixTest | covered |
| Destructive tools dry-run unless confirm; idempotencyKey (4.1) | ToolExecutor, Models/IdempotencyKey | ToolContractTest | covered |
| Output summary, cursor pagination <=100, stable error codes (4.1) | ToolExecutor, CrudService | ToolContractTest, Services/DomainServicesTest | covered |
| Rate limit per token, audit channel mcp (4.1) | Middleware/ThrottleMcpCalls, AuditLogger | AuthenticationTest, ToolContractTest | covered |
| Resources erp://me, academic-calendar, grading-scale; 3 prompts (4.1) | Mcp/Resources, Mcp/Prompts | ToolContractTest | covered |
| Tool catalog (4.2): me_*, user_*, role_*, permission_matrix_*, department/program/semester/course CRUD, enrollment_*, result_*, transcript_get, hall_*, library_*, clearance_*, notice_*, help_search_features, audit_log_search | Mcp/Domains/* (109 tools) | RoleToolMatrixTest, ScenarioTest, ToolContractTest | covered |
| Tool name variants: `semester_set_active`, `course_offering_create`, `clearance_stage_config_get/update` present; extra tools beyond minimum | Mcp/Domains | RoleToolMatrixTest | covered |
| Parity test + mcp/EXCLUDED.md (4.3) | Mcp/ParityTest | Mcp/ParityTest | covered |
| README_MCP.md: token, clients, Inspector, example calls per role (4.4) | README_MCP.md | none (doc) | covered |
| MCP wizard: 2FA gate, 5 steps, client snippets in one config file, polling connection test (4.5) | Pages/Settings/AiIntegrations, config/mcp_clients.php, Services/Mcp/ClientSnippets | Mcp/AiIntegrationsPageTest, Browser/McpIntegrationsTest (E2E 13) | covered |
| Snippet formats verified against current client docs (4.5) | config/mcp_clients.php | AiIntegrationsPageTest (fill-in only) | deviation (D-021, could not fetch docs) |
| Integrations list: last used, 7-day calls, stop/rename/activity/stop all, max 5, read-only (4.5) | AiIntegrations, IntegrationService | AiIntegrationsPageTest, AuthenticationTest | covered |
| Notifications: created, revoked, expiring 7d, new IP (4.5) | IntegrationService, McpNotification | AiIntegrationsPageTest (new IP), McpOversightTest (revoke, expiring); "created" notice not asserted | covered |
| Disabling 2FA / admin reset revokes integrations (4.5, 10.3) | McpIntegrationsStopRequested listener | TwoFactorSettingsPageTest, AdminSecurityToolsTest, AuthenticationTest | covered |
| Admin oversight, global and per-role switch, usage overview (4.5) | Pages/Security/McpOversight, McpSettingsService | Mcp/McpOversightTest, Browser E2E 14 | covered |
| Assistant: widget on every page, feature find, deep link, stuck-help context, data answers, refusal naming roles (5.1) | Services/Assistant/*, resources/views/assistant/widget.blade.php | Assistant/AssistantChatTest, Browser/AssistantTest (E2E 9) | covered |
| Writes through confirmation card (dry-run), confirm re-checks permissions (5.1) | AssistantService, ToolExecutor::preview | AssistantChatTest, ReviewHardeningTest, Browser/AssistantTest | covered |
| POST /api/assistant/chat with SSE, key server-side, provider interface, FakeProvider (5.2) | routes/web.php, Assistant/Providers | AssistantChatTest | covered |
| Optional live API smoke test `ASSISTANT_LIVE_TEST=1` (10.5) | none | none | missing (opt-in, no key available; open) |
| Feature index from menu registry, build/test fails on missing entry, accessible-only (5.2) | Assistant/FeatureIndex, config/feature_index.php | Assistant/FeatureIndexTest | covered |
| System prompt, masking, tool-call limit, rate limit, history view/delete, audit channel, fallback (5.2) | SystemPromptBuilder, PiiMasker, AssistantService | AssistantChatTest | covered |
| Profile gate: redirect, PROFILE_INCOMPLETE for API/MCP except me_*, re-gate on new field (6) | Middleware/EnsureProfileComplete, ProfileCompletionChecker | Profile/ProfileGateTest, Mcp/ScenarioTest, Browser E2E 1 | covered |
| Configurable required fields, progress bar, per-field validation (6) | Pages/Settings/ProfileFields, Pages/Profile/CompleteProfile | ProfileGateTest | covered |
| Photo cropped to passport ratio with size/type checks (6) | CompleteProfile (imageEditor, aspect ratio), ProfileService | ProfileGateTest, ReviewHardeningTest; crop UI and multipart upload not browser-testable | deviation (D-017) |
| Fields lock after clearance applied (6) | ProfileService::lockFieldsForClearance | ProfileGateTest, Clearance/ClearanceFlowTest | covered |
| Result menu: semester list, GPA, CGPA, print view, published only (7) | Pages/Results/MyResults, ResultService, results print route | Results/ResultsTest, Browser E2E 2 | covered |
| GPA/CGPA pure function, configurable scale, rounding, retakes (7) | Services/Results/GradingScaleService, ResultService, config/grading.php | Unit/Results/GpaCalculatorTest, ResultsTest | covered |
| Unpublished results never leak via UI/API/MCP/assistant (7, 10.3) | ResultService | ResultsTest, Mcp/ScenarioTest; assistant path via shared tools (no dedicated assertion) | covered |
| Result workflow enter/submit/approve/publish (4.2) | ResultService, Mcp/Domains/ResultTools | ResultsTest, ScenarioTest | covered |
| Student experience: apply, request number, timeline, resubmit at rejecting stage, ready message (8.1) | Pages/Clearance/{Apply,MyClearance}, ClearanceService | ClearancePagesTest, ClearanceFlowTest, Browser E2E 3/4 | covered |
| Four configurable stages in order, skip non-residential, super admin reorder/skip (8.2) | ClearanceStage, StageResolver, Pages/Clearance/ClearanceStages | ClearanceFlowTest, Browser E2E 5 | covered |
| Approver sees dues: hall dues yes; room handover / discipline notes / lab-equipment dues (8.2) | Pages/Clearance/ViewClearance (hall dues, library loans) | Browser/ClearanceJourneyTest | deviation (no discipline/lab data model; only dues and loans shown, see D-008) |
| Eligibility rules (8.3) | EligibilityChecker | Clearance/EligibilityTest | covered (D-018) |
| State machine incl. skip, cancel, terminal states, events (8.4) | Enums/ClearanceStatus, ClearanceService::transition, ClearanceEvent | Unit/Clearance/ClearanceStatusTest, ClearanceFlowTest | covered |
| Optimistic locking, CONFLICT; INVALID_STATE (8.4) | `version` column, ClearanceService | ClearanceFlowTest | covered |
| Signature upload, no approval without it, snapshot of signer/image (8.5) | Pages/Settings/MySignature, StaffSignatureService | ClearanceFlowTest | covered |
| SHA-256 hash chain, verify reports tampering (8.5) | Clearance/HashChain | HashChainIntegrityTest, ClearanceFlowTest | covered |
| Optional password/OTP before approving (8.5, "configurable") | none | none | deviation (D-pre 9: not required) |
| Clearance Desk search, default Ready filter (8.6) | Pages/Clearance/ClearanceDesk | ClearanceDeskTest | covered |
| Print view with 4 signatures, QR, empty Principal box, seal, footer; PDF (8.6) | ClearancePrintService, print views | ClearanceDeskTest, Browser E2E 3 | covered (PDF content checks only, D-012) |
| First print sets PRINTED; reprints logged + DUPLICATE; super admin override (8.6) | ClearancePrint, ClearancePrintService | ClearanceDeskTest, Browser E2E 8 | covered |
| Mark collected with who/when/ID verified (8.6) | ClearanceService | ClearanceDeskTest | covered |
| Public /verify/clearance/{code}, minimal data, integrity warning, unguessable code (8.7) | Controllers verify route, HashChain | ClearanceDeskTest, Browser E2E 7 | covered |
| Notifications table (8.8) incl. admin_office daily digest, reminder + escalation | ClearanceNotifier, ClearanceReminderService, schedule | ClearanceFlowTest, ClearanceReminderTest | covered |
| Email/SMS through notification module (8.8) | ExternalMessenger log driver (D-013, D-015) | ClearanceReminderTest (log driver) | deviation (D-013) |
| Approver dashboards: waiting list, filters, counts on dashboard, bulk view, reject needs reason (8.9) | Pages/Clearance/PendingApprovals, Widgets/ClearanceWaiting | ClearancePagesTest, ClearanceFlowTest; dashboard count widget test added in this review | covered |
| Data model tables (9) | database/migrations (names adapted: role_scopes, mfa_role_policies, grading_scales, ...) | Rbac/RoleKeyMigrationTest and all feature tests | deviation (D-007, D-011 naming) |
| Seed `seed:test`: one user per role, 2 heads, 2 provosts, 5 student states, results incl. retake, signatures, 2FA user, MCP integration (10.1) | Console/Commands, Database/Seeders/Testing | SeedTestCommandTest, SeedSecurityFixturesTest, SeedDemoCommandTest | covered |
| Unit tests 10.2 (GPA, state machine, scope, hash chain, TOTP, recovery, UA) | see above | see above | covered |
| Integration tests 10.3 (gate, optimistic lock, audit, revoked session, password change, integration rules, read-only, limit) | see above | ClearanceFlowTest (lock is sequential version race, not parallel), AuthenticationTest | covered |
| Role x tool matrix, destructive preview, schema, parity, revoked token (10.4) | Mcp tests | Mcp/RoleToolMatrixTest, ToolContractTest, ParityTest, AuthenticationTest | covered |
| Assistant tests (10.5) | see above | AssistantChatTest, FeatureIndexTest | covered |
| E2E 1-15 (10.6) | tests/Browser | ProfileAndResultsTest (1,2), ClearanceJourneyTest (3-8), AssistantTest (9), McpCourseTest (10), DevicesTest (11), TwoFactorTest (12,15), McpIntegrationsTest (13,14) | covered |
| CI runs lint, unit, integration, MCP, E2E with fresh DB and seed (10.7) | .github/workflows/tests.yml, .autopilot/verify.sh | n/a | covered |
| CI stores traces/screenshots/video on failure (10.7) | tests.yml uploads artifacts | n/a | partial (screenshots/logs only, no video) |
| Coverage >= 90% for calculator, state machine, policies (10.7) | CI runs with `coverage: none` | n/a | missing (not measured) |
| Language Bangla (12.11) | English only (pre-made decision 11) | n/a | deviation |
| docs: ARCHITECTURE_NOTES.md, DECISIONS.md, README_MCP.md, mcp/EXCLUDED.md | docs/, README_MCP.md, mcp/ | ParityTest reads EXCLUDED.md | covered |

Counts (rows above): covered 65, partial 3, missing 2, deviation-documented 10.

### Fixed in this review
- 3A.1 "changing the password ... tells the user so": a `PasswordChanged` in-app notification (with count of devices signed out and a Devices link) is now sent from `UserPasswordObserver`; test in `tests/Feature/Security/SessionTrackingTest.php`.
- 8.9 dashboard counts: `ClearanceWaiting` widget had no test; added to `tests/Feature/Clearance/ClearancePagesTest.php`.

### Open
- Coverage target (>= 90% on calculator, state machine, policies) is not measured: CI runs `coverage: none`; enabling it needs pcov/xdebug in CI (environment change, not a code gap).
- Optional live assistant smoke test (`ASSISTANT_LIVE_TEST=1`) not written: needs a real `ANTHROPIC_API_KEY`, excluded from CI by spec anyway.
- stdio transport is not exercised end to end through a real process; only its revalidation logic is tested.
- Recovery-code Download/Print buttons exist in the view but no test clicks them.
- CI failure artifacts have no video.

## Correctness findings
| # | Where | Severity | Finding | Status |
|---|-------|----------|---------|--------|
| C1 | app/Services/Results/ResultService.php:237 | Medium | CGPA chose the "latest" retake by result id while the transcript used semester order; now shared `attemptSortKey`. | fixed |
| C2 | app/Services/Clearance/ClearanceService.php:78 | Medium | Eligibility was checked outside the transaction (race with duplicate/ineligible apply); now re-checked under a student row lock. | fixed |

## Test quality
Browser suite drives real UI sessions (login, click, assert). Added a browser journey for logged-out sessions on open Livewire pages, and Feature tests for every fix above. No existing tests were deleted, skipped or weakened.
