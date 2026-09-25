import {getPortalAccessToken, getPortalSession} from "@/lib/portal/session";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {portalAccountDisplayName} from "@/lib/portal/types";
import PortalSessionsList from "@/components/portal/PortalSessionsList";
import {parsePortalListQuery} from "@/lib/portal/list-query";
import {redirect} from "next/navigation";
import {portalRoutes} from "@/lib/portal/routes";
import {portalRequestOrUnavailable} from "@/lib/portal/request-or-unavailable";

export default async function PortalSessionsPage({searchParams}: {searchParams: Promise<Record<string, string | string[] | undefined>>}): Promise<React.JSX.Element> {
    const [account, dictionary, token, rawSearchParams] = await Promise.all([getPortalSession(), getTranslations(), getPortalAccessToken(), searchParams]);
    const query = parsePortalListQuery(rawSearchParams, "sessions");
    if (!token) redirect(portalRoutes.login);
    const response = await portalRequestOrUnavailable(async () => (await PortalApiClient.fromCurrentRequest(token)).sessions(query));
    if (response.response.status === 401) redirect(portalRoutes.sessionInvalid);
    if (!response.response.ok || !response.data) redirect(portalRoutes.unavailable);
    const accountDisplayName = portalAccountDisplayName(account);
    return <section><PortalSessionsList account={account} accountLabel={accountDisplayName} content={dictionary.portal.sessions} filters={dictionary.portal.filters} query={query} response={response.data} /></section>;
}
