import ArrowRightIcon from "@heroicons/react/24/outline/ArrowRightIcon";
import Link from "next/link";
import type {JSX} from "react";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodExtrasSection(): Promise<JSX.Element> {
    const {otherFormats} = (await getTranslations()).earlyChildhoodPage;

    return <section aria-labelledby="other-formats-title" className="bg-surface-container-low px-6 py-14 sm:px-margin lg:py-16">
            <div className="mx-auto max-w-7xl">
                <div>
                    <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{otherFormats.eyebrow}</p>
                    <h2 className="mt-3 font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl" id="other-formats-title">{otherFormats.title}</h2>
                    <p className="mt-3 max-w-3xl leading-7 text-on-surface-variant">{otherFormats.description}</p>
                </div>
                <ul className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {otherFormats.formats.map((format) => <li key={format.title}>
                        <Link className="group flex h-full flex-col rounded-xl border border-outline-variant/50 bg-background/80 p-5 transition duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:bg-background hover:shadow-md motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href={format.href}>
                            <span className="flex-1">
                                <span className="block font-serif text-xl font-semibold text-secondary transition-colors group-hover:text-primary">{format.title}</span>
                                <span className="mt-2 block leading-7 text-on-surface-variant">{format.description}</span>
                            </span>
                            <span className="mt-5 inline-flex items-center gap-2 font-sans text-sm font-bold text-primary">
                                {otherFormats.cardLink}
                                <ArrowRightIcon aria-hidden="true" className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                            </span>
                        </Link>
                    </li>)}
                </ul>
                <div className="mt-6 border-t border-outline-variant/70 pt-5">
                    <p className="leading-7 text-on-surface-variant">{otherFormats.serviceArea}</p>
                </div>
            </div>
        </section>
}
