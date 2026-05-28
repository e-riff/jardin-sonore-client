import type {MetadataRoute} from "next";
import {getSiteUrl} from "@/lib/site-url";

const siteUrl = getSiteUrl();
const isProduction = process.env.NODE_ENV === "production";

export default function robots(): MetadataRoute.Robots {
    return {
        rules: {
            userAgent: "*",
            allow: isProduction ? "/" : undefined,
            disallow: isProduction ? "/api/" : "/",
        },
        host: siteUrl,
        sitemap: `${siteUrl}/sitemap.xml`,
    };
}
