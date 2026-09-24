import Link from "next/link";
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
        <Link className="mb-5 inline-flex font-semibold text-primary underline underline-offset-4" href={backUrl}>{content.back}</Link>
        <article className="portal-content-sheet">
            <p className="portal-eyebrow">{item.type === "fingerplay" ? filters.fingerplay : filters.nurseryRhyme}</p>
            <h1 className="font-serif text-4xl font-semibold">{item.title}</h1>
            {item.source ? <p className="mt-2 text-sm text-on-surface-variant">{content.source} : {item.source}</p> : null}
            <div className="mt-4"><PortalThemeBadges themes={item.themes} /></div>
            {item.organizations.length > 1 ? <p className="mt-4 text-sm text-on-surface-variant">{content.organizations} : {item.organizations.map((organization) => organization.name).join(", ")}</p> : null}
            {(hasBlocks || item.body.trim()) ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.lyrics}</h2>
                {hasBlocks ? <div className="mt-4 space-y-3 leading-7">{item.contentBlocks.map((block, index) => {
                    if (block.kind === "break") return <div aria-hidden="true" className="h-2" key={index} />;
                    if (block.kind === "section") return <h3 className="pt-2 font-semibold" key={index}>{block.text}</h3>;
                    return <div className="grid gap-1 sm:grid-cols-2 sm:gap-6" key={index}><p className="whitespace-pre-wrap">{block.text}</p>{block.gesture ? <p className="border-l-2 border-primary/25 pl-3 italic text-on-surface-variant">{block.gesture}</p> : null}</div>;
                })}</div> : <p className="mt-4 whitespace-pre-wrap leading-7">{item.body}</p>}
            </section> : null}
            {item.generalInstructions ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.instructions}</h2><p className="mt-3 whitespace-pre-wrap">{item.generalInstructions}</p></section> : null}
            {item.notes ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.notes}</h2><p className="mt-3 whitespace-pre-wrap">{item.notes}</p></section> : null}
            {item.media.length > 0 ? <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.media}</h2><ul className="mt-3 space-y-3">{item.media.map((media, index) => <li key={`${media.url}-${index}`}><a className="font-semibold text-primary underline underline-offset-4" href={media.url} rel="noopener noreferrer" target="_blank">{media.title || content.externalLink}</a></li>)}</ul></section> : null}
        </article>
    </section>;
}
