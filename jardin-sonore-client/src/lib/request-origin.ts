import {NextRequest} from "next/server";

const siteUrl = (process.env.PUBLIC_SITE_URL ?? "https://jardin-sonore.fr").replace(/\/+$/, "");
const isProduction = process.env.NODE_ENV === "production";

function getOrigin(value: string | null): string | null {
    if (!value) {
        return null;
    }

    try {
        return new URL(value).origin;
    } catch {
        return null;
    }
}

export function isAllowedRequestOrigin(request: NextRequest): boolean {
    const allowedOrigins = new Set([
        new URL(siteUrl).origin,
        request.nextUrl.origin,
    ]);
    const origin = getOrigin(request.headers.get("origin"));
    const referer = getOrigin(request.headers.get("referer"));

    if (origin) {
        return allowedOrigins.has(origin);
    }

    if (referer) {
        return allowedOrigins.has(referer);
    }

    return !isProduction;
}
