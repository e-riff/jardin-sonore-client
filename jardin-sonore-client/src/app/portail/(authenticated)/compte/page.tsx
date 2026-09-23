import {getTranslations} from "@/i18n/server";
import PortalProfileForm from "@/components/portal/PortalProfileForm";

export default async function PortalAccountPage(): Promise<React.JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.portal.account;
    return <section className="mx-auto w-full max-w-144">
        <p className="portal-eyebrow">{content.eyebrow}</p>
        <h1 className="font-serif text-4xl font-semibold">{content.title}</h1>
        <p className="mt-3 text-on-surface-variant">{content.description}</p>
        <PortalProfileForm content={content} />
    </section>;
}
