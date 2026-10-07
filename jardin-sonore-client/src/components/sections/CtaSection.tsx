import {JSX} from "react";
import CtaContactPanel from "@/components/CtaContactPanel";
import {getTranslations} from "@/i18n/server";
import type {CtaContent} from "@/types/content";

interface CtaSectionProps {
    content?: Pick<CtaContent, "eyebrow" | "title" | "description" | "quoteCta" | "callCta">;
}

export default async function CtaSection({content: contentOverride}: CtaSectionProps = {}): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content: CtaContent = {...dictionary.cta, ...contentOverride};

    return (
        <section className="bg-surface-container-low px-6 py-lg sm:px-margin lg:py-16" id="contact">
            <div className="relative mx-auto max-w-7xl overflow-hidden rounded-xl bg-primary px-8 py-16 text-center text-on-primary soft-shadow sm:px-16 md:px-24 md:py-24">
                <div className="relative z-10">
                    <p className="mb-3 font-sans text-xs font-bold uppercase tracking-[0.22em] text-on-primary/75">{content.eyebrow}</p>
                    <h2 className="mx-auto max-w-3xl font-serif text-3xl font-semibold leading-tight sm:text-4xl">{content.title}</h2>
                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-on-primary/90">{content.description}</p>
                    <CtaContactPanel content={content} />
                </div>
            </div>
        </section>
    );
}
