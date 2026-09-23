import Link from "next/link";
import {getTranslations} from "@/i18n/server";
import {requestPortalPasswordResetAction, logoutPortalAction} from "@/app/portail/actions";
import PortalAccountHeader from "@/components/portal/PortalAccountHeader";
import {getPortalAccessToken, getPortalSession} from "@/lib/portal/session";
import {portalRoutes} from "@/lib/portal/routes";

export default async function PortalResetPage({searchParams}: {searchParams: Promise<{sent?: string}>}): Promise<React.JSX.Element> {
    const [content, token, parameters] = await Promise.all([(await getTranslations()).portal.reset, getPortalAccessToken(), searchParams]);
    const account = token ? await getPortalSession() : null;
    const panel = parameters.sent
        ? <div className="w-full max-w-[28rem] rounded-xl bg-secondary-container p-8 text-on-secondary-container"><p>{account ? content.sentLoggedIn : content.sentAnonymous}</p><Link className="mt-5 inline-block font-semibold underline" href={account ? portalRoutes.sessions : portalRoutes.login}>{account ? content.portalLink : content.loginLink}</Link></div>
        : <div className="w-full max-w-[28rem] rounded-xl border border-outline-variant bg-white p-8 shadow-sm"><p className="portal-eyebrow">{content.eyebrow}</p><h1 className="font-serif text-3xl font-semibold">{content.title}</h1><p className="mt-3 text-sm leading-6 text-on-surface-variant">{content.description}</p><form action={requestPortalPasswordResetAction} className="mt-8 grid gap-5"><label className="grid gap-2 text-sm font-semibold" htmlFor="reset-email"><span>{content.emailLabel}</span><input className="rounded-lg border border-outline-variant px-4 py-3" id="reset-email" name="email" type="email" autoComplete="email" required /></label><button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white" type="submit">{content.submit}</button></form><Link className="mt-6 inline-block text-sm font-semibold text-primary underline" href={account ? portalRoutes.sessions : portalRoutes.login}>{account ? content.portalLink : content.loginLink}</Link></div>;
    if (!account) return <section className="flex min-h-screen items-center justify-center bg-background px-4 py-16">{panel}</section>;
    return <div className="portal-shell"><PortalAccountHeader account={account} onLogout={logoutPortalAction} /><main className="flex min-h-[calc(100vh-4.5rem)] items-center justify-center px-4 py-16">{panel}</main></div>;
}
