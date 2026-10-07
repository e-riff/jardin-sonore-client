import {ComponentType, ReactNode, SVGProps} from "react";
import type {Dictionary} from "@/i18n/types";

export type IconComponent = ComponentType<SVGProps<SVGSVGElement>>;

export interface StatItem {
    icon: IconComponent;
    value: string;
    label: string;
    tone: "primary" | "secondary" | "tertiary";
}

export interface ServiceItem {
    slug: string;
    title: string;
    description: string;
    tone: "primary" | "secondary" | "tertiary";
    imageSrc: string;
    imageAlt: string;
    badge: string;
    modal: ServiceModalContent;
}

export interface ServiceModalPoint {
    icon: "age" | "calendar" | "clock" | "document" | "duration" | "group" | "music" | "participants" | "path" | "sessions" | "sparkles" | "training";
    label: string;
    text: string;
}

export interface ServiceModalResource {
    label: string;
    description: string;
    href: string;
}

export interface ServiceModalContent {
    eyebrow: string;
    subtitle: string;
    body: readonly string[];
    practicalTitle: string;
    points: readonly ServiceModalPoint[];
    resourcesTitle?: string;
    resources?: readonly ServiceModalResource[];
    detailPageLink?: {
        href: string;
        label: string;
    };
    ctaLabel: string;
}

export interface ExplorationPhoto {
    title?: string;
    description?: string;
    imageSrc: string;
    imageAlt: string;
}

export interface ExplorationPhotoGroup {
    title: string;
    subtitle: string;
    images: readonly ExplorationPhoto[];
}

export interface TestimonialItem {
    quote: string;
    date: string;
    firstName: string;
    lastNameInitial: string;
    role: string;
    organization?: string;
    organizationHref?: string;
    structure?: string;
    structureHref?: string;
    personHref?: string;
    photoFileName?: string;
    photoAlt?: string;
    tone: "primary" | "secondary";
}

export interface ChecklistItem {
    text: string;
    icon?: ReactNode;
}

export type Tone = "primary" | "secondary" | "tertiary";

export interface LinkItem {
    label: string;
    href: string;
}

type CtaCopyKey = "eyebrow" | "title" | "description" | "quoteCta" | "callCta";

export type CtaContent = Omit<Dictionary["cta"], CtaCopyKey> & Record<CtaCopyKey, string>;
