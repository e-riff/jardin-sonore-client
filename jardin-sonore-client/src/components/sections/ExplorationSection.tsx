import {JSX} from "react";
import Link from "next/link";
import ExplorationGallery from "@/components/sections/ExplorationGallery";
import TestimonialsSection from "@/components/sections/TestimonialsSection";
import {getTranslations} from "@/i18n/server";
import SectionHeading from "@/components/SectionHeading";

export default async function ExplorationSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.exploration;

    return (
        <section className="bg-surface-container-low px-6 pb-lg pt-xl sm:px-margin lg:pb-16 lg:pt-28" id="en-seance">
            <div className="mx-auto max-w-7xl">
                <SectionHeading eyebrow={content.eyebrow} title={content.title} description={content.description} centered />

                <div className="mt-12">
                    <div className="mb-5 border-l-4 border-secondary/50 pl-5">
                        <p className="text-sm leading-6 text-on-surface-variant">
                            <span className="mr-2 font-sans font-bold uppercase tracking-[0.18em] text-secondary">{content.photosTitle}</span>
                            {content.photosDescription}
                        </p>
                    </div>
                    <ExplorationGallery content={content} />
                </div>

                <div className="mt-14 scroll-mt-28" id="temoignages">
                    <div className="mb-5 border-l-4 border-primary/50 pl-5">
                        <p className="text-sm leading-6 text-on-surface-variant">
                            <span className="mr-2 font-sans font-bold uppercase tracking-[0.18em] text-primary">{content.testimonialsTitle}</span>
                            {content.testimonialsDescription}
                        </p>
                    </div>
                    <TestimonialsSection />
                </div>
                <div className="mt-10 text-center">
                    <Link className="font-sans text-sm font-bold text-primary underline underline-offset-4 hover:text-primary-container" href="/eveil-musical-creche">{content.offerLink}</Link>
                </div>
            </div>
        </section>
    );
}
