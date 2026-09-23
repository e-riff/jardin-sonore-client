"use client";

import Link from "next/link";
import {type JSX, useEffect} from "react";
import {useTranslations} from "@/i18n/translations-provider";
import {portalRoutes} from "@/lib/portal/routes";

interface PortalErrorProps {
    error: Error & {digest?: string};
    reset: () => void;
}

export default function PortalError({error, reset}: PortalErrorProps): JSX.Element {
    const content = useTranslations().portal.error;

    useEffect(() => {
        console.error("Portal rendering error", error);
    }, [error]);

    return (
        <main className="mx-auto flex min-h-[60vh] max-w-3xl flex-col items-center justify-center px-6 py-20 text-center">
            <p className="text-sm font-bold uppercase tracking-[0.18em] text-primary">{content.eyebrow}</p>
            <h1 className="mt-4 font-serif text-4xl text-on-surface sm:text-5xl">{content.title}</h1>
            <p className="mt-5 max-w-xl text-lg leading-relaxed text-on-surface-variant">{content.description}</p>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
                <button type="button" onClick={reset} className="rounded-full bg-primary px-6 py-3 text-sm font-bold text-on-primary shadow-sm transition hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    {content.retry}
                </button>
                <Link href={portalRoutes.sessions} className="rounded-full border border-outline-variant px-6 py-3 text-sm font-bold text-primary transition hover:bg-surface-container-high focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    {content.sessionsLink}
                </Link>
            </div>
        </main>
    );
}
