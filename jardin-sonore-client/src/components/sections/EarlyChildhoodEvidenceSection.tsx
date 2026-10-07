import Image from "next/image";
import type {JSX} from "react";
import EditorialCopy from "@/components/EditorialCopy";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodEvidenceSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.evidence;

    return <section aria-labelledby="supports-title" className="px-6 py-12 sm:px-margin lg:py-14" id="supports-sonores">
        <div className="mx-auto grid max-w-7xl items-center gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)] lg:gap-12">
            <div aria-label={content.photosLabel} className="grid grid-cols-[minmax(0,11fr)_minmax(0,15fr)] gap-3" role="group">
                {content.photos.map((photo, index) => <figure className={`relative overflow-hidden rounded-xl bg-surface-container-low ${index === 0 ? "col-start-1 row-start-1 aspect-square" : index === 1 ? "col-start-1 row-start-2 aspect-square" : "col-start-2 row-span-2 aspect-[2/3]"}`} key={`${photo.src}-${index}`}>
                    <Image alt={photo.alt} className="object-cover object-center" fill sizes="(min-width: 1024px) 30vw, 50vw" src={photo.src} />
                </figure>)}
            </div>
            <div className="lg:order-2">
                <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.eyebrow}</p>
                <h2 className="mt-3 text-balance font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl" id="supports-title">{content.title}</h2>
                <EditorialCopy className="mt-4 leading-7 text-on-surface-variant" text={content.description} />
                {content.supports.length > 0 ? <ul className="mt-5 divide-y divide-outline-variant/60 border-y border-outline-variant/60">
                    {content.supports.map((support) => <li className="flex items-center gap-3 py-2.5 leading-6 text-on-surface-variant" key={support}>
                        <span aria-hidden="true" className="h-2 w-2 shrink-0 rounded-full bg-secondary" />
                        {support}
                    </li>)}
                </ul> : null}
            </div>
        </div>
    </section>;
}
