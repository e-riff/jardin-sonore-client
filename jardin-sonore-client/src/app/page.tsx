import {JSX} from "react";
import AboutSection from "@/components/sections/AboutSection";
import CtaSection from "@/components/sections/CtaSection";
import ExplorationSection from "@/components/sections/ExplorationSection";
import Hero from "@/components/Hero";
import ServicesSection from "@/components/sections/ServicesSection";
import StatsSection from "@/components/sections/StatsSection";
// import TestimonialsSection from "@/components/TestimonialsSection";

export default function Home(): JSX.Element {
    return (
        <>
            <Hero backgroundImage="Hero-perso.png"/>
            <StatsSection />
            <AboutSection />
            <ExplorationSection />
            <ServicesSection />
            {/* @TODO: rajouter quand ok */}
            {/*<TestimonialsSection />*/}
            <CtaSection />
        </>
    );
}
