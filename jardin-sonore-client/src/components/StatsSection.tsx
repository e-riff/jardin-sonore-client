import {AcademicCapIcon, BuildingOffice2Icon, ClockIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import StatsCard from "@/components/StatsCard";
import {StatItem} from "@/types/content";

const stats: StatItem[] = [
    {icon: ClockIcon, value: "10 ans", label: "d'expertise reconnue", tone: "primary"},
    {icon: AcademicCapIcon, value: "Bac +5", label: "musique & pédagogie", tone: "tertiary"},
    {icon: BuildingOffice2Icon, value: "30+", label: "structures partenaires", tone: "secondary"},
];

export default function StatsSection(): JSX.Element {
    return (
        <section className="relative z-20 -mt-20 px-6 sm:-mt-24 sm:px-margin" aria-label="Chiffres clés">
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
