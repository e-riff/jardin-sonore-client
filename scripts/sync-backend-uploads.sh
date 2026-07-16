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

SSH_KEY_OPTION=()
if [[ -n "${CPANEL_SSH_KEY:-}" ]]; then
  SSH_KEY_OPTION=(-i "$CPANEL_SSH_KEY")
fi

SSH_COMMAND=(ssh -p "$CPANEL_SSH_PORT" "${SSH_KEY_OPTION[@]}")
REMOTE="$CPANEL_SSH_USER@$CPANEL_SSH_HOST"
REMOTE_UPLOADS_PATH="${CPANEL_BACKEND_UPLOADS_PATH:-$CPANEL_BACKEND_PATH/public/uploads}"
LOCAL_UPLOADS_PATH="${LOCAL_BACKEND_UPLOADS_PATH:-$BACKEND_DIR/public/uploads}"

mkdir -p "$LOCAL_UPLOADS_PATH"

"${SSH_COMMAND[@]}" "$REMOTE" "mkdir -p '$REMOTE_UPLOADS_PATH'"

rsync -az --delete \
  -e "${SSH_COMMAND[*]}" \
  "$REMOTE:$REMOTE_UPLOADS_PATH/" \
  "$LOCAL_UPLOADS_PATH/"

printf 'Backend uploads synchronized from %s:%s to %s\n' "$REMOTE" "$REMOTE_UPLOADS_PATH" "$LOCAL_UPLOADS_PATH"
