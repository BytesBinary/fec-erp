# MCP exclusions

Every public method of a class under `app/Services/**` is either exposed as an MCP tool (the tool declares
it with `->covers('Class::method')`) or listed here with a reason. `tests/Mcp/ParityTest.php` fails when a
new public service method is neither, so "everything can be done through MCP" stays true as the code grows.

Format: one entry per line, `` `Class::method` `` or `` `Class::*` `` or `` `*::method` `` (any class), then a dash and the reason.

## Security-sensitive, requires interactive step-up (web only)

- `App\Services\Security\TwoFactorService::*` — enabling/disabling 2FA and recovery codes are web-only, they need password + code step-up.
- `App\Services\Security\RecoveryCodeService::*` — recovery codes are web-only.
- `App\Services\Security\TrustedDeviceService::*` — trusted devices are web-only.
- `App\Services\Security\MfaPolicy::*` — per-role 2FA policy is changed on the web (super admin).
- `App\Services\Security\UserSecurityService::*` — session revocation and 2FA reset for other users are web-only (reason + audit trail on screen).
- `App\Services\Security\SessionTracker::begin` — creates the browser session record at login.
- `App\Services\Security\SessionTracker::find` — resolves the current browser session.
- `App\Services\Security\SessionTracker::hash` — internal helper.
- `App\Services\Security\SessionTracker::touch` — internal housekeeping.
- `App\Services\Security\SessionTracker::revoke` — revoking sessions is web-only.
- `App\Services\Security\SessionTracker::revokeOthers` — revoking sessions is web-only.
- `App\Services\Security\SessionTracker::revokeAll` — revoking sessions is web-only.
- `App\Services\Security\SessionTracker::prune` — scheduled housekeeping.
- `App\Services\Security\DeviceParser::*` — internal user-agent parsing.
- `App\Services\Mcp\ClientSnippets::*` — renders the connection snippets of the web setup wizard.
- `App\Services\Mcp\IntegrationService::*` — creating, renaming and stopping AI integrations is web-only (needs a fresh 2FA code).
- `App\Services\Mcp\McpSettingsService::*` — global MCP switches are web-only (super admin).
- `App\Services\Clearance\StaffSignatureService::*` — signature images are uploaded through the web (binary file, personal).

## Internal collaborators (no user operation of their own)

- `App\Services\Audit\AuditLogger::*` — the audit writer itself.
- `App\Services\Clearance\ApproverResolver::*` — authorization helper used by the clearance service.
- `App\Services\Clearance\ClearanceNotifier::*` — sends notifications as a side effect of tools.
- `App\Services\Clearance\ClearancePrintService::*` — renders the printable document in the browser (`clearance_print` records the print and returns its URLs).
- `App\Services\Clearance\EligibilityChecker::*` — used by `clearance_check_eligibility` and `clearance_apply`.
- `App\Services\Clearance\EligibilityResult::*` — value object.
- `App\Services\Clearance\HashChain::genesis` — internal.
- `App\Services\Clearance\HashChain::hashFor` — internal.
- `App\Services\Clearance\HashChain::lastHash` — internal.
- `App\Services\Clearance\QrCodeService::*` — image rendering for the print view.
- `App\Services\Clearance\RequestNumberGenerator::*` — internal id generation.
- `App\Services\Clearance\StageResolver::*` — internal chain ordering.
- `App\Services\Assistant\AssistantService::*` — the assistant itself (it runs the same tools in-process).
- `App\Services\Assistant\FeatureIndex::entries` — internal; `help_search_features` covers `search`.
- `App\Services\Assistant\FeatureIndex::menuClasses` — internal, used by the completeness test.
- `App\Services\Assistant\FeatureIndex::entriesFor` — internal; `help_search_features` covers `search`.
- `App\Services\Assistant\PiiMasker::*` — masking helper.
- `App\Services\Assistant\SystemPromptBuilder::*` — prompt assembly.
- `App\Services\Assistant\Providers\*` — language-model adapters.
- `App\Services\Notifications\*` — message delivery driver.
- `App\Services\Profile\ProfileCompletionChecker::*` — used by `me_get_profile`.
- `App\Services\Profile\ProfileService::studentOf` — internal lookup.
- `App\Services\Profile\ProfileService::describe` — staff-side profile read; `me_get_profile` covers self.
- `App\Services\Profile\ProfileService::syncCompletion` — internal bookkeeping.
- `App\Services\Profile\ProfileService::lockFieldsForClearance` — side effect of `clearance_apply`.
- `App\Services\Profile\ProfileService::isGated` — internal gate check.
- `App\Services\Results\GradingScaleService::bands` — internal cache read.
- `App\Services\Results\GradingScaleService::forget` — internal cache reset.
- `App\Services\Results\GradingScaleService::gradeFor` — used by `result_enter_marks`.
- `App\Services\Results\ResultService::publishedSummary` — internal summary for eligibility.
- `App\Services\Halls\HallResidencyService::*` — see tools; unlisted methods are covered.

## Intentionally not exposed

- `*::delete` — hard deletion is never exposed to AI clients; deactivate/archive tools exist where the domain supports it.
- `*::restore` — restoring soft-deleted rows is an admin action done on the web.
- `*::query` — returns an Eloquent builder (not a user operation); list tools wrap it.
- `*::permission` — internal permission-name helper.
- `App\Services\RoutineGeneratorService::*` — legacy routine generator has no actor/authorization; it stays in the web UI until it is refactored onto the service pattern.
