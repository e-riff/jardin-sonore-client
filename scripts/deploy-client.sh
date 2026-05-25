#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLIENT_DIR="$ROOT_DIR/jardin-sonore-client"
DEPLOY_DIR="$CLIENT_DIR/deploy"
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
: "${CPANEL_APP_PATH:?Missing CPANEL_APP_PATH. Set it in .env.deploy.local or in the shell environment.}"
: "${CPANEL_BUILD_ENV:=docker}"

SSH_KEY_OPTION=()
if [[ -n "${CPANEL_SSH_KEY:-}" ]]; then
  SSH_KEY_OPTION=(-i "$CPANEL_SSH_KEY")
fi

SSH_COMMAND=(ssh -p "$CPANEL_SSH_PORT" "${SSH_KEY_OPTION[@]}")
REMOTE="$CPANEL_SSH_USER@$CPANEL_SSH_HOST"

cd "$CLIENT_DIR"

rm -rf "$DEPLOY_DIR"

case "$CPANEL_BUILD_ENV" in
  docker)
    docker run --rm \
      --user "$(id -u):$(id -g)" \
      -e NEXT_TELEMETRY_DISABLED=1 \
      -e npm_config_cache=/tmp/npm-cache \
      -v "$CLIENT_DIR:/app" \
      -w /app \
      node:24-alpine \
      sh -c "npm ci && npm run lint && npm run build"
    ;;
  local)
    npm ci
    npm run lint
    npm run build
    ;;
  *)
    printf 'Unsupported CPANEL_BUILD_ENV: %s\n' "$CPANEL_BUILD_ENV" >&2
    printf 'Expected "docker" or "local".\n' >&2
    exit 1
    ;;
esac

mkdir -p "$DEPLOY_DIR/.next" "$DEPLOY_DIR/public"

cp -R .next/standalone/. "$DEPLOY_DIR/"
cp -R .next/static "$DEPLOY_DIR/.next/static"
cp -R public/. "$DEPLOY_DIR/public/"

rsync -az --delete \
  -e "${SSH_COMMAND[*]}" \
  "$DEPLOY_DIR/" \
  "$REMOTE:$CPANEL_APP_PATH/"

"${SSH_COMMAND[@]}" "$REMOTE" "cd '$CPANEL_APP_PATH' && mkdir -p tmp && touch tmp/restart.txt"

printf 'Client deployed to %s:%s\n' "$REMOTE" "$CPANEL_APP_PATH"
