import {notFound} from "next/navigation";
import PortalDocumentPanel from "@/components/portal/PortalDocumentPanel";
import PortalSessionActions from "@/components/portal/PortalSessionActions";
import PortalSessionPreview from "@/components/portal/PortalSessionPreview";
import {PortalApiClient} from "@/lib/portal/api-client";
import {getPortalAccessToken} from "@/lib/portal/session";
import {getTranslations} from "@/i18n/server";

export default async function PortalSessionPage({params}: {params: Promise<{uuid: string}>}): Promise<React.JSX.Element> {
    const [{uuid}, token, dictionary] = await Promise.all([params, getPortalAccessToken(), getTranslations()]);
    if (!token) notFound();
    const result = await (await PortalApiClient.fromCurrentRequest(token)).session(uuid);
    if (result.response.status === 404 || !result.response.ok || !result.data) notFound();
    const session = result.data;
    const content = dictionary.portal.detail;
    return <section><div className="hidden lg:block"><PortalSessionActions documentStatus={session.documentStatus} sessionUuid={session.uuid} showDocumentLink={false} /></div><div className="lg:hidden"><PortalSessionActions documentStatus={session.documentStatus} sessionUuid={session.uuid} /></div><div className="grid gap-8 lg:grid-cols-[1fr_20rem]"><article className="portal-content-sheet"><p className="portal-eyebrow">{session.sharedAt ?? session.sessionDate}</p><h1 className="font-serif text-4xl font-semibold">{session.title}</h1>{session.theme&&<p className="mt-2 text-on-surface-variant">{session.theme}</p>}{session.generalNotes&&<section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.intention}</h2><p className="mt-3 whitespace-pre-wrap">{session.generalNotes}</p></section>}{session.materialSummary&&<section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.material}</h2><p className="mt-3 whitespace-pre-wrap">{session.materialSummary}</p></section>}<PortalSessionPreview sequences={session.sequences} /></article><div className="hidden lg:block"><PortalDocumentPanel sessionUuid={session.uuid} status={session.documentStatus}/></div></div></section>;
}
