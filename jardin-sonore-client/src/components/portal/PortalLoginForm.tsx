"use client";

import {useActionState, JSX} from "react";
import {idlePortalFormState, loginPortalAction} from "@/app/portail/actions";
import {useTranslations} from "@/i18n/translations-provider";

export default function PortalLoginForm(): JSX.Element {
    const [state, formAction, pending] = useActionState(loginPortalAction, idlePortalFormState);
    const content = useTranslations().portal.login;

    return (
        <form action={formAction} className="grid gap-5">
            <label className="grid gap-2 font-sans text-sm font-bold text-on-surface-variant">
                <span>{content.emailLabel}</span>
                <input className="rounded-xl border border-outline-variant bg-surface px-4 py-3 text-on-surface" name="email" type="email" autoComplete="email" required />
            </label>
            <label className="grid gap-2 font-sans text-sm font-bold text-on-surface-variant">
                <span>{content.passwordLabel}</span>
                <input className="rounded-xl border border-outline-variant bg-surface px-4 py-3 text-on-surface" name="password" type="password" autoComplete="current-password" required />
            </label>
            {state.status === "error" && <p className="rounded-xl bg-primary-fixed px-4 py-3 text-sm text-on-primary-fixed-variant" role="alert">{content.error}</p>}
            <button className="rounded-full bg-primary px-6 py-3 font-sans text-sm font-bold tracking-wide text-on-primary transition hover:bg-primary-container disabled:cursor-wait disabled:opacity-70" type="submit" disabled={pending}>
                {pending ? content.submitting : content.submit}
            </button>
        </form>
    );
}
