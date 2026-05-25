import {SunIcon, BuildingStorefrontIcon, UsersIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import SectionHeading from "@/components/SectionHeading";
import ServiceCard from "@/components/ServiceCard";
import {getTranslations} from "@/i18n/server";
import {ServiceItem} from "@/types/content";

const serviceIcons: ServiceItem["icon"][] = [
    BuildingStorefrontIcon,
    SunIcon,
    UsersIcon,
];

export default async function ServicesSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.services;

    const services: ServiceItem[] = content.items.map((item, index) => ({...item, icon: serviceIcons[index]}));

    return (
        <section className="bg-surface-container-low px-6 py-xl sm:px-margin" id="prestations">
            <div className="mx-auto max-w-320">
                <SectionHeading centered title={content.title} description={content.description} />
                <div className="mx-auto mt-4 h-1.5 w-16 rounded-full bg-primary-container" />
                <div className="mt-14 grid grid-cols-1 gap-gutter md:grid-cols-3">
                    {services.map((service: ServiceItem) => <ServiceCard {...service} ctaLabel={content.discoverCta} key={service.title} />)}
                </div>
            </div>
        </section>
    );
}
