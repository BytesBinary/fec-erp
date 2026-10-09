# AUTOPILOT MODE — unattended overnight run

You are running **unattended**. Nobody is watching and nobody can answer questions until morning.
These rules OVERRIDE any instruction in IMPLEMENTATION_SPEC.md that says "ask", "wait for approval",
or "stop and ask the product owner".

## 1. Never stop to ask
- Never wait for approval. Never end a turn with a question.
- When something is ambiguous, pick the most reasonable option, implement it, make it configurable
  where cheap, and record it in `docs/DECISIONS.md` (decision, alternatives, why, how to change it).
- Phase 0's "wait for approval" step is skipped. Write `docs/ARCHITECTURE_NOTES.md` and continue.

## 2. Decisions already made (use these defaults)
| Topic | Decision |
|---|---|
| Stack | Use whatever the codebase already uses. If the repo is empty or has no app yet, use Next.js (App Router, TypeScript) + PostgreSQL + Prisma + Playwright + Vitest, run via docker compose. |
| Head vs Principal | They are different people. "Head of Institution" (Head Sir) approves digitally as the last online stage. The Principal only signs the printed copy on paper. |
| Rejection | The request resumes at the stage that rejected it. Earlier approvals stay valid. |
| Clearance eligibility | Profile complete + all final results published + account active + no other active request. |
| Grading scale | Bangladesh UGC uniform scale: 80–100 A+ 4.00, 75–79 A 3.75, 70–74 A- 3.50, 65–69 B+ 3.25, 60–64 B 3.00, 55–59 B- 2.75, 50–54 C+ 2.50, 45–49 C 2.25, 40–44 D 2.00, <40 F 0.00. Store it as config. |
| Retakes and improvements | The best attempt counts. Only one attempt per course goes into the CGPA. A failed course with no passing attempt counts as 0.00 with its credits. Make this configurable. |
| Rounding | Compute at full precision, display at 2 decimals, round half up. |
| Approval security | No OTP. Approvers must be logged in and have a signature image uploaded. |
| Notifications | In-app always. Email/SMS only if the codebase already has them; otherwise build a pluggable interface with a log-only driver. |
| AI assistant | Claude API via `ANTHROPIC_API_KEY` and `ASSISTANT_MODEL` env vars. Mask NID and phone numbers before sending data to the model. If no key is set, the widget falls back to feature search. All tests use FakeProvider. |
| Language | English UI, with strings kept in an i18n-ready structure (Bangla can be added later). |
| 2FA | TOTP authenticator app. Optional for everyone by default, required for MCP. Super admin can enforce it per role (off by default). "Trust this device" allowed for 30 days. |
| Sessions | Inactivity expiry: 30 days with "remember me", 12 hours without. A password change logs out all other devices. |
| MCP integrations | Default expiry 90 days (options 30/90/180/365). "Never" is disabled unless super admin allows it. Max 5 active integrations per user. A step-up 2FA code is required for every new integration. |
| Missing modules (halls, library) | Build minimal versions sufficient for clearance (hall assignment + dues, library loans + fines). |

## 3. Safety rules (hard limits)
- Work only inside this repository. Do not modify files outside it.
- NEVER run `git push`, `git reset --hard`, `git clean -fdx`, `git checkout -- .`, force operations, or delete branches.
- NEVER delete or rewrite existing migrations. Only add new ones.
- NEVER connect to a production database or use production credentials. Use a local or test database
  (docker compose or SQLite for tests if the stack allows). If `.env` points at a remote DB, create `.env.test`/`.env.local`
  for a local one and use that.
- NEVER commit secrets. Put placeholders in `.env.example`.
- Do not disable, skip or delete existing tests to make the suite pass. Fix the code. Quarantine a test only if it was
  already failing before you started, and list it in the morning report.
- If a tool or service is genuinely unavailable (e.g. no Docker), work around it and note it in the report.
  Do not loop forever on the same error. After 3 failed approaches, write it down and move on.

## 4. Working rhythm
- Read `IMPLEMENTATION_SPEC.md`, `docs/ARCHITECTURE_NOTES.md`, `docs/DECISIONS.md` and `.autopilot/PROGRESS.md` at the start
  of every phase, because each phase is a fresh session with no memory.
- Commit after each meaningful step with conventional commit messages, on the current branch.
- Keep `.autopilot/PROGRESS.md` updated: what is done, what is in progress, what is left, and known issues.
- Before declaring a phase done, run `.autopilot/verify.sh` and make it pass.
- Prefer subagents for independent parallel work (e.g. writing tests while implementing), and a separate
  reviewer subagent that has not seen your reasoning to check each phase against the spec.

## 5. Verification script (created in Phase 0, maintained after)
`.autopilot/verify.sh` must be an executable bash script that, from the repo root:
1. Installs dependencies if needed, starts required services (e.g. `docker compose up -d db`), runs migrations and `seed:test`.
2. Runs lint, type-check, unit, integration, MCP contract and E2E tests — whichever exist so far.
3. Exits 0 only if all of them pass. It prints a clear summary at the end.
It must be idempotent and safe to run repeatedly.

## 6. Morning deliverables
By the end, these must exist and be accurate:
- `MORNING_REPORT.md` at the repo root, covering:
  - What was built, per feature
  - Final test results
  - **Exact commands to start the app**, plus the URL
  - **Login credentials for every seeded demo user**, one per role
  - A 5-minute click-through demo script: profile gate → results → full clearance journey → print → QR verify → AI assistant → Devices page (log out another device) → enable 2FA → MCP setup wizard → see and stop an integration
  - How to connect an MCP client
  - Decisions made, known issues and what is left
- `README_MCP.md`, `docs/DECISIONS.md`, `docs/ARCHITECTURE_NOTES.md`
- A demo seed (`seed:demo`) with realistic data, so the app looks alive when opened
