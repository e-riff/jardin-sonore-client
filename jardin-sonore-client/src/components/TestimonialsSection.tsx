import {JSX} from "react";
import Card from "@/components/Card";
import Section from "@/components/Section";
import SectionHeading from "@/components/SectionHeading";
import {TestimonialItem} from "@/types/content";

const testimonials: TestimonialItem[] = [
    {
        quote: "Une approche d'une rare finesse qui a transformé le climat sonore de notre crèche. Les enfants attendent ce moment avec impatience chaque semaine.",
        author: "Marie-Claire R.",
        role: "Directrice Crèche Arc-en-Ciel",
        initials: "MC",
        tone: "primary",
    },
    {
        quote: "L'intervenant fait preuve d'une pédagogie exemplaire. Les ateliers sont riches, variés et parfaitement adaptés au rythme des plus petits.",
        author: "Jean-Pierre D.",
        role: "Coordinateur Petite Enfance",
        initials: "JP",
        tone: "secondary",
    },
];

const avatarClasses: Record<TestimonialItem["tone"], string> = {
    primary: "bg-primary-container text-on-primary-container",
    secondary: "bg-secondary-container text-secondary",
};

export default function TestimonialsSection(): JSX.Element {
    return (
        <Section id="testimonials" className="overflow-hidden">
            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-4">
                    <SectionHeading title="Ils nous font confiance" description="Ce que disent les directrices et directeurs d'établissements partenaires de nos interventions sonores." />
                </div>
                <div className="lg:col-span-8">
                    <div className="flex snap-x gap-gutter overflow-x-auto pb-8 scrollbar-hide">
                        {testimonials.map((testimonial: TestimonialItem) => (
                            <Card className="relative min-w-[320px] snap-start bg-surface-container sm:min-w-[450px]" key={testimonial.author}>
                                <span className="absolute right-8 top-5 font-serif text-7xl text-primary/10" aria-hidden="true">“</span>
                                <blockquote className="relative text-lg italic leading-8 text-on-surface">“{testimonial.quote}”</blockquote>
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
