import Link from "next/link";
import PortalListFilters from "@/components/portal/PortalListFilters";
import PortalListPagination from "@/components/portal/PortalListPagination";
import PortalThemeBadges from "@/components/portal/PortalThemeBadges";
import type {Dictionary} from "@/i18n/types";
import type {PortalListQuery} from "@/lib/portal/list-query";
import {portalRoutes} from "@/lib/portal/routes";
import type {PortalAccount, PortalSessionListResponse} from "@/lib/portal/types";

const frenchDateFormatter = new Intl.DateTimeFormat("fr-FR", {timeZone: "UTC"});
const formatDate = (date: string): string => frenchDateFormatter.format(new Date(`${date}T12:00:00Z`));

export default function PortalSessionsList({account, content, filters, response, query}: {account: PortalAccount; content: Dictionary["portal"]["sessions"]; filters: Dictionary["portal"]["filters"]; response: PortalSessionListResponse; query: PortalListQuery}): React.JSX.Element {
    return <>
        <PortalListFilters content={filters} kind="sessions" organizations={account.organizations} query={query} themes={response.availableThemes} />
        {response.items.length === 0 ? <p className="mt-8 text-on-surface-variant">{content.empty}</p> : <div className="mt-5 divide-y divide-outline-variant border-y border-outline-variant">{response.items.map((session) => <article className="py-5" key={session.slug}>
            <p className="text-sm text-on-surface-variant">{content.sharedAt} {formatDate(session.sharedAt ?? session.sessionDate)}</p>
            {account.organizations.length > 1 ? <p className="mt-1 text-sm text-on-surface-variant">{session.organizations.map((organization) => organization.name).join(", ")}</p> : null}
            <h2 className="mt-1 font-serif text-xl font-semibold"><Link className="hover:text-primary" href={portalRoutes.session(session.slug)}>{session.title}</Link></h2>
            {session.subtitle ? <p className="mt-1 text-sm text-on-surface-variant">{session.subtitle}</p> : null}
            <div className="mt-2"><PortalThemeBadges themes={session.themes} /></div>
        </article>)}</div>}
        <PortalListPagination basePath={portalRoutes.sessions} content={filters} pagination={response.pagination} query={query} />
    </>;
}
