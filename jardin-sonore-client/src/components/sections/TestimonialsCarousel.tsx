'use client';

import Image from "next/image";
import {JSX, useEffect, useRef, useState} from "react";
import Card from "@/components/Card";
import {TestimonialItem} from "@/types/content";

interface TestimonialsCarouselProps {
    items: readonly TestimonialItem[];
    quoteMark: string;
}

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

function getOrganization(testimonial: TestimonialItem): string | undefined {
    return testimonial.organization ?? testimonial.structure;
}

function getPhotoSrc(testimonial: TestimonialItem): string | undefined {
    if (!testimonial.photoFileName) {
        return undefined;
    }

    return `/images/testimonials/${testimonial.photoFileName}`;
}

function ExpandableQuote({quote, quoteMark}: {quote: string; quoteMark: string}): JSX.Element {
    const [isExpanded, setIsExpanded] = useState(false);
    const [isOverflowing, setIsOverflowing] = useState(false);
    const quoteRef = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        const quoteElement = quoteRef.current;

        if (!quoteElement) {
            return;
        }

        const updateOverflowState = (): void => {
            const previousDisplay = quoteElement.style.display;
            const previousOverflow = quoteElement.style.overflow;
            const previousWebkitLineClamp = quoteElement.style.webkitLineClamp;
            const previousWebkitBoxOrient = quoteElement.style.webkitBoxOrient;

            quoteElement.style.display = "-webkit-box";
            quoteElement.style.overflow = "hidden";
            quoteElement.style.webkitLineClamp = "8";
            quoteElement.style.webkitBoxOrient = "vertical";

            const nextIsOverflowing = quoteElement.scrollHeight > quoteElement.clientHeight + 1;

            quoteElement.style.display = previousDisplay;
            quoteElement.style.overflow = previousOverflow;
            quoteElement.style.webkitLineClamp = previousWebkitLineClamp;
            quoteElement.style.webkitBoxOrient = previousWebkitBoxOrient;

            setIsOverflowing(nextIsOverflowing);

            if (!nextIsOverflowing) {
                setIsExpanded(false);
            }
        };

        updateOverflowState();

        const resizeObserver = new ResizeObserver(updateOverflowState);
        resizeObserver.observe(quoteElement);

        return () => {
            resizeObserver.disconnect();
        };
    }, [quote]);

    return (
        <div className="relative">
            <blockquote className="relative max-w-3xl">
                <p
                    className="whitespace-pre-line text-base italic leading-8 text-on-surface sm:text-lg"
                    ref={quoteRef}
                    style={
                        isExpanded
                            ? undefined
                            : {
                                display: "-webkit-box",
                                overflow: "hidden",
                                WebkitBoxOrient: "vertical",
                                WebkitLineClamp: 8,
                            }
                    }
                >
                    {quoteMark}{quote}{quoteMark}
                </p>
            </blockquote>
            {isOverflowing ? (
                <button
                    aria-expanded={isExpanded}
                    className="mt-4 inline-flex cursor-pointer items-center gap-2 font-sans text-sm font-bold text-secondary transition hover:text-primary"
                    type="button"
                    onClick={() => {
                        setIsExpanded((currentValue) => !currentValue);
                    }}
                >
                    <span aria-hidden="true" className="text-base leading-none">{isExpanded ? "−" : "+"}</span>
                    <span>{isExpanded ? "Voir moins" : "… Voir plus"}</span>
                </button>
            ) : null}
        </div>
    );
}

