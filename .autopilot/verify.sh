#!/usr/bin/env bash
# =============================================================================
#  .autopilot/verify.sh — one-shot verification for the FEC ERP (Laravel 12).
#
#  1. Installs dependencies if missing (composer, npm, Playwright chromium).
#  2. Builds front-end assets when the Vite manifest is missing or stale.
#  3. Migrates + seeds a throw-away SQLite database (never the dev MySQL DB)
#     to prove migrations and seeders run from scratch.
#  4. Runs lint (Pint), then every PHPUnit/Pest test suite declared in
#     phpunit.xml (Unit, Feature, Browser/E2E, and any suite added later,
#     e.g. Mcp). Tests themselves run on in-memory SQLite.
#  5. Prints a summary and exits 0 only if every step passed.
#
#  Idempotent and safe to run repeatedly. Usage: .autopilot/verify.sh
#  Env: VERIFY_SKIP_BROWSER=1 skips the Browser suite (local quick runs only).
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 1

LOG_DIR="$ROOT/.autopilot/logs"
mkdir -p "$LOG_DIR"
VERIFY_DB="$ROOT/database/verify.sqlite"

declare -a STEP_NAMES=()
declare -a STEP_RESULTS=()
FAILED=0

record() {
  STEP_NAMES+=("$1")
  STEP_RESULTS+=("$2")
  [[ "$2" == "PASS" || "$2" == "SKIP" ]] || FAILED=1
}

run_step() {
  local name="$1"; shift
  echo
  echo "────────────────────────────────────────────────────────────────"
  echo "▶ $name"
  echo "────────────────────────────────────────────────────────────────"
  if "$@"; then
    record "$name" "PASS"
  else
    record "$name" "FAIL"
  fi
}

# Isolated environment for artisan commands run by this script. Real env vars
# take precedence over .env, so the developer's MySQL database is never used.
isolated() {
  env APP_ENV=testing \
      DB_CONNECTION=sqlite DB_DATABASE="$VERIFY_DB" \
      CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
      MAIL_MAILER=array BROADCAST_CONNECTION=null \
      "$@"
}

# ---------------------------------------------------------------- deps -------
install_deps() {
  if [[ ! -f vendor/autoload.php ]]; then
    composer install --no-interaction --prefer-dist || return 1
  fi
  if [[ ! -d node_modules/playwright ]] || [[ ! -d node_modules/vite ]]; then
    npm ci --no-audit --no-fund || npm install --no-audit --no-fund || return 1
  fi
  if ! compgen -G "$HOME/.cache/ms-playwright/chromium*" >/dev/null; then
    npx playwright install chromium || return 1
  fi
  if [[ ! -f .env ]]; then
    cp .env.example .env && php artisan key:generate --no-interaction || return 1
  fi
  php artisan config:clear --no-interaction >/dev/null || return 1
}

build_assets() {
  local manifest="public/build/manifest.json"
  if [[ -f "$manifest" ]] && [[ -z "$(find resources vite.config.js package.json -newer "$manifest" -type f -print -quit 2>/dev/null)" ]]; then
    echo "Assets up to date — skipping build."
    return 0
  fi
  npm run build
}

migrate_and_seed() {
  rm -f "$VERIFY_DB"
  touch "$VERIFY_DB"
  isolated php artisan migrate:fresh --force --no-interaction || return 1
  echo "Checking every migration rolls back cleanly..."
  isolated php artisan migrate:reset --force --no-interaction || return 1
  isolated php artisan migrate --force --no-interaction || return 1
  echo "Checking the development seeders (db:seed) on a fresh schema..."
  isolated php artisan db:seed --force --no-interaction || return 1
  if isolated php artisan list --raw 2>/dev/null | grep -q '^seed:test'; then
    echo "Seeding the deterministic test dataset (seed:test, twice for idempotency)..."
    isolated php artisan migrate:fresh --force --no-interaction || return 1
    isolated php artisan seed:test --no-interaction || return 1
    isolated php artisan seed:test --no-interaction || return 1
  fi
}

lint() {
  vendor/bin/pint --test --format agent
}

run_suite() {
  local suite="$1"
  timeout 1800 php vendor/bin/pest --colors=never --testsuite="$suite"
}

# ---------------------------------------------------------------- run --------
START=$(date +%s)

run_step "Install dependencies" install_deps
run_step "Build front-end assets" build_assets
run_step "Migrate + seed (fresh SQLite)" migrate_and_seed
run_step "Lint (Pint)" lint

if [[ -f vendor/bin/phpstan ]]; then
  run_step "Static analysis (PHPStan)" vendor/bin/phpstan analyse --no-progress --memory-limit=1G
else
  record "Static analysis (not configured)" "SKIP"
fi

mapfile -t SUITES < <(grep -oP '<testsuite name="\K[^"]+' phpunit.xml)
for suite in "${SUITES[@]}"; do
  if [[ "$suite" == "Browser" && "${VERIFY_SKIP_BROWSER:-}" == "1" ]]; then
    record "Tests: $suite" "SKIP"
    continue
  fi
  run_step "Tests: $suite" run_suite "$suite"
done

rm -f "$VERIFY_DB"

# ---------------------------------------------------------------- summary ----
ELAPSED=$(( $(date +%s) - START ))
echo
echo "════════════════════════════════════════════════════════════════"
echo " VERIFY SUMMARY  ($(date '+%Y-%m-%d %H:%M:%S'), ${ELAPSED}s)"
echo "════════════════════════════════════════════════════════════════"
for i in "${!STEP_NAMES[@]}"; do
  printf ' %-6s %s\n' "${STEP_RESULTS[$i]}" "${STEP_NAMES[$i]}"
done
echo "════════════════════════════════════════════════════════════════"
if [[ $FAILED -eq 0 ]]; then
  echo " RESULT: ALL CHECKS PASSED"
  exit 0
fi
echo " RESULT: FAILED"
exit 1
