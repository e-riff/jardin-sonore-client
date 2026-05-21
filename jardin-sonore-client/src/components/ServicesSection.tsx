import {AcademicCapIcon, BuildingStorefrontIcon, UserGroupIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import SectionHeading from "@/components/SectionHeading";
import ServiceCard from "@/components/ServiceCard";
import {ServiceItem} from "@/types/content";

const services: ServiceItem[] = [
    {
        icon: BuildingStorefrontIcon,
        title: "Crèches & EAMU",
        description: "Ateliers hebdomadaires adaptés aux tout-petits, centrés sur la manipulation et l'éveil sensoriel.",
        tone: "primary",
    },
    {
        icon: AcademicCapIcon,
        title: "Écoles",
        description: "Projets pédagogiques sur-mesure pour les classes maternelles, exploration des instruments du monde.",
        tone: "tertiary",
    },
    {
        icon: UserGroupIcon,
        title: "Parents-Enfants",
        description: "Des moments privilégiés de partage en famille pour renforcer le lien à travers la vibration sonore.",
        tone: "secondary",
    },
];

export default function ServicesSection(): JSX.Element {
    return (
        <section className="bg-surface-container-low px-6 py-xl sm:px-margin" id="services">
            <div className="mx-auto max-w-[1280px]">
                <SectionHeading centered title="Nos Interventions" description="Des formats adaptés à tous les environnements d'accueil de la petite enfance." />
                <div className="mx-auto mt-4 h-1.5 w-16 rounded-full bg-primary-container" />
                <div className="mt-14 grid grid-cols-1 gap-gutter md:grid-cols-3">
                    {services.map((service: ServiceItem) => <ServiceCard {...service} key={service.title} />)}
                </div>
            </div>
        </section>
    );
}
