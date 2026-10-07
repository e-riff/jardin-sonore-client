import type {Dictionary} from "@/i18n/types";

type BreadcrumbKey = "home" | "earlyChildhood";

interface PublicRoute {
    parent: string | null;
    label: BreadcrumbKey;
}

const publicRoutes: Record<string, PublicRoute> = {
    "/": {parent: null, label: "home"},
    "/eveil-musical-creche": {parent: "/", label: "earlyChildhood"},
};

export interface BreadcrumbItem {
    href: string;
    label: string;
}

export function getPublicBreadcrumbs(pathname: string, labels: Dictionary["breadcrumbs"]): BreadcrumbItem[] {
    const items: BreadcrumbItem[] = [];
    const visited = new Set<string>();
    let currentPath: string | null = pathname;

    while (currentPath !== null) {
        if (visited.has(currentPath)) {
            throw new Error(`Circular breadcrumb route: ${currentPath}`);
        }

        visited.add(currentPath);
        const route: PublicRoute | undefined = publicRoutes[currentPath];

        if (!route) {
            throw new Error(`Unknown public breadcrumb route: ${currentPath}`);
        }

        items.unshift({href: currentPath, label: labels[route.label]});
        currentPath = route.parent;
    }

    return items;
}
