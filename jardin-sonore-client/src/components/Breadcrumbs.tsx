import Link from "next/link";
import type {JSX} from "react";
import {getTranslations} from "@/i18n/server";
import {getPublicBreadcrumbs} from "@/lib/public-breadcrumbs";
import {getSiteUrl} from "@/lib/site-url";

interface BreadcrumbsProps {
    pathname: string;
}

export default async function Breadcrumbs({pathname}: BreadcrumbsProps): Promise<JSX.Element> {
    const {breadcrumbs: labels} = await getTranslations();
    const items = getPublicBreadcrumbs(pathname, labels);
    const siteUrl = getSiteUrl();
    const structuredData = {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        itemListElement: items.map((item, index) => ({
            "@type": "ListItem",
            position: index + 1,
            name: item.label,
            item: `${siteUrl}${item.href === "/" ? "" : item.href}`,
        })),
    };

    return (
        <>
            <nav aria-label={labels.ariaLabel} className="font-sans text-sm text-on-surface-variant">
                <ol className="flex flex-wrap items-center gap-2">
                    {items.map((item, index) => <li className="flex items-center gap-2" key={item.href}>
                        {index > 0 ? <span aria-hidden="true" className="text-outline">/</span> : null}
                        {index === items.length - 1
                            ? <span aria-current="page" className="font-semibold text-on-surface">{item.label}</span>
                            : <Link className="underline underline-offset-4 hover:text-primary" href={item.href}>{item.label}</Link>}
                    </li>)}
                </ol>
            </nav>
            <script type="application/ld+json" dangerouslySetInnerHTML={{__html: JSON.stringify(structuredData).replace(/</g, "\\u003c")}} />
        </>
    );
}
