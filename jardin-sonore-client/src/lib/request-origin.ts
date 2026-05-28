import {NextRequest} from "next/server";

const siteUrl = (process.env.PUBLIC_SITE_URL ?? "https://jardin-sonore.fr").replace(/\/+$/, "");
const isProduction = process.env.NODE_ENV === "production";

function getAllowedOrigins(request: NextRequest): Set<string> {
    const origins = new Set([
        new URL(siteUrl).origin,
        request.nextUrl.origin,
    ]);

    for (const origin of [...origins]) {
        const url = new URL(origin);
        const alternateHost = url.hostname.startsWith("www.")
            ? url.hostname.replace(/^www\./, "")
            : `www.${url.hostname}`;

        origins.add(`${url.protocol}//${alternateHost}`);
    }

    return origins;
}

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
    const allowedOrigins = getAllowedOrigins(request);
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
