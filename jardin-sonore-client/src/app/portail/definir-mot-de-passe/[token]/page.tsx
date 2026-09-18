import {JSX} from "react";
import PortalPasswordForm from "@/components/portal/PortalPasswordForm";
import {getTranslations} from "@/i18n/server";

interface PortalPasswordPageProps {
    params: Promise<{token: string}>;
}

export default async function PortalPasswordPage({params}: PortalPasswordPageProps): Promise<JSX.Element> {
    const {token} = await params;
    const content = (await getTranslations()).portal.password;

    return (
        <section className="mx-auto flex min-h-[70vh] max-w-lg items-center px-6 py-24 sm:px-margin">
            <div className="w-full rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-7 ambient-shadow sm:p-10">
                <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary">{content.eyebrow}</p>
                <h1 className="mt-3 font-serif text-4xl leading-tight text-on-surface">{content.title}</h1>
                <p className="mt-4 font-sans text-sm leading-6 text-on-surface-variant">{content.description}</p>
                <div className="mt-8"><PortalPasswordForm token={token} /></div>
            </div>
        </section>
    );
}
