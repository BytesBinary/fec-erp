# AUTOPILOT MODE — unattended run (interactive, in tmux, driven by /goal)

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

- Do not kill or control the tmux session you run in, and do not send keystrokes to it. Do not start other
  `claude` processes (no nested `claude -p`). Use your own subagents instead.

## 4. Working rhythm (one long interactive session driven by /goal)
- You run in ONE interactive Claude Code session inside tmux, driven by `/goal`. The goal is re-checked after every turn,
  and a watchdog may re-send the same `/goal` if the session goes idle. When the goal arrives again, do NOT start over:
  re-read `.autopilot/PROGRESS.md` and `git log --oneline -30`, then continue exactly where the work stopped.
- Your context will be compacted many times. After each compaction, re-read this file, `.autopilot/PROGRESS.md`,
  `docs/ARCHITECTURE_NOTES.md` and `docs/DECISIONS.md` before continuing. PROGRESS.md is your memory: keep it accurate.
- **Earlier headless run:** `.autopilot/state*/` folders and "checkpoint after phase N" commits from an older script are
  NOT evidence of work (they only contain empty marker files). Only phase 0 was really finished, and phase 1 was partly built
  (central Authorizer, scope policies, admin users with roles + scopes). Verify the real state from code and tests first.
- PROGRESS.md format: one row per phase (0, 1, 1S, 2, 3, 4, 5, 5M, 6, 7, review, report) with status
  (todo / in progress / done), date, test counts from verify.sh, and notes. Mark a phase done ONLY after verify.sh passes
  with that phase's tests included.
- Commit after each meaningful step with conventional commit messages, on the current branch.
- End each phase by showing the verify.sh summary in your reply, because the goal checker only sees the conversation.
- Use subagents for independent parallel work (e.g. writing E2E tests while implementing). For the final review, use a
  fresh subagent that has not seen your reasoning (see the review priorities in §7).
- The user may type into the session to watch or steer. Their messages take priority. Answer briefly, then continue the goal.
- Usage limits are handled by Claude Code (it waits and continues). After a limit, just continue from PROGRESS.md.

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

## 7. Independent review (after phase 7, before the morning report)
Start a fresh subagent that did not write the code. Give it `git diff <first autopilot commit>..HEAD` and IMPLEMENTATION_SPEC.md.
It writes `docs/REVIEW.md` and then fixes every issue (with tests). Priorities:
1. Security: every MCP tool, API route, page and assistant action enforces authorize() and scope server-side; students never
   see other students' data or unpublished results; the clearance stage order cannot be bypassed; tokens are hashed; no secrets
   are committed; revoked sessions and integrations are rejected immediately; MCP is impossible without 2FA; TOTP secrets are
   encrypted, recovery codes are hashed and single-use; 2FA cannot be bypassed via API, assistant, MCP or "trusted device".
2. Spec coverage: walk every section and list anything missing or wrong.
3. Correctness: CGPA math, state machine edge cases, concurrency.
4. Tests: E2E tests are real UI journeys, not mocked shortcuts.

## 8. Finishing
When every phase, the review and MORNING_REPORT.md are done, verify.sh passes and git status is clean, create
`.autopilot/DONE` containing a 5-line summary, and commit it. That file tells the watchdog the work is finished.
