"use client";

import {useEffect, useRef, useState} from "react";
import {ChevronDownIcon, XMarkIcon} from "@heroicons/react/24/outline";
import {usePathname, useRouter} from "next/navigation";
import type {Dictionary} from "@/i18n/types";
import {applyPortalListQueryPatch, hasActivePortalListFilters, parsePortalListQuery, PORTAL_CATEGORY_PREVIEW_LIMIT, portalCategoryPreview, portalListQueryToSearchParams, selectPortalListSort, togglePortalListTheme, type PortalListKind, type PortalListQuery, type PortalListSort} from "@/lib/portal/list-query";
import type {PortalOrganization, PortalTheme} from "@/lib/portal/types";

interface PortalListFiltersProps {
    accountLabel: string;
    title: string;
    introduction: string;
    kind: PortalListKind;
    query: PortalListQuery;
    organizations: PortalOrganization[];
    themes: PortalTheme[];
    content: Dictionary["portal"]["filters"];
}

export default function PortalListFilters({accountLabel, title, introduction, kind, query, organizations, themes, content}: PortalListFiltersProps): React.JSX.Element {
    const router = useRouter();
    const pathname = usePathname();
    const queryRef = useRef(query);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const categoriesButtonRef = useRef<HTMLButtonElement>(null);
    const categoriesPanelRef = useRef<HTMLDivElement>(null);
    const [currentQuery, setCurrentQuery] = useState(query);
    const [previousQuery, setPreviousQuery] = useState(query);
    const [categoriesOpen, setCategoriesOpen] = useState(false);
    const [showAllCategories, setShowAllCategories] = useState(false);
    if (query !== previousQuery) {
        setPreviousQuery(query);
        setCurrentQuery(query);
    }
    useEffect(() => {
        queryRef.current = query;
    }, [query]);
    useEffect(() => () => {
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    }, []);
    useEffect(() => {
        if (!categoriesOpen) return;
        const closeOnOutsideClick = (event: PointerEvent): void => {
            const target = event.target as Node;
            if (!categoriesButtonRef.current?.contains(target) && !categoriesPanelRef.current?.contains(target)) setCategoriesOpen(false);
        };
        const closeOnEscape = (event: KeyboardEvent): void => {
            if (event.key === "Escape") {
                setCategoriesOpen(false);
                categoriesButtonRef.current?.focus();
            }
        };
        document.addEventListener("pointerdown", closeOnOutsideClick);
        document.addEventListener("keydown", closeOnEscape);
        return () => {
            document.removeEventListener("pointerdown", closeOnOutsideClick);
            document.removeEventListener("keydown", closeOnEscape);
        };
    }, [categoriesOpen]);

    const navigate = (next: PortalListQuery): void => {
        const search = portalListQueryToSearchParams(next).toString();
        router.replace(`${pathname}${search ? `?${search}` : ""}`, {scroll: false});
    };
    const update = (change: Partial<PortalListQuery> | ((current: PortalListQuery) => Partial<PortalListQuery>), debounce = false): void => {
        const patch = typeof change === "function" ? change(queryRef.current) : change;
        const next = applyPortalListQueryPatch(queryRef, patch);
        setCurrentQuery(next);
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        searchTimeoutRef.current = debounce ? setTimeout(() => navigate(queryRef.current), 350) : null;
        if (!debounce) navigate(next);
    };
    const reset = (): void => {
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        searchTimeoutRef.current = null;
        const next = parsePortalListQuery(new URLSearchParams(), kind);
        queryRef.current = next;
        setCurrentQuery(next);
        setCategoriesOpen(false);
        router.replace(pathname, {scroll: false});
    };
    const sortOptions: Array<{value: PortalListSort; label: string; shortLabel: string}> = kind === "sessions"
        ? [{value: "date", label: content.sortDate, shortLabel: content.sortDateShort}, {value: "title", label: content.sortTitle, shortLabel: content.sortTitleShort}]
        : [{value: "updatedAt", label: content.sortUpdatedAt, shortLabel: content.sortDateShort}, {value: "title", label: content.sortTitle, shortLabel: content.sortTitleShort}];
    const hasActiveFilters = hasActivePortalListFilters(currentQuery, kind);
    const themeButton = (theme: PortalTheme): React.JSX.Element => {
        const selected = currentQuery.themeUuids.includes(theme.uuid);
        return <button aria-pressed={selected} className={`portal-theme-badge min-h-8 transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${selected ? "portal-theme-badge--selected" : ""}`} key={theme.uuid} onClick={() => update((current) => ({themeUuids: togglePortalListTheme(current, theme.uuid).themeUuids}))} style={{"--badge-color": theme.color} as React.CSSProperties} type="button">{theme.label}</button>;
    };

    return <>
        <p className="portal-eyebrow">{accountLabel}<span className="mx-2 text-outline-variant">—</span><span className="text-secondary">{organizations.map((organization) => organization.name).join(", ")}</span></p>
        <div className="flex flex-wrap items-end justify-between gap-4">
            <h1 className="font-serif text-4xl font-semibold">{title}</h1>
            {organizations.length > 1 ? <label className="grid gap-1 text-sm font-semibold" htmlFor={`${kind}-organization`}><span>{content.organizationLabel}</span><select className="w-44 rounded-md border border-outline-variant bg-white px-3 py-2 font-normal" id={`${kind}-organization`} onChange={(event) => update({organizationUuid: event.target.value})} value={currentQuery.organizationUuid}><option value="">{content.allOrganizations}</option>{organizations.map((organization) => <option key={organization.uuid} value={organization.uuid}>{organization.name}</option>)}</select></label> : null}
        </div>
        <p className="mt-3 text-on-surface-variant">{introduction}</p>
        <div className="relative mt-8 rounded-xl border border-outline-variant bg-surface-container-low p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3 text-sm font-semibold">
                <label htmlFor={`${kind}-search`}>{content.searchLabel}</label>
                <button aria-hidden={!hasActiveFilters} className={`inline-flex min-h-6 shrink-0 items-center gap-1.5 rounded-md px-1 text-xs font-semibold text-primary hover:bg-primary/10 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${hasActiveFilters ? "" : "invisible pointer-events-none"}`} onClick={reset} tabIndex={hasActiveFilters ? 0 : -1} type="button"><XMarkIcon aria-hidden="true" className="h-3.5 w-3.5" />{content.reset}</button>
            </div>
            <input className="mt-1 w-full max-w-2xl rounded-md border border-outline-variant bg-white px-3 py-2 font-normal focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" id={`${kind}-search`} name="q" onChange={(event) => update({query: event.target.value}, true)} type="search" value={currentQuery.query} />
            <div className="mt-4 flex flex-wrap items-start gap-x-4 gap-y-3">
                <div className="flex min-w-0 flex-wrap items-start gap-3 lg:flex-1">
                    {kind === "repertoire" ? <label className="grid shrink-0 gap-1 text-sm font-semibold"><span>{content.typeLabel}</span><select className="w-36 rounded-md border border-outline-variant bg-white px-3 py-2 font-normal focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onChange={(event) => update({type: event.target.value as PortalListQuery["type"]})} value={currentQuery.type}><option value="">{content.allTypes}</option><option value="nursery_rhyme">{content.nurseryRhyme}</option><option value="fingerplay">{content.fingerplay}</option></select></label> : null}
                    <div className="grid shrink-0 gap-1 text-sm font-semibold lg:hidden"><span>{content.categoriesLabel}</span><button aria-controls={categoriesOpen ? `${kind}-categories-panel` : undefined} aria-expanded={categoriesOpen} className="inline-flex min-h-10 items-center gap-2 rounded-md border border-outline-variant bg-white px-3 py-2 text-sm font-normal text-on-surface disabled:cursor-not-allowed disabled:opacity-60" disabled={themes.length === 0} onClick={() => setCategoriesOpen((open) => !open)} ref={categoriesButtonRef} title={themes.length === 0 ? content.noCategories : undefined} type="button">{content.categoriesLabel}{currentQuery.themeUuids.length > 0 ? ` (${currentQuery.themeUuids.length})` : null}<ChevronDownIcon aria-hidden="true" className="h-4 w-4" /></button></div>
                    <div className="hidden min-w-0 flex-1 gap-1 lg:grid"><span className="text-sm font-semibold">{content.categoriesLabel}{currentQuery.themeUuids.length > 0 ? ` (${currentQuery.themeUuids.length})` : null}</span><div aria-label={content.categoriesLabel} className="flex flex-wrap gap-1.5" id={`${kind}-desktop-categories`} role="group">{themes.length > 0 ? portalCategoryPreview(themes, showAllCategories).map(themeButton) : <span className="py-2 text-sm text-on-surface-variant">{content.noCategories}</span>}</div>{themes.length > PORTAL_CATEGORY_PREVIEW_LIMIT ? <button aria-controls={`${kind}-desktop-categories`} aria-expanded={showAllCategories} className="w-fit text-xs font-semibold text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onClick={() => setShowAllCategories((expanded) => !expanded)} type="button">{showAllCategories ? content.showFewerCategories : content.showAllCategories}</button> : null}</div>
                </div>
                <div className="ml-auto flex shrink-0 items-center gap-2 self-end text-sm font-semibold lg:grid lg:items-start lg:gap-1 lg:self-auto"><span className="hidden lg:block">{content.sortLabel}</span><div aria-label={content.sortLabel} className="flex rounded-lg border border-outline-variant bg-white p-1" role="group">{sortOptions.map((option) => {
                    const selected = currentQuery.sort === option.value;
                    return <button aria-label={`${option.label}, ${selected ? currentQuery.direction === "asc" ? content.ascending : content.descending : content.sortLabel}`} aria-pressed={selected} className={`rounded-md px-3 py-1.5 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${selected ? "bg-primary text-white" : "text-primary hover:bg-primary/10"}`} key={option.value} onClick={() => update((current) => selectPortalListSort(current, option.value))} type="button">{option.shortLabel}{selected ? <span aria-hidden="true" className="ml-1">{currentQuery.direction === "asc" ? "↑" : "↓"}</span> : null}</button>;
                })}</div></div>
            </div>
            {categoriesOpen ? <div aria-label={content.categoriesLabel} className="absolute left-4 right-4 top-full z-20 mt-2 max-h-64 overflow-y-auto rounded-lg border border-outline-variant bg-white p-3 shadow-lg sm:left-5 sm:right-auto sm:min-w-80 lg:hidden" id={`${kind}-categories-panel`} ref={categoriesPanelRef} role="group"><div className="flex flex-wrap gap-1.5">{themes.map(themeButton)}</div></div> : null}
        </div>
    </>;
}
