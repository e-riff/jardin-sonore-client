'use client';

import {JSX, useSyncExternalStore} from "react";
import "altcha";
import "altcha/i18n/fr-fr";
import type {} from "altcha/types/react";

const subscribe = (): (() => void) => (): void => {};
const getClientSnapshot = (): boolean => true;
const getServerSnapshot = (): boolean => false;

export default function AltchaWidget(): JSX.Element {
    const isClient = useSyncExternalStore(subscribe, getClientSnapshot, getServerSnapshot);

    if (!isClient) {
        return <div className="min-h-16 rounded-lg border border-outline-variant/20 bg-surface" aria-hidden="true" />;
    }

    return (
        <div className="mt-6 [&_altcha-widget]:w-full">
            <altcha-widget
                challenge="/api/altcha/challenge"
                configuration={JSON.stringify({hideFooter: true, minDuration: 600})}
                language="fr-fr"
                name="altcha"
                type="checkbox"
                style={{
                    "--altcha-border-radius": "0.5rem",
                    "--altcha-color-base": "var(--color-surface)",
                    "--altcha-color-base-content": "var(--color-on-surface)",
                    "--altcha-color-primary": "var(--color-primary)",
                    "--altcha-color-primary-content": "var(--color-on-primary)",
                    "--altcha-border-color": "color-mix(in oklab, var(--color-outline-variant) 50%, transparent)",
                    "--altcha-max-width": "100%",
                }}
            />
        </div>
    );
}
