import {ComponentType, ReactNode, SVGProps} from "react";

export type IconComponent = ComponentType<SVGProps<SVGSVGElement>>;

export interface StatItem {
    icon: IconComponent;
    value: string;
    label: string;
    tone: "primary" | "secondary" | "tertiary";
}

export interface ServiceItem {
    icon: IconComponent;
    title: string;
    description: string;
    tone: "primary" | "secondary" | "tertiary";
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
