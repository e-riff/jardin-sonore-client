import type {MetadataRoute} from "next";

const siteUrl = (process.env.PUBLIC_SITE_URL ?? "https://jardin-sonore.fr").replace(/\/+$/, "");

export default function sitemap(): MetadataRoute.Sitemap {
    return [
        {
            url: siteUrl,
            lastModified: new Date(),
            changeFrequency: "monthly",
            priority: 1,
        },
    ];
}
