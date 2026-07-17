#!/bin/sh

set -eu

HOST_UID="${HOST_UID:-1000}"
HOST_GID="${HOST_GID:-1000}"

ensure_directory_owner() {
    target_directory="$1"

    mkdir -p "$target_directory"
    chown -R "$HOST_UID:$HOST_GID" "$target_directory"
}

if [ "$(id -u)" -eq 0 ]; then
    ensure_directory_owner /app/node_modules
    ensure_directory_owner /app/.next

    exec su-exec "$HOST_UID:$HOST_GID" "$@"
fi

exec "$@"
