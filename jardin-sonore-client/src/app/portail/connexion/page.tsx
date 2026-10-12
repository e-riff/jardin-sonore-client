import type {Metadata} from "next";
import fr from "@/i18n/dictionaries/fr";
import {getTranslations} from "@/i18n/server";
import {loginPortalAction} from "@/app/portail/actions";
import {redirect} from "next/navigation";
import {PortalApiClient} from "@/lib/portal/api-client";
import {getPortalAccessToken} from "@/lib/portal/session";
import {resolvePortalLoginDestination} from "@/lib/portal/login-destination";
import {portalRequestOrUnavailable} from "@/lib/portal/request-or-unavailable";
import {portalRoutes} from "@/lib/portal/routes";

export const metadata: Metadata = {title: fr.portal.login.title};

export default async function PortalLoginPage({searchParams}: {searchParams: Promise<Record<string, string | string[] | undefined>>}): Promise<React.JSX.Element> {
    const [dictionary, parameters, token] = await Promise.all([getTranslations(), searchParams, getPortalAccessToken()]);
    const content = dictionary.portal.login;
    const destination = resolvePortalLoginDestination(parameters.next);
    const hasLoginError = parameters.error === "1";
    if (token) {
        const result = await portalRequestOrUnavailable(async () => (await PortalApiClient.fromCurrentRequest(token)).me());
        if (result.response.ok && result.data) redirect(destination);
        if (result.response.status !== 401) redirect(portalRoutes.unavailable);
    }

    return <section className="portal-shell flex min-h-screen items-center justify-center px-4 py-16"><div className="w-full max-w-112 rounded-xl border border-outline-variant bg-white p-8 shadow-sm"><p className="portal-eyebrow">{content.eyebrow}</p><h1 className="font-serif text-3xl font-semibold">{content.title}</h1><p className="mt-3 text-sm leading-6 text-on-surface-variant">{content.description}</p>{hasLoginError ? <p className="mt-5 rounded-lg border border-primary/30 bg-primary-fixed/45 px-4 py-3 text-sm text-primary" id="portal-login-error" role="alert">{content.error}</p> : null}<form action={loginPortalAction} className="mt-8 grid gap-5" name="portal-login"><input type="hidden" name="next" value={destination} /><label className="grid gap-2 text-sm font-semibold" htmlFor="portal-email"><span>{content.emailLabel}</span><input aria-describedby={hasLoginError ? "portal-login-error" : undefined} aria-invalid={hasLoginError} className="rounded-lg border border-outline-variant px-4 py-3" id="portal-email" name="email" type="email" autoComplete="username" required /></label><label className="grid gap-2 text-sm font-semibold" htmlFor="portal-password"><span>{content.passwordLabel}</span><input aria-describedby={hasLoginError ? "portal-login-error" : undefined} aria-invalid={hasLoginError} className="rounded-lg border border-outline-variant px-4 py-3" id="portal-password" name="password" type="password" autoComplete="current-password" required /></label><button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white" type="submit">{content.submit}</button></form><a className="mt-6 inline-block text-sm font-semibold text-primary underline" href="/portail/reinitialiser-mot-de-passe">{content.resetLink}</a></div></section>;
}
