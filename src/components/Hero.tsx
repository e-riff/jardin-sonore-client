import Image from "next/image";
import {JSX} from "react";
import BrandLogo from "@/components/BrandLogo";
import Button from "@/components/Button";

interface HeroProps {
    backgroundImage: string;
}

export default function Hero({backgroundImage}: HeroProps): JSX.Element {
    return (
        <section className="relative flex min-h-[86vh] items-center justify-center overflow-hidden px-6 py-28" id="top">
            <Image
                alt="Enfant explorant des instruments d'éveil musical"
                className="object-cover"
                fill
                priority
                sizes="100vw"
                src={`/${backgroundImage}`}
            />
            <div className="absolute inset-0 hero-gradient" />

            <div className="relative z-10 mx-auto max-w-[690px] rounded-xl border border-white/60 bg-surface/82 px-8 py-12 text-center backdrop-blur-xl ambient-shadow sm:px-14 sm:py-16">
                <BrandLogo className="text-4xl font-semibold leading-tight sm:text-5xl" />
                <p className="mx-auto mt-5 max-w-xl text-lg leading-8 text-on-surface-variant sm:text-2xl">
                    Éveil musical bienveillant pour la petite enfance
                </p>
                <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Button href="#about">En savoir plus</Button>
                    <Button href="#contact" variant="secondary">Nous contacter</Button>
                </div>
            </div>
        </section>
    );
}
