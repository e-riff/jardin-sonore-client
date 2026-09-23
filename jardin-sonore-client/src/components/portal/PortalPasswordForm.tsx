"use client";
import {useActionState, useMemo, useState} from "react";
import {definePortalPasswordAction} from "@/app/portail/actions";
import type {PortalPasswordFormState} from "@/app/portail/actions";
import {useTranslations} from "@/i18n/translations-provider";

export default function PortalPasswordForm({token}: {token: string}): React.JSX.Element {
 const [password,setPassword]=useState(""); const [confirmation,setConfirmation]=useState(""); const initialState: PortalPasswordFormState={status:"idle"}; const [state,formAction,pending]=useActionState(definePortalPasswordAction,initialState); const c=useTranslations().portal.password;
 const checks=useMemo(()=>({length:password.length>=12, lower:/[a-z]/.test(password), upper:/[A-Z]/.test(password), digit:/\d/.test(password), same:password.length>0&&password===confirmation}),[password,confirmation]); const valid=Object.values(checks).every(Boolean);
 if(state.status==="unavailable") return <p role="alert">{c.unavailable}</p>;
 return <form action={formAction} className="grid gap-4"><input name="token" type="hidden" value={token}/><label className="grid gap-2 text-sm font-semibold"><span>{c.passwordLabel}</span><input className="rounded-lg border border-outline-variant px-4 py-3" name="password" type="password" autoComplete="new-password" value={password} onChange={e=>setPassword(e.target.value)} required/></label><ul className="text-sm" aria-live="polite"><li>{checks.length?"✓":"○"} {c.minimumLength}</li><li>{checks.lower?"✓":"○"} {c.lowercase}</li><li>{checks.upper?"✓":"○"} {c.uppercase}</li><li>{checks.digit?"✓":"○"} {c.digit}</li></ul><label className="grid gap-2 text-sm font-semibold"><span>{c.confirmationLabel}</span><input className="rounded-lg border border-outline-variant px-4 py-3" type="password" autoComplete="new-password" value={confirmation} onChange={e=>setConfirmation(e.target.value)} required/></label>{confirmation&& !checks.same&&<p className="text-sm text-red-700" role="alert">{c.mismatch}</p>}{state.status==="error"&&<p role="alert">{c.error}</p>}<button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:opacity-50" disabled={!valid||pending} type="submit">{c.submit}</button></form>;
}
