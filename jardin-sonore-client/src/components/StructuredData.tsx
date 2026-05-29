import {JSX} from "react";
import fr from "@/i18n/dictionaries/fr";
import {getSiteUrl} from "@/lib/site-url";

const siteUrl = getSiteUrl();

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
            image: `${siteUrl}/images/hero-perso.webp`,
            logo: `${siteUrl}/favicon/favicon-96x96.png`,
            areaServed,
            founder: {
                "@id": `${siteUrl}/#person`,
            },
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
        {
            "@id": `${siteUrl}/#person`,
            "@type": "Person",
            name: "Emeric RIFF",
            givenName: "Emeric",
            familyName: "RIFF",
            jobTitle: "Intervenant musical petite enfance",
            description: "Musicien, professeur de musique et intervenant auprès des jeunes enfants.",
            image: `${siteUrl}/images/fondateur-couvercles.webp`,
            hasCredential: {
                "@type": "EducationalOccupationalCredential",
                name: "Diplôme dans le champ de la formation et de la petite enfance",
            },
            worksFor: {
                "@id": `${siteUrl}/#organization`,
            },
        },
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
