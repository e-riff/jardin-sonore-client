import {ComponentType, ReactNode, SVGProps} from "react";

export type IconComponent = ComponentType<SVGProps<SVGSVGElement>>;

export interface StatItem {
    icon: IconComponent;
    value: string;
    label: string;
    tone: "primary" | "secondary" | "tertiary";
}

export interface ServiceItem {
    title: string;
    description: string;
    tone: "primary" | "secondary" | "tertiary";
    imageSrc: string;
    imageAlt: string;
    badge: string;
}

export interface ExplorationPhoto {
    title: string;
    description?: string;
    imageSrc: string;
    imageAlt: string;
}

export interface TestimonialItem {
    quote: string;
    author: string;
    role: string;
    initials: string;
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
