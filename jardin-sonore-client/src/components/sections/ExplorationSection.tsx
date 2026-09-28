import {ArrowRightIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import Link from "next/link";
import {JSX} from "react";
import ExplorationGallery from "@/components/sections/ExplorationGallery";
import TestimonialsSection from "@/components/sections/TestimonialsSection";
import {getTranslations} from "@/i18n/server";
import SectionHeading from "@/components/SectionHeading";

export default async function ExplorationSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.exploration;

    return (
        <section className="bg-surface-container-low px-6 py-xl sm:px-margin lg:py-28" id="en-seance">
            <div className="mx-auto max-w-7xl">
                <SectionHeading eyebrow={content.eyebrow} title={content.title} description={content.description} centered />

                <div className="mt-12 grid overflow-hidden rounded-2xl border border-outline-variant/70 bg-surface shadow-sm md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div className="relative min-h-56 md:min-h-full">
                        <Image
                            alt={content.portalImageAlt}
                            className="object-cover"
                            fill
                            sizes="(min-width: 768px) 40vw, 100vw"
                            src="/images/portail-structures-mock.webp"
                        />
                    </div>
                    <div className="p-7 sm:p-9 lg:p-11">
                        <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.portalEyebrow}</p>
                        <h2 className="mt-3 font-serif text-2xl font-semibold text-on-surface sm:text-3xl">{content.portalTitle}</h2>
                        <p className="mt-4 leading-7 text-on-surface-variant">{content.portalDescription}</p>
                        <ul className="mt-5 grid gap-2 text-sm leading-6 text-on-surface-variant">
                            {content.portalResources.map((resource: string) => <li className="flex gap-3" key={resource}><span aria-hidden="true" className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary" />{resource}</li>)}
                        </ul>
                        <Link className="mt-7 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="/portail/connexion">
                            {content.portalCta}
                            <ArrowRightIcon aria-hidden="true" className="h-4 w-4" />
                        </Link>
                    </div>
                </div>

                <div className="mt-14">
                    <div className="mb-5 border-l-4 border-secondary/50 pl-5">
                        <p className="text-sm leading-6 text-on-surface-variant">
                            <span className="mr-2 font-sans font-bold uppercase tracking-[0.18em] text-secondary">{content.photosTitle}</span>
                            {content.photosDescription}
                        </p>
                    </div>
                    <ExplorationGallery content={content} />
                </div>

                <div className="mt-14">
                    <div className="mb-5 border-l-4 border-primary/50 pl-5">
                        <p className="text-sm leading-6 text-on-surface-variant">
                            <span className="mr-2 font-sans font-bold uppercase tracking-[0.18em] text-primary">{content.testimonialsTitle}</span>
                            {content.testimonialsDescription}
                        </p>
                    </div>
                    <TestimonialsSection />
                </div>
            </div>
        </section>
    );
}
