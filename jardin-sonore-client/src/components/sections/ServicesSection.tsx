import {JSX} from "react";
import SectionHeading from "@/components/SectionHeading";
import ServicesBrowser from "@/components/ServicesBrowser";
import {getTranslations} from "@/i18n/server";

export default async function ServicesSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.services;

    const services = content.items;

    return (
        <section className="bg-surface-container-low px-6 py-xl sm:px-margin lg:py-28" id="formats">
            <div className="mx-auto max-w-7xl">
                <SectionHeading centered eyebrow={content.eyebrow} title={content.title} description={content.description} />
                <div className="mx-auto mt-4 h-1.5 w-16 rounded-full bg-primary-container" />
                <ServicesBrowser closeLabel={content.closeModalLabel} discoverCta={content.discoverCta} services={services} />
            </div>
        </section>
    );
}
