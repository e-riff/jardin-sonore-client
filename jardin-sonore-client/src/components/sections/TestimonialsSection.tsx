import {JSX} from "react";
import Card from "@/components/Card";
import Section from "@/components/Section";
import SectionHeading from "@/components/SectionHeading";
import {getTranslations} from "@/i18n/server";
import {TestimonialItem} from "@/types/content";

const avatarClasses: Record<TestimonialItem["tone"], string> = {
    primary: "bg-primary-container text-on-primary-container",
    secondary: "bg-secondary-container text-secondary",
};

export default async function TestimonialsSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.testimonials;

    return (
        <Section id="testimonials" className="overflow-hidden">
            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-4">
                    <SectionHeading title={content.title} description={content.description} />
                </div>
                <div className="lg:col-span-8">
                    <div className="flex snap-x gap-gutter overflow-x-auto pb-8 scrollbar-hide">
                        {content.items.map((testimonial: TestimonialItem) => (
                            <Card className="relative min-w-80 snap-start bg-surface-container sm:min-w-112.5" key={testimonial.author}>
                                <span className="absolute right-8 top-5 font-serif text-7xl text-primary/10" aria-hidden="true">{content.quoteMark}</span>
                                <blockquote className="relative text-lg italic leading-8 text-on-surface">{content.quoteMark}{testimonial.quote}{content.quoteMark}</blockquote>
                                <div className="mt-10 flex items-center gap-5">
                                    <div className={`flex h-14 w-14 items-center justify-center rounded-full font-sans text-sm font-bold ${avatarClasses[testimonial.tone]}`}>
                                        {testimonial.initials}
                                    </div>
                                    <div>
                                        <p className="font-sans text-sm font-bold text-on-surface">{testimonial.author}</p>
                                        <p className="mt-1 font-sans text-[11px] font-bold uppercase tracking-[0.18em] text-on-surface-variant">{testimonial.role}</p>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>
        </Section>
    );
}
