#!/bin/sh

set -eu

trap 'exit 0' INT TERM

while :; do
    if ! php bin/console app:sessions:dispatch-notifications --recover-after=30; then
        printf '%s\n' 'Session notification distribution failed; retrying on the next pass.' >&2
    fi
    if ! php bin/console app:commercial:dispatch-contact-requests; then
        printf '%s\n' 'Commercial contact email distribution failed; retrying on the next pass.' >&2
    fi
    if ! php bin/console app:commercial:create-invoice-reminders; then
        printf '%s\n' 'Commercial invoice reminder creation failed; retrying on the next pass.' >&2
    fi
    if ! php bin/console app:commercial:dispatch-digest; then
        printf '%s\n' 'Personal commercial digest distribution failed; retrying on the next pass.' >&2
    fi

    sleep 60 &
    wait "$!"
done
