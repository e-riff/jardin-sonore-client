import type {Metadata} from "next";
import NewsletterConfirmationPanel from "@/components/newsletter/NewsletterConfirmationPanel";
import {getTranslations} from "@/i18n/server";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {robots: {index: false, follow: false}, referrer: "no-referrer"};

export default async function NewsletterConfirmationPage({params}: {params: Promise<{token: string}>}): Promise<React.JSX.Element> {
    const [{token}, dictionary] = await Promise.all([params, getTranslations()]);
    return <main className="flex min-h-[70vh] w-full flex-col items-center justify-center bg-background px-6 py-16">
        <section className="w-full min-w-0 max-w-lg rounded-xl border border-outline-variant bg-surface p-6 sm:p-8">
            <p className="text-sm font-semibold uppercase tracking-wider text-primary">{dictionary.brand.name}</p>
            <h1 className="mt-3 font-serif text-3xl font-semibold">{dictionary.newsletter.confirmation.title}</h1>
            <NewsletterConfirmationPanel linkIsValid={"confirmation" === token} />
        </section>
    </main>;
}
