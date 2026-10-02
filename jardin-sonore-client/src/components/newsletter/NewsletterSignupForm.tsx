"use client";

import {type FormEvent, useId, useState} from "react";
import AltchaWidget from "@/components/AltchaWidget";
import {useTranslations} from "@/i18n/translations-provider";
import {showLegalPage} from "@/lib/legal-publication";

export default function NewsletterSignupForm(): React.JSX.Element {
    const content = useTranslations().newsletter.signup;
    const emailId = useId();
    const [pending, setPending] = useState(false);
    const [accepted, setAccepted] = useState(false);
    const [error, setError] = useState<"captcha" | "unavailable" | null>(null);
    const [captchaVersion, setCaptchaVersion] = useState(0);

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (pending) return;
        const formData = new FormData(event.currentTarget);
        setPending(true);
        setError(null);
        try {
            const response = await fetch("/api/newsletter/subscriptions", {
                method: "POST", headers: {"Content-Type": "application/json"},
                body: JSON.stringify({emailAddress: formData.get("emailAddress"), altcha: formData.get("altcha")}),
            });
            if (response.status === 202) setAccepted(true);
            else setError(response.status === 403 ? "captcha" : "unavailable");
        } catch {
            setError("unavailable");
        } finally {
            setPending(false);
            setCaptchaVersion(version => version + 1);
        }
    }

    return <section id="newsletter" className="mt-4 min-w-0 text-left" aria-labelledby={`${emailId}-title`}>
        <h2 id={`${emailId}-title`} className="sr-only">{content.title}</h2>
        {!accepted && <>
            <p className="text-sm text-on-surface-variant">{content.description}</p>
            <form className="mt-3 grid w-full max-w-[27rem] grid-cols-[minmax(0,20rem)_auto] items-center gap-2" onSubmit={submit} aria-busy={pending}>
                <input id={emailId} name="emailAddress" type="email" autoComplete="email" required maxLength={255}
                    aria-label={content.emailLabel} placeholder={content.emailLabel}
                    className="col-span-2 min-h-11 w-full rounded-lg border border-outline-variant bg-background px-3 py-2.5 text-on-surface placeholder:text-on-surface-variant focus:placeholder-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary" />
                <AltchaWidget key={captchaVersion} className="mt-0 w-full min-w-0 max-w-[20rem]" />
                <button type="submit" disabled={pending} className="min-h-11 whitespace-nowrap rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-60">
                    {pending ? content.sending : content.submit}
                </button>
            </form>
            <p className="mt-3 text-xs leading-5 text-on-surface-variant">
                {content.privacyNotice} {showLegalPage ? <a className="font-semibold text-primary-container underline underline-offset-2 hover:text-primary-container" href="/mentions-legales#confidentialite">{content.privacyLink}</a> : null}
            </p>
        </>}
        <p role="status" aria-live="polite" className="mt-2 text-sm leading-6 text-on-surface-variant">
            {accepted ? content.accepted : error === "captcha" ? content.captchaError : error === "unavailable" ? content.error : ""}
        </p>
    </section>;
}