function AuthorIdentity({testimonial}: {testimonial: TestimonialItem}): JSX.Element {
    const organization = getOrganization(testimonial);
    const organizationHref = testimonial.organizationHref ?? testimonial.structureHref;
    const photoSrc = getPhotoSrc(testimonial);
    const roleAndOrganization = organization ? `${testimonial.role} - ${organization}` : testimonial.role;

    return (
        <div className="mt-auto flex items-end gap-5">
            {photoSrc ? (
                <div className="relative h-14 w-14 shrink-0 overflow-hidden rounded-full">
                    <Image
                        alt={testimonial.photoAlt ?? `Portrait de ${testimonial.firstName}`}
                        className="object-cover"
                        fill
                        sizes="56px"
                        src={photoSrc}
                    />
                </div>
            ) : (
                <div className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-full font-sans text-sm font-bold ${avatarClasses[testimonial.tone]}`}>
                    {getAuthorInitials(testimonial)}
                </div>
            )}
            <div className="min-w-0">
                <p className="font-sans text-[11px] italic text-on-surface-variant">{testimonial.date}</p>
                {testimonial.personHref ? (
                    <a className="mt-0.5 block font-sans text-sm font-bold text-on-surface transition hover:text-primary" href={testimonial.personHref}>
                        {getAuthorName(testimonial)}
                    </a>
                ) : (
                    <p className="mt-0.5 font-sans text-sm font-bold text-on-surface">{getAuthorName(testimonial)}</p>
                )}
                <p className="mt-1 font-sans text-[11px] font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                    {organizationHref && organization ? (
                        <>
                            {testimonial.role}
                            {" - "}
                            <a className="transition hover:text-primary" href={organizationHref}>
                                {organization}
                            </a>
                        </>
                    ) : (
                        roleAndOrganization
                    )}
                </p>
            </div>
        </div>
    );
}

function ArrowIcon({direction}: {direction: "left" | "right"}): JSX.Element {
    return (
        <svg aria-hidden="true" className="h-4 w-4" fill="none" viewBox="0 0 24 24">
            <path
                d={direction === "left" ? "M15 18L9 12L15 6" : "M9 18L15 12L9 6"}
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
            />
        </svg>
    );
}

export default function TestimonialsCarousel({items, quoteMark}: TestimonialsCarouselProps): JSX.Element {
    const trackRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);

    useEffect(() => {
        const trackElement = trackRef.current;

        if (!trackElement) {
            return;
        }

        const updateScrollState = (): void => {
            setCanScrollLeft(trackElement.scrollLeft > 8);
            setCanScrollRight(trackElement.scrollLeft + trackElement.clientWidth < trackElement.scrollWidth - 8);
        };

        updateScrollState();

        trackElement.addEventListener("scroll", updateScrollState, {passive: true});
        window.addEventListener("resize", updateScrollState);

        return () => {
            trackElement.removeEventListener("scroll", updateScrollState);
            window.removeEventListener("resize", updateScrollState);
        };
    }, []);

    const scrollByCard = (direction: "left" | "right"): void => {
        const trackElement = trackRef.current;

        if (!trackElement) {
            return;
        }

        trackElement.scrollBy({
            left: (direction === "left" ? -1 : 1) * Math.min(trackElement.clientWidth * 0.88, 420),
            behavior: "smooth",
        });
    };

    return (
        <div className="relative overflow-hidden">
            <div className="mb-5 hidden items-center justify-between md:flex">
                <div className="flex items-center gap-3">
                    <button
                        aria-label="Voir le témoignage précédent"
                        className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-outline-variant bg-surface-container text-on-surface transition hover:border-primary hover:text-primary disabled:cursor-default disabled:opacity-40"
                        disabled={!canScrollLeft}
                        type="button"
                        onClick={() => {
                            scrollByCard("left");
                        }}
                    >
                        <ArrowIcon direction="left" />
                    </button>
                    <button
                        aria-label="Voir le témoignage suivant"
                        className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-outline-variant bg-surface-container text-on-surface transition hover:border-primary hover:text-primary disabled:cursor-default disabled:opacity-40"
                        disabled={!canScrollRight}
                        type="button"
                        onClick={() => {
                            scrollByCard("right");
                        }}
                    >
                        <ArrowIcon direction="right" />
                    </button>
                </div>
            </div>
            <div className="mb-5 flex items-center justify-center gap-3 md:hidden">
                <button
                    aria-label="Voir le témoignage précédent"
                    className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-outline-variant bg-surface-container text-on-surface transition hover:border-primary hover:text-primary disabled:cursor-default disabled:opacity-40"
                    disabled={!canScrollLeft}
                    type="button"
                    onClick={() => {
                        scrollByCard("left");
                    }}
                >
                    <ArrowIcon direction="left" />
                </button>
                <button
                    aria-label="Voir le témoignage suivant"
                    className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-outline-variant bg-surface-container text-on-surface transition hover:border-primary hover:text-primary disabled:cursor-default disabled:opacity-40"
                    disabled={!canScrollRight}
                    type="button"
                    onClick={() => {
                        scrollByCard("right");
                    }}
                >
                    <ArrowIcon direction="right" />
                </button>
            </div>
            <div className={`pointer-events-none absolute inset-y-16 left-0 z-10 hidden w-16 bg-linear-to-r from-background via-background/70 to-transparent transition-opacity md:block ${canScrollLeft ? "opacity-100" : "opacity-0"}`} />
            <div className={`pointer-events-none absolute inset-y-16 right-0 z-10 hidden w-24 bg-linear-to-l from-background via-background/82 to-transparent transition-opacity md:block ${canScrollRight ? "opacity-100" : "opacity-0"}`} />
            <div
                className="scroll-smooth flex touch-pan-x snap-x snap-proximity items-stretch gap-gutter overflow-x-auto pb-8 pr-6 pl-1 scrollbar-hide md:overscroll-y-contain md:pr-28"
                ref={trackRef}
            >
                {items.map((testimonial: TestimonialItem) => (
                    <Card
                        className="relative flex min-h-full min-w-[calc(100vw-4.25rem)] snap-start flex-col bg-surface-container sm:min-w-152 lg:min-w-2xl"
                        key={`${testimonial.firstName}-${testimonial.lastNameInitial}-${testimonial.date}`}
                    >
                        <span className="absolute right-8 top-5 font-serif text-7xl text-primary/10" aria-hidden="true">{quoteMark}</span>
                        <ExpandableQuote quote={testimonial.quote} quoteMark={quoteMark} />
                        <div className="mt-10" />
                        <AuthorIdentity testimonial={testimonial} />
                    </Card>
                ))}
            </div>
        </div>
    );
}
