import {AcademicCapIcon, BuildingOffice2Icon, ClockIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import StatsCard from "@/components/StatsCard";
import {getTranslations} from "@/i18n/server";
import {StatItem} from "@/types/content";

const statIcons: StatItem["icon"][] = [
    ClockIcon,
    AcademicCapIcon,
    BuildingOffice2Icon,
];

export default async function StatsSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.stats;

    const stats: StatItem[] = content.items.map((item, index) => ({...item, icon: statIcons[index]}));

    return (
        <section className="relative z-20 -mt-20 px-6 sm:-mt-24 sm:px-margin" aria-label={content.ariaLabel}>
            <div className="mx-auto grid max-w-[1100px] grid-cols-1 gap-8 rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-8 ambient-shadow sm:p-10 md:grid-cols-[1fr_auto_1fr_auto_1fr] md:items-center">
                {stats.map((stat: StatItem, index: number) => (
                    <div className="contents" key={stat.label}>
                        <StatsCard {...stat} />
                        {index < stats.length - 1 ? <div className="hidden h-16 w-px bg-outline-variant/35 md:block" /> : null}
                    </div>
                ))}
            </div>
        </section>
    );
}
