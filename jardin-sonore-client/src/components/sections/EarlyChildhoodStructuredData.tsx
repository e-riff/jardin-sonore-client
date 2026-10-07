import type {JSX} from "react";
import {getTranslations} from "@/i18n/server";
import {getSiteUrl} from "@/lib/site-url";

const pathname = "/eveil-musical-creche";

export default async function EarlyChildhoodStructuredData(): Promise<JSX.Element> {
    const {metadata: content} = (await getTranslations()).earlyChildhoodPage;
    const siteUrl = getSiteUrl();
    const structuredData = {
        "@context": "https://schema.org",
        "@type": "Service",
        "@id": `${siteUrl}${pathname}#service`,
        name: content.title,
        description: content.description,
        serviceType: "Ateliers d’éveil musical en structure petite enfance",
        url: `${siteUrl}${pathname}`,
        provider: {"@id": `${siteUrl}/#organization`},
        areaServed: content.serviceAreas.map((name) => ({"@type": "Place", name})),
    };

    return <script type="application/ld+json" dangerouslySetInnerHTML={{__html: JSON.stringify(structuredData).replace(/</g, "\\u003c")}} />;
}
