import {JSX} from "react";
import AboutSection from "@/components/AboutSection";
import CtaSection from "@/components/CtaSection";
import Hero from "@/components/Hero";
import ServicesSection from "@/components/ServicesSection";
import StatsSection from "@/components/StatsSection";

export default function Home(): JSX.Element {
    return (
        <>
            <Hero backgroundImage="Hero2.jpg" />
            <StatsSection />
            <AboutSection />
            <ServicesSection />
            <CtaSection />
        </>
    );
}
