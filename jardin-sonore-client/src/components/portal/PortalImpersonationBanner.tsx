"use client";

import type {JSX} from "react";
import {useTranslations} from "@/i18n/translations-provider";

interface PortalImpersonationBannerProps {
    onTerminate: () => Promise<void>;
}

export default function PortalImpersonationBanner({onTerminate}: PortalImpersonationBannerProps): JSX.Element {
    const content = useTranslations().portal.impersonation;

    return <aside className="border-b border-tertiary/25 bg-tertiary-container/15 px-4 py-3 font-sans sm:px-6" role="status">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-on-surface"><span className="font-bold">{content.title}</span> — {content.description}</p>
            <form action={onTerminate}><button className="text-sm font-bold text-tertiary underline underline-offset-4" type="submit">{content.action}</button></form>
        </div>
    </aside>;
}
