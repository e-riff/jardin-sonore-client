import type {ReactNode} from "react";
import PortalAccountHeader from "@/components/portal/PortalAccountHeader";
import PortalImpersonationBanner from "@/components/portal/PortalImpersonationBanner";
import {PortalAccountProvider} from "@/components/portal/PortalAccountProvider";
import {logoutPortalAction} from "@/app/portail/actions";
import {getPortalSession, isPortalImpersonation} from "@/lib/portal/session";

export default async function AuthenticatedPortalLayout({children}: {children: ReactNode}): Promise<ReactNode> {
    const account = await getPortalSession();
    const impersonating = await isPortalImpersonation();
    return <PortalAccountProvider account={account}><div className="portal-shell"><PortalAccountHeader onLogout={logoutPortalAction} />{impersonating ? <PortalImpersonationBanner onTerminate={logoutPortalAction} /> : null}<main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">{children}</main></div></PortalAccountProvider>;
}
