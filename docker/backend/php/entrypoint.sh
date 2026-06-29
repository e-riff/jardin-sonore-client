#!/bin/sh

set -eu

UPLOAD_DIRECTORY=/app/public/uploads/mailing/recommendations

mkdir -p "$UPLOAD_DIRECTORY"
chown -R www-data:www-data /app/public/uploads/mailing
chmod -R ug+rwX,o+rX /app/public/uploads/mailing

exec "$@"
