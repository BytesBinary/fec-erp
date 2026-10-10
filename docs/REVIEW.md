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
No missing section was identified in the spot review beyond items already tracked in `docs/DECISIONS.md`. Full spec walk-through is covered by the phase test suites (Feature, Mcp, Browser).

## Correctness findings
| # | Where | Severity | Finding | Status |
|---|-------|----------|---------|--------|
| C1 | app/Services/Results/ResultService.php:237 | Medium | CGPA chose the "latest" retake by result id while the transcript used semester order; now shared `attemptSortKey`. | fixed |
| C2 | app/Services/Clearance/ClearanceService.php:78 | Medium | Eligibility was checked outside the transaction (race with duplicate/ineligible apply); now re-checked under a student row lock. | fixed |

## Test quality
Browser suite drives real UI sessions (login, click, assert). Added a browser journey for logged-out sessions on open Livewire pages, and Feature tests for every fix above. No existing tests were deleted, skipped or weakened.
