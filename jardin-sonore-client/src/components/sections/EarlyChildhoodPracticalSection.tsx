import ArrowDownTrayIcon from "@heroicons/react/24/outline/ArrowDownTrayIcon";
import {CalendarDaysIcon, ClockIcon, FaceSmileIcon, UserGroupIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import type {JSX} from "react";
import EditorialCopy from "@/components/EditorialCopy";
import {getTranslations} from "@/i18n/server";

const detailIcons = {
    age: FaceSmileIcon,
    participants: UserGroupIcon,
    duration: ClockIcon,
    sessions: CalendarDaysIcon,
};

export default async function EarlyChildhoodPracticalSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.practice;

    return <section aria-labelledby="reperes-ateliers-title" className="scroll-mt-24 px-6 py-16 sm:px-margin lg:py-20" id="reperes-ateliers">
        <div className="mx-auto grid max-w-7xl items-center gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-14">
            <figure className="relative aspect-[5/3] overflow-hidden rounded-2xl bg-surface-container-low">
                <Image alt={content.imageAlt} className="object-cover" fill sizes="(min-width: 1024px) 42vw, 100vw" src="/images/ateliers/installation-sonore-automne.webp" />
            </figure>
            <div>
                <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.eyebrow}</p>
                <h2 className="mt-3 text-balance font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl" id="reperes-ateliers-title">{content.title}</h2>
                <EditorialCopy className="mt-4 leading-7 text-on-surface-variant" text={content.description} />
                <dl className="mt-9 grid grid-cols-2 gap-x-3 gap-y-7 lg:grid-cols-4">
                    {content.details.map((detail) => {
                        const Icon = detailIcons[detail.icon];

                        return <div className="relative flex min-h-[150px] flex-col items-center justify-center rounded-2xl border border-outline-variant/55 bg-surface-container-lowest px-2.5 pb-3 pt-7 text-center transition-[border-color,box-shadow] duration-200 hover:border-secondary/35 hover:shadow-sm motion-reduce:transition-none" key={detail.label}>
                            <span aria-hidden="true" className={`absolute -top-[15px] left-1/2 z-10 flex size-[46px] -translate-x-1/2 items-center justify-center rounded-full ring-[3px] ring-background ${detail.icon === "age" || detail.icon === "sessions" ? "bg-primary-fixed text-primary" : detail.icon === "participants" ? "bg-secondary-container text-secondary" : "bg-tertiary-container text-on-tertiary-container"}`}>
                                <Icon className="size-5" />
                            </span>
                            <dt className="sr-only">{detail.label}</dt>
                            <dd className="flex flex-col items-center gap-1">
                                <span className="font-serif text-[32px] font-semibold leading-none tracking-tight text-secondary lg:text-[clamp(22px,2.3vw,34px)]">{detail.value}</span>
                                <span className="font-sans text-sm leading-5 text-on-surface-variant">{detail.description}</span>
                            </dd>
                        </div>;
                    })}
                </dl>
                <a className="mt-6 flex items-center gap-4 border border-outline-variant/70 bg-background p-4 transition hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" download href={content.resourceHref}>
                    <ArrowDownTrayIcon aria-hidden="true" className="h-6 w-6 shrink-0 text-primary" />
                    <span>
                        <span className="block font-sans text-sm font-bold text-on-surface">{content.resourceTitle}</span>
                        <span className="mt-1 block text-sm leading-6 text-on-surface-variant">{content.resourceDescription}</span>
                        <span className="mt-2 block font-sans text-sm font-semibold text-primary underline underline-offset-4">{content.resourceLink}</span>
                    </span>
                </a>
            </div>
        </div>
    </section>;
}
