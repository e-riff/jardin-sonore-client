"use client";

import {useEffect, useState} from "react";
import Link from "next/link";
import {useTranslations} from "@/i18n/translations-provider";
import type {NewsletterConfirmationState} from "@/lib/newsletter/api-client";

export default function NewsletterConfirmationPanel({linkIsValid}: {
    linkIsValid: boolean;
}): React.JSX.Element {
    const content = useTranslations().newsletter.confirmation;
    const [state, setState] = useState<NewsletterConfirmationState | "checking">("checking");
    const [token, setToken] = useState("");
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        const rawToken = window.location.hash.slice(1);
        window.history.replaceState(null, "", window.location.pathname);
        const timeoutId = window.setTimeout(() => {
            if (linkIsValid && /^[a-f0-9]{64}$/.test(rawToken)) {
                setToken(rawToken);
                setState("ready");
            } else {
                setState("unavailable");
            }
        }, 0);

        return () => window.clearTimeout(timeoutId);
    }, [linkIsValid]);

    async function confirm(): Promise<void> {
        if (pending) return;
        setPending(true);
        setError(false);
        try {
            const response = await fetch("/api/newsletter/confirmations/confirmation", {
                method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify({token}),
            });
            if (!response.ok) throw new Error("Confirmation unavailable.");
            const data: {state: NewsletterConfirmationState} = await response.json();
            setState(data.state);
        } catch {
            setError(true);
        } finally {
            setPending(false);
        }
    }

    return <div aria-busy={pending}>
        <p className="mt-4 leading-7 text-on-surface-variant" role="status" aria-live="polite">
            {error ? content.error : state === "checking" ? content.loading : state === "ready" ? content.description : state === "confirmed" ? content.confirmed : state === "consumed" ? content.consumed : content.unavailable}
        </p>
        {state === "ready" && token && <button onClick={confirm} disabled={pending} className="mt-6 min-h-11 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:opacity-60">
            {pending ? content.confirming : error ? content.retry : content.submit}
        </button>}
        <Link href="/#newsletter" className="mt-6 block text-sm font-semibold text-primary underline underline-offset-4">{content.return}</Link>
    </div>;
}
