"use client";

import {JSX, useState} from "react";
import ServiceCard from "@/components/ServiceCard";
import ServiceModal from "@/components/ServiceModal";
import {ServiceItem} from "@/types/content";

interface ServicesBrowserProps {
    services: readonly ServiceItem[];
    discoverCta: string;
    closeLabel: string;
}

export default function ServicesBrowser({services, discoverCta, closeLabel}: ServicesBrowserProps): JSX.Element {
    const [selectedService, setSelectedService] = useState<ServiceItem | null>(null);

    return (
        <>
            <div className="mt-14 grid grid-cols-1 gap-gutter md:grid-cols-3">
                {services.map((service) => (
                    <ServiceCard {...service} ctaLabel={discoverCta} key={service.title} onDiscover={() => setSelectedService(service)} />
                ))}
            </div>
            {selectedService ? <ServiceModal closeLabel={closeLabel} service={selectedService} onClose={() => setSelectedService(null)} /> : null}
        </>
    );
}
