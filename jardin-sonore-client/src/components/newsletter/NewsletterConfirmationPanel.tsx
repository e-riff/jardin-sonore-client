"use client";

import {useState} from "react";
import Link from "next/link";
import {useTranslations} from "@/i18n/translations-provider";
import type {NewsletterConfirmationState} from "@/lib/newsletter/api-client";

export default function NewsletterConfirmationPanel({token, initialState, serviceUnavailable = false}: {
    token: string; initialState: NewsletterConfirmationState; serviceUnavailable?: boolean;
}): React.JSX.Element {
    const content = useTranslations().newsletter.confirmation;
    const [state, setState] = useState(initialState);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(serviceUnavailable);

    async function confirm(): Promise<void> {
        if (pending) return;
        setPending(true);
        setError(false);
        try {
            const response = await fetch(`/api/newsletter/confirmations/${encodeURIComponent(token)}`, {method: "POST"});
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
            {error ? content.error : state === "ready" ? content.description : state === "confirmed" ? content.confirmed : state === "consumed" ? content.consumed : content.unavailable}
        </p>
        {state === "ready" && <button onClick={confirm} disabled={pending} className="mt-6 min-h-11 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:opacity-60">
            {pending ? content.confirming : content.submit}
        </button>}
        {serviceUnavailable && state !== "ready" && <button onClick={() => window.location.reload()} className="mt-6 min-h-11 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary">{content.retry}</button>}
        <Link href="/#newsletter" className="mt-6 block text-sm font-semibold text-primary underline underline-offset-4">{content.return}</Link>
    </div>;
}
