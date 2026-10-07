import type {Metadata} from "next";
import type {JSX} from "react";
import CtaSection from "@/components/sections/CtaSection";
import EarlyChildhoodApproachSection from "@/components/sections/EarlyChildhoodApproachSection";
import EarlyChildhoodEvidenceSection from "@/components/sections/EarlyChildhoodEvidenceSection";
import EarlyChildhoodExtrasSection from "@/components/sections/EarlyChildhoodExtrasSection";
import EarlyChildhoodPortalSection from "@/components/sections/EarlyChildhoodPortalSection";
import EarlyChildhoodHeroSection from "@/components/sections/EarlyChildhoodHeroSection";
import EarlyChildhoodPracticalSection from "@/components/sections/EarlyChildhoodPracticalSection";
import EarlyChildhoodStructuredData from "@/components/sections/EarlyChildhoodStructuredData";
import EarlyChildhoodTestimonialSection from "@/components/sections/EarlyChildhoodTestimonialSection";
import EarlyChildhoodWorkshopsSection from "@/components/sections/EarlyChildhoodWorkshopsSection";
import fr from "@/i18n/dictionaries/fr";

const pathname = "/eveil-musical-creche";
const {title, description} = fr.earlyChildhoodPage.metadata;
const socialImage = {
    url: "/images/social/jardin-sonore-og.jpg",
    width: 1200,
    height: 630,
    alt: fr.metadata.socialImageAlt,
};

export const metadata: Metadata = {
    title,
    description,
    alternates: {canonical: pathname},
    openGraph: {
        type: "website",
        locale: "fr_FR",
        url: pathname,
        siteName: fr.brand.name,
        title,
        description,
        images: [socialImage],
    },
    twitter: {
        card: "summary_large_image",
        title,
        description,
        images: [socialImage.url],
    },
};

export default function EarlyChildhoodPage(): JSX.Element {
    return <>
        <EarlyChildhoodStructuredData />
        <EarlyChildhoodHeroSection />
        <EarlyChildhoodWorkshopsSection />
        <EarlyChildhoodPracticalSection />
        <EarlyChildhoodApproachSection />
        <EarlyChildhoodEvidenceSection />
        <EarlyChildhoodPortalSection />
        <EarlyChildhoodTestimonialSection />
        <EarlyChildhoodExtrasSection />
        <CtaSection content={fr.earlyChildhoodPage.contact} />
    </>;
}
