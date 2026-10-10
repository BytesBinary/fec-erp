#!/usr/bin/env bash
# =============================================================================
#  One-time setup + preflight for the ERP autopilot. Safe to re-run.
#  Checks everything, fixes what it can (asking first), and tells you exactly
#  what is still missing. Then start with:  ./autopilot/start.sh
# =============================================================================
set -u
cd "$(dirname "$0")/.." || exit 1

ok()   { printf '  \033[32m✔\033[0m %s\n' "$1"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; WARN=$((WARN+1)); }
fail() { printf '  \033[31m✘\033[0m %s\n' "$1"; FAIL=$((FAIL+1)); }
ask()  { printf '  %s [y/N] ' "$1"; read -r a; [ "$a" = "y" ] || [ "$a" = "Y" ]; }
WARN=0; FAIL=0
MIN_CLAUDE="2.1.269"   # /goal pause+resume on usage limits, idle check-ins, auto-continue after limit reset
BRANCH="${BRANCH:-autopilot/erp-features}"

# ---------------------------------------------------------------------------
echo "== Old headless run =="
old_pids=$(pgrep -f "autopilot/run\.sh" 2>/dev/null | grep -vxE "$$|$PPID" || true)
if [ -n "$old_pids" ]; then
  warn "the old headless autopilot (run.sh) is still running"
  if ask "Stop it now?"; then
    kill $old_pids 2>/dev/null; pkill -f "autopilot-phase" 2>/dev/null; pkill -f "autopilot-review" 2>/dev/null; pkill -f "autopilot-report" 2>/dev/null
    sleep 2; ok "stopped"; WARN=$((WARN-1))
  fi
else
  ok "no old headless run is running"
fi
if [ -d .autopilot/state ]; then
  mv .autopilot/state ".autopilot/state.headless-old-$(date +%s)"
  ok "moved the old (wrong) phase markers out of the way (.autopilot/state.headless-old-*)"
fi
for f in autopilot/run.sh autopilot/watch.py; do
  [ -f "$f" ] && rm -f "$f" && ok "removed old headless file $f"
done

# ---------------------------------------------------------------------------
echo
echo "== Tools =="
for t in git tmux jq; do
  if command -v "$t" >/dev/null; then ok "$t"
  else fail "$t not installed  (macOS: brew install $t   Ubuntu/WSL: sudo apt install $t)"; fi
done
if command -v node >/dev/null; then ok "node $(node -v)"; else warn "node not found (needed if the ERP is a Node/JS project)"; fi
if command -v docker >/dev/null; then
  if docker info >/dev/null 2>&1; then ok "docker is running"; else warn "docker is installed but not running — start Docker Desktop if the ERP uses a local database in Docker"; fi
else
  warn "docker not found (fine if your database runs without Docker)"
fi

# ---------------------------------------------------------------------------
echo
echo "== Claude Code =="
if command -v claude >/dev/null; then
  ver=$(claude --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -n1)
  if [ -n "$ver" ] && [ "$(printf '%s\n%s\n' "$MIN_CLAUDE" "$ver" | sort -V | head -n1)" = "$MIN_CLAUDE" ]; then
    ok "claude $ver"
  else
    fail "claude ${ver:-unknown} is too old (need $MIN_CLAUDE+). Run: claude update"
  fi
  if claude auth status >/dev/null 2>&1; then
    method=$(claude auth status 2>/dev/null | jq -r '.authMethod // empty' 2>/dev/null)
    if [ "$method" = "claude.ai" ]; then
      ok "signed in with a claude.ai subscription (auto-continue after usage limits works)"
    elif [ -n "$method" ]; then
      warn "signed in via '$method'. Automatic continue after usage limits only works with a claude.ai subscription login (claude auth login)"
    else
      ok "signed in"
    fi
  else
    fail "Claude Code is not signed in. Run: claude auth login"
  fi
else
  fail "Claude Code not installed (https://code.claude.com/docs/en/quickstart)"
fi

USER_SETTINGS="$HOME/.claude/settings.json"
if [ -f "$USER_SETTINGS" ] && [ "$(jq -r '.autoContinueAtUsageLimit' "$USER_SETTINGS" 2>/dev/null)" = "false" ]; then
  warn "'Continue automatically at usage limit' is turned OFF in $USER_SETTINGS"
  if ask "Turn it back on?"; then
    cp "$USER_SETTINGS" "$USER_SETTINGS.bak.$(date +%s)"
    tmp=$(mktemp) && jq '.autoContinueAtUsageLimit = true' "$USER_SETTINGS" > "$tmp" && mv "$tmp" "$USER_SETTINGS" \
      && { ok "turned on (backup saved next to it)"; WARN=$((WARN-1)); }
  fi
