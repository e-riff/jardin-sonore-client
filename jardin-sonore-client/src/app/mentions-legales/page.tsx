import type {Metadata} from "next";
import Link from "next/link";
import {notFound} from "next/navigation";
import type {JSX} from "react";
import fr from "@/i18n/dictionaries/fr";
import {getTranslations} from "@/i18n/server";
import {legalPagePublished, showLegalPage} from "@/lib/legal-publication";

export const metadata: Metadata = {
    title: fr.legalPage.title,
    description: fr.legalPage.description,
    alternates: {canonical: "/mentions-legales"},
    openGraph: {url: "/mentions-legales", title: fr.legalPage.title, description: fr.legalPage.description},
    twitter: {title: fr.legalPage.title, description: fr.legalPage.description},
    robots: legalPagePublished && process.env.NODE_ENV === "production" ? {index: true, follow: true} : {index: false, follow: false},
};

export default async function LegalPage(): Promise<JSX.Element> {
    if (!showLegalPage) notFound();

    const content = (await getTranslations()).legalPage;
    const editorDetails = [
        [content.tradeNameLabel, content.tradeName],
        [content.siretLabel, content.siret],
        [content.identityLabel, content.identity],
    ];

    return <article className="mx-auto w-full max-w-4xl px-6 pb-20 pt-32 sm:px-margin lg:pt-40">
        <header className="relative overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface-container-low px-6 py-9 sm:px-10 sm:py-12">
            <span aria-hidden="true" className="absolute inset-y-0 left-0 w-1 bg-primary" />
            <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary-container">{content.eyebrow}</p>
            <h1 className="mt-4 text-balance font-serif text-3xl font-semibold leading-tight text-primary sm:text-5xl">{content.title}</h1>
            <p className="mt-5 max-w-2xl text-lg leading-8 text-on-surface-variant">{content.description}</p>
        </header>

        <nav aria-label={content.contentsLabel} className="mt-10 flex flex-wrap gap-x-6 gap-y-3 border-y border-outline-variant py-5 font-sans text-sm font-semibold">
            <a className="text-primary underline underline-offset-4 hover:text-primary-container" href="#mentions-legales">{content.legalLink}</a>
            <a className="text-primary underline underline-offset-4 hover:text-primary-container" href="#confidentialite">{content.privacyLink}</a>
        </nav>

        <section aria-labelledby="mentions-legales-title" className="mt-12 scroll-mt-24" id="mentions-legales">
            <h2 className="font-serif text-3xl font-semibold text-on-surface" id="mentions-legales-title">{content.legalTitle}</h2>
            <h3 className="mt-8 font-sans text-xl font-semibold text-on-surface">{content.editorTitle}</h3>
            <dl className="mt-4 divide-y divide-outline-variant border-y border-outline-variant font-sans text-sm leading-6">
                {editorDetails.map(([label, value]) => <div className="grid gap-1 py-3 sm:grid-cols-[12rem_1fr] sm:gap-4" key={label}><dt className="font-semibold text-on-surface">{label}</dt><dd className="min-w-0 break-words text-on-surface-variant">{value}</dd></div>)}
            </dl>
            <h3 className="mt-8 font-sans text-xl font-semibold text-on-surface">{content.contactTitle}</h3>
            <p className="mt-3 leading-7 text-on-surface-variant">{content.contactText} <Link className="font-semibold text-primary underline underline-offset-4 hover:text-primary-container" href="/#contact">{content.contactLink}</Link></p>
            <h3 className="mt-8 font-sans text-xl font-semibold text-on-surface">{content.hostTitle}</h3>
            <address className="mt-3 font-sans not-italic leading-7 text-on-surface-variant">
                {content.hostName}<br />
                {content.hostAddress}<br />
                <a className="font-semibold text-primary underline underline-offset-4 hover:text-primary-container" href="tel:+33444446040">{content.hostPhone}</a>
            </address>
        </section>

        <section aria-labelledby="confidentialite-title" className="mt-16 scroll-mt-24 border-t border-outline-variant pt-12" id="confidentialite">
            <h2 className="font-serif text-3xl font-semibold text-on-surface" id="confidentialite-title">{content.privacyTitle}</h2>
            {[
                [content.controllerTitle, content.controllerText],
                [content.contactDataTitle, content.contactDataText],
                [content.newsletterTitle, content.newsletterText],
                [content.portalTitle, content.portalText],
                [content.recipientsTitle, content.recipientsText],
                [content.cookiesTitle, content.cookiesText],
            ].map(([title, body]) => <section className="mt-8" key={title}><h3 className="font-sans text-xl font-semibold text-on-surface">{title}</h3><p className="mt-3 leading-7 text-on-surface-variant">{body}</p></section>)}
            <section className="mt-8">
                <h3 className="font-sans text-xl font-semibold text-on-surface">{content.rightsTitle}</h3>
                <p className="mt-3 leading-7 text-on-surface-variant">{content.rightsText} <a className="font-semibold text-primary underline underline-offset-4 hover:text-primary-container" href="https://www.cnil.fr/">{content.cnilLink}</a>.</p>
            </section>
        </section>
    </article>;
}
