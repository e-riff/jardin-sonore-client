import {redirect} from "next/navigation";
import PortalRepertoireList from "@/components/portal/PortalRepertoireList";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {parsePortalListQuery} from "@/lib/portal/list-query";
import {portalRoutes} from "@/lib/portal/routes";
import {portalRequestOrUnavailable} from "@/lib/portal/request-or-unavailable";
import {getPortalAccessToken, getPortalSession} from "@/lib/portal/session";
import {portalAccountDisplayName} from "@/lib/portal/types";

export default async function PortalRepertoirePage({searchParams}: {searchParams: Promise<Record<string, string | string[] | undefined>>}): Promise<React.JSX.Element> {
    const [account, dictionary, token, rawSearchParams] = await Promise.all([getPortalSession(), getTranslations(), getPortalAccessToken(), searchParams]);
    const query = parsePortalListQuery(rawSearchParams, "repertoire");
    if (!token) redirect(portalRoutes.login);
    const response = await portalRequestOrUnavailable(async () => (await PortalApiClient.fromCurrentRequest(token)).repertoire(query));
    if (response.response.status === 401) redirect(portalRoutes.sessionInvalid);
    if (!response.response.ok || !response.data) redirect(portalRoutes.unavailable);

    return <section>
        <PortalRepertoireList account={account} accountLabel={portalAccountDisplayName(account)} content={dictionary.portal.repertoire} filters={dictionary.portal.filters} query={query} response={response.data} />
    </section>;
}
