import Image from "next/image";
import {JSX} from "react";
import Section from "@/components/Section";
import SectionHeading from "@/components/SectionHeading";
import {getTranslations} from "@/i18n/server";

export default async function FounderSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.founder;

    return (
        <Section id="intervenant-musical" className="bg-background">
            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <SectionHeading eyebrow={content.eyebrow} title={content.title} />
                    <div className="mt-8 grid gap-6 text-lg leading-8 text-on-surface-variant">
                        {content.paragraphs.map((paragraph: string) => (
                            <p key={paragraph}>{paragraph}</p>
                        ))}
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-2xl lg:order-first">
                    <Image
                        alt={content.imageAlt}
                        className="aspect-4/3 w-full object-cover"
                        height={896}
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        src="/images/fondateur-couvercles.webp"
                        width={1200}
                    />
                </div>
            </div>
        </Section>
    );
}
