import Image from "next/image";
import LightBulbIcon from "@heroicons/react/24/outline/LightBulbIcon";
import type {JSX} from "react";
import SectionHeading from "@/components/SectionHeading";
import EditorialCopy from "@/components/EditorialCopy";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodApproachSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.approach;

    return <section aria-labelledby="approche-creche-title" className="scroll-mt-24 bg-surface-container-low px-6 py-16 sm:px-margin lg:py-20" id="approche-creche">
        <div className="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)] lg:gap-16">
            <div>
                <SectionHeading id="approche-creche-title" eyebrow={content.eyebrow} title={content.title} description={<EditorialCopy text={content.description} />} />
                <aside aria-labelledby="cocreation-title" className="mt-7 rounded-xl border border-outline-variant/60 bg-background/80 p-5 sm:p-6">
                    <div className="flex items-start gap-4">
                        <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-fixed/55 text-primary">
                            <LightBulbIcon className="h-6 w-6" />
                        </span>
                        <div>
                            <p className="font-sans text-xs font-bold uppercase tracking-[0.16em] text-secondary">{content.cocreation.eyebrow}</p>
                            <h3 className="mt-2 font-serif text-xl font-semibold text-on-surface" id="cocreation-title">{content.cocreation.title}</h3>
                            <EditorialCopy className="mt-2 leading-7 text-on-surface-variant" text={content.cocreation.description} />
                        </div>
                    </div>
                </aside>
            </div>
            <figure className="relative aspect-[4/3] overflow-hidden rounded-2xl">
                <Image alt={content.cocreation.imageAlt} className="object-cover" fill sizes="(min-width: 1024px) 40vw, 100vw" src="/images/ateliers/co-construction-lyre.webp" />
            </figure>
        </div>
    </section>;
}
