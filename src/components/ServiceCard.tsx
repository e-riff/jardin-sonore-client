import {ArrowRightIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import Card from "@/components/Card";
import {ServiceItem} from "@/types/content";

const toneClasses: Record<ServiceItem["tone"], {icon: string; bubble: string; link: string}> = {
    primary: {icon: "text-primary", bubble: "bg-primary-fixed/65", link: "text-primary"},
    secondary: {icon: "text-secondary", bubble: "bg-secondary-container/70", link: "text-secondary"},
    tertiary: {icon: "text-tertiary", bubble: "bg-on-tertiary-container/35", link: "text-tertiary"},
};

export default function ServiceCard({icon: Icon, title, description, tone}: ServiceItem): JSX.Element {
    return (
        <Card className="group flex h-full flex-col items-center text-center transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_-28px_rgb(135_54_45_/_0.42)]">
            <div className={`mb-8 flex h-16 w-16 items-center justify-center rounded-xl ${toneClasses[tone].bubble}`}>
                <Icon className={`h-8 w-8 ${toneClasses[tone].icon}`} aria-hidden="true" />
            </div>
            <h3 className="font-serif text-2xl font-semibold text-on-surface">{title}</h3>
            <p className="mt-4 flex-1 text-base leading-7 text-on-surface-variant">{description}</p>
            <a className={`mt-8 inline-flex items-center gap-2 font-sans text-sm font-bold tracking-[0.05em] transition-all group-hover:gap-4 ${toneClasses[tone].link}`} href="#contact">
                Découvrir <ArrowRightIcon className="h-4 w-4" aria-hidden="true" />
            </a>
        </Card>
    );
}
