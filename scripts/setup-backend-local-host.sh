#!/usr/bin/env bash
set -euo pipefail

BACKEND_LOCAL_HOSTNAME="${BACKEND_LOCAL_HOST:-admin.jardin-sonore.local}"
HOSTS_LINE="127.0.0.1 ${BACKEND_LOCAL_HOSTNAME}"

if ! grep -Eq "^[[:space:]]*127\\.0\\.0\\.1[[:space:]].*\\b${BACKEND_LOCAL_HOSTNAME}\\b" /etc/hosts; then
  printf 'Adding %s to /etc/hosts\n' "$BACKEND_LOCAL_HOSTNAME"
  printf '%s\n' "$HOSTS_LINE" | sudo tee -a /etc/hosts >/dev/null
else
  printf '%s is already present in /etc/hosts\n' "$BACKEND_LOCAL_HOSTNAME"
fi

if command -v mkcert >/dev/null 2>&1; then
  CERT_DIR="docker/backend/nginx/certs"
  mkdir -p "$CERT_DIR"
  mkcert -install
  mkcert \
    -cert-file "$CERT_DIR/${BACKEND_LOCAL_HOSTNAME}.pem" \
    -key-file "$CERT_DIR/${BACKEND_LOCAL_HOSTNAME}-key.pem" \
    "$BACKEND_LOCAL_HOSTNAME"
  printf 'Local certificate generated in %s\n' "$CERT_DIR"
else
  printf 'mkcert is not installed. Install it if you want trusted local HTTPS certificates.\n' >&2
  printf 'On Ubuntu/Debian, use your package manager or see: https://github.com/FiloSottile/mkcert\n' >&2
fi
