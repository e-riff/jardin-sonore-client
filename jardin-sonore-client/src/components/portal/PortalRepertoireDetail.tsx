import Link from "next/link";
import {ArrowLeftIcon} from "@heroicons/react/24/outline";
import PortalLyrics from "@/components/portal/PortalLyrics";
import PortalMediaGallery from "@/components/portal/PortalMediaGallery";
import PortalThemeBadges from "@/components/portal/PortalThemeBadges";
import type {Dictionary} from "@/i18n/types";
import type {PortalListQuery} from "@/lib/portal/list-query";
import {portalListQueryToSearchParams} from "@/lib/portal/list-query";
import {portalRoutes} from "@/lib/portal/routes";
import type {PortalRepertoireDetail as RepertoireDetail} from "@/lib/portal/types";

export default function PortalRepertoireDetail({item, content, filters, query}: {item: RepertoireDetail; content: Dictionary["portal"]["repertoire"]; filters: Dictionary["portal"]["filters"]; query: PortalListQuery}): React.JSX.Element {
    const search = portalListQueryToSearchParams(query).toString();
    const backUrl = `${portalRoutes.repertoire}${search ? `?${search}` : ""}`;
    const hasBlocks = item.contentBlocks.length > 0;

    return <section>
        <Link className="mb-6 inline-flex items-center gap-2 rounded-full border border-primary px-4 py-2 text-sm font-semibold leading-none text-primary transition hover:bg-primary hover:text-white" href={backUrl}><ArrowLeftIcon aria-hidden="true" className="h-4 w-4" />{content.back}</Link>
        <article className="portal-content-sheet">
            <p className="portal-eyebrow">{item.type === "fingerplay" ? filters.fingerplay : filters.nurseryRhyme}</p>
            <h1 className="font-serif text-4xl font-semibold">{item.title}</h1>
            {item.source ? <p className="mt-2 text-sm text-on-surface-variant">{content.source} : {item.source}</p> : null}
            <div className="mt-4"><PortalThemeBadges themes={item.themes} /></div>
            {item.organizations.length > 1 ? <p className="mt-4 text-sm text-on-surface-variant">{content.organizations} : {item.organizations.map((organization) => organization.name).join(", ")}</p> : null}
            {(hasBlocks || item.body.trim()) ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.lyrics}</h2><div className="mt-4"><PortalLyrics blocks={item.contentBlocks} body={item.body} /></div></section> : null}
            {item.generalInstructions ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.instructions}</h2><p className="mt-3 whitespace-pre-wrap">{item.generalInstructions}</p></section> : null}
            {item.notes ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.notes}</h2><p className="mt-3 whitespace-pre-wrap">{item.notes}</p></section> : null}
            {item.media.length > 0 ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.media}</h2><PortalMediaGallery content={content} media={item.media} /></section> : null}
        </article>
    </section>;
}
