import {JSX} from "react";

interface BadgeProps {
    label: string;
    tone: "primary" | "secondary" | "tertiary";
}

const toneClasses: Record<BadgeProps["tone"], string> = {
    primary: "bg-primary-fixed/60 text-primary",
    secondary: "bg-secondary-container/60 text-secondary",
    tertiary: "bg-on-tertiary-container/30 text-tertiary",
};

export default function Badge({label, tone}: BadgeProps): JSX.Element {
    return (
        <span className={`inline-flex items-center gap-3 rounded-full px-5 py-3 font-sans text-sm font-semibold ${toneClasses[tone]}`}>
            <span className="h-2.5 w-2.5 rounded-full bg-current" />
            {label}
        </span>
    );
}
