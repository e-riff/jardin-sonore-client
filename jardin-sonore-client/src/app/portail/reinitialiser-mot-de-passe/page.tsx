import {JSX} from "react";
import PortalResetRequestForm from "@/components/portal/PortalResetRequestForm";
import {getTranslations} from "@/i18n/server";

export default async function PortalResetPage(): Promise<JSX.Element> {
    const content = (await getTranslations()).portal.reset;

    return (
        <section className="mx-auto flex min-h-[70vh] max-w-lg items-center px-6 py-24 sm:px-margin">
            <div className="w-full rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-7 ambient-shadow sm:p-10">
                <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary">{content.eyebrow}</p>
                <h1 className="mt-3 font-serif text-4xl leading-tight text-on-surface">{content.title}</h1>
                <p className="mt-4 font-sans text-sm leading-6 text-on-surface-variant">{content.description}</p>
                <div className="mt-8"><PortalResetRequestForm /></div>
                <a className="mt-6 inline-block font-sans text-sm font-bold text-primary underline underline-offset-4" href="/portail/connexion">{content.loginLink}</a>
            </div>
        </section>
    );
}
