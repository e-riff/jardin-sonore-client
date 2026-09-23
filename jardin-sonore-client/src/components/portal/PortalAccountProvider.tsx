"use client";

import {createContext, type ReactNode, useContext, useState} from "react";
import type {PortalAccount} from "@/lib/portal/types";

interface PortalAccountContextValue {
    account: PortalAccount;
    setAccount: (account: PortalAccount) => void;
}

const PortalAccountContext = createContext<PortalAccountContextValue | null>(null);

export function PortalAccountProvider({account, children}: {account: PortalAccount; children: ReactNode}): React.JSX.Element {
    const [currentAccount, setCurrentAccount] = useState(account);

    return <PortalAccountContext.Provider value={{account: currentAccount, setAccount: setCurrentAccount}}>{children}</PortalAccountContext.Provider>;
}

export function usePortalAccount(): PortalAccountContextValue {
    const context = useContext(PortalAccountContext);
    if (!context) throw new Error("usePortalAccount must be used inside PortalAccountProvider.");

    return context;
}
