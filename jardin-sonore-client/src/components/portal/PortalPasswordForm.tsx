"use client";

import {useActionState, JSX} from "react";
import {definePortalPasswordAction, idlePortalFormState} from "@/app/portail/actions";
import {useTranslations} from "@/i18n/translations-provider";

interface PortalPasswordFormProps {
    token: string;
}

export default function PortalPasswordForm({token}: PortalPasswordFormProps): JSX.Element {
    const [state, formAction, pending] = useActionState(definePortalPasswordAction, idlePortalFormState);
    const content = useTranslations().portal.password;

    if (state.status === "unavailable") {
        return <p className="rounded-2xl bg-surface-container px-5 py-4 font-sans text-sm leading-6 text-on-surface-variant" role="alert">{content.unavailable}</p>;
    }

    return (
        <form action={formAction} className="grid gap-5">
            <input name="token" type="hidden" value={token} />
            <label className="grid gap-2 font-sans text-sm font-bold text-on-surface-variant">
                <span>{content.passwordLabel}</span>
                <input className="rounded-xl border border-outline-variant bg-surface px-4 py-3 text-on-surface" name="password" type="password" autoComplete="new-password" minLength={12} required />
            </label>
            <p className="font-sans text-sm leading-6 text-on-surface-variant">{content.hint}</p>
            {state.status === "error" && <p className="rounded-xl bg-primary-fixed px-4 py-3 text-sm text-on-primary-fixed-variant" role="alert">{content.error}</p>}
            <button className="rounded-full bg-primary px-6 py-3 font-sans text-sm font-bold tracking-wide text-on-primary transition hover:bg-primary-container disabled:cursor-wait disabled:opacity-70" type="submit" disabled={pending}>
                {pending ? content.submitting : content.submit}
            </button>
        </form>
    );
}
