import {isIP} from "node:net";
import {headers as requestHeaders} from "next/headers.js";
import type {
    PortalAccount,
    PortalApiResult,
    PortalLoginData,
    PortalPasswordData,
    PortalProfileData,
    PortalSessionDetail,
    PortalSessionListResponse,
    PortalTokenResponse,
} from "./types.ts";

export type {
    PortalAccount,
    PortalApiResult,
    PortalLoginData,
    PortalPasswordData,
    PortalProfileData,
    PortalSessionDetail,
    PortalSessionListResponse,
    PortalTokenResponse,
} from "./types.ts";

export class PortalApiUnavailableError extends Error {
    public constructor() {
        super("The portal API is unavailable.");
    }
}

const PORTAL_API_TIMEOUT_MS = 10_000;

export const portalClientIpFromHeaders = (headers: Headers): string | null => {
    const forwardedFor = headers.get("x-forwarded-for");
    const clientIp = forwardedFor?.split(",", 1)[0]?.trim() ?? null;

    return clientIp && 0 !== isIP(clientIp) ? clientIp : null;
};

const getPortalApiBaseUrl = (): string => {
    const portalApiBaseUrl = process.env.PORTAL_API_BASE_URL;

    if (!portalApiBaseUrl) {
        throw new Error("PORTAL_API_BASE_URL must be configured on the server.");
    }

    return portalApiBaseUrl.replace(/\/$/, "");
};

const readJson = async <T>(response: Response): Promise<T | null> => {
    const contentType = response.headers.get("content-type");

    if (!contentType?.includes("application/json")) {
        return null;
    }

    return response.json().catch((): null => null) as Promise<T | null>;
};

export class PortalApiClient {
    private readonly accessToken?: string;
    private readonly portalApiBaseUrl: string;
    private readonly clientIp: string | null;

    public constructor(
        accessToken?: string,
        portalApiBaseUrl: string = getPortalApiBaseUrl(),
        clientIp: string | null = null,
    ) {
        this.accessToken = accessToken;
        this.portalApiBaseUrl = portalApiBaseUrl;
        this.clientIp = clientIp;
    }

    public static async fromCurrentRequest(accessToken?: string): Promise<PortalApiClient> {
        return new PortalApiClient(accessToken, undefined, portalClientIpFromHeaders(await requestHeaders()));
    }

    public async login(data: PortalLoginData): Promise<PortalApiResult<PortalTokenResponse>> {
        return this.jsonRequest<PortalTokenResponse>("/api/portal/auth/login", data);
    }

    public async logout(): Promise<Response> {
        return this.request("/api/portal/auth/logout", {method: "POST"}, true);
    }

    public async requestPasswordReset(email: string): Promise<PortalApiResult<unknown>> {
        return this.jsonRequest<unknown>("/api/portal/auth/password-reset-requests", {email});
    }

    public async consumePasswordToken(token: string, data: PortalPasswordData): Promise<PortalApiResult<PortalTokenResponse>> {
        return this.jsonRequest<PortalTokenResponse>(`/api/portal/password-tokens/${encodeURIComponent(token)}/consume`, data);
    }

    public async me(): Promise<PortalApiResult<PortalAccount>> {
        return this.getJson<PortalAccount>("/api/portal/me");
    }

    public async updateProfile(data: PortalProfileData): Promise<PortalApiResult<PortalAccount>> {
        return this.jsonRequest<PortalAccount>("/api/portal/me/profile", data, "PATCH", true);
    }

    public async updateAvatar(avatar: File): Promise<PortalApiResult<PortalAccount>> {
        const formData = new FormData();
        formData.append("avatar", avatar);
        const response = await this.request("/api/portal/me/avatar", {body: formData, method: "POST"}, true);

        return {data: await readJson<PortalAccount>(response), response};
    }

    public avatar(): Promise<Response> {
        return this.request("/api/portal/me/avatar", {}, true);
    }

    public async sessions(organizationUuid?: string, page?: number): Promise<PortalApiResult<PortalSessionListResponse>> {
        const searchParams = new URLSearchParams();
        if (organizationUuid) {
            searchParams.set("organization", organizationUuid);
        }
        if (page) {
            searchParams.set("page", String(page));
        }
        const queryString = searchParams.toString();

        return this.getJson<PortalSessionListResponse>(`/api/portal/sessions${queryString ? `?${queryString}` : ""}`);
    }

    public async session(uuid: string): Promise<PortalApiResult<PortalSessionDetail>> {
        return this.getJson<PortalSessionDetail>(`/api/portal/sessions/${encodeURIComponent(uuid)}`);
    }

    public document(uuid: string): Promise<Response> {
        return this.request(`/api/portal/sessions/${encodeURIComponent(uuid)}/document.pdf`, {}, true);
    }

    private async getJson<T>(path: string): Promise<PortalApiResult<T>> {
        const response = await this.request(path, {}, true);

        return {data: await readJson<T>(response), response};
    }

    private async jsonRequest<T>(path: string, data: unknown, method = "POST", authenticated = false): Promise<PortalApiResult<T>> {
        const response = await this.request(path, {
            body: JSON.stringify(data),
            headers: {"content-type": "application/json"},
            method,
        }, authenticated);

        return {data: await readJson<T>(response), response};
    }

    private async request(path: string, init: RequestInit, authenticated = false): Promise<Response> {
        const headers = new Headers(init.headers);
        headers.set("accept", "application/json");
        const portalBffSharedSecret = process.env.PORTAL_BFF_SHARED_SECRET;

        if (this.clientIp && portalBffSharedSecret) {
            headers.set("x-portal-client-ip", this.clientIp);
            headers.set("x-portal-bff-secret", portalBffSharedSecret);
        }

        if (authenticated) {
            if (!this.accessToken) {
                throw new Error("An authenticated portal request requires a session token.");
            }

            headers.set("authorization", `Bearer ${this.accessToken}`);
        }

        try {
            return await fetch(`${this.portalApiBaseUrl}${path}`, {
                ...init,
                cache: "no-store",
                credentials: "omit",
                headers,
                signal: AbortSignal.timeout(PORTAL_API_TIMEOUT_MS),
            });
        } catch {
            throw new PortalApiUnavailableError();
        }
    }
}
