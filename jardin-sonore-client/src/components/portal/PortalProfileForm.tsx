"use client";

import Image from "next/image";
import {useActionState, useEffect} from "react";
import {useFormStatus} from "react-dom";
import {useRouter} from "next/navigation";
import {updatePortalProfileAction, type PortalProfileFormState} from "@/app/portail/actions";
import type {PortalAccount} from "@/lib/portal/types";
import type {Dictionary} from "@/i18n/types";

type AccountContent = Dictionary["portal"]["account"];

const initialState: PortalProfileFormState = {status: "idle"};

function ProfileSubmitButton({content}: {content: AccountContent}): React.JSX.Element {
    const {pending} = useFormStatus();

    return <button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:cursor-wait disabled:opacity-60" disabled={pending} type="submit">{pending ? content.saving : content.submit}</button>;
}

export default function PortalProfileForm({account, content}: {account: PortalAccount; content: AccountContent}): React.JSX.Element {
    const [state, formAction] = useActionState(updatePortalProfileAction, initialState);
    const router = useRouter();

    useEffect(() => {
        if (state.status === "success") router.refresh();
    }, [router, state.status]);

    return <form action={formAction} className="portal-content-sheet mt-8 grid gap-5">
        {state.status === "success" && <p aria-live="polite" className="rounded-lg bg-secondary-container p-4 text-on-secondary-container">{content.saved}</p>}
        {state.status === "error" && <p aria-live="assertive" className="rounded-lg bg-primary-fixed p-4 text-primary">{content.error}</p>}
        <label className="grid gap-2 text-sm font-semibold">
            <span>{content.emailLabel}</span>
            <input className="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface-variant" defaultValue={account.email} readOnly />
        </label>
        <label className="grid gap-2 text-sm font-semibold">
            <span>{account.organizations.length > 1 ? content.organizationPlural : content.organizationSingle}</span>
            <input className="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface-variant" defaultValue={account.organizations.map((organization) => organization.name).join(", ")} readOnly />
        </label>
        {account.avatarPath ? <div className="grid gap-2 text-sm font-semibold"><span>{content.currentPhoto}</span><Image alt={content.currentPhotoAlt} className="h-16 w-16 rounded-full border border-outline-variant object-cover" height={64} src="/portail/avatar" unoptimized width={64} /></div> : null}
        <label className="grid gap-2 text-sm font-semibold">
            <span>{content.photoLabel} <span className="font-normal text-on-surface-variant">({content.optional})</span></span>
            <input accept="image/jpeg,image/png,image/webp" className="block w-full text-sm text-on-surface-variant file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:font-semibold file:text-primary" name="avatar" type="file" />
            <span className="text-xs font-normal text-on-surface-variant">{content.photoHint}</span>
        </label>
        <label className="grid gap-2 text-sm font-semibold"><span>{content.firstNameLabel}</span><input className="rounded-lg border border-outline-variant px-4 py-3" defaultValue={account.firstName ?? ""} name="firstName" autoComplete="given-name" /></label>
        <label className="grid gap-2 text-sm font-semibold"><span>{content.lastNameLabel}</span><input className="rounded-lg border border-outline-variant px-4 py-3" defaultValue={account.lastName ?? ""} name="lastName" autoComplete="family-name" /></label>
        <label className="flex items-start gap-3 rounded-lg border border-outline-variant bg-surface-container-low p-4 text-sm">
            <input className="mt-0.5 h-4 w-4 accent-primary" defaultChecked={account.newSessionNotificationsEnabled} name="newSessionNotificationsEnabled" type="checkbox" />
            <span><span className="block font-semibold">{content.notificationsTitle}</span><span className="mt-1 block text-on-surface-variant">{content.notificationsDescription}</span></span>
        </label>
        <ProfileSubmitButton content={content} />
    </form>;
}
