"use client";

import {useActionState, useEffect, useRef} from "react";
import {changePortalPasswordAction, type PortalChangePasswordFormState} from "@/app/portail/actions";
import {useTranslations} from "@/i18n/translations-provider";

const initialState: PortalChangePasswordFormState = {status: "idle"};

export default function PortalChangePasswordForm(): React.JSX.Element {
    const content = useTranslations().portal.changePassword;
    const [state, formAction, pending] = useActionState(changePortalPasswordAction, initialState);
    const formRef = useRef<HTMLFormElement>(null);

    useEffect(() => {
        if (state.status === "success") formRef.current?.reset();
    }, [state]);

    const errorMessage = state.reason === "current" ? content.currentError
        : state.reason === "new" ? content.newError
            : state.reason === "mismatch" ? content.mismatch : content.error;

    return <form action={formAction} className="grid gap-5" ref={formRef}>
        <label className="grid gap-2 text-sm font-semibold" htmlFor="portal-current-password"><span>{content.currentLabel}</span><input autoComplete="current-password" className="rounded-lg border border-outline-variant px-4 py-3" id="portal-current-password" name="currentPassword" required type="password" /></label>
        <label className="grid gap-2 text-sm font-semibold" htmlFor="portal-new-password"><span>{content.newLabel}</span><input autoComplete="new-password" className="rounded-lg border border-outline-variant px-4 py-3" id="portal-new-password" minLength={12} name="newPassword" required type="password" /></label>
        <label className="grid gap-2 text-sm font-semibold" htmlFor="portal-confirm-password"><span>{content.confirmationLabel}</span><input autoComplete="new-password" className="rounded-lg border border-outline-variant px-4 py-3" id="portal-confirm-password" minLength={12} name="confirmation" required type="password" /></label>
        {state.status === "error" ? <p className="text-sm text-red-700" role="alert">{errorMessage}</p> : null}
        {state.status === "success" ? <p className="text-sm text-green-800" role="status">{content.saved}</p> : null}
        <button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:opacity-50" disabled={pending} type="submit">{content.submit}</button>
    </form>;
}
