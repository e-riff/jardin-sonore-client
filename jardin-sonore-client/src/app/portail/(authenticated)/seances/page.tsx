import {getPortalAccessToken, getPortalSession} from "@/lib/portal/session";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {portalAccountDisplayName} from "@/lib/portal/types";
import PortalSessionsList from "@/components/portal/PortalSessionsList";

export default async function PortalSessionsPage(): Promise<React.JSX.Element> {
    const [account, dictionary, token] = await Promise.all([getPortalSession(), getTranslations(), getPortalAccessToken()]);
    const response = token ? await (await PortalApiClient.fromCurrentRequest(token)).sessions() : null;
    const sessions = response?.response.ok ? response.data?.items ?? [] : [];
    const accountDisplayName = portalAccountDisplayName(account);
    return <section><p className="portal-eyebrow">{accountDisplayName}<span className="mx-2 text-outline-variant">—</span><span className="text-secondary">{account.organizations.map((organization) => organization.name).join(", ")}</span></p><h1 className="font-serif text-4xl font-semibold">{dictionary.portal.sessions.title}</h1><p className="mt-3 text-on-surface-variant">{dictionary.portal.sessions.introduction}</p><PortalSessionsList account={account} content={dictionary.portal.sessions} sessions={sessions} /></section>;
}
