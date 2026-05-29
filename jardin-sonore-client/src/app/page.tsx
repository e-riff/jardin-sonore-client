import {JSX} from "react";
import AboutSection from "@/components/sections/AboutSection";
import CtaSection from "@/components/sections/CtaSection";
import ExplorationSection from "@/components/sections/ExplorationSection";
import FounderSection from "@/components/sections/FounderSection";
import Hero from "@/components/Hero";
import ServicesSection from "@/components/sections/ServicesSection";
import StatsSection from "@/components/sections/StatsSection";
import StructuredData from "@/components/StructuredData";
// import TestimonialsSection from "@/components/TestimonialsSection";

export default function Home(): JSX.Element {
    return (
        <>
            <StructuredData />
            <Hero backgroundImage="images/hero-perso.webp"/>
            <StatsSection />
            <FounderSection />
            <ServicesSection />
            <AboutSection />
            <ExplorationSection />
            {/* @TODO: rajouter quand ok */}
            {/*<TestimonialsSection />*/}
            <CtaSection />
        </>
    );
}
