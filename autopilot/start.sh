#!/usr/bin/env bash
# =============================================================================
#  ERP autopilot: interactive Claude Code in tmux, so you can watch it work.
#
#    ./autopilot/start.sh           start (or re-attach to) the run
#    ./autopilot/start.sh resume    continue the last conversation (the /goal is restored)
#    ./autopilot/start.sh stop      stop everything
#
#  Inside tmux:   Ctrl+B then D  = leave it running and go back to your terminal
#                 Ctrl+B then 1  = Claude window,   Ctrl+B then 2 = watchdog window
#  Come back any time with:  tmux attach -t erp
#
#  Optional env vars:  MODEL=opus   SESSION=erp   IDLE_MIN=30 (watchdog)
# =============================================================================
set -eu
cd "$(dirname "$0")/.."

SESSION="${SESSION:-erp}"
MODE="${1:-start}"

attach() {
  if [ -n "${TMUX:-}" ]; then exec tmux switch-client -t "$SESSION"; else exec tmux attach -t "$SESSION"; fi
}

if [ "$MODE" = "stop" ]; then
  tmux kill-session -t "$SESSION" 2>/dev/null && echo "Stopped." || echo "Nothing running."
  exit 0
fi

command -v tmux   >/dev/null || { echo "tmux is not installed. Run ./autopilot/setup.sh first." >&2; exit 1; }
command -v claude >/dev/null || { echo "Claude Code is not installed. Run ./autopilot/setup.sh first." >&2; exit 1; }
[ -f autopilot/goal.md ]     || { echo "autopilot/goal.md is missing." >&2; exit 1; }

if tmux has-session -t "$SESSION" 2>/dev/null; then
  echo "Autopilot is already running. Attaching… (Ctrl+B then D to leave it running)"
  sleep 1; attach
fi

if [ -f .autopilot/DONE ]; then
  echo "The goal is already complete (.autopilot/DONE exists). Open MORNING_REPORT.md."
  echo "To run again anyway: rm .autopilot/DONE && ./autopilot/start.sh resume"
  exit 0
fi

mkdir -p .autopilot/logs
chmod +x autopilot/*.sh 2>/dev/null || true

FIRST="new"; [ "$MODE" = "resume" ] && FIRST="resume"
tmux new-session -d -s "$SESSION" -n claude -c "$PWD" \
  "MODEL='${MODEL:-}' ./autopilot/claude-loop.sh $FIRST"
tmux new-window -d -t "$SESSION" -n watchdog -c "$PWD" \
  "SESSION='$SESSION' IDLE_MIN='${IDLE_MIN:-30}' ./autopilot/watchdog.sh"
tmux set-option -t "$SESSION" -g mouse on >/dev/null 2>&1 || true   # scroll with the mouse wheel
tmux select-window -t "$SESSION:claude"

echo "Autopilot started in tmux session '$SESSION'."
echo "  Leave it running:  Ctrl+B then D      Come back:  tmux attach -t $SESSION"
sleep 2
attach
