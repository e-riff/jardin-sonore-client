import {JSX} from "react";
import fr from "@/i18n/dictionaries/fr";

const siteUrl = (process.env.PUBLIC_SITE_URL ?? "https://jardin-sonore.fr").replace(/\/+$/, "");

const areaServed = [
    "Saint-Étienne",
    "Lyon",
    "Pilat",
    "Forez",
    "Giers",
    "Grand Lyon",
    "Roanne",
    "Haute-Loire",
    "Annonay",
    "Condrieu",
    "Vienne",
].map((name) => ({
    "@type": "Place",
    name,
}));

const services = fr.services.items.map((service) => ({
    "@type": "Service",
    name: service.title,
    description: service.description,
    serviceType: service.title,
    areaServed,
    provider: {
        "@id": `${siteUrl}/#organization`,
    },
}));

const structuredData = {
    "@context": "https://schema.org",
    "@graph": [
        {
            "@id": `${siteUrl}/#organization`,
            "@type": "ProfessionalService",
            name: fr.brand.name,
            description: fr.metadata.description,
            url: siteUrl,
            image: `${siteUrl}/Hero-perso.png`,
            logo: `${siteUrl}/favicon/favicon-96x96.png`,
            areaServed,
            sameAs: [
                "https://www.instagram.com/jardin.sonore/",
            ],
            contactPoint: {
                "@type": "ContactPoint",
                contactType: "customer service",
                availableLanguage: "fr",
                url: `${siteUrl}/#contact`,
            },
            makesOffer: services.map((service) => ({
                "@type": "Offer",
                itemOffered: service,
            })),
        },
        {
            "@id": `${siteUrl}/#website`,
            "@type": "WebSite",
            name: fr.brand.name,
            description: fr.metadata.description,
            url: siteUrl,
            publisher: {
                "@id": `${siteUrl}/#organization`,
            },
            inLanguage: "fr-FR",
        },
        ...services,
    ],
};

export default function StructuredData(): JSX.Element {
    return (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{
                __html: JSON.stringify(structuredData).replace(/</g, "\\u003c"),
            }}
        />
    );
}
