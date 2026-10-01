export type NewsletterConfirmationState = "ready" | "confirmed" | "consumed" | "unavailable";

export class NewsletterApiUnavailableError extends Error {
    readonly status: number;
    constructor(status = 503) {
        super("Newsletter service unavailable.");
        this.status = status;
    }
}

export class NewsletterApiClient {
    private readonly baseUrl: string;

    constructor(baseUrl = process.env.PORTAL_API_BASE_URL ?? "") {
        this.baseUrl = baseUrl.replace(/\/+$/, "");
    }

    async requestSubscription(emailAddress: string, clientIp?: string): Promise<{status: number}> {
        const response = await this.request("/subscription-requests", "POST", {emailAddress}, clientIp);
        if (response.status !== 202) throw new NewsletterApiUnavailableError();
        return {status: response.status};
    }

    async confirmationState(token: string): Promise<NewsletterConfirmationState> {
        return this.readState(await this.request(`/confirmations/${encodeURIComponent(token)}`, "GET"));
    }

    async confirm(token: string): Promise<NewsletterConfirmationState> {
        return this.readState(await this.request(`/confirmations/${encodeURIComponent(token)}`, "POST"));
    }

    private async readState(response: Response): Promise<NewsletterConfirmationState> {
        const data: unknown = await response.json().catch(() => null);
        if (typeof data !== "object" || data === null || !("state" in data) ||
            !["ready", "confirmed", "consumed", "unavailable"].includes(String(data.state))) {
            throw new NewsletterApiUnavailableError();
        }
        return data.state as NewsletterConfirmationState;
    }

    private async request(path: string, method: string, body?: {emailAddress: string}, clientIp?: string): Promise<Response> {
        const secret = process.env.PORTAL_BFF_SHARED_SECRET;
        if (!this.baseUrl || !secret) throw new NewsletterApiUnavailableError();
        const headers = new Headers({Accept: "application/json", "X-Portal-Bff-Secret": secret});
        if (clientIp) headers.set("X-Portal-Client-IP", clientIp);
        if (body) headers.set("Content-Type", "application/json");
        let response: Response;
        try {
            response = await fetch(`${this.baseUrl}/api/newsletter${path}`, {
                method, headers, body: body ? JSON.stringify(body) : undefined,
                cache: "no-store", credentials: "omit", redirect: "error", signal: AbortSignal.timeout(10_000),
            });
        } catch {
            throw new NewsletterApiUnavailableError();
        }
        if (!response.ok) throw new NewsletterApiUnavailableError(response.status === 429 ? 429 : 503);
        return response;
    }
}
