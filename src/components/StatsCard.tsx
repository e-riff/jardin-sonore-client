import {JSX} from "react";
import {StatProps} from "@/types/stats";

export default function StatsCard({ icon, value, label }: StatProps): JSX.Element {
    return (
        <div
            className="
        mx-4
        flex flex-col items-center
        p-4 bg-white/50 backdrop-blur-sm rounded-2xl
        shadow-sm hover:shadow-lg
        transition transform hover:-translate-y-1
        duration-200
      "
        >
            <div className="flex items-center justify-center w-16 h-16 rounded-full bg-accent/40 mb-3">
                <div className="w-8 h-8 text-accent-700">{icon}</div>
            </div>
            <span className="text-xl font-semibold text-primary mb-1">{value}</span>
            <span className="text-sm text-secondary text-center">{label}</span>
        </div>
    )
}