else
  ok "auto-continue after usage limits is on"
fi
if [ -f "$USER_SETTINGS" ] && [ "$(jq -r '.permissions.disableAutoMode // empty' "$USER_SETTINGS" 2>/dev/null)" = "disable" ]; then
  fail "auto mode is disabled in $USER_SETTINGS (permissions.disableAutoMode). Remove that line."
fi

# ---------------------------------------------------------------------------
echo
echo "== Project safety rules (.claude/settings.json) =="
mkdir -p .claude
PROJ_SETTINGS=.claude/settings.json
[ -f "$PROJ_SETTINGS" ] || echo '{}' > "$PROJ_SETTINGS"
if jq -e '.permissions.deny // [] | index("Bash(git push *)")' "$PROJ_SETTINGS" >/dev/null 2>&1; then
  ok "git push is already blocked for Claude in this project"
else
  tmp=$(mktemp)
  if jq '.permissions.deny = ((.permissions.deny // []) + ["Bash(git push)", "Bash(git push *)"] | unique)' "$PROJ_SETTINGS" > "$tmp"; then
    mv "$tmp" "$PROJ_SETTINGS"; ok "blocked git push for Claude in this project (you review and push yourself)"
  else
    rm -f "$tmp"; fail "could not update $PROJ_SETTINGS (is it valid JSON?)"
  fi
fi

echo
echo "== Claude memory (CLAUDE.md) =="
MARK="<!-- erp-autopilot -->"
if [ -f CLAUDE.md ] && grep -qF "$MARK" CLAUDE.md; then
  ok "CLAUDE.md already loads the autopilot rules"
else
  printf '\n%s\n## Autopilot\nWhile the ERP autopilot runs, follow these rules (they survive context compaction):\n@autopilot/AUTOPILOT_RULES.md\n' "$MARK" >> CLAUDE.md
  ok "CLAUDE.md now loads autopilot/AUTOPILOT_RULES.md (so the rules are never forgotten)"
fi

# ---------------------------------------------------------------------------
echo
echo "== Files =="
for f in IMPLEMENTATION_SPEC.md autopilot/AUTOPILOT_RULES.md autopilot/goal.md autopilot/start.sh autopilot/claude-loop.sh autopilot/watchdog.sh; do
  [ -f "$f" ] && ok "$f" || fail "$f is missing"
done
[ -x .autopilot/verify.sh ] && ok ".autopilot/verify.sh (made in phase 0)" || warn ".autopilot/verify.sh not found yet — Claude will create it"
chmod +x autopilot/*.sh 2>/dev/null
mkdir -p .autopilot/logs
grep -qxF ".autopilot/logs/" .gitignore 2>/dev/null || echo ".autopilot/logs/" >> .gitignore

# ---------------------------------------------------------------------------
echo
echo "== Git =="
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  fail "not a git repository"
else
  git config user.email >/dev/null || warn "git user.email is not set (Claude needs it to commit): git config user.email you@example.com"
  cur=$(git rev-parse --abbrev-ref HEAD)
  if [ "$cur" = "$BRANCH" ]; then
    ok "on branch $BRANCH (your main branch is untouched)"
  elif git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git checkout -q "$BRANCH" && ok "switched to existing branch $BRANCH" || fail "could not switch to $BRANCH (commit or stash your changes first)"
  else
    git checkout -q -b "$BRANCH" && ok "created branch $BRANCH" || fail "could not create $BRANCH"
  fi
  if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    git add -A && git commit -qm "chore(autopilot): switch to interactive tmux autopilot" \
      && ok "committed the setup changes" || warn "could not commit the setup changes"
  fi
fi

# ---------------------------------------------------------------------------
echo
echo "== Power =="
case "$(uname -s)" in
  Darwin) ok "macOS: the autopilot keeps the Mac awake by itself (caffeinate). Keep the lid open or use power adapter + external display." ;;
  *)      warn "Linux/WSL/Windows: turn OFF sleep in your power settings for tonight, or the run pauses while the computer sleeps." ;;
esac

echo
if [ "$FAIL" -gt 0 ]; then
  echo "Setup: $FAIL problem(s) must be fixed before starting."; exit 1
fi
echo "Setup done with $WARN warning(s). Start the run with:  ./autopilot/start.sh"
