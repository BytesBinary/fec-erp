#!/usr/bin/env bash
# =============================================================================
#  ERP Autopilot — runs Claude Code unattended through every phase of
#  IMPLEMENTATION_SPEC.md: build -> test -> fix -> independent review -> report.
#
#  Usage (from your repo root):
#     ./autopilot/run.sh            # run in this terminal
#     ./autopilot/run.sh --bg       # run in background; safe to close terminal
#     tail -f .autopilot/logs/autopilot.log     # watch progress
#
#  Re-running is safe: finished phases are skipped, so if it stops for any
#  reason (laptop slept, rate limit), just run it again.
#
#  Optional env vars:
#     MODEL=opus            model alias for Claude Code (default: opus)
#     MAX_TURNS=500         max agent turns per session
#     FIX_ATTEMPTS=4        fix-and-retest rounds per phase if tests fail
#     BUDGET_USD=           stop a session at this estimated spend (API billing only)
#     BRANCH=autopilot/erp-features
# =============================================================================
set -uo pipefail

MODEL="${MODEL:-opus}"
MAX_TURNS="${MAX_TURNS:-500}"
FIX_ATTEMPTS="${FIX_ATTEMPTS:-4}"
BUDGET_USD="${BUDGET_USD:-}"
BRANCH="${BRANCH:-autopilot/erp-features}"

SPEC="IMPLEMENTATION_SPEC.md"
RULES="autopilot/AUTOPILOT_RULES.md"
AP_DIR=".autopilot"
STATE_DIR="$AP_DIR/state"
LOG_DIR="$AP_DIR/logs"
MAIN_LOG="$LOG_DIR/autopilot.log"

# ---------- background mode --------------------------------------------------
if [[ "${1:-}" == "--bg" ]]; then
  mkdir -p "$LOG_DIR"
  nohup "$0" > "$LOG_DIR/nohup.out" 2>&1 &
  echo "Autopilot started in background (PID $!)."
  echo "Watch progress:  tail -f $MAIN_LOG"
  exit 0
fi

# ---------- keep the machine awake -------------------------------------------
if [[ -z "${AUTOPILOT_AWAKE:-}" ]]; then
  export AUTOPILOT_AWAKE=1
  if command -v caffeinate >/dev/null 2>&1; then           # macOS
    exec caffeinate -dims "$0" "$@"
  elif command -v systemd-inhibit >/dev/null 2>&1; then    # Linux
    exec systemd-inhibit --what=sleep:idle --why="ERP autopilot" "$0" "$@"
  fi
  # Windows/WSL: set Power options -> Sleep: Never before you go to bed.
fi

mkdir -p "$STATE_DIR" "$LOG_DIR"
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$MAIN_LOG"; }
die() { log "FATAL: $*"; exit 1; }

new_uuid() {
  if command -v uuidgen >/dev/null 2>&1; then uuidgen | tr 'A-Z' 'a-z'
  elif [[ -r /proc/sys/kernel/random/uuid ]]; then cat /proc/sys/kernel/random/uuid
  else python3 -c 'import uuid; print(uuid.uuid4())'; fi
}

# ---------- preflight --------------------------------------------------------
command -v claude >/dev/null 2>&1 || die "Claude Code not installed. See https://docs.claude.com/en/docs/claude-code/overview"
claude auth status >/dev/null 2>&1 || die "Claude Code is not logged in. Run: claude auth login"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "Run this from the root of your git repository."
[[ -f "$SPEC" ]]  || die "$SPEC not found in repo root."
[[ -f "$RULES" ]] || die "$RULES not found."

# Keep autopilot logs out of git
grep -qxF "$AP_DIR/logs/" .gitignore 2>/dev/null || echo "$AP_DIR/logs/" >> .gitignore

# Work on a dedicated branch so your main branch is untouched
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
  if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git checkout "$BRANCH" || die "Could not switch to $BRANCH"
  else
    git checkout -b "$BRANCH" || die "Could not create $BRANCH"
  fi
