import {JSX} from "react";
import {StatProps} from "@/types/stats"
import StatsCard from "./StatsCard";
import { ClockIcon, BuildingStorefrontIcon, AcademicCapIcon } from "@heroicons/react/24/outline";


const stats: StatProps[] = [
    { icon: <ClockIcon />,    value: '10 ans',    label: "d'expérience" },
    { icon: <AcademicCapIcon />, value: 'Bac+5', label: 'Musique & petite enfance' },
    { icon: <BuildingStorefrontIcon />,  value: '30',  label: "établissements en confiance" },
]

export default function StatsSection(): JSX.Element {
    return (
        <section className="container grid grid-cols-1 sm:grid-cols-3 gap-8
                       pt-16 bg-bgLight -mt-40">
            {stats.map((stat, i) => (
                <StatsCard key={i} icon={stat.icon} value={stat.value} label={stat.label} />
            ))}
        </section>
    )
}