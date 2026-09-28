"use client";

import {ArrowRightIcon} from "@heroicons/react/24/outline";
import Link from "next/link";
import {JSX, useEffect, useState} from "react";
import {portalRoutes} from "@/lib/portal/routes";

interface PortalShowcaseLinkProps {
    loginLabel: string;
    accountLabel: string;
}

export default function PortalShowcaseLink({loginLabel, accountLabel}: PortalShowcaseLinkProps): JSX.Element {
    const [authenticated, setAuthenticated] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        async function checkSession(): Promise<void> {
            try {
                const response = await fetch("/api/portal/session-status", {cache: "no-store", signal: controller.signal});
                if (!response.ok) return;
                const status: {authenticated?: boolean} = await response.json();
                if (!controller.signal.aborted) setAuthenticated(status.authenticated === true);
            } catch {
                // The default link remains usable if the session check fails.
            }
        }

        void checkSession();
        return () => controller.abort();
    }, []);

    return (
        <Link className="mt-7 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href={portalRoutes.sessions}>
            {authenticated ? accountLabel : loginLabel}
            <ArrowRightIcon aria-hidden="true" className="h-4 w-4" />
        </Link>
    );
}
