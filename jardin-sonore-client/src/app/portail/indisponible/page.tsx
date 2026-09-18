import {JSX} from "react";
import Link from "next/link";
import {getTranslations} from "@/i18n/server";

export default async function PortalUnavailablePage(): Promise<JSX.Element> {
    const content = (await getTranslations()).portal.unavailable;

    return (
        <section className="mx-auto max-w-xl px-6 py-24 sm:px-margin">
            <p className="font-sans text-sm font-bold tracking-wider text-primary">{content.eyebrow}</p>
            <h1 className="mt-3 font-display text-4xl text-on-surface">{content.title}</h1>
            <p className="mt-5 font-sans leading-7 text-on-surface-variant">{content.description}</p>
            <Link className="mt-8 inline-flex rounded-full bg-primary px-6 py-3 font-sans text-sm font-bold text-on-primary" href="/portail/connexion">
                {content.loginLink}
            </Link>
        </section>
    );
}
