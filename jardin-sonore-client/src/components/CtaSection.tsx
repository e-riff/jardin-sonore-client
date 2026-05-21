import {JSX} from "react";
import Button from "@/components/Button";
import {getTranslations} from "@/i18n/server";

export default async function CtaSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.cta;

    return (
        <section className="px-6 pb-xl sm:px-margin" id="contact">
            <div className="relative mx-auto max-w-[1280px] overflow-hidden rounded-2xl bg-primary px-8 py-16 text-center text-on-primary ambient-shadow sm:px-16 md:py-24">
                <div className="absolute -left-24 -top-28 h-80 w-80 rounded-full bg-primary-fixed-dim/20 blur-3xl" />
                <div className="absolute -bottom-28 -right-24 h-80 w-80 rounded-full bg-secondary-container/15 blur-3xl" />
                <div className="relative z-10">
                    <h2 className="mx-auto max-w-3xl font-serif text-3xl font-semibold leading-tight sm:text-4xl">{content.title}</h2>
                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-on-primary/90">{content.description}</p>
                    <div className="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                        <Button href="mailto:contact@jardin-sonore.fr" variant="light">{content.quoteCta}</Button>
                        <Button href="tel:+33000000000" variant="secondary" className="!border-white/55 !bg-white/10 !text-on-primary hover:!border-white/80 hover:!bg-white/20 hover:!text-on-primary">{content.callCta}</Button>
                    </div>
                </div>
            </div>
        </section>
    );
}
