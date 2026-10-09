export interface ContactSubmission {
    name: string;
    emailAddress: string;
    message: string;
    organizationName: string;
    city: string;
    phone: string;
    submissionKey: string;
}

export class CommercialContactApiClient {
    private readonly baseUrl: string;

    constructor(baseUrl = process.env.PORTAL_API_BASE_URL ?? "") {
        this.baseUrl = baseUrl.replace(/\/+$/, "");
    }

    async record(submission: ContactSubmission): Promise<number> {
        const secret = process.env.PORTAL_BFF_SHARED_SECRET;
        if (!this.baseUrl || !secret) throw new Error("Commercial contact backend is not configured.");

        let response: Response;
        try {
            response = await fetch(`${this.baseUrl}/api/commercial/contact-requests`, {
                method: "POST",
                headers: {Accept: "application/json", "Content-Type": "application/json", "X-Portal-Bff-Secret": secret},
                body: JSON.stringify(submission),
                cache: "no-store",
                credentials: "omit",
                redirect: "error",
                signal: AbortSignal.timeout(10_000),
            });
        } catch {
            throw new Error("Commercial contact backend is unavailable.");
        }

        if (response.status !== 202) throw new Error("Commercial contact backend rejected the request.");
        const result: unknown = await response.json().catch(() => null);
        if (typeof result !== "object" || result === null || !("requestId" in result) || !Number.isSafeInteger(result.requestId) || Number(result.requestId) < 1) {
            throw new Error("Commercial contact backend returned an invalid receipt.");
        }

        return Number(result.requestId);
    }
}
