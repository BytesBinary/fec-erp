# FEC ERP documentation

Everything about the application, in one place. Start with **FEATURES.md** (what the system does) and **OPERATIONS.md** (how to run it).

| Document | What it contains |
|---|---|
| [FEATURES.md](FEATURES.md) | **Complete feature list** by module, with who can use each feature and the screenshots that show it, plus the must-not-miss highlights |
| [PRESENTATION.md](PRESENTATION.md) | Slide-by-slide outline (24 slides, plus a 10-slide version), live demo script, likely questions |
| [WORKFLOWS.md](WORKFLOWS.md) | Diagrams: student journey, clearance states, results workflow, portal sync, email pipeline, security checks, how AI clients act |
| [ROLES_AND_PERMISSIONS.md](ROLES_AND_PERMISSIONS.md) | The 9 roles, data scopes, the full permission catalog and the default role → permission matrix (generated from the code) |
| [MCP_TOOLS.md](MCP_TOOLS.md) | The 130 MCP tools by domain: permission, kind (read / write / destructive), parameters (generated from the code) |
| [DATA_MODEL.md](DATA_MODEL.md) | All 72 tables by module with purpose and exact columns (generated from the migrations) |
| [OPERATIONS.md](OPERATIONS.md) | Install, environment settings, scheduled jobs, result-portal first use, turning email on, MCP, accounts, tests and CI, troubleshooting |
| [EMAIL_EVENTS.md](EMAIL_EVENTS.md) | The ~70 email events: recipient, default, immediate / digest, content, what is excluded; architecture and recovery |
| [ARCHITECTURE_NOTES.md](ARCHITECTURE_NOTES.md) | Layers, services, middleware, how the modules fit together |
| [DECISIONS.md](DECISIONS.md) | 27 recorded design decisions (D-001 … D-027) |
| [REVIEW.md](REVIEW.md) | Independent security / correctness / spec-coverage review and its fixes |
| [../README_MCP.md](../README_MCP.md) | How to connect an AI client to the MCP server |
| [../IMPLEMENTATION_SPEC.md](../IMPLEMENTATION_SPEC.md) | The original specification the system was built against |

## Screenshots

Screenshots are produced from the demo dataset with a real browser, one per screen and role, and saved to `docs/screenshots/<role>/<screen>.png`. The folder is **ignored by git** (images are large and regenerated at will), so it exists only on the machine that generated it. File names used in the other documents follow `<role>__<screen>` (for example `student/results.png`).

To regenerate them:

```bash
cp docs/tools/DocsScreenshotsTest.php.stub tests/Browser/DocsScreenshotsTest.php
npm run build
php artisan test tests/Browser/DocsScreenshotsTest.php      # about 10 minutes, needs Chromium
# then move the PNGs from tests/Browser/Screenshots/ into docs/screenshots/<role>/ (the script name before "__" is the role)
rm tests/Browser/DocsScreenshotsTest.php
```

The generator seeds the demo data, adds sample portal pulls, publications and email deliveries so every screen has content, logs in as each role and captures each page. It is kept as a `.stub` so it never runs as part of the normal test suite.

## Known gaps (also listed in the pull request)

- **No password-reset flow.** A new account's first password is handed over by the office; it is never sent by email.
- **Custom roles are global** unless listed under `rbac.role_scopes` in `config/erp.php`; there is no UI picker for a role's data scope yet (D-024).
- **The result parser has seen four real pages**; watch the first real publications and keep the shadow mode until you trust it.
- **Coverage percentage is not measured** in CI, and the stdio MCP transport has no end-to-end process test.
- **Photo upload** is not covered by a browser test (the test harness cannot send multipart uploads, D-017).
- **PDF images**: the clearance PDF omits raster images when the PHP `gd` extension is missing (the HTML print view is the primary output).

## Where things are in the code

| Area | Path |
|---|---|
| Filament panel, pages, resources, widgets | `app/Filament` |
| Domain logic (all channels call these) | `app/Services/**` |
| Models, enums, policies, observers, jobs, events | `app/Models`, `app/Enums`, `app/Policies`, `app/Observers`, `app/Jobs`, `app/Events` |
| Authorization | `app/Support/Authorization` |
| MCP server, tool registry and domains | `app/Mcp` |
| Assistant | `app/Services/Assistant`, `app/Http/Controllers/Assistant`, `resources/views/assistant` |
| Result portal | `app/Services/ResultPortal`, `config/result_portal.php` |
| Email notifications | `app/Services/Notifications`, `config/notification_events.php` |
| Configuration | `config/*.php` (`erp.php` holds the role / permission defaults) |
| Tests | `tests/Unit`, `tests/Feature`, `tests/Mcp`, `tests/Browser` |