fi
if [[ -n "$(git status --porcelain)" ]]; then
  git add -A && git commit -qm "chore(autopilot): snapshot before autopilot run"
fi
[[ -f "$STATE_DIR/base_commit" ]] || git rev-parse HEAD > "$STATE_DIR/base_commit"
BASE_COMMIT="$(cat "$STATE_DIR/base_commit")"

log "=== Autopilot starting on branch $BRANCH (base $BASE_COMMIT), model=$MODEL ==="

# ---------- one Claude Code session, with retry + resume ---------------------
# run_claude <label> <prompt>
run_claude() {
  local label="$1" prompt="$2"
  local sid; sid="$(new_uuid)"
  local logf="$LOG_DIR/${label}.jsonl"
  local attempt=1 rc=1
  local -a common=(
    --dangerously-skip-permissions
    --model "$MODEL"
    --max-turns "$MAX_TURNS"
    --append-system-prompt-file "$RULES"
    --output-format stream-json --verbose
    --disallowedTools "Bash(git push *)" "Bash(git reset --hard *)" "Bash(git clean *)" "Bash(git branch -D *)" "Bash(rm -rf / *)" "Bash(rm -rf ~ *)"
  )
  [[ -n "$BUDGET_USD" ]] && common+=(--max-budget-usd "$BUDGET_USD")

  log "  -> session '$label' ($sid)"
  claude -p "$prompt" --session-id "$sid" --name "autopilot-$label" "${common[@]}" >> "$logf" 2>&1
  rc=$?
  while [[ $rc -ne 0 && $attempt -lt 4 ]]; do
    local wait=$(( attempt * 300 ))
    log "     session '$label' exited with code $rc (rate limit, turn limit or network). Waiting ${wait}s, then resuming (attempt $((attempt+1))/4)."
    sleep "$wait"
    claude -p "You were interrupted. Re-read .autopilot/PROGRESS.md and git log, then continue the same task until it is fully done: $prompt" \
      --resume "$sid" "${common[@]}" >> "$logf" 2>&1
    rc=$?
    attempt=$((attempt+1))
  done
  log "  <- session '$label' finished (exit $rc)"
  return $rc
}

verify() {
  local out="$LOG_DIR/verify-$1.txt"
  if [[ ! -x "$AP_DIR/verify.sh" ]]; then
    echo "verify.sh missing or not executable" > "$out"; return 1
  fi
  log "  verifying ($1)..."
  timeout 3600 bash "$AP_DIR/verify.sh" > "$out" 2>&1
}

checkpoint() {
  git add -A >/dev/null 2>&1
  git commit -qm "chore(autopilot): checkpoint after $1" >/dev/null 2>&1 || true
}

