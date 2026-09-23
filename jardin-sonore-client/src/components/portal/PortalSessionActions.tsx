"use client";

import type {JSX} from "react";
import {ArrowLeftIcon, DocumentArrowDownIcon} from "@heroicons/react/24/outline";
import {useRouter} from "next/navigation";
import {useTranslations} from "@/i18n/translations-provider";
import {portalDocumentState} from "@/lib/portal/document-status";
import {portalRoutes} from "@/lib/portal/routes";
import type {PortalDocumentStatus} from "@/lib/portal/types";

interface PortalSessionActionsProps {
    documentStatus: PortalDocumentStatus;
    showDocumentLink?: boolean;
    sessionUuid: string;
}

export default function PortalSessionActions({documentStatus, sessionUuid, showDocumentLink = true}: PortalSessionActionsProps): JSX.Element {
    const router = useRouter();
    const content = useTranslations().portal.detail;
    const documentState = portalDocumentState(documentStatus);
    const isDocumentReady = documentState === "ready";
    const unavailableDocumentLabel = documentState === "unavailable" ? content.pdfUnavailable : content.pdfPreparing;

    return <div className="mb-6 flex items-center justify-between gap-3">
        <button className="inline-flex items-center gap-2 rounded-full border border-primary px-4 py-2 text-sm font-semibold leading-none text-primary transition hover:bg-primary hover:text-white" onClick={() => router.back()} type="button"><ArrowLeftIcon aria-hidden="true" className="h-4 w-4" />{content.back}</button>
        {showDocumentLink && (isDocumentReady ? <a aria-label={content.downloadPdfAriaLabel} className="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold leading-none text-on-primary transition hover:bg-primary-container" href={portalRoutes.document(sessionUuid)}><DocumentArrowDownIcon aria-hidden="true" className="h-4 w-4" />{content.downloadPdf}</a> : <span aria-disabled="true" className="inline-flex cursor-not-allowed items-center gap-2 rounded-full bg-surface-container-high px-4 py-2 text-sm font-semibold leading-none text-on-surface-variant" title={unavailableDocumentLabel}><DocumentArrowDownIcon aria-hidden="true" className="h-4 w-4" />{unavailableDocumentLabel}</span>)}
    </div>;
}
