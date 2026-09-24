import type {PortalTheme} from "@/lib/portal/types";

export default function PortalThemeBadges({themes}: {themes: PortalTheme[]}): React.JSX.Element | null {
    if (themes.length === 0) return null;
    return <div className="flex flex-wrap gap-2">{themes.map((theme) =>
        <span className="rounded-full border border-l-4 border-outline-variant bg-white px-2.5 py-1 text-xs font-semibold text-on-surface" key={theme.uuid} style={{borderLeftColor: theme.color}}>{theme.label}</span>,
    )}</div>;
}
