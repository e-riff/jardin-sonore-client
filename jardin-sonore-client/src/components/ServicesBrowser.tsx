"use client";

import {JSX, useCallback, useSyncExternalStore} from "react";
import ServiceCard from "@/components/ServiceCard";
import ServiceModal from "@/components/ServiceModal";
import {ServiceItem} from "@/types/content";

const subscribeToLocation = (callback: () => void): (() => void) => {
    window.addEventListener("popstate", callback);

    return () => window.removeEventListener("popstate", callback);
};

const getLocationSearch = (): string => window.location.search;
const getServerLocationSearch = (): string => "";

interface ServicesBrowserProps {
    services: readonly ServiceItem[];
    discoverCta: string;
    closeLabel: string;
    backLabel: string;
}

export default function ServicesBrowser({services, discoverCta, closeLabel, backLabel}: ServicesBrowserProps): JSX.Element {
    const locationSearch = useSyncExternalStore(subscribeToLocation, getLocationSearch, getServerLocationSearch);
    const requestedSlug = new URLSearchParams(locationSearch).get("format");
    const linkedService = services.find((service) => service.slug === requestedSlug) ?? null;
    const selectedService = linkedService;

    const openService = useCallback((service: ServiceItem): void => {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set("format", service.slug);
        window.history.pushState(window.history.state, "", `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`);
        window.dispatchEvent(new PopStateEvent("popstate"));
    }, []);

    const closeService = useCallback((): void => {
        const currentUrl = new URL(window.location.href);

        if (currentUrl.searchParams.has("format")) {
            currentUrl.searchParams.delete("format");
            window.history.replaceState(window.history.state, "", `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`);
            window.dispatchEvent(new PopStateEvent("popstate"));
        }
    }, []);

    return (
        <>
            <div className="mt-14 grid grid-cols-1 gap-gutter md:grid-cols-3">
                {services.map((service) => (
                    <ServiceCard {...service} ctaLabel={discoverCta} key={service.title} onDiscover={() => openService(service)} />
                ))}
            </div>
            {selectedService ? <ServiceModal backLabel={backLabel} closeLabel={closeLabel} service={selectedService} onClose={closeService} /> : null}
        </>
    );
}
