import type {MetadataRoute} from "next";
import {getSiteUrl} from "@/lib/site-url";
import {legalPagePublished} from "@/lib/legal-publication";

const siteUrl = getSiteUrl();
const isProduction = process.env.NODE_ENV === "production";

export default function robots(): MetadataRoute.Robots {
    return {
        rules: {
            userAgent: "*",
            allow: isProduction ? "/" : undefined,
            disallow: isProduction ? ["/api/", "/portail/", "/newsletter/confirmer/", ...(!legalPagePublished ? ["/mentions-legales"] : [])] : "/",
        },
        host: siteUrl,
        sitemap: `${siteUrl}/sitemap.xml`,
    };
}
