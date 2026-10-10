# Autopilot Progress

Branch: `autopilot/erp-features` · Spec: `IMPLEMENTATION_SPEC.md` · Plan: `docs/ARCHITECTURE_NOTES.md` §10 ·
Decisions: `docs/DECISIONS.md` · Verify: `.autopilot/verify.sh`

## Phase checklist

- [x] **0** — Discovery, architecture notes, decisions, verify.sh, E2E tooling (2026-10-10)
- [x] **1** — RBAC foundation, central authorize(), audit log, service layers, seed:test skeleton (2026-10-10; verify: Unit 35, Feature 72, Browser 2 — all pass)
- [x] **1S** — Account security: sessions/devices, new-device alerts, TOTP 2FA, recovery codes, admin tools (E2E 11, 12, 15) (2026-10-10; verify: Unit 47, Feature 146, Browser 7 — all pass)
- [x] **2** — Profile gate + Results/CGPA (E2E 1, 2) (2026-10-10; verify: Unit 70, Feature 195, Browser 9 — all pass)
- [x] **3** — Clearance core (E2E 3 to READY, 4, 5, 6) (2026-10-10; verify: Unit 86, Feature 253, Browser 14 — all pass)
- [ ] **4** — Clearance desk, print/PDF, verification (E2E 3 full, 7, 8)
- [ ] **5** — MCP server + full tool catalog, contract/matrix/parity tests (E2E 10), README_MCP.md
- [ ] **5M** — MCP access management (E2E 13, 14)
- [ ] **6** — AI assistant + feature index (E2E 9)
- [ ] **7** — Hardening, reminders, a11y, seed:demo, CI workflow, docs

## Phase 0 — done

- Stack: Laravel 12 / Filament 5 / Livewire 4 / Pest 4 / spatie-permission via Filament Shield; MySQL in dev,
  SQLite for tests.
- Added E2E tooling: `pestphp/pest-plugin-browser` + `playwright` + Chromium headless shell;
  `tests/Browser/SmokeTest.php` (real login form → dashboard; guest redirect) passes.
- `tests/BrowserTestCase.php` forces the compiled Vite manifest (a dev `public/hot` file may exist).
- `.autopilot/verify.sh`: deps → asset build (if stale) → migrate / reset / migrate / seed on throw-away
  SQLite → Pint → every testsuite in phpunit.xml (auto-discovers new suites, e.g. `Mcp`). Uses `seed:test`
  automatically once it exists. **Passing** (Unit, Feature, Browser).
- Fixed pre-existing problems (D-004): scaffold `ExampleTest` was failing; `CourseSeeder` was MySQL-only.
- Wrote `docs/ARCHITECTURE_NOTES.md` (incl. per-phase file/module plan) and `docs/DECISIONS.md`.

## In progress

- (none)

## Next up (Phase 4)

See ARCHITECTURE_NOTES §10 "Phase 1". Start with migrations (is_active, role rename, role_scopes,
audit_logs, programs, semesters, notices), `RoleKey` enum, `config/erp.php`, `Authorizer`, `AuditLogger`,
services, `seed:test`.

## Known issues / notes for later phases

- Local PHP has no `gd` extension (D-012) — dompdf PNG fidelity limited locally; use SVG/data URIs.
- `laravel/mcp` is currently only a dev (transitive) dependency — promote to `require` in Phase 5.
- No PHPStan/Larastan: verify.sh reports static analysis as SKIP.
- No test CI workflow yet (only deploy.yml) — add `.github/workflows/tests.yml` in Phase 7.
- A developer `composer run dev` session (artisan serve :8000 + vite) was running during Phase 0; tests and
  verify.sh do not touch it or its MySQL database.
- Browser-test tips: Filament input ids like `form.email` → selector `[id="form.email"]`; click
  `button[type=submit]` rather than `press('Sign in')`; wait after Livewire submits.
- `AdminUserSeeder` creates `admin@fec.edu.bd` but assigns no role (pre-existing; Phase 1 seeds roles).

- Browser tests: each `visit()` is a separate context; tests/Support/ResetRequestState (prepended in BrowserTestCase) resets auth/session/scoped state per request. Filament modal inputs have ids like `mountedActionSchema0.password`. Click by button text ('Verify'), because topbar 'Sign out' is also a submit button. Run browser tests with output redirected to a file (chrome keeps pipes open).
- Phase 1S exposes events `TwoFactorDeactivated` and `McpIntegrationsStopRequested`; Phase 5M must add listeners that revoke integrations.
- Phase 2: browser server cannot receive multipart uploads (D-017). Seeded student data/expected CGPAs: TestDataset::EXPECTED_RESULTS. Feature tests that switch users in one test must call `$this->flushSession()`.
- Phase 3: eligibility rule D-018; ClearanceService::transition() is the only status writer; ClearanceService has hooks for Phase 4 (markPrinted/markCollected, desk). Browser tests flush cache per request (login throttle).
