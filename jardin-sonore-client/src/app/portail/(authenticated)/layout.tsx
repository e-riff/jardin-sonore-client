import type {ReactNode} from "react";
import PortalAccountHeader from "@/components/portal/PortalAccountHeader";
import {logoutPortalAction} from "@/app/portail/actions";
import {getPortalSession} from "@/lib/portal/session";

export default async function AuthenticatedPortalLayout({children}: {children: ReactNode}): Promise<ReactNode> {
    const account = await getPortalSession();
    return <div className="portal-shell"><PortalAccountHeader account={account} onLogout={logoutPortalAction} /><main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">{children}</main></div>;
}
