import Image from "next/image";
import Link from "next/link";
import type {JSX} from "react";
import Breadcrumbs from "@/components/Breadcrumbs";
import EditorialCopy from "@/components/EditorialCopy";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodHeroSection(): Promise<JSX.Element> {
    const {hero} = (await getTranslations()).earlyChildhoodPage;

    return <section className="bg-surface-container-low px-6 pb-16 pt-24 sm:px-margin lg:pb-24 lg:pt-28">
        <div className="mx-auto max-w-7xl">
            <Breadcrumbs pathname="/eveil-musical-creche" />
            <div className="mt-6 grid items-start gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-center lg:gap-12">
                <div>
                    <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{hero.eyebrow}</p>
                    <h1 className="mt-5 max-w-3xl text-balance font-serif text-4xl font-semibold leading-tight text-primary sm:text-5xl lg:text-6xl">{hero.title}</h1>
                    <EditorialCopy className="mt-7 max-w-2xl text-lg leading-8 text-on-surface-variant" text={hero.introduction} />
                    <div className="mt-8 flex flex-wrap gap-3 font-sans text-sm font-bold">
                        <Link className="inline-flex min-h-12 items-center justify-center rounded-full bg-primary px-6 py-3 text-on-primary transition-colors duration-200 hover:bg-primary-container motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="#contact">{hero.primaryCta}</Link>
                        <Link className="inline-flex min-h-12 items-center justify-center rounded-full border border-primary px-6 py-3 text-primary transition-colors duration-200 hover:bg-primary-fixed motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="#ateliers">{hero.secondaryCta}</Link>
                    </div>
                </div>
                <div className="relative aspect-[16/10] overflow-hidden rounded-2xl shadow-sm">
                    <Image alt={hero.imageAlt} className="object-cover" fill priority sizes="(min-width: 1024px) 48vw, 100vw" src="/images/ateliers/exploration-tissu-hero.webp" />
                </div>
            </div>
        </div>
    </section>;
}
