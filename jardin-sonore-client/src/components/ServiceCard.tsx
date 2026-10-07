import {ArrowRightIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import {JSX} from "react";
import {ServiceItem} from "@/types/content";

const toneClasses: Record<ServiceItem["tone"], {badge: string; link: string; title: string}> = {
    primary: {badge: "bg-primary-container text-on-primary", link: "text-primary", title: "group-hover:text-primary"},
    secondary: {badge: "bg-secondary text-on-secondary", link: "text-secondary", title: "group-hover:text-secondary"},
    tertiary: {badge: "bg-tertiary-container text-on-tertiary", link: "text-tertiary", title: "group-hover:text-tertiary"},
};

interface ServiceCardProps extends ServiceItem {
    ctaLabel: string;
    onDiscover: () => void;
}

export default function ServiceCard({slug, title, description, tone, ctaLabel, imageSrc, imageAlt, badge, onDiscover}: ServiceCardProps): JSX.Element {
    return (
        <article className="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest soft-shadow transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_-28px_rgb(135_54_45/0.42)] focus-within:ring-3 focus-within:ring-primary/45 motion-reduce:transition-none">
            <button aria-haspopup="dialog" aria-label={`${ctaLabel} ${title}`} className="absolute inset-0 z-10 cursor-pointer rounded-2xl focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary" id={`service-${slug}`} onClick={onDiscover} type="button" />
            <div className="relative aspect-4/3 overflow-hidden">
                <Image
                    alt={imageAlt}
                    className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105 motion-reduce:transition-none"
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
                <span className={`mt-8 inline-flex items-center gap-2 self-start font-sans text-sm font-bold tracking-wider ${toneClasses[tone].link}`}>
                    {ctaLabel} <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                </span>
            </div>
        </article>
    );
}
