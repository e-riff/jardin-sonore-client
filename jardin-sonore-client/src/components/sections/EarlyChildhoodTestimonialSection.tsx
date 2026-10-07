import ArrowRightIcon from "@heroicons/react/24/outline/ArrowRightIcon";
import Link from "next/link";
import type {JSX} from "react";
import {getTranslations} from "@/i18n/server";

export default async function EarlyChildhoodTestimonialSection(): Promise<JSX.Element> {
    const content = (await getTranslations()).earlyChildhoodPage.testimonialsPrompt;

    return <aside className="bg-background px-6 py-14 sm:px-margin lg:py-16">
        <div className="relative mx-auto w-full max-w-[36rem] pt-8 text-center">
            <span aria-hidden="true" className="pointer-events-none absolute left-1/2 top-0 -translate-x-1/2 font-serif text-[7rem] leading-none text-primary/10">“</span>
            <figure className="relative">
                <blockquote className="text-balance font-serif text-xl font-medium leading-relaxed text-on-surface sm:text-[22px]">« {content.quote} »</blockquote>
                <figcaption className="mt-5 font-sans text-xs font-semibold tracking-wide text-secondary sm:text-sm">{content.attribution}</figcaption>
            </figure>
            <Link className="relative mt-5 inline-flex items-center gap-2 font-sans text-sm font-semibold text-primary underline underline-offset-4 transition-colors hover:text-primary-container motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="/#temoignages">
                {content.link}
                <ArrowRightIcon aria-hidden="true" className="size-4" />
            </Link>
        </div>
    </aside>;
}
