import type {Metadata} from "next";
import Link from "next/link";
import {redirect} from "next/navigation";
import PortalChangePasswordForm from "@/components/portal/PortalChangePasswordForm";
import fr from "@/i18n/dictionaries/fr";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {portalRequestOrUnavailable} from "@/lib/portal/request-or-unavailable";
import {portalRoutes} from "@/lib/portal/routes";
import {getPortalAccessToken, isPortalImpersonation} from "@/lib/portal/session";

export const metadata: Metadata = {title: fr.portal.changePassword.title};

export default async function PortalChangePasswordPage(): Promise<React.JSX.Element> {
    const token = await getPortalAccessToken();
    const loginDestination = `${portalRoutes.login}?next=${encodeURIComponent(portalRoutes.passwordChange)}`;
    if (!token) redirect(loginDestination);

    const result = await portalRequestOrUnavailable(async () => (await PortalApiClient.fromCurrentRequest(token)).me());
    if (result.response.status === 401) redirect(loginDestination);
    if (!result.response.ok || !result.data) redirect(portalRoutes.unavailable);
    if (await isPortalImpersonation()) redirect(portalRoutes.account);

    const content = (await getTranslations()).portal.changePassword;
    return <section className="portal-shell flex min-h-screen items-center justify-center px-4 py-16"><div className="w-full max-w-112 rounded-xl border border-outline-variant bg-white p-8 shadow-sm"><p className="portal-eyebrow">{content.eyebrow}</p><h1 className="font-serif text-3xl font-semibold">{content.title}</h1><p className="mt-3 text-sm leading-6 text-on-surface-variant">{content.description}</p><div className="mt-8"><PortalChangePasswordForm /></div><Link className="mt-6 inline-block text-sm font-semibold text-primary underline" href={portalRoutes.account}>{content.accountLink}</Link></div></section>;
}
