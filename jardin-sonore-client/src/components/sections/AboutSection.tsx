import {CheckCircleIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import {JSX} from "react";
import Badge from "@/components/Badge";
import Section from "@/components/Section";
import SectionHeading from "@/components/SectionHeading";
import {getTranslations} from "@/i18n/server";

export default async function AboutSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.about;

    return (
        <Section id="a-propos">
            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <SectionHeading title={content.title} eyebrow={content.eyebrow} />
                    {content.paragraphs.map((paragraph: string, index: number) => (
                        <p className={`${index === 0 ? "mt-7" : "mt-5"} text-lg leading-8 text-on-surface-variant`} key={paragraph}>
                            {paragraph}
                        </p>
                    ))}

                    <ul className="mt-8 grid gap-3 text-on-surface">
                        {content.benefits.map((benefit: string) => (
                            <li className="flex items-center gap-3 font-sans text-sm font-semibold" key={benefit}>
                                <CheckCircleIcon className="h-5 w-5 text-secondary" aria-hidden="true" />
                                {benefit}
                            </li>
                        ))}
                    </ul>

                    <div className="mt-9 flex flex-wrap gap-4">
                        {content.badges.map((badge) => <Badge {...badge} key={badge.label} />)}
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-2xl ambient-shadow">
                    <Image
                        alt={content.imageAlt}
                        className="aspect-4/3 w-full object-cover"
                        height={900}
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        src="/Tambour collectif.jpg"
                        width={1200}
                    />
                </div>
            </div>
        </Section>
    );
}
