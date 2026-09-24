import Link from "next/link";
import type {Dictionary} from "@/i18n/types";
import {portalListQueryToSearchParams, type PortalListQuery} from "@/lib/portal/list-query";
import type {PortalPagination} from "@/lib/portal/types";

export default function PortalListPagination({basePath, pagination, query, content}: {basePath: string; pagination: PortalPagination; query: PortalListQuery; content: Dictionary["portal"]["filters"]}): React.JSX.Element | null {
    const totalPages = Math.ceil(pagination.total / pagination.pageSize);
    if (totalPages <= 1) return null;
    const link = (page: number): string => {
        const search = portalListQueryToSearchParams({...query, page}).toString();
        return `${basePath}${search ? `?${search}` : ""}`;
    };

    return <nav aria-label={content.pageStatus} className="mt-6 flex items-center justify-between gap-3 text-sm">
        {pagination.page > 1 ? <Link className="font-semibold text-primary underline underline-offset-4" href={link(pagination.page - 1)}>{content.previousPage}</Link> : <span />}
        <span aria-live="polite">{content.pageStatus} {pagination.page} / {totalPages}</span>
        {pagination.page < totalPages ? <Link className="font-semibold text-primary underline underline-offset-4" href={link(pagination.page + 1)}>{content.nextPage}</Link> : <span />}
    </nav>;
}
