import {ArrowRightIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import {JSX, KeyboardEvent as ReactKeyboardEvent} from "react";
import {ServiceItem} from "@/types/content";

const toneClasses: Record<ServiceItem["tone"], {badge: string; link: string; title: string}> = {
    primary: {badge: "bg-primary-container text-on-primary", link: "text-primary", title: "group-hover:text-primary"},
    secondary: {badge: "bg-secondary text-on-secondary", link: "text-secondary", title: "group-hover:text-secondary"},
    tertiary: {badge: "bg-tertiary-container text-on-tertiary", link: "text-tertiary", title: "group-hover:text-tertiary"},
};

interface ServiceCardProps extends ServiceItem {
    ctaLabel: string;
    onDiscover?: () => void;
}

export default function ServiceCard({title, description, tone, ctaLabel, imageSrc, imageAlt, badge, onDiscover}: ServiceCardProps): JSX.Element {
    const handleKeyDown = (event: ReactKeyboardEvent<HTMLElement>): void => {
        if (!onDiscover || (event.key !== "Enter" && event.key !== " ")) {
            return;
        }

        event.preventDefault();
        onDiscover();
    };

    return (
        <article
            aria-label={`${ctaLabel} ${title}`}
            aria-haspopup="dialog"
            className="group flex h-full cursor-pointer flex-col overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest soft-shadow transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_-28px_rgb(135_54_45/0.42)] focus:outline-none focus-visible:ring-3 focus-visible:ring-primary/45"
            role="button"
            tabIndex={0}
            onClick={onDiscover}
            onKeyDown={handleKeyDown}
        >
            <div className="relative aspect-4/3 overflow-hidden">
                <Image
                    alt={imageAlt}
                    className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                    fill
                    sizes="(min-width: 768px) 33vw, 100vw"
                    src={imageSrc}
                />
                <span className={`absolute left-4 top-4 rounded-full px-3 py-1 font-sans text-xs font-bold uppercase tracking-[0.12em] ${toneClasses[tone].badge}`}>
                    {badge}
                </span>
            </div>
            <div className="flex flex-1 flex-col p-6 sm:p-8">
                <h3 className={`font-serif text-2xl font-semibold text-on-surface transition-colors ${toneClasses[tone].title}`}>{title}</h3>
                <p className="mt-4 flex-1 text-base leading-7 text-on-surface-variant">{description}</p>
                <span className={`mt-8 inline-flex items-center gap-2 self-start font-sans text-sm font-bold tracking-wider transition-all group-hover:gap-4 ${toneClasses[tone].link}`}>
                    {ctaLabel} <ArrowRightIcon className="h-4 w-4" aria-hidden="true" />
                </span>
            </div>
        </article>
    );
}
