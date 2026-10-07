import Image from "next/image";
import type {JSX} from "react";
import EditorialCopy from "@/components/EditorialCopy";
import PortalShowcaseLink from "@/components/sections/PortalShowcaseLink";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodPortalSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.portal;

    return <section aria-labelledby="portail-creche-title" className="bg-surface-container px-6 py-16 sm:px-margin lg:py-20" id="portail-creche">
        <div className="mx-auto grid max-w-7xl overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <figure className="relative min-h-64 md:min-h-full">
                <Image alt={content.imageAlt} className="object-cover" fill sizes="(min-width: 768px) 40vw, 100vw" src="/images/portail-structures-mock.webp" />
            </figure>
            <div className="p-6 sm:p-9 lg:p-12">
                <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.eyebrow}</p>
                <h2 className="mt-3 text-balance font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl" id="portail-creche-title">{content.title}</h2>
                <EditorialCopy className="mt-4 leading-7 text-on-surface-variant" text={content.description} />
                <ul className="mt-6 divide-y divide-outline-variant/60 border-y border-outline-variant/60">
                    {content.resources.map((resource) => <li className="grid grid-cols-[0.5rem_minmax(0,1fr)] items-center gap-4 py-4 leading-6 text-on-surface-variant" key={resource}><span aria-hidden="true" className="h-2 w-2 rounded-full bg-secondary" />{resource}</li>)}
                </ul>
                <PortalShowcaseLink accountLabel={content.accountCta} loginLabel={content.cta} />
            </div>
        </div>
    </section>;
}
