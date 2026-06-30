#!/bin/sh

set -eu

if [ "$(id -u)" -eq 0 ]; then
    UPLOAD_DIRECTORY=/app/public/uploads/mailing/recommendations

    if [ "${APP_ENV:-prod}" = "dev" ]; then
        rm -rf /app/public/assets
    fi

    mkdir -p "$UPLOAD_DIRECTORY"
    chown -R www-data:www-data /app/public/uploads/mailing
    chmod -R ug+rwX,o+rX /app/public/uploads/mailing
fi

exec "$@"
