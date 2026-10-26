import type {Metadata} from "next";
import fr from "@/i18n/dictionaries/fr";
import PortalPasswordForm from "@/components/portal/PortalPasswordForm";
import {getTranslations} from "@/i18n/server";
import {PortalApiClient} from "@/lib/portal/api-client-core";

export const metadata: Metadata = {title: fr.portal.password.title, robots: {index: false, follow: false}};

export default async function PortalPasswordPage({params}: {params: Promise<{token: string}>}): Promise<React.JSX.Element> {
    const [{token}, dictionary] = await Promise.all([params, getTranslations()]);
    const c = dictionary.portal.password;
    let tokenIsValid = false;
    let tokenCheckFailed = false;

    try {
        const result = await (await PortalApiClient.fromCurrentRequest()).validatePasswordToken(token);
        tokenIsValid = result.response.ok && result.data?.valid === true;
    } catch {
        tokenCheckFailed = true;
    }

    return (
        <section className="portal-shell flex min-h-screen items-center justify-center px-4 py-16">
            <div className="w-full max-w-[28rem] rounded-xl border border-outline-variant bg-white p-8 shadow-sm">
                <p className="portal-eyebrow">{c.eyebrow}</p>
                <h1 className="font-serif text-3xl font-semibold">{c.title}</h1>
                <p className="mt-3 text-sm text-on-surface-variant">{c.description}</p>
                <div className="mt-8">
                    {tokenCheckFailed ? (
                        <p role="alert">{c.validationUnavailable}</p>
                    ) : tokenIsValid ? (
                        <PortalPasswordForm token={token}/>
                    ) : (
                        <p role="alert">{c.unavailable}</p>
                    )}
                </div>
            </div>
        </section>
    );
}
