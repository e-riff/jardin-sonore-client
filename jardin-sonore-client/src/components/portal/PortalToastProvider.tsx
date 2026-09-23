"use client";

import {createContext, type ReactNode, useCallback, useContext, useRef, useState} from "react";
import {useTranslations} from "@/i18n/translations-provider";

type PortalToastTone = "success" | "error" | "info";

interface PortalToast {
    id: number;
    message: string;
    tone: PortalToastTone;
}

interface PortalToastContextValue {
    notify: (message: string, tone: PortalToastTone) => void;
}

const PortalToastContext = createContext<PortalToastContextValue | null>(null);

const toneClasses: Record<PortalToastTone, string> = {
    success: "border-secondary/35 bg-secondary-container text-on-secondary-container",
    error: "border-primary/35 bg-primary-fixed text-primary",
    info: "border-outline-variant bg-surface-container-high text-on-surface",
};

export function PortalToastProvider({children}: {children: ReactNode}): React.JSX.Element {
    const [toasts, setToasts] = useState<PortalToast[]>([]);
    const nextToastId = useRef(0);
    const close = useCallback((id: number) => setToasts((currentToasts) => currentToasts.filter((toast) => toast.id !== id)), []);
    const notify = useCallback((message: string, tone: PortalToastTone) => {
        const id = nextToastId.current++;
        setToasts((currentToasts) => [...currentToasts, {id, message, tone}]);
        window.setTimeout(() => close(id), 6000);
    }, [close]);

    return <PortalToastContext.Provider value={{notify}}>{children}<PortalToastRegion close={close} toasts={toasts} /></PortalToastContext.Provider>;
}

function PortalToastRegion({toasts, close}: {toasts: PortalToast[]; close: (id: number) => void}): React.JSX.Element {
    const content = useTranslations().portal.notifications;

    return <div aria-live="polite" className="pointer-events-none fixed inset-x-4 bottom-5 z-50 flex flex-col items-center gap-3 sm:inset-x-auto sm:right-6 sm:items-end">
        {toasts.map((toast) => <div className={`pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg ${toneClasses[toast.tone]}`} key={toast.id} role={toast.tone === "error" ? "alert" : "status"} style={{width: "min(24rem, calc(100vw - 2rem))"}}>
            <p className="flex-1 text-sm font-semibold leading-5">{toast.message}</p>
            <button aria-label={content.close} className="-mr-1 -mt-1 grid h-7 w-7 shrink-0 place-items-center rounded-full text-lg leading-none transition hover:bg-black/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current" onClick={() => close(toast.id)} type="button">×</button>
        </div>)}
    </div>;
}

export function usePortalToast(): PortalToastContextValue {
    const context = useContext(PortalToastContext);
    if (!context) throw new Error("usePortalToast must be used inside PortalToastProvider.");

    return context;
}
