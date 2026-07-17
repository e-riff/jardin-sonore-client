#!/bin/sh

set -eu

UPLOAD_DIRECTORIES="
    /app/public/uploads/mailing/banners
    /app/public/uploads/mailing/recommendations
    /app/public/uploads/media/resources
    /app/public/uploads/media/images
    /app/public/uploads/session/recommendations
"

if [ "${APP_ENV:-prod}" = "dev" ] && [ -w /app/public ]; then
    rm -rf /app/public/assets
fi

for upload_directory in $UPLOAD_DIRECTORIES; do
    mkdir -p "$upload_directory"
done

if [ -w /app/public/uploads ]; then
    chmod -R u+rwX,g+rwX /app/public/uploads
fi

exec "$@"
