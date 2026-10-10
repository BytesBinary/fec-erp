# Morning report — FEC ERP autopilot run

Branch: `autopilot/erp-features` (nothing was pushed). All phases 0, 1, 1S, 2, 3, 4, 5, 5M, 6, 7 are built, then an independent review (two fresh reviewers) fixed what it found.

## Start it

```bash
cd /home/nprime/github/others/fec-erp
composer install && npm install && npm run build
cp -n .env.example .env && php artisan key:generate     # set DB_* in .env (MySQL, database fec_erp)
php artisan seed:demo --fresh                           # migrates from scratch and loads demo data (refuses in production)
php artisan serve                                       # app at http://127.0.0.1:8000
php artisan schedule:work                               # optional: reminders, expiry notices, session pruning
```

`composer run dev` starts server, queue, logs and Vite together. `seed:demo --fresh` drops every table, so use it only on a throw-away database.

Run all checks: `.autopilot/verify.sh` (deps, build, migrate+seed on SQLite, Pint, Unit, Feature, Mcp, Browser).

## Demo logins (URL http://127.0.0.1:8000, password `password` for all)

| Role | Email |
|---|---|
| Super admin | superadmin@fec.test |
| Admin office | office@fec.test |
| Head of institution | head@fec.test |
| Principal | principal@fec.test |
| Department head (CSE) | head.cse@fec.test |
| Department head (EEE) | head.eee@fec.test |
| Hall provost (BJH) | provost.bjh@fec.test |
| Hall provost (SKH) | provost.skh@fec.test |
| Librarian | librarian@fec.test |
| Teacher | teacher@fec.test |
| Student, eligible, hall resident | student.eligible@fec.test |
| Student, eligible, non-resident | student.nonresident@fec.test |
| Student, eligible, library loan outstanding | student.libraryloan@fec.test |
| Student, program unfinished | student.unfinished@fec.test |
| Student, profile incomplete (profile gate) | student.incomplete@fec.test |
| Teacher with 2FA (test fixture) | mfa.teacher@fec.test, TOTP secret `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP` |

The 2FA and MCP-token fixtures (`mfa.teacher`, `mcp.teacher`, the seeded token) exist for tests. They are also in the demo seed, so never run `seed:demo` against real data.

## Test results

Final full `.autopilot/verify.sh` (2026-10-10): **RESULT: ALL CHECKS PASSED**, exit 0. Unit 86, Feature 383, Mcp 82, Browser 27 (all passing). Static analysis is not configured (skipped).

## Five-minute click-through

1. Sign in as `student.incomplete@fec.test`: you are held on **Complete profile** until the required fields are filled.
2. Sign in as `student.eligible@fec.test`: **Results** shows CGPA 3.56 and the semester GPAs. Open **Clearance → Apply**.
3. Sign in as `provost.bjh@fec.test` → **Clearance → Pending**: approve the hall stage. Then `librarian@`, `head.cse@`, `head@` in that order. The student ends at *ready for collection*.
4. Open the request and use **Print**; reprints carry a DUPLICATE watermark. Scan or open `/verify/clearance/{code}` (public).
5. Sign in as `student.libraryloan@fec.test`, apply, and see the library stage reject.
6. As `superadmin@fec.test`: **Security → MCP integrations**, **Two-factor policy**, **Settings → AI integrations**, audit log.
7. As any user, click **Assistant** (bottom right) and ask for a feature or your results. Writes show a confirmation card first.

## Connect an MCP client

Tokens are created only in the web app: turn on 2FA (**Settings → Two-factor authentication**), then **Settings → AI integrations** and run the wizard (the token is shown once, with a ready snippet per client). Endpoint: `POST /mcp` with `Authorization: Bearer erpmcp_…`. Details: `README_MCP.md`. Tools shown depend on the user's role.

## What was built

RBAC with one central authorizer and audit log; account security (sessions, devices, TOTP, recovery codes, trusted devices, role-based 2FA policy); student profile gate; results and CGPA workflow; the full clearance workflow (hall, library, department, head) with hash chain, signatures, print/PDF and public verification; an MCP server with 109 role-filtered tools; MCP access management; an in-app AI assistant (confirmation cards, PII masking); demo seeder, reminders, accessibility fixes, CI workflow.

## Review outcome

`docs/REVIEW.md` has the details. Fixed: revoked sessions on open Livewire pages, MCP connections surviving revocation, tamperable Livewire ids, profile photo path, assistant link injection, hash chain covering signatures, double-confirm of assistant writes, retake ordering in CGPA, clearance eligibility race, missing password-change notice, an untested approver count widget.

## Known issues and what is left

- Coverage percentage is not measured (needs a coverage driver in CI).
- No live-Claude assistant smoke test (needs a real `ANTHROPIC_API_KEY`); the assistant is tested with a fake provider.
- The stdio MCP transport is not tested through a real process.
- Recovery-code Download/Print buttons are not click-tested.
- Photo upload is not covered by a browser test (harness cannot send multipart uploads, D-017); PDF omits raster images when GD is missing.
- Deliberate deviations (no geo-IP, no accountant role, no Bangla, log-only email/SMS, no re-confirmation on approval): `docs/DECISIONS.md` D-023.

Docs: `docs/DECISIONS.md`, `docs/ARCHITECTURE_NOTES.md`, `docs/REVIEW.md`, `README_MCP.md`.
