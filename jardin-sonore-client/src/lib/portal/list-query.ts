export type PortalListKind = "sessions" | "repertoire";
export type PortalListSort = "date" | "updatedAt" | "title";
export type PortalListDirection = "asc" | "desc";

export interface PortalListQuery {
    query: string;
    organizationUuid: string;
    themeUuids: string[];
    type: "" | "nursery_rhyme" | "fingerplay";
    sort: PortalListSort;
    direction: PortalListDirection;
    page: number;
}

type SearchParamsInput = URLSearchParams | Record<string, string | string[] | undefined>;

function values(searchParams: SearchParamsInput, key: string): string[] {
    if (searchParams instanceof URLSearchParams) return searchParams.getAll(key);
    const value = searchParams[key];
    return typeof value === "string" ? [value] : value ?? [];
}

export function parsePortalListQuery(searchParams: SearchParamsInput, kind: PortalListKind): PortalListQuery {
    const requestedSort = values(searchParams, "sort")[0];
    const defaultSort = kind === "sessions" ? "date" : "updatedAt";
    const allowedSorts = kind === "sessions" ? ["date", "title"] : ["updatedAt", "title"];
    const requestedType = values(searchParams, "type")[0];
    const requestedDirection = values(searchParams, "direction")[0];
    const requestedPage = Number(values(searchParams, "page")[0] ?? 1);

    return {
        query: (values(searchParams, "q")[0] ?? "").trim(),
        organizationUuid: values(searchParams, "organization")[0] ?? "",
        themeUuids: [...new Set([...values(searchParams, "theme[]"), ...values(searchParams, "theme")].filter(Boolean))],
        type: kind === "repertoire" && (requestedType === "nursery_rhyme" || requestedType === "fingerplay") ? requestedType : "",
        sort: allowedSorts.includes(requestedSort ?? "") ? requestedSort as PortalListSort : defaultSort,
        direction: requestedDirection === "asc" ? "asc" : "desc",
        page: Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1,
    };
}

export function portalListQueryToSearchParams(query: PortalListQuery): URLSearchParams {
    const searchParams = new URLSearchParams();
    if (query.query) searchParams.set("q", query.query);
    if (query.organizationUuid) searchParams.set("organization", query.organizationUuid);
    query.themeUuids.forEach((themeUuid) => searchParams.append("theme[]", themeUuid));
    if (query.type) searchParams.set("type", query.type);
    if (query.sort !== "date" && query.sort !== "updatedAt") searchParams.set("sort", query.sort);
    if (query.direction !== "desc") searchParams.set("direction", query.direction);
    if (query.page > 1) searchParams.set("page", String(query.page));
    return searchParams;
}

export function updatePortalListQuery(query: PortalListQuery, patch: Partial<PortalListQuery>): PortalListQuery {
    return {...query, ...patch, page: 1};
}
