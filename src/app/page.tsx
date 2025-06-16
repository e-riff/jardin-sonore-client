import {JSX} from "react";
import Hero from "@/components/Hero";
import StatsSection from "@/components/StatsSection";

export default function Home(): JSX.Element {
  return (
      <div>
          <Hero
              title="Jardin Sonore"
              subtitle="Éveil musical pour la petite enfance"
              ctaText="En savoir plus"
              backgroundImage="Hero2.jpg"
          />

          <StatsSection></StatsSection>

      </div>
  );
}
