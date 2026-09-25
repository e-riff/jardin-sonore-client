import type {PortalTheme} from "@/lib/portal/types";

export default function PortalThemeBadges({themes}: {themes: PortalTheme[]}): React.JSX.Element | null {
    if (themes.length === 0) return null;
    return <div className="flex flex-wrap gap-1.5">{themes.map((theme) =>
        <span className="portal-theme-badge" key={theme.uuid} style={{"--badge-color": theme.color} as React.CSSProperties}>{theme.label}</span>,
    )}</div>;
}
