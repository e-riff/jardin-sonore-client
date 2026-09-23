"use server";

import {redirect} from "next/navigation";
import {revalidatePath} from "next/cache";
import {PortalApiClient} from "@/lib/portal/api-client";
import {clearPortalSession, getPortalAccessToken, setPortalSession} from "@/lib/portal/session";
import {portalRoutes} from "@/lib/portal/routes";
import type {PortalAccount} from "@/lib/portal/types";

export async function logoutPortalAction(): Promise<void> {
    const token = await getPortalAccessToken();
    if (token) await (await PortalApiClient.fromCurrentRequest(token)).logout().catch((): undefined => undefined);
    await clearPortalSession();
    redirect("/");
}

export async function loginPortalAction(formData: FormData): Promise<void> {
    const email = formData.get("email");
    const password = formData.get("password");
    const result = await (await PortalApiClient.fromCurrentRequest()).login({
        email: typeof email === "string" ? email.trim() : "",
        password: typeof password === "string" ? password : "",
    });
    const token = result.data?.token;
    if (!result.response.ok || !token) {
        redirect(`${portalRoutes.login}?error=1`);
    }
    await setPortalSession(token);
    redirect(portalRoutes.sessions);
}

export async function requestPortalPasswordResetAction(formData: FormData): Promise<void> {
    const email = formData.get("email");
    try {
        await (await PortalApiClient.fromCurrentRequest()).requestPasswordReset(typeof email === "string" ? email.trim() : "");
    } catch {
        redirect(`${portalRoutes.passwordReset}?error=1`);
    }
    redirect(`${portalRoutes.passwordReset}?sent=1`);
}

export interface PortalProfileFormState { status: "idle" | "success" | "error"; field?: "avatar"; account?: PortalAccount; }

export async function updatePortalProfileAction(_previousState: PortalProfileFormState, formData: FormData): Promise<PortalProfileFormState> {
    const token = await getPortalAccessToken();
    if (!token) return {status: "error"};

    try {
        const portalApiClient = await PortalApiClient.fromCurrentRequest(token);
        const avatar = formData.get("avatar");
        if (avatar && typeof avatar !== "string" && avatar.size > 0) {
            if (avatar.size > 2_000_000) return {status: "error", field: "avatar"};
            const avatarResult = await portalApiClient.updateAvatar(avatar);
            if (!avatarResult.response.ok) return {status: "error", field: "avatar"};
        }
        const result = await portalApiClient.updateProfile({
            firstName: typeof formData.get("firstName") === "string" ? String(formData.get("firstName")).trim() : "",
            lastName: typeof formData.get("lastName") === "string" ? String(formData.get("lastName")).trim() : "",
            newSessionNotificationsEnabled: formData.get("newSessionNotificationsEnabled") === "on",
        });
        if (!result.response.ok || !result.data) return {status: "error"};
        revalidatePath("/portail", "layout");

        return {status: "success", account: result.data};
    } catch {
        return {status: "error"};
    }
}

export interface PortalPasswordFormState { status: "idle" | "error" | "unavailable"; }
export async function definePortalPasswordAction(_previousState: PortalPasswordFormState, formData: FormData): Promise<PortalPasswordFormState> {
    const token = formData.get("token"); const password = formData.get("password");
    try {
        const result = await (await PortalApiClient.fromCurrentRequest()).consumePasswordToken(typeof token === "string" ? token : "", {password: typeof password === "string" ? password : ""});
        if (result.response.status === 404) return {status: "unavailable"};
        if (!result.response.ok || !result.data?.token) return {status: "error"};
        await setPortalSession(result.data.token);
    } catch { return {status: "error"}; }
    redirect(portalRoutes.sessions);
}
