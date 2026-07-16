#!/bin/sh

set -eu

if [ "$(id -u)" -eq 0 ]; then
    UPLOAD_DIRECTORIES="
        /app/public/uploads/mailing/banners
        /app/public/uploads/mailing/recommendations
        /app/public/uploads/media/resources
        /app/public/uploads/media/images
        /app/public/uploads/session/recommendations
    "

    if [ "${APP_ENV:-prod}" = "dev" ]; then
        rm -rf /app/public/assets
    fi

    for upload_directory in $UPLOAD_DIRECTORIES; do
        mkdir -p "$upload_directory"
    done

    chown -R www-data:www-data /app/public/uploads
    chmod -R ug+rwX,o+rX /app/public/uploads
fi

exec "$@"
