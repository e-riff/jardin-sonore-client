"use client";

import Image from "next/image";
import {useActionState, useEffect, useState} from "react";
import {useFormStatus} from "react-dom";
import {updatePortalProfileAction, type PortalProfileFormState} from "@/app/portail/actions";
import {usePortalToast} from "@/components/portal/PortalToastProvider";
import {portalAvatarUrl} from "@/lib/portal/types";
import type {Dictionary} from "@/i18n/types";
import {usePortalAccount} from "@/components/portal/PortalAccountProvider";

type AccountContent = Dictionary["portal"]["account"];

const initialState: PortalProfileFormState = {status: "idle"};
const MAX_AVATAR_SIZE_BYTES = 2_000_000;

function ProfileSubmitButton({content}: {content: AccountContent}): React.JSX.Element {
    const {pending} = useFormStatus();

    return <button className="rounded-lg bg-primary px-5 py-3 font-semibold text-white disabled:cursor-wait disabled:opacity-60" disabled={pending} type="submit">{pending ? content.saving : content.submit}</button>;
}

export default function PortalProfileForm({content}: {content: AccountContent}): React.JSX.Element {
    const [state, formAction] = useActionState(updatePortalProfileAction, initialState);
    const [avatarError, setAvatarError] = useState<string | null>(null);
    const {notify} = usePortalToast();
    const {account, setAccount} = usePortalAccount();

    useEffect(() => {
        if (state.status === "success") {
            if (state.account) setAccount(state.account);
            notify(content.saved, "success");
        }
        if (state.status === "error" && state.field !== "avatar") notify(content.error, "error");
    }, [content.error, content.saved, notify, setAccount, state]);

    const displayedAvatarError = avatarError ?? (state.field === "avatar" ? content.photoInvalid : null);

    const handleAvatarChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        const avatar = event.currentTarget.files?.item(0);
        setAvatarError(avatar && avatar.size > MAX_AVATAR_SIZE_BYTES ? content.photoTooLarge : null);
    };

    const preventInvalidAvatarSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        if (avatarError) event.preventDefault();
    };

    return <form action={formAction} className="portal-content-sheet mt-8 grid gap-5" onSubmit={preventInvalidAvatarSubmit}>
        <label className="grid gap-2 text-sm font-semibold">
            <span>{content.emailLabel}</span>
            <input className="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface-variant" defaultValue={account.email} readOnly />
        </label>
        <label className="grid gap-2 text-sm font-semibold">
            <span>{account.organizations.length > 1 ? content.organizationPlural : content.organizationSingle}</span>
            <input className="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface-variant" defaultValue={account.organizations.map((organization) => organization.name).join(", ")} readOnly />
        </label>
        {account.avatarPath ? <div className="grid gap-2 text-sm font-semibold"><span>{content.currentPhoto}</span><Image alt={content.currentPhotoAlt} className="h-16 w-16 rounded-full border border-outline-variant object-cover" height={64} src={portalAvatarUrl(account.avatarPath)} unoptimized width={64} /></div> : null}
        <label className="grid gap-2 text-sm font-semibold">
            <span>{content.photoLabel} <span className="font-normal text-on-surface-variant">({content.optional})</span></span>
            <input accept="image/jpeg,image/png,image/webp" aria-describedby={displayedAvatarError ? "portal-avatar-error" : undefined} aria-invalid={Boolean(displayedAvatarError)} className="block w-full text-sm text-on-surface-variant file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:font-semibold file:text-primary" name="avatar" onChange={handleAvatarChange} type="file" />
            <span className="text-xs font-normal text-on-surface-variant">{content.photoHint}</span>
            {displayedAvatarError ? <span className="text-sm font-normal text-primary" id="portal-avatar-error" role="alert">{displayedAvatarError}</span> : null}
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
