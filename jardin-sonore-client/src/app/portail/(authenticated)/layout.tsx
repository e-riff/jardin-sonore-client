import type {ReactNode} from "react";
import PortalAccountHeader from "@/components/portal/PortalAccountHeader";
import {PortalAccountProvider} from "@/components/portal/PortalAccountProvider";
import {logoutPortalAction} from "@/app/portail/actions";
import {getPortalSession} from "@/lib/portal/session";

export default async function AuthenticatedPortalLayout({children}: {children: ReactNode}): Promise<ReactNode> {
    const account = await getPortalSession();
    return <PortalAccountProvider account={account}><div className="portal-shell"><PortalAccountHeader onLogout={logoutPortalAction} /><main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">{children}</main></div></PortalAccountProvider>;
}
