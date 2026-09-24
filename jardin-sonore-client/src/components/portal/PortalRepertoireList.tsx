import Image from "next/image";
import Link from "next/link";
import PortalListFilters from "@/components/portal/PortalListFilters";
import PortalListPagination from "@/components/portal/PortalListPagination";
import PortalThemeBadges from "@/components/portal/PortalThemeBadges";
import type {Dictionary} from "@/i18n/types";
import {portalListQueryToSearchParams, type PortalListQuery} from "@/lib/portal/list-query";
import {portalRoutes} from "@/lib/portal/routes";
import type {PortalAccount, PortalRepertoireListResponse} from "@/lib/portal/types";

const dateFormatter = new Intl.DateTimeFormat("fr-FR", {dateStyle: "medium"});

export default function PortalRepertoireList({account, content, filters, response, query}: {account: PortalAccount; content: Dictionary["portal"]["repertoire"]; filters: Dictionary["portal"]["filters"]; response: PortalRepertoireListResponse; query: PortalListQuery}): React.JSX.Element {
    const detailSearch = portalListQueryToSearchParams(query).toString();
    return <>
        <PortalListFilters content={filters} kind="repertoire" organizations={account.organizations} query={query} themes={response.availableThemes} />
        {response.items.length === 0 ? <p className="mt-8 text-on-surface-variant">{content.empty}</p> : <div className="mt-5 divide-y divide-outline-variant border-y border-outline-variant">{response.items.map((item) => <article className="flex gap-4 py-5 sm:gap-6" key={item.slug}>
            {item.thumbnailUrl ? <Image alt="" className="h-20 w-28 shrink-0 rounded-md object-cover sm:h-24 sm:w-36" height={96} src={item.thumbnailUrl} unoptimized width={144} /> : null}
            <div className="min-w-0 flex-1">
                <p className="text-sm text-on-surface-variant">{item.type === "fingerplay" ? filters.fingerplay : filters.nurseryRhyme} · {content.updatedAt} {dateFormatter.format(new Date(item.updatedAt))}</p>
                <h2 className="mt-1 font-serif text-xl font-semibold"><Link className="hover:text-primary" href={`${portalRoutes.repertoireItem(item.slug)}${detailSearch ? `?${detailSearch}` : ""}`}>{item.title}</Link></h2>
                {account.organizations.length > 1 ? <p className="mt-1 text-sm text-on-surface-variant">{item.organizations.map((organization) => organization.name).join(", ")}</p> : null}
                <div className="mt-2"><PortalThemeBadges themes={item.themes} /></div>
            </div>
        </article>)}</div>}
        <PortalListPagination basePath={portalRoutes.repertoire} content={filters} pagination={response.pagination} query={query} />
    </>;
}