# ---------- phase definitions ------------------------------------------------
declare -A PHASE_TASK
PHASE_TASK[0]="PHASE 0 — Discovery. Study the whole codebase and write docs/ARCHITECTURE_NOTES.md as described in §2 of IMPLEMENTATION_SPEC.md, including the concrete file/module plan for all later phases. Create docs/DECISIONS.md and .autopilot/PROGRESS.md (phase checklist: 0, 1, 1S, 2, 3, 4, 5, 5M, 6, 7). Create the executable .autopilot/verify.sh described in AUTOPILOT_RULES §5, wired to whatever test tooling exists. If E2E tooling is missing, install and configure it now (Playwright unless the repo already uses something else) with one passing smoke test. Make verify.sh pass on the current code. Do not wait for approval."
PHASE_TASK[1]="PHASE 1 — RBAC foundation (§3 of the spec): roles, permissions as data with seeds, scoped user_roles, a single central authorize() + policy functions, audit log for all writes with channel, and service layers for any module that lacks one. Add the deterministic seed:test dataset skeleton (§10.1). Full unit tests for policies. Existing tests must still pass."
PHASE_TASK[1S]="PHASE 1S — Account security (§3A of the spec): server-side session records for every login (revocation must work immediately even with JWTs), the Settings → Security → Devices page with per-device logout and 'log out all other devices', new-device login alerts, password change logging out other devices, TOTP 2FA with QR + manual key, encrypted secret, 10 hashed one-time recovery codes, ±1 step drift, replay protection, 5-attempt lockout, 2FA login step with optional 30-day trusted device, per-role 2FA enforcement setting, and super admin session/2FA reset tools. Unit + integration tests from §10.2/§10.3 and E2E 11, 12 and 15 from §10.6."
PHASE_TASK[2]="PHASE 2 — Student profile completion gate (§6) and Results/CGPA (§7), with configurable required fields and grading scale, the pure GPA/CGPA calculator, the Result menu and print view. Unit tests (table-driven CGPA) plus E2E tests 1 and 2 from §10.6."
PHASE_TASK[3]="PHASE 3 — Clearance core (§8.1–8.5, 8.8, 8.9): stage config, eligibility, the state machine via a single transition() with optimistic locking, approvals with signature snapshots and the hash chain, student apply/timeline/resubmit, approver dashboards, notifications, hall/library minimal modules if missing. Unit tests for the state machine and policies plus E2E tests 3–6 from §10.6 (E2E 3 may stop at READY_FOR_COLLECTION until Phase 4)."
PHASE_TASK[4]="PHASE 4 — Clearance desk (§8.6) and public verification (§8.7): search, A4 print view + server-side PDF with all embedded signatures, QR code, empty Principal signature and seal box, print/reprint logging with DUPLICATE watermark, mark collected, verify page with tamper detection. Complete E2E 3 end to end, plus E2E 7 and 8."
PHASE_TASK[5]="PHASE 5 — MCP server (§4): official MCP SDK, Streamable HTTP mounted at /mcp plus a stdio entrypoint, token authentication backed by the mcp_integrations table (the management UI comes in phase 5M), a single tool registry calling domain services, role-filtered tools/list plus enforced tools/call, confirm/dry-run for destructive tools, resources and prompts, rate limiting, audit. Full tool catalog from §4.2. Contract tests, the role×tool matrix test, the parity test (§4.3), E2E 10, and README_MCP.md. Check the current MCP SDK docs before writing code."
PHASE_TASK[5M]="PHASE 5M — MCP access management (§4.5 and §4.1 of the spec): MCP requires 2FA (UI and server-side check on every request), Settings → AI Integrations page, the 5-step guided setup wizard (client choice, name/access level/expiry, 2FA step-up, token shown once + ready-to-paste client config snippets — check each client's current official MCP config docs first — and a live 'Connected ✓' test), the active integrations list with last used, IP, 7-day call count, stop/rename/activity/stop all, read-only integrations, the per-user limit, notifications, disabling 2FA revoking all integrations, and super admin oversight (all-users list, revoke with reason, global and per-role MCP switches, usage overview). Integration tests from §10.3 and E2E 13 and 14 from §10.6."
PHASE_TASK[6]="PHASE 6 — AI assistant (§5): server-side streaming chat endpoint, AssistantProvider with Claude implementation + FakeProvider, in-process use of the same role-filtered tool registry, an auto-generated feature index with a completeness test, deep links, a confirmation card for writes, PII masking, history, rate limits, and graceful fallback without an API key. The widget appears on every authenticated page. Assistant tests (§10.5) and E2E 9."
PHASE_TASK[7]="PHASE 7 — Hardening (§11 phase 7): pending-approval reminders and escalation, accessibility pass on the new pages, rate limits, docs. Create seed:demo with realistic data. Make sure the WHOLE suite passes. Update README_MCP.md, docs/DECISIONS.md and .autopilot/PROGRESS.md."

# ---------- main loop --------------------------------------------------------
for phase in 0 1 1S 2 3 4 5 5M 6 7; do
  if [[ -f "$STATE_DIR/phase-$phase.done" ]]; then
    log "Phase $phase already done — skipping."
    continue
  fi
  log "=== Phase $phase: start ==="

  run_claude "phase-$phase" "Read IMPLEMENTATION_SPEC.md, autopilot/AUTOPILOT_RULES.md, docs/ARCHITECTURE_NOTES.md, docs/DECISIONS.md and .autopilot/PROGRESS.md if they exist. Then do this phase completely, including its tests, and run .autopilot/verify.sh until it passes. Commit as you go. Update .autopilot/PROGRESS.md at the end.

