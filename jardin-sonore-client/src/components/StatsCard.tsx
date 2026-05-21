import {JSX} from "react";
import {StatItem} from "@/types/content";

const toneClasses: Record<StatItem["tone"], {icon: string; bubble: string}> = {
    primary: {icon: "text-primary", bubble: "bg-primary-fixed/55"},
    secondary: {icon: "text-secondary", bubble: "bg-secondary-container/55"},
    tertiary: {icon: "text-tertiary", bubble: "bg-on-tertiary-container/25"},
};

export default function StatsCard({icon: Icon, value, label, tone}: StatItem): JSX.Element {
    return (
        <div className="flex flex-col items-center text-center">
            <div className={`mb-4 flex h-14 w-14 items-center justify-center rounded-full ${toneClasses[tone].bubble}`}>
                <Icon className={`h-7 w-7 ${toneClasses[tone].icon}`} aria-hidden="true" />
            </div>
            <strong className="font-serif text-2xl font-semibold text-primary">{value}</strong>
            <span className="mt-2 max-w-[190px] font-sans text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{label}</span>
        </div>
    );
}
