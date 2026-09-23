import {getPortalSession} from "@/lib/portal/session";
import {getTranslations} from "@/i18n/server";
import PortalProfileForm from "@/components/portal/PortalProfileForm";

export default async function PortalAccountPage(): Promise<React.JSX.Element> {
    const [account, dictionary] = await Promise.all([getPortalSession(), getTranslations()]);
    const content = dictionary.portal.account;
    return <section className="mx-auto w-full max-w-144">
        <p className="portal-eyebrow">{content.eyebrow}</p>
        <h1 className="font-serif text-4xl font-semibold">{content.title}</h1>
        <p className="mt-3 text-on-surface-variant">{content.description}</p>
        <PortalProfileForm account={account} content={content} />
    </section>;
}
