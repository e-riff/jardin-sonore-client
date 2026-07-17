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
LOCAL_UPLOADS_PARENT="$(dirname "$LOCAL_UPLOADS_PATH")"

ensure_writable_local_uploads_path() {
  if [[ ! -e "$LOCAL_UPLOADS_PATH" ]]; then
    mkdir -p "$LOCAL_UPLOADS_PATH"
    return
  fi

  if [[ -w "$LOCAL_UPLOADS_PATH" ]]; then
    return
  fi

  if [[ ! -w "$LOCAL_UPLOADS_PARENT" ]]; then
    printf 'Local uploads path is not writable and parent directory cannot be modified: %s\n' "$LOCAL_UPLOADS_PATH" >&2
    printf 'Repair ownership manually, for example with: sudo chown -R "$(id -u):$(id -g)" %q\n' "$LOCAL_UPLOADS_PATH" >&2
    exit 1
  fi

  local backup_path
  backup_path="${LOCAL_UPLOADS_PATH}.docker-owned.$(date +%Y%m%d-%H%M%S)"

  mv "$LOCAL_UPLOADS_PATH" "$backup_path"
  mkdir -p "$LOCAL_UPLOADS_PATH"

  printf 'Moved non-writable local uploads directory to %s\n' "$backup_path" >&2
}

ensure_writable_local_uploads_path

"${SSH_COMMAND[@]}" "$REMOTE" "mkdir -p '$REMOTE_UPLOADS_PATH'"

rsync -rlz --delete \
  --omit-dir-times \
  --no-perms \
  --no-owner \
  --no-group \
  -e "${SSH_COMMAND[*]}" \
  "$REMOTE:$REMOTE_UPLOADS_PATH/" \
  "$LOCAL_UPLOADS_PATH/"

printf 'Backend uploads synchronized from %s:%s to %s\n' "$REMOTE" "$REMOTE_UPLOADS_PATH" "$LOCAL_UPLOADS_PATH"
