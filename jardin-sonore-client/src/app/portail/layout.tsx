import type {Metadata} from "next";
import Link from "next/link";
import type {ReactNode} from "react";
import fr from "@/i18n/dictionaries/fr";
import {showLegalPage} from "@/lib/legal-publication";

export const metadata: Metadata = {title: fr.header.portalLink, robots: {index: false, follow: false}};

export default function PortalLayout({children}: {children: ReactNode}): ReactNode {
    return <>
        {children}
        {showLegalPage ? <div className="bg-background px-4 py-5 text-center font-sans text-sm">
            <Link className="font-semibold text-primary underline underline-offset-4 hover:text-primary-container" href="/mentions-legales">{fr.footer.legalLink}</Link>
        </div> : null}
    </>;
}
