"use server";

import {redirect} from "next/navigation";
import {PortalApiClient} from "@/lib/portal/api-client";
import {clearPortalSession, getPortalAccessToken, setPortalSession} from "@/lib/portal/session";

export interface PortalFormState {
    status: "idle" | "error" | "reset-confirmed" | "unavailable";
}

const idlePortalFormState: PortalFormState = {status: "idle"};

const formValue = (formData: FormData, name: string): string => {
    const value = formData.get(name);

    return typeof value === "string" ? value.trim() : "";
};

const tokenFrom = (responseData: {token: string} | null): string | null => {
    const token = responseData?.token;

    return typeof token === "string" && token.length > 0 ? token : null;
};

export async function loginPortalAction(_previousState: PortalFormState, formData: FormData): Promise<PortalFormState> {
    let result;
    try {
        result = await (await PortalApiClient.fromCurrentRequest()).login({
            email: formValue(formData, "email"),
            password: formValue(formData, "password"),
        });
    } catch {
        return {status: "error"};
    }
    const token = tokenFrom(result.data);

    if (!result.response.ok || !token) {
        if (result.response.status === 401) {
            await clearPortalSession();
        }

        return {status: "error"};
    }

    await setPortalSession(token);
    redirect("/portail/seances");
}

export async function logoutPortalAction(): Promise<void> {
    const accessToken = await getPortalAccessToken();

    if (accessToken) {
        await (await PortalApiClient.fromCurrentRequest(accessToken)).logout().catch((): undefined => undefined);
    }

    await clearPortalSession();
    redirect("/portail/connexion");
}

export async function requestPortalPasswordResetAction(_previousState: PortalFormState, formData: FormData): Promise<PortalFormState> {
    let result;
    try {
        result = await (await PortalApiClient.fromCurrentRequest()).requestPasswordReset(formValue(formData, "email"));
    } catch {
        return {status: "error"};
    }

    if (result.response.status === 202) {
        return {status: "reset-confirmed"};
    }

    return {status: "error"};
}

export async function definePortalPasswordAction(_previousState: PortalFormState, formData: FormData): Promise<PortalFormState> {
    let result;
    try {
        result = await (await PortalApiClient.fromCurrentRequest()).consumePasswordToken(
            formValue(formData, "token"),
            {password: formValue(formData, "password")},
        );
    } catch {
        return {status: "error"};
    }
    const token = tokenFrom(result.data);

    if (result.response.status === 404) {
        return {status: "unavailable"};
    }

    if (!result.response.ok || !token) {
        return {status: "error"};
    }

    await setPortalSession(token);
    redirect("/portail/seances");
}

export {idlePortalFormState};
