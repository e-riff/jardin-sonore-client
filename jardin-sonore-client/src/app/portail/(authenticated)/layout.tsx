import {JSX, ReactNode} from "react";
import BrandLogo from "@/components/BrandLogo";
import {logoutPortalAction} from "@/app/portail/actions";
import {getTranslations} from "@/i18n/server";
import {getPortalSession} from "@/lib/portal/session";

interface AuthenticatedPortalLayoutProps {
    children: ReactNode;
}

export default async function AuthenticatedPortalLayout({children}: AuthenticatedPortalLayoutProps): Promise<JSX.Element> {
    const [dictionary, portalSession] = await Promise.all([getTranslations(), getPortalSession()]);
    const content = dictionary.portal.shell;

    return (
        <div className="min-h-screen bg-surface-container-low">
            <header className="border-b border-outline-variant/50 bg-background px-6 py-4 sm:px-margin">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-5">
                    <a href="/portail/seances" aria-label={content.homeAriaLabel}><BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold" /></a>
                    <div className="flex items-center gap-4">
                        <p className="hidden font-sans text-sm text-on-surface-variant sm:block">{portalSession.email}</p>
                        <form action={logoutPortalAction}>
                            <button className="rounded-full border border-primary/35 px-4 py-2 font-sans text-sm font-bold text-primary transition hover:bg-primary-fixed/35" type="submit">{content.logout}</button>
                        </form>
                    </div>
                </div>
            </header>
            <div>{children}</div>
        </div>
    );
}
