#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/jardin-sonore-backend"
DEPLOY_ENV_FILE="$ROOT_DIR/.env.deploy.local"

if [[ -f "$DEPLOY_ENV_FILE" ]]; then
  set -a
  # shellcheck source=/dev/null
  source "$DEPLOY_ENV_FILE"
  set +a
fi

: "${CPANEL_SSH_HOST:?Missing CPANEL_SSH_HOST. Set it in .env.deploy.local or in the shell environment.}"
: "${CPANEL_SSH_USER:?Missing CPANEL_SSH_USER. Set it in .env.deploy.local or in the shell environment.}"
: "${CPANEL_SSH_PORT:=22}"
: "${CPANEL_BACKEND_PATH:?Missing CPANEL_BACKEND_PATH. Set it in .env.deploy.local or in the shell environment.}"
: "${CPANEL_PHP_BIN:=php}"
: "${CPANEL_COMPOSER_BIN:=composer}"
: "${CPANEL_BACKEND_RUN_MIGRATIONS:=1}"
: "${CPANEL_BACKEND_STOP_MESSENGER_WORKERS:=1}"

SSH_KEY_OPTION=()
if [[ -n "${CPANEL_SSH_KEY:-}" ]]; then
  SSH_KEY_OPTION=(-i "$CPANEL_SSH_KEY")
fi

SSH_COMMAND=(ssh -p "$CPANEL_SSH_PORT" "${SSH_KEY_OPTION[@]}")
REMOTE="$CPANEL_SSH_USER@$CPANEL_SSH_HOST"

cd "$BACKEND_DIR"

composer validate --strict

"${SSH_COMMAND[@]}" "$REMOTE" "mkdir -p '$CPANEL_BACKEND_PATH'"

rsync -az --delete \
  -e "${SSH_COMMAND[*]}" \
  --exclude '.env.local' \
  --exclude '.env.*.local' \
  --exclude 'public/assets/' \
  --exclude 'var/' \
  --exclude 'vendor/' \
  --exclude '.php-cs-fixer.cache' \
  --exclude 'data/*.sql' \
  "$BACKEND_DIR/" \
  "$REMOTE:$CPANEL_BACKEND_PATH/"

REMOTE_COMMANDS=(
  "set -euo pipefail"
  "cd '$CPANEL_BACKEND_PATH'"
  'if [[ -d var/cache/prod ]]; then mv var/cache/prod "var/cache/prod.previous.$(date +%s)"; fi'
  "$CPANEL_COMPOSER_BIN install --no-dev --prefer-dist --no-interaction --optimize-autoloader"
  "$CPANEL_PHP_BIN bin/console asset-map:compile --env=prod --no-debug"
)

if [[ "$CPANEL_BACKEND_RUN_MIGRATIONS" == "1" ]]; then
  REMOTE_COMMANDS+=("$CPANEL_PHP_BIN bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug")
fi

REMOTE_COMMANDS+=(
  "$CPANEL_PHP_BIN bin/console cache:clear --env=prod --no-debug"
)

if [[ "$CPANEL_BACKEND_STOP_MESSENGER_WORKERS" == "1" ]]; then
  REMOTE_COMMANDS+=("$CPANEL_PHP_BIN bin/console messenger:stop-workers --env=prod --no-debug")
fi

"${SSH_COMMAND[@]}" "$REMOTE" "$(printf '%s && ' "${REMOTE_COMMANDS[@]}") true"

printf 'Backend deployed to %s:%s\n' "$REMOTE" "$CPANEL_BACKEND_PATH"
