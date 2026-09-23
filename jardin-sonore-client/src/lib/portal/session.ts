import "server-only";
import {cookies} from "next/headers";
import {redirect} from "next/navigation";
import type {PortalAccount} from "./types";
import {PortalApiClient, PortalApiUnavailableError} from "./api-client";
import {expirePortalSession, PORTAL_SESSION_COOKIE_NAME, writePortalSession} from "./cookie-options";
import {portalRoutes} from "./routes";

export const getPortalAccessToken = async (): Promise<string | null> => (await cookies()).get(PORTAL_SESSION_COOKIE_NAME)?.value ?? null;
export const setPortalSession = async (token: string): Promise<void> => writePortalSession(await cookies(), token);
export const clearPortalSession = async (): Promise<void> => expirePortalSession(await cookies());
export const getPortalSession = async (): Promise<PortalAccount> => {
    const token = await getPortalAccessToken();
    if (!token) redirect(portalRoutes.login);
    try {
        const result = await (await PortalApiClient.fromCurrentRequest(token)).me();
        if (result.response.status === 401) redirect(portalRoutes.sessionInvalid);
        if (!result.response.ok || !result.data) redirect(portalRoutes.unavailable);
        return result.data;
    } catch (error) {
        if (error instanceof PortalApiUnavailableError) redirect(portalRoutes.unavailable);
        throw error;
    }
};
