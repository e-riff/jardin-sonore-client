import type {MetadataRoute} from "next";
import {getSiteUrl} from "@/lib/site-url";
import {showLegalPage} from "@/lib/legal-publication";

const siteUrl = getSiteUrl();

export default function sitemap(): MetadataRoute.Sitemap {
    return [
        {
            url: siteUrl,
            changeFrequency: "monthly",
            priority: 1,
        },
        ...(showLegalPage ? [{
            url: `${siteUrl}/mentions-legales`,
            changeFrequency: "yearly",
            priority: 0.2,
        } as const] : []),
    ];
}
