const productionSiteUrl = "https://jardin-sonore.fr";

function normalizeSiteUrl(url: string): string {
    const trimmedUrl = url.trim().replace(/\/+$/, "");

    if (/^localhost(:\d+)?$/i.test(trimmedUrl) || /^127\.0\.0\.1(:\d+)?$/.test(trimmedUrl)) {
        return `http://${trimmedUrl}`;
    }

    if (!/^https?:\/\//i.test(trimmedUrl)) {
        return `https://${trimmedUrl}`;
    }

    return trimmedUrl;
}

export function getSiteUrl(): string {
    return normalizeSiteUrl(process.env.PUBLIC_SITE_URL ?? productionSiteUrl);
}
