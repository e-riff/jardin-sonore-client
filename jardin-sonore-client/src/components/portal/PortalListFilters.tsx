"use client";

import {useRef, useState} from "react";
import {usePathname, useRouter} from "next/navigation";
import type {Dictionary} from "@/i18n/types";
import {applyPortalListQueryPatch, parsePortalListQuery, portalListQueryToSearchParams, type PortalListKind, type PortalListQuery, type PortalListSort} from "@/lib/portal/list-query";
import type {PortalOrganization, PortalTheme} from "@/lib/portal/types";

interface PortalListFiltersProps {
    kind: PortalListKind;
    query: PortalListQuery;
    organizations: PortalOrganization[];
    themes: PortalTheme[];
    content: Dictionary["portal"]["filters"];
}

export default function PortalListFilters({kind, query, organizations, themes, content}: PortalListFiltersProps): React.JSX.Element {
    const router = useRouter();
    const pathname = usePathname();
    const queryRef = useRef(query);
    const [currentQuery, setCurrentQuery] = useState(query);
    const update = (change: Partial<PortalListQuery> | ((current: PortalListQuery) => Partial<PortalListQuery>)): void => {
        const patch = typeof change === "function" ? change(queryRef.current) : change;
        const next = applyPortalListQueryPatch(queryRef, patch);
        setCurrentQuery(next);
        const search = portalListQueryToSearchParams(next).toString();
        router.replace(`${pathname}${search ? `?${search}` : ""}`, {scroll: false});
    };
    const reset = (): void => {
        const next = parsePortalListQuery(new URLSearchParams(), kind);
        queryRef.current = next;
        setCurrentQuery(next);
        router.replace(pathname, {scroll: false});
    };
    const sortOptions: Array<{value: PortalListSort; label: string}> = kind === "sessions"
        ? [{value: "date", label: content.sortDate}, {value: "title", label: content.sortTitle}]
        : [{value: "updatedAt", label: content.sortUpdatedAt}, {value: "title", label: content.sortTitle}];

    return <div className="mt-8 space-y-5 rounded-xl border border-outline-variant bg-surface-container-low p-4 sm:p-5">
        <form className="flex flex-col gap-2 sm:flex-row sm:items-end" key={currentQuery.query} onSubmit={(event) => {
            event.preventDefault();
            update({query: String(new FormData(event.currentTarget).get("q") ?? "").trim()});
        }} role="search">
            <label className="grid min-w-0 flex-1 gap-1 text-sm font-semibold" htmlFor={`${kind}-search`}><span>{content.searchLabel}</span><input className="rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" defaultValue={currentQuery.query} id={`${kind}-search`} name="q" type="search" /></label>
            <button className="rounded-md bg-primary px-4 py-2 font-semibold text-white" type="submit">{content.searchAction}</button>
        </form>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {organizations.length > 1 ? <label className="grid gap-1 text-sm font-semibold"><span>{content.organizationLabel}</span><select className="min-w-0 rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" onChange={(event) => update({organizationUuid: event.target.value})} value={currentQuery.organizationUuid}><option value="">{content.allOrganizations}</option>{organizations.map((organization) => <option key={organization.uuid} value={organization.uuid}>{organization.name}</option>)}</select></label> : null}
            {kind === "repertoire" ? <label className="grid gap-1 text-sm font-semibold"><span>{content.typeLabel}</span><select className="rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" onChange={(event) => update({type: event.target.value as PortalListQuery["type"]})} value={currentQuery.type}><option value="">{content.allTypes}</option><option value="nursery_rhyme">{content.nurseryRhyme}</option><option value="fingerplay">{content.fingerplay}</option></select></label> : null}
            <label className="grid gap-1 text-sm font-semibold"><span>{content.sortLabel}</span><select className="rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" onChange={(event) => update({sort: event.target.value as PortalListSort})} value={currentQuery.sort}>{sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <label className="grid gap-1 text-sm font-semibold"><span>{content.directionLabel}</span><select className="rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" onChange={(event) => update({direction: event.target.value as PortalListQuery["direction"]})} value={currentQuery.direction}><option value="desc">{content.descending}</option><option value="asc">{content.ascending}</option></select></label>
        </div>
        {themes.length > 0 ? <fieldset className="space-y-2"><legend className="text-sm font-semibold">{content.categoriesLabel}</legend><div className="flex flex-wrap gap-2">{themes.map((theme) => {
            const selected = currentQuery.themeUuids.includes(theme.uuid);
            return <button aria-pressed={selected} className={`rounded-full border border-l-4 px-3 py-1.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${selected ? "border-primary bg-primary text-white" : "border-outline-variant bg-white text-on-surface hover:bg-primary/10"}`} key={theme.uuid} onClick={() => update((current) => ({themeUuids: current.themeUuids.includes(theme.uuid) ? current.themeUuids.filter((uuid) => uuid !== theme.uuid) : [...current.themeUuids, theme.uuid]}))} style={{borderLeftColor: theme.color}} type="button">{theme.label}</button>;
        })}</div></fieldset> : null}
        <button className="text-sm font-semibold text-primary underline underline-offset-4" onClick={reset} type="button">{content.reset}</button>
    </div>;
}
