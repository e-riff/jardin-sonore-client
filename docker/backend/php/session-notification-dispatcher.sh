#!/bin/sh

set -eu

trap 'exit 0' INT TERM

while :; do
    if ! php bin/console app:sessions:dispatch-notifications --recover-after=30; then
        printf '%s\n' 'Session notification distribution failed; retrying on the next pass.' >&2
    fi

    sleep 60 &
    wait "$!"
done
