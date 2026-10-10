#!/usr/bin/env bash
# =============================================================================
#  Watchdog: runs in the tmux "watchdog" window next to Claude.
#
#  Claude Code already does most of the work by itself:
#    - /goal starts the next turn after every turn
#    - after a usage limit it waits and continues when the limit resets
#  This is the safety net for when it still ends up idle: it gives up after
#  repeated limit hits, the goal got cleared, it is stuck on "press enter",
#  or it is simply sitting at the prompt with the goal unfinished.
#
#  It only types into Claude's window when ALL of these are true:
#    - .autopilot/DONE does not exist (the goal is not finished)
#    - Claude is not working (no "esc to interrupt" on screen)
#    - Claude is not already waiting for a usage-limit reset by itself
#    - no permission/trust question is on screen (it never answers those)
#    - nothing happened in the conversation for IDLE_MIN minutes
#  It then re-sends the /goal (which resumes from PROGRESS.md), backing off
#  30 -> 60 -> 120 min if Claude stays idle (e.g. weekly limit reached).
# =============================================================================
set -u
cd "$(dirname "$0")/.."

SESSION="${SESSION:-erp}"
TARGET="$SESSION:claude"
IDLE_MIN="${IDLE_MIN:-30}"
MAX_BACKOFF_MIN=120
LOG=.autopilot/logs/watchdog.log
mkdir -p .autopilot/logs

log() { echo "[$(date '+%F %T')] $*" | tee -a "$LOG"; }

mtime() { stat -c %Y "$1" 2>/dev/null || stat -f %m "$1" 2>/dev/null || echo 0; }
hash_of() { if command -v md5sum >/dev/null; then md5sum | cut -d' ' -f1; else md5 -q; fi; }

# Claude Code stores this project's conversations here (path with non-alphanumerics -> '-')
PROJ_DIR="$HOME/.claude/projects/$(printf '%s' "$PWD" | sed 's/[^a-zA-Z0-9]/-/g')"
last_activity() {
  # newest change of either the conversation transcript or the screen
  local t=0 f
  if [ -d "$PROJ_DIR" ]; then
    f=$(ls -t "$PROJ_DIR"/*.jsonl 2>/dev/null | head -n1)
    [ -n "$f" ] && t=$(mtime "$f")
  fi
  [ "$t" -gt "$screen_changed" ] && echo "$t" || echo "$screen_changed"
}

send_goal() {
  tmux send-keys -t "$TARGET" -l "$(cat autopilot/goal.md)"
  sleep 1
  tmux send-keys -t "$TARGET" Enter
}

backoff=$IDLE_MIN
screen_hash=""
screen_changed=$(date +%s)
last_nudge=0
nudges=0

log "watchdog started (idle threshold ${IDLE_MIN} min). Watching tmux window '$TARGET'."
[ -d "$PROJ_DIR" ] || log "note: $PROJ_DIR not found yet; using screen changes only until it appears."

while tmux has-session -t "$SESSION" 2>/dev/null; do
  if [ -f .autopilot/DONE ]; then
    log "✔ .autopilot/DONE exists — goal complete. Watchdog exiting."
    exit 0
  fi

  screen=$(tmux capture-pane -p -t "$TARGET" -S -60 2>/dev/null || true)
  # ignore clock/timer digits so a ticking status line doesn't count as activity
  h=$(printf '%s' "$screen" | tr -d '0-9' | hash_of)
  now=$(date +%s)
  if [ "$h" != "$screen_hash" ]; then screen_hash=$h; screen_changed=$now; fi

  tail_screen=$(printf '%s\n' "$screen" | tail -n 25)
  act=$(last_activity)
  # real work for 10+ minutes after our last nudge resets the back-off
  if [ "$backoff" -ne "$IDLE_MIN" ] && [ "$act" -gt $(( last_nudge + 600 )) ]; then backoff=$IDLE_MIN; fi
  [ "$last_nudge" -gt "$act" ] && act=$last_nudge
  idle_s=$(( now - act ))

  if printf '%s' "$tail_screen" | grep -qiE 'esc to interrupt|ctrl\+c to interrupt'; then
    : # Claude is working
  elif printf '%s' "$tail_screen" | grep -qiE 'continuing automatically|continuing shortly'; then
    : # Claude Code is waiting for the usage limit to reset by itself
  elif printf '%s' "$tail_screen" | grep -qiE 'do you trust|do you want to (proceed|make|allow|create|run)|❯ *1\. *yes'; then
    if [ "$idle_s" -ge 600 ] && [ $(( (now / 60) % 30 )) -eq 0 ]; then
      log "⚠ Claude is waiting for YOUR answer on screen (permission/trust question). Attach with: tmux attach -t $SESSION"
    fi
  elif printf '%s' "$tail_screen" | grep -qiE 'press enter to continue'; then
    if [ $(( now - last_nudge )) -ge 120 ]; then
      log "usage limit has reset while the computer slept — pressing Enter."
      tmux send-keys -t "$TARGET" Enter
      last_nudge=$now
    fi
  elif [ "$idle_s" -ge $(( backoff * 60 )) ]; then
    nudges=$((nudges + 1))
    log "Claude idle for $(( idle_s / 60 )) min with the goal unfinished — re-sending /goal (nudge #$nudges, next check after $(( backoff * 2 > MAX_BACKOFF_MIN ? MAX_BACKOFF_MIN : backoff * 2 )) min idle)."
    send_goal
    last_nudge=$now
    backoff=$(( backoff * 2 )); [ "$backoff" -gt "$MAX_BACKOFF_MIN" ] && backoff=$MAX_BACKOFF_MIN
  fi

  sleep "${POLL_SEC:-60}"
done
log "tmux session '$SESSION' is gone — watchdog exiting."
