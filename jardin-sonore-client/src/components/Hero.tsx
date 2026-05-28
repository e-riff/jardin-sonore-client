import Image from "next/image";
import {JSX} from "react";
import BrandLogo from "@/components/BrandLogo";
import Button from "@/components/Button";
import {getTranslations} from "@/i18n/server";

interface HeroProps {
    backgroundImage: string;
}

export default async function Hero({backgroundImage}: HeroProps): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.hero;

    return (
        <section className="relative overflow-hidden" id="top">
            <div className="relative flex min-h-screen items-center">
                <Image
                    alt={content.imageAlt}
                    className="object-cover object-center"
                    fill
                    priority
                    sizes="100vw"
                    src={`/${backgroundImage}`}
                />
                <div className="absolute inset-0 hero-gradient" />

                <div className="relative z-10 mx-auto flex w-full max-w-330 items-center justify-center px-5 pb-24 pt-28 sm:px-8 sm:pt-32 lg:px-12 lg:pb-28 lg:pt-36">
                    <div className="w-full max-w-195 rounded-xl border border-white/60 bg-surface/82 px-7 py-10 text-center backdrop-blur ambient-shadow sm:px-10 sm:py-12 md:px-14 md:py-16">
                        <BrandLogo label={dictionary.brand.name} className="text-[2.5rem] font-semibold leading-none sm:text-6xl" />
                        <h1 className="mx-auto mt-5 max-w-[24ch] text-balance text-xl font-normal leading-[1.35] text-on-surface-variant sm:mt-6 sm:text-[2rem]">
                            {content.tagline}
                        </h1>
                        <p className="mx-auto mt-4 max-w-full text-sm font-semibold uppercase tracking-[0.14em] text-on-surface-variant/75 sm:whitespace-nowrap sm:text-base">
                            {content.serviceArea}
                        </p>
                        <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:mt-10 sm:flex-row">
                            <Button className="min-w-47.5" href="#a-propos">{content.primaryCta}</Button>
                            <Button className="min-w-47.5" href="#contact" variant="secondary">{content.secondaryCta}</Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
