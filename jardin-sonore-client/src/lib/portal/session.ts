import "server-only";
import {cookies} from "next/headers";
import {redirect} from "next/navigation";
import {PortalAccount, PortalApiClient, PortalApiUnavailableError} from "./api-client";
import {
    expirePortalSession,
    PORTAL_SESSION_COOKIE_NAME,
    PortalSessionOptions,
    writePortalSession,
} from "./cookie-options";

export {PORTAL_SESSION_COOKIE_NAME, PORTAL_SESSION_MAX_AGE_SECONDS, writePortalSession} from "./cookie-options";

export const setPortalSession = async (token: string, options: PortalSessionOptions = {}): Promise<void> => {
    writePortalSession(await cookies(), token, options);
};

export const clearPortalSession = async (): Promise<void> => {
    expirePortalSession(await cookies());
};

export const getPortalAccessToken = async (): Promise<string | null> => {
    const portalSessionCookie = (await cookies()).get(PORTAL_SESSION_COOKIE_NAME);

    return portalSessionCookie?.value ?? null;
};

export const getPortalSession = async (): Promise<PortalAccount> => {
    const accessToken = await getPortalAccessToken();

    if (!accessToken) {
        redirect("/portail/connexion");
    }

    let data: PortalAccount | null;
    let response: Response;
    try {
        ({data, response} = await (await PortalApiClient.fromCurrentRequest(accessToken)).me());
    } catch (error) {
        if (error instanceof PortalApiUnavailableError) {
            redirect("/portail/indisponible");
        }

        throw error;
    }

    if (response.status === 401) {
        redirect("/portail/session-invalide");
    }

    if (!response.ok || !data) {
        throw new Error("The portal account response is unavailable.");
    }

    return data;
};
