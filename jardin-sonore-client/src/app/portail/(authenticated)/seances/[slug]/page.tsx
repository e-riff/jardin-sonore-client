import {notFound} from "next/navigation";
import PortalDocumentPanel from "@/components/portal/PortalDocumentPanel";
import PortalSessionActions from "@/components/portal/PortalSessionActions";
import PortalSessionPreview from "@/components/portal/PortalSessionPreview";
import {PortalApiClient} from "@/lib/portal/api-client";
import {getPortalAccessToken} from "@/lib/portal/session";
import {getTranslations} from "@/i18n/server";

export default async function PortalSessionPage({params}: {params: Promise<{slug: string}>}): Promise<React.JSX.Element> {
    const [{slug}, token, dictionary] = await Promise.all([params, getPortalAccessToken(), getTranslations()]);
    if (!token) notFound();
    const result = await (await PortalApiClient.fromCurrentRequest(token)).session(slug);
    if (result.response.status === 404 || !result.response.ok || !result.data) notFound();
    const session = result.data;
    const content = dictionary.portal.detail;
    return <section><div className="hidden lg:block"><PortalSessionActions documentStatus={session.documentStatus} sessionSlug={session.slug} showDocumentLink={false} /></div><div className="lg:hidden"><PortalSessionActions documentStatus={session.documentStatus} sessionSlug={session.slug} /></div><div className="grid gap-8 lg:grid-cols-[1fr_20rem]"><article className="portal-content-sheet"><p className="portal-eyebrow">{session.sharedAt ?? session.sessionDate}</p><h1 className="font-serif text-4xl font-semibold">{session.title}</h1>{session.theme&&<p className="mt-2 text-on-surface-variant">{session.theme}</p>}{session.instrumentNames.length > 0 && <p className="mt-4 text-sm text-on-surface-variant"><span className="font-semibold text-on-surface">{content.instruments} : </span>{session.instrumentNames.join(" · ")}</p>}{session.generalNotes&&<section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.intention}</h2><p className="mt-3 whitespace-pre-wrap">{session.generalNotes}</p></section>}{session.materialSummary&&<section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.material}</h2><p className="mt-3 whitespace-pre-wrap">{session.materialSummary}</p></section>}<PortalSessionPreview sequences={session.sequences} /></article><div className="hidden lg:block"><PortalDocumentPanel sessionSlug={session.slug} status={session.documentStatus}/></div></div></section>;
}
