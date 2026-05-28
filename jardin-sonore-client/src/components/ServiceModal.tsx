"use client";

import {
    AcademicCapIcon,
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    ClockIcon,
    DocumentTextIcon,
    MusicalNoteIcon,
    SparklesIcon,
    Squares2X2Icon,
    UserGroupIcon,
    XMarkIcon,
} from "@heroicons/react/24/outline";
import Image from "next/image";
import {JSX, KeyboardEvent as ReactKeyboardEvent, useEffect, useId, useRef} from "react";
import {IconComponent, ServiceItem, ServiceModalPoint} from "@/types/content";

const pointIcons: Record<ServiceModalPoint["icon"], IconComponent> = {
    calendar: CalendarDaysIcon,
    clock: ClockIcon,
    document: DocumentTextIcon,
    group: UserGroupIcon,
    music: MusicalNoteIcon,
    path: Squares2X2Icon,
    sparkles: SparklesIcon,
    training: AcademicCapIcon,
};

const toneClasses: Record<ServiceItem["tone"], {accent: string; iconBg: string; iconText: string; cta: string}> = {
    primary: {
        accent: "text-primary",
        iconBg: "bg-primary-fixed",
        iconText: "text-primary",
        cta: "bg-primary text-on-primary shadow-[0_18px_35px_-22px_rgb(190_79_65/0.7)] hover:bg-primary-container",
    },
    secondary: {
        accent: "text-secondary",
        iconBg: "bg-secondary-container",
        iconText: "text-secondary",
        cta: "bg-secondary text-on-secondary shadow-[0_18px_35px_-22px_rgb(71_102_75/0.7)] hover:bg-on-secondary-container",
    },
    tertiary: {
        accent: "text-tertiary",
        iconBg: "bg-tertiary-container/20",
        iconText: "text-tertiary",
        cta: "bg-tertiary text-on-tertiary shadow-[0_18px_35px_-22px_rgb(109_72_32/0.7)] hover:bg-tertiary-container",
    },
};

interface ServiceModalProps {
    service: ServiceItem;
    closeLabel: string;
    onClose: () => void;
}

