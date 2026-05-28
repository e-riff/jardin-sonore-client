import type {MetadataRoute} from "next";

const siteUrl = (process.env.PUBLIC_SITE_URL ?? "https://jardin-sonore.fr").replace(/\/+$/, "");
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
