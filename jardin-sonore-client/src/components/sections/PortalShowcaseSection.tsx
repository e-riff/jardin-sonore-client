import Image from "next/image";
import {JSX} from "react";
import PortalShowcaseLink from "@/components/sections/PortalShowcaseLink";
import {getTranslations} from "@/i18n/server";

export default async function PortalShowcaseSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.portalShowcase;

    return (
        <section aria-labelledby="espace-structures-title" className="scroll-mt-16 bg-background px-6 py-lg sm:px-margin lg:py-16" id="espace-structures">
            <div className="mx-auto grid max-w-7xl overflow-hidden rounded-2xl border border-outline-variant/70 bg-surface shadow-sm md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <div className="relative min-h-56 md:min-h-full">
                    <Image
                        alt={content.imageAlt}
                        className="object-cover"
                        fill
                        sizes="(min-width: 768px) 40vw, 100vw"
                        src="/images/portail-structures-mock.webp"
                    />
                </div>
                <div className="p-7 sm:p-9 lg:p-11">
                    <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.eyebrow}</p>
                    <h2 className="mt-3 font-serif text-2xl font-semibold text-on-surface sm:text-3xl" id="espace-structures-title">{content.title}</h2>
                    <p className="mt-4 leading-7 text-on-surface-variant">{content.description}</p>
                    <ul className="mt-5 grid gap-2 text-sm leading-6 text-on-surface-variant">
                        {content.resources.map((resource: string) => <li className="flex gap-3" key={resource}><span aria-hidden="true" className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary" />{resource}</li>)}
                    </ul>
                    <PortalShowcaseLink accountLabel={content.accountCta} loginLabel={content.cta} />
                </div>
            </div>
        </section>
    );
}
