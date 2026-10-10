#!/usr/bin/env bash
# Runs inside the tmux "claude" window. Starts interactive Claude Code with the
# /goal and, if Claude ever exits before the goal is done, restarts it with
# --continue (the conversation and goal are restored) after a short pause.
set -u
cd "$(dirname "$0")/.."

FIRST="${1:-new}"
GOAL="$(cat autopilot/goal.md)"
MODEL_ARGS=(); [ -n "${MODEL:-}" ] && MODEL_ARGS=(--model "$MODEL")

# Keep the computer awake while Claude runs (macOS). On Linux/WSL, disable sleep in power settings.
AWAKE=(); command -v caffeinate >/dev/null 2>&1 && AWAKE=(caffeinate -ims)

run=0
while :; do
  if [ -f .autopilot/DONE ]; then
    printf '\n\033[32m✔ Autopilot goal complete. Open MORNING_REPORT.md\033[0m\n'
    exec "${SHELL:-bash}"
  fi

  if [ "$run" -eq 0 ] && [ "$FIRST" = "new" ]; then
    "${AWAKE[@]}" claude --permission-mode auto --name erp-autopilot "${MODEL_ARGS[@]}" "$GOAL"
  else
    "${AWAKE[@]}" claude --permission-mode auto --continue "${MODEL_ARGS[@]}" "$GOAL"
  fi
  run=$((run + 1))
  echo "[$(date '+%F %T')] claude exited (run $run)" >> .autopilot/logs/watchdog.log

  [ -f .autopilot/DONE ] && continue
  printf '\n\033[33mClaude exited before the goal was done. Restarting in 60s (press Ctrl+C to stop here).\033[0m\n'
  sleep 60 || exit 0
done
