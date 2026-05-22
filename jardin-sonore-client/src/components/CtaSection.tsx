import {JSX} from "react";
import CtaContactPanel from "@/components/CtaContactPanel";
import {getTranslations} from "@/i18n/server";

export default async function CtaSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.cta;

    return (
        <section className="px-6 pb-xl sm:px-margin" id="contact">
            <div className="relative mx-auto max-w-[1280px] overflow-hidden rounded-xl border border-outline-variant/20 bg-surface-container px-8 py-16 text-center soft-shadow sm:px-16 md:px-24 md:py-24">
                <div className="relative z-10">
                    <h2 className="mx-auto max-w-3xl font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl">{content.title}</h2>
                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-on-surface-variant">{content.description}</p>
                    <CtaContactPanel content={content} />
                </div>
            </div>
        </section>
    );
}
