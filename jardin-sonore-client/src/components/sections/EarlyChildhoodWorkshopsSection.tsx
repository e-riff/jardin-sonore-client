import Image from "next/image";
import {AcademicCapIcon, AdjustmentsHorizontalIcon, ArrowPathIcon} from "@heroicons/react/24/outline";
import type {JSX} from "react";
import SectionHeading from "@/components/SectionHeading";
import EditorialCopy from "@/components/EditorialCopy";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodWorkshopsSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.workshops;
    const pointIcons = [AdjustmentsHorizontalIcon, AcademicCapIcon, ArrowPathIcon];
    const pointTones = ["text-primary bg-primary-fixed/55", "text-secondary bg-secondary-container/55", "text-tertiary bg-tertiary-container/30"];

    return <section aria-labelledby="ateliers-title" className="scroll-mt-24 px-6 py-16 sm:px-margin lg:py-20" id="ateliers">
        <div className="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)] lg:gap-16">
            <div>
                <SectionHeading id="ateliers-title" eyebrow={content.eyebrow} title={content.title} description={<EditorialCopy text={content.introduction} />} />
                <ol className="mt-8 divide-y divide-outline-variant/70 border-y border-outline-variant/70">
                    {content.points.map((point, index) => {
                        const Icon = pointIcons[index];

                        return <li className="grid grid-cols-[2.75rem_minmax(0,1fr)] items-center gap-4 py-5 sm:grid-cols-[3rem_minmax(0,1fr)]" key={point.title}>
                        <span aria-hidden="true" className={`flex h-11 w-11 items-center justify-center rounded-full sm:h-12 sm:w-12 ${pointTones[index]}`}><Icon className="h-5 w-5 sm:h-6 sm:w-6" /></span>
                        <div>
                            <h3 className="font-sans text-lg font-bold text-secondary">{point.title}</h3>
                            <p className="mt-2 leading-7 text-on-surface-variant">{point.description}</p>
                        </div>
                    </li>;
                    })}
                </ol>
            </div>
            <div className="relative aspect-[3/4] overflow-hidden rounded-2xl bg-surface-container-low">
                <Image alt={content.imageAlt} className="object-cover object-center" fill sizes="(min-width: 1024px) 44vw, 100vw" src="/images/galerie/animateur-tabla-enfants.webp" />
            </div>
        </div>
    </section>;
}
