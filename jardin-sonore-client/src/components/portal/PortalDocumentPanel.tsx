"use client";

import type {JSX} from "react";
import {portalDocumentState} from "@/lib/portal/document-status";
import type {PortalDocumentStatus} from "@/lib/portal/types";
import {useTranslations} from "@/i18n/translations-provider";

interface PortalDocumentPanelProps {
    status: PortalDocumentStatus;
    sessionUuid: string;
}

export default function PortalDocumentPanel({status, sessionUuid}: PortalDocumentPanelProps): JSX.Element {
    const content = useTranslations().portal.document;
    const state = portalDocumentState(status);

    return <aside className="portal-document-panel" aria-labelledby="portal-document-title">
        <p className="portal-eyebrow">{content.eyebrow}</p>
        <h2 className="font-serif text-xl font-semibold text-on-surface" id="portal-document-title">{content.title}</h2>
        <p className="mt-2 text-sm leading-6 text-on-surface-variant">{content[state].description}</p>
        {state === "ready" && <a className="portal-document-link" href={`/portail/seances/${encodeURIComponent(sessionUuid)}/document.pdf`}>{content.ready.action}</a>}
    </aside>;
}
