"use client";

import Link from "next/link";
import {useMemo, useState} from "react";
import type {Dictionary} from "@/i18n/types";
import type {PortalAccount, PortalSessionSummary} from "@/lib/portal/types";
import {portalRoutes} from "@/lib/portal/routes";

type SessionsContent = Dictionary["portal"]["sessions"];
type SortKey = "date" | "title" | "theme";

const frenchCollator = new Intl.Collator("fr", {sensitivity: "base"});
const frenchDateFormatter = new Intl.DateTimeFormat("fr-FR", {timeZone: "UTC"});

function formatSharedDate(sharedAt: string): string {
    return frenchDateFormatter.format(new Date(`${sharedAt}T12:00:00Z`));
}

function compareSessions(sortKey: SortKey, left: PortalSessionSummary, right: PortalSessionSummary): number {
    if (sortKey === "date") return (right.sharedAt ?? right.sessionDate).localeCompare(left.sharedAt ?? left.sessionDate);
    if (sortKey === "theme") return frenchCollator.compare(left.theme ?? "\uffff", right.theme ?? "\uffff") || frenchCollator.compare(left.title, right.title);

    return frenchCollator.compare(left.title, right.title);
}

function SortButton({active, children, onClick}: {active: boolean; children: string; onClick: () => void}): React.JSX.Element {
    return <button aria-pressed={active} className={`rounded-md px-3 py-2 text-sm font-semibold transition ${active ? "bg-primary text-white" : "text-primary hover:bg-primary/10"}`} onClick={onClick} type="button">{children}</button>;
}

export default function PortalSessionsList({account, content, sessions}: {account: PortalAccount; content: SessionsContent; sessions: PortalSessionSummary[]}): React.JSX.Element {
    const [organizationUuid, setOrganizationUuid] = useState<string>("");
    const [sortKey, setSortKey] = useState<SortKey>("date");
    const visibleSessions = useMemo(() => sessions
        .filter((session) => "" === organizationUuid || session.organizations.some((organization) => organization.uuid === organizationUuid))
        .toSorted((left, right) => compareSessions(sortKey, left, right)), [organizationUuid, sessions, sortKey]);

    return <>
        <div className="mt-8 flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-low p-3 sm:flex-row sm:items-end sm:justify-between">
            {account.organizations.length > 1 ? <label className="grid min-w-0 gap-1 text-sm font-semibold"><span>{content.organizationLabel}</span><select className="min-w-0 rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" onChange={(event) => setOrganizationUuid(event.target.value)} value={organizationUuid}><option value="">{content.allOrganizations}</option>{account.organizations.map((organization) => <option key={organization.uuid} value={organization.uuid}>{organization.name}</option>)}</select></label> : null}
            <div className="grid gap-1"><span className="text-sm font-semibold">{content.sortLabel}</span><div className="flex w-full rounded-lg border border-outline-variant bg-white p-1 sm:w-auto"><SortButton active={sortKey === "date"} onClick={() => setSortKey("date")}>{content.sortDate}</SortButton><SortButton active={sortKey === "title"} onClick={() => setSortKey("title")}>{content.sortTitle}</SortButton><SortButton active={sortKey === "theme"} onClick={() => setSortKey("theme")}>{content.sortTheme}</SortButton></div></div>
        </div>
        <div className="mt-5 divide-y divide-outline-variant border-y border-outline-variant">{visibleSessions.map((session) => <Link className="block py-5 transition hover:text-primary" href={portalRoutes.session(session.slug)} key={session.slug}>{session.sharedAt ? <p className="text-sm text-on-surface-variant">{content.sharedAt} {formatSharedDate(session.sharedAt)}</p> : null}{account.organizations.length > 1 ? <p className="mt-1 text-sm text-on-surface-variant">{session.organizations.map((organization) => organization.name).join(", ")}</p> : null}<div className="mt-1 flex flex-wrap items-center gap-2"><h2 className="font-serif text-xl font-semibold">{session.title}</h2>{session.theme ? <span className="rounded-full bg-secondary-container px-2.5 py-1 text-xs font-semibold text-secondary">{session.theme}</span> : null}</div></Link>)}</div>
        {visibleSessions.length === 0 ? <p className="mt-8 text-on-surface-variant">{content.empty}</p> : null}
    </>;
}
