import {getTranslations} from "@/i18n/server";

export default async function PortalUnavailablePage(): Promise<React.JSX.Element> {
    const content = (await getTranslations()).portal.unavailable;

    return <section className="portal-shell flex min-h-screen items-center justify-center px-4"><div className="w-full max-w-[32rem] rounded-xl border border-outline-variant bg-white p-8"><p className="portal-eyebrow">{content.eyebrow}</p><h1 className="font-serif text-3xl font-semibold">{content.title}</h1><p className="mt-3 text-on-surface-variant">{content.description}</p><a className="mt-6 inline-block font-semibold text-primary underline" href="/portail/connexion">{content.action}</a></div></section>;
}