export default function ServiceModal({service, closeLabel, onClose}: ServiceModalProps): JSX.Element {
    const modal = service.modal;
    const tone = toneClasses[service.tone];
    const titleId = useId();
    const descriptionId = useId();
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        document.body.style.overflow = "hidden";
        closeButtonRef.current?.focus();

        const onKeyDown = (event: globalThis.KeyboardEvent): void => {
            if (event.key === "Escape") {
                onClose();
            }
        };

        window.addEventListener("keydown", onKeyDown);

        return () => {
            document.body.style.overflow = "";
            window.removeEventListener("keydown", onKeyDown);
            previousActiveElement?.focus();
        };
    }, [onClose]);

    const trapFocus = (event: ReactKeyboardEvent<HTMLDivElement>): void => {
        if (event.key !== "Tab") {
            return;
        }

        const focusableElements = dialogRef.current?.querySelectorAll<HTMLElement>(
            "a[href], button:not([disabled]), [tabindex]:not([tabindex='-1'])",
        );

        if (!focusableElements || focusableElements.length === 0) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    };

    return (
        <div aria-describedby={descriptionId} aria-labelledby={titleId} aria-modal="true" className="fixed inset-0 z-50 bg-on-background/45 backdrop-blur-sm md:p-8" ref={dialogRef} role="dialog" onKeyDown={trapFocus} onMouseDown={(event) => {
            if (event.target === event.currentTarget) {
                onClose();
            }
        }}>
            <div className="flex h-dvh flex-col overflow-y-auto bg-background md:mx-auto md:h-[min(880px,calc(100dvh-4rem))] md:max-w-5xl md:rounded-2xl md:shadow-2xl">
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-outline-variant/20 bg-background/92 px-5 py-4 backdrop-blur-md md:px-8">
                    <button aria-label={closeLabel} className="cursor-pointer rounded-full p-2 text-on-surface-variant transition hover:bg-surface-container-high hover:text-primary" ref={closeButtonRef} onClick={onClose} type="button">
                        <XMarkIcon className="h-7 w-7" aria-hidden="true" />
                    </button>
                    <span className={`font-sans text-xs font-bold uppercase tracking-[0.22em] ${tone.accent}`}>{modal.eyebrow}</span>
                    <div className="h-11 w-11" aria-hidden="true" />
                </div>

                <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col px-5 pb-6 pt-6 md:px-8 md:pb-10">
                    <div className="relative aspect-square overflow-hidden rounded-2xl border border-outline-variant/30 shadow-[0_18px_38px_-30px_rgb(85_66_64/0.9)] sm:aspect-[16/9]">
                        <Image alt={service.imageAlt} className="object-cover" fill sizes="(min-width: 768px) 760px, 100vw" src={service.imageSrc} />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/62 via-black/8 to-transparent" />
                        <div className="absolute bottom-5 left-5 right-5">
                            <p className="mb-2 inline-flex rounded-full bg-background/90 px-3 py-1 font-sans text-xs font-bold uppercase tracking-[0.14em] text-on-surface-variant backdrop-blur-sm">{service.badge}</p>
                            <h2 className="font-serif text-3xl font-bold leading-tight text-white drop-shadow-sm md:text-4xl" id={titleId}>{service.title}</h2>
                        </div>
                    </div>

                    <div className="mt-8 grid gap-8 md:grid-cols-[1.05fr_0.95fr] md:gap-10">
                        <div>
                            <p className="font-serif text-2xl italic leading-10 text-on-surface md:text-3xl" id={descriptionId}>{modal.subtitle}</p>
                            <div className="mt-6 space-y-4 font-sans text-base leading-8 text-on-surface-variant">
                                {modal.body.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
                            </div>
                        </div>

                        <div>
                            <h3 className={`font-serif text-2xl font-semibold ${tone.accent}`}>{modal.practicalTitle}</h3>
                            <div className="mt-5 grid gap-3">
                                {modal.points.map((point) => {
                                    const Icon = pointIcons[point.icon];

                                    return (
                                        <div className="flex gap-4 rounded-xl border border-outline-variant/25 bg-surface-container-lowest p-4 shadow-[0_12px_28px_-24px_rgb(85_66_64/0.8)]" key={`${point.label}-${point.text}`}>
                                            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${tone.iconBg}`}>
                                                <Icon className={`h-5 w-5 ${tone.iconText}`} aria-hidden="true" />
                                            </span>
                                            <span>
                                                <strong className="block font-sans text-sm font-bold uppercase tracking-[0.12em] text-on-surface">{point.label}</strong>
                                                <span className="mt-1 block font-sans text-sm leading-6 text-on-surface-variant">{point.text}</span>
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>

                            {modal.resources && modal.resources.length > 0 ? (
                                <div className="mt-6">
                                    <h3 className="font-sans text-sm font-bold uppercase tracking-[0.16em] text-on-surface">{modal.resourcesTitle}</h3>
                                    <div className="mt-3 grid gap-3">
                                        {modal.resources.map((resource) => (
                                            <a className="group flex cursor-pointer items-center gap-4 rounded-xl border border-dashed border-outline/40 bg-surface-container-low p-4 transition hover:border-primary hover:bg-primary-fixed/35" download href={resource.href} key={resource.href}>
                                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-background text-primary">
                                                    <DocumentTextIcon className="h-5 w-5" aria-hidden="true" />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <strong className="block font-sans text-sm font-bold text-on-surface">{resource.label}</strong>
                                                    <span className="mt-1 block font-sans text-xs leading-5 text-on-surface-variant">{resource.description}</span>
                                                </span>
                                                <ArrowDownTrayIcon className="h-5 w-5 shrink-0 text-primary transition group-hover:translate-y-0.5" aria-hidden="true" />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    </div>
                </div>

                <div className="sticky bottom-0 border-t border-outline-variant/20 bg-background/95 p-5 backdrop-blur-md md:px-8">
                    <div className="mx-auto flex max-w-4xl flex-col gap-3 sm:flex-row">
                        <a className={`inline-flex min-h-14 flex-1 cursor-pointer items-center justify-center rounded-lg px-6 text-center font-sans text-sm font-bold uppercase tracking-[0.14em] transition ${tone.cta}`} href="#contact" onClick={onClose}>
                            {modal.ctaLabel}
                        </a>
                        <button className="inline-flex min-h-14 flex-1 cursor-pointer items-center justify-center rounded-lg border border-outline px-6 font-sans text-sm font-bold uppercase tracking-[0.14em] text-on-surface-variant transition hover:border-primary hover:text-primary sm:flex-none" onClick={onClose} type="button">
                            Retour
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
