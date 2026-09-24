import {notFound, redirect} from "next/navigation";
import PortalRepertoireDetail from "@/components/portal/PortalRepertoireDetail";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {parsePortalListQuery} from "@/lib/portal/list-query";
import {portalRoutes} from "@/lib/portal/routes";
import {portalRequestOrUnavailable} from "@/lib/portal/request-or-unavailable";
import {getPortalAccessToken} from "@/lib/portal/session";

export default async function PortalRepertoireItemPage({params, searchParams}: {params: Promise<{slug: string}>; searchParams: Promise<Record<string, string | string[] | undefined>>}): Promise<React.JSX.Element> {
    const [{slug}, rawSearchParams, token, dictionary] = await Promise.all([params, searchParams, getPortalAccessToken(), getTranslations()]);
    if (!token) redirect(portalRoutes.login);
    const response = await portalRequestOrUnavailable(async () => (await PortalApiClient.fromCurrentRequest(token)).repertoireItem(slug));
    if (response.response.status === 401) redirect(portalRoutes.sessionInvalid);
    if (response.response.status === 404) notFound();
    if (!response.response.ok || !response.data) redirect(portalRoutes.unavailable);
    const query = parsePortalListQuery(rawSearchParams, "repertoire");

    return <PortalRepertoireDetail content={dictionary.portal.repertoire} filters={dictionary.portal.filters} item={response.data} query={query} />;
}
