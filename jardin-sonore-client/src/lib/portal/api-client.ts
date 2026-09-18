import "server-only";
import {isIP} from "node:net";
import {headers as requestHeaders} from "next/headers";

export interface PortalOrganization {
    uuid: string;
    name: string;
}

export interface PortalAccount {
    email: string;
    organizations: PortalOrganization[];
}

export interface PortalLoginData {
    email: string;
    password: string;
}

export interface PortalPasswordData {
    password: string;
}

export interface PortalTokenResponse {
    token: string;
}

export interface PortalApiResult<T> {
    data: T | null;
    response: Response;
}

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
    public constructor(
        private readonly accessToken?: string,
        private readonly portalApiBaseUrl: string = getPortalApiBaseUrl(),
        private readonly clientIp: string | null = null,
    ) {
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
        const response = await this.request("/api/portal/me", {}, true);

        return {data: await readJson<PortalAccount>(response), response};
    }

    public document(uuid: string): Promise<Response> {
        return this.request(`/api/portal/sessions/${encodeURIComponent(uuid)}/document.pdf`, {}, true);
    }

    private async jsonRequest<T>(path: string, data: unknown): Promise<PortalApiResult<T>> {
        const response = await this.request(path, {
            body: JSON.stringify(data),
            headers: {"content-type": "application/json"},
            method: "POST",
        });

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
