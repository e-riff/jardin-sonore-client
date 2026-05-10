import {CheckCircleIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import {JSX} from "react";
import Badge from "@/components/Badge";
import Section from "@/components/Section";
import SectionHeading from "@/components/SectionHeading";

const benefits: string[] = [
    "Développement de la motricité fine",
    "Stimulation du langage",
    "Socialisation et partage",
];

export default function AboutSection(): JSX.Element {
    return (
        <Section id="about">
            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <SectionHeading title="Un éveil en douceur pour chaque enfant" eyebrow="Approche" />
                    <p className="mt-7 text-lg leading-8 text-on-surface-variant">
                        Notre approche Jardin Sonore repose sur une pédagogie bienveillante où le jeu et la découverte sonore sont au cœur de l&apos;apprentissage. Nous créons un environnement sécurisant permettant aux tout-petits d&apos;explorer leur sensibilité musicale.
                    </p>
                    <p className="mt-5 text-lg leading-8 text-on-surface-variant">
                        Loin de la performance, nous privilégions l&apos;écoute, le rythme corporel et l&apos;interaction sociale à travers des instruments adaptés et des rituels sonores apaisants.
                    </p>

                    <ul className="mt-8 grid gap-3 text-on-surface">
                        {benefits.map((benefit: string) => (
                            <li className="flex items-center gap-3 font-sans text-sm font-semibold" key={benefit}>
                                <CheckCircleIcon className="h-5 w-5 text-secondary" aria-hidden="true" />
                                {benefit}
                            </li>
                        ))}
                    </ul>

                    <div className="mt-9 flex flex-wrap gap-4">
                        <Badge label="Exploration" tone="secondary" />
                        <Badge label="Créativité" tone="tertiary" />
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-2xl ambient-shadow">
                    <Image
                        alt="Atelier musical avec instruments colorés pour enfants"
                        className="aspect-[4/3] w-full object-cover"
                        height={900}
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        src="/Hero.jpg"
                        width={1200}
                    />
                </div>
            </div>
        </Section>
    );
}