${PHASE_TASK[$phase]}"
  checkpoint "phase $phase"

  ok=0
  for ((i=1; i<=FIX_ATTEMPTS; i++)); do
    if verify "phase-$phase-try-$i"; then ok=1; break; fi
    log "  verify failed (round $i/$FIX_ATTEMPTS) — starting fix session."
    run_claude "phase-$phase-fix-$i" "The verification script .autopilot/verify.sh is failing after phase $phase. The last 150 lines of its output are below. Find the root causes and fix the CODE (never weaken, skip or delete tests). Re-run .autopilot/verify.sh until it passes, then commit.

----- verify output (tail) -----
$(tail -n 150 "$LOG_DIR/verify-phase-$phase-try-$i.txt")"
    checkpoint "phase $phase fix $i"
  done

  if [[ $ok -eq 1 ]]; then
    touch "$STATE_DIR/phase-$phase.done"
    log "=== Phase $phase: DONE, tests green ==="
  else
    echo "Phase $phase: tests still failing after $FIX_ATTEMPTS fix rounds" >> "$STATE_DIR/incomplete.txt"
    touch "$STATE_DIR/phase-$phase.done"   # move on so the rest still gets built
    log "=== Phase $phase: finished WITH FAILING TESTS — continuing; see MORNING_REPORT ==="
  fi
done

# ---------- independent review (fresh session, has not seen the work) -------
if [[ ! -f "$STATE_DIR/review.done" ]]; then
  log "=== Independent review ==="
  run_claude "review" "You are an independent senior reviewer. You did NOT write this code. Review everything changed since commit $BASE_COMMIT (use git diff $BASE_COMMIT..HEAD) against IMPLEMENTATION_SPEC.md. Priorities:
1) Security: every MCP tool, API route, page and assistant action enforces authorize() and scope server-side; students can never see other students' data or unpublished results; the clearance stage order cannot be bypassed; tokens are hashed; no secrets are committed; revoked sessions and integrations are rejected immediately; MCP is impossible without 2FA; TOTP secrets are encrypted, recovery codes hashed and single-use; 2FA cannot be bypassed via API, assistant, MCP or 'trusted device' tricks.
2) Spec coverage: walk §1–§10 item by item and list anything missing or wrong.
3) Correctness: CGPA math, state machine edge cases, concurrency.
4) Tests: are the E2E tests real (full UI journeys, not mocked shortcuts)?
Write your findings to docs/REVIEW.md, then FIX every issue you found, add tests for each fix, and run .autopilot/verify.sh until it passes. Commit."
  checkpoint "review"
  verify "after-review" || run_claude "review-fix" "After the review, .autopilot/verify.sh fails. Fix the code until it passes. Output tail:
$(tail -n 150 "$LOG_DIR/verify-after-review.txt")"
  checkpoint "review fix"
  touch "$STATE_DIR/review.done"
fi

# ---------- final report -----------------------------------------------------
log "=== Final verification and morning report ==="
if verify "final"; then FINAL="PASSING"; else FINAL="FAILING (see .autopilot/logs/verify-final.txt)"; fi
run_claude "report" "Write MORNING_REPORT.md exactly as described in autopilot/AUTOPILOT_RULES.md §6. The final test status from .autopilot/verify.sh is: $FINAL. Incomplete phases, if any: $(cat "$STATE_DIR/incomplete.txt" 2>/dev/null || echo none). Actually start the app once to confirm your start commands work (then stop it), and confirm the demo login credentials by checking the seed code. Commit."
checkpoint "morning report"

log "=== Autopilot finished. Final tests: $FINAL. Open MORNING_REPORT.md ==="
