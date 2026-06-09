import {JSX} from "react";
import Card from "@/components/Card";
import {getTranslations} from "@/i18n/server";
import {TestimonialItem} from "@/types/content";

const avatarClasses: Record<TestimonialItem["tone"], string> = {
    primary: "bg-primary-container text-on-primary-container",
    secondary: "bg-secondary-container text-secondary",
};

function getAuthorName(testimonial: TestimonialItem): string {
    return `${testimonial.firstName} ${testimonial.lastNameInitial}.`;
}

function getAuthorInitials(testimonial: TestimonialItem): string {
    return `${testimonial.firstName.charAt(0)}${testimonial.lastNameInitial}`.toUpperCase();
}

function SignatureLine({testimonial}: {testimonial: TestimonialItem}): JSX.Element {
    const roleAndStructure = [testimonial.role, testimonial.structure].filter(Boolean).join(" · ");

    return (
        <div>
            {testimonial.personHref ? (
                <a className="font-sans text-sm font-bold text-on-surface transition hover:text-primary" href={testimonial.personHref}>
                    {getAuthorName(testimonial)}
                </a>
            ) : (
                <p className="font-sans text-sm font-bold text-on-surface">{getAuthorName(testimonial)}</p>
            )}
            {roleAndStructure ? (
                <p className="mt-1 font-sans text-[11px] font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                    {testimonial.structureHref && testimonial.structure ? (
                        <>
                            {testimonial.role} ·{" "}
                            <a className="transition hover:text-primary" href={testimonial.structureHref}>
                                {testimonial.structure}
                            </a>
                        </>
                    ) : (
                        roleAndStructure
                    )}
                </p>
            ) : null}
            <p className="mt-2 font-sans text-xs font-semibold text-on-surface-variant">{testimonial.date}</p>
        </div>
    );
}

export default async function TestimonialsSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.testimonials;

    return (
        <div className="overflow-hidden">
            <div className="flex snap-x gap-gutter overflow-x-auto pb-8 scrollbar-hide">
                {content.items.map((testimonial: TestimonialItem) => (
                    <Card className="relative min-w-80 snap-start bg-surface-container sm:min-w-150" key={`${testimonial.firstName}-${testimonial.lastNameInitial}-${testimonial.date}`}>
                        <span className="absolute right-8 top-5 font-serif text-7xl text-primary/10" aria-hidden="true">{content.quoteMark}</span>
                        <blockquote className="relative max-w-3xl text-base italic leading-8 text-on-surface sm:text-lg">
                            {content.quoteMark}{testimonial.quote}{content.quoteMark}
                        </blockquote>
                        <div className="mt-10 flex items-center gap-5">
                            <div className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-full font-sans text-sm font-bold ${avatarClasses[testimonial.tone]}`}>
                                {getAuthorInitials(testimonial)}
                            </div>
                            <SignatureLine testimonial={testimonial} />
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}
