import {verifyAltchaPayload} from "../altcha.ts";
import {isAllowedRequestOrigin} from "../request-origin.ts";
import {portalClientIpFromHeaders} from "../portal/api-client-core.ts";
import {NewsletterApiClient, NewsletterApiUnavailableError} from "./api-client.ts";

const jsonResponse = (data: object, status: number): Response => Response.json(data, {status, headers: {"Cache-Control": "no-store"}});
const unavailable = (error: unknown): Response => jsonResponse({status: "unavailable"}, error instanceof NewsletterApiUnavailableError ? error.status : 503);

export async function handleNewsletterSubscription(request: Request): Promise<Response> {
    if (!isAllowedRequestOrigin(request)) return jsonResponse({status: "invalid"}, 403);
    let input: unknown;
    try {
        const text = await request.text();
        if (text.length > 16_384) return jsonResponse({status: "invalid"}, 400);
        input = JSON.parse(text);
    } catch {
        return jsonResponse({status: "invalid"}, 400);
    }
    if (typeof input !== "object" || input === null || !("emailAddress" in input) || typeof input.emailAddress !== "string") {
        return jsonResponse({status: "invalid"}, 400);
    }
    const emailAddress = input.emailAddress.trim().toLowerCase();
    if (emailAddress.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress)) return jsonResponse({status: "invalid"}, 400);
    const altcha = "altcha" in input && typeof input.altcha === "string" ? input.altcha : null;
    if (!await verifyAltchaPayload(altcha)) return jsonResponse({status: "captcha"}, 403);
    try {
        await new NewsletterApiClient().requestSubscription(emailAddress, portalClientIpFromHeaders(request.headers) ?? undefined);
        return jsonResponse({status: "accepted"}, 202);
    } catch (error) {
        return unavailable(error);
    }
}

export async function handleNewsletterConfirmation(request: Request): Promise<Response> {
    if (!isAllowedRequestOrigin(request)) return jsonResponse({status: "invalid"}, 403);
    let input: unknown;
    try {
        const text = await request.text();
        if (text.length > 1024) return jsonResponse({status: "invalid"}, 400);
        input = JSON.parse(text);
    } catch {
        return jsonResponse({status: "invalid"}, 400);
    }
    if (typeof input !== "object" || input === null || !("token" in input) || typeof input.token !== "string") return jsonResponse({status: "invalid"}, 400);
    const token = input.token;
    if (!/^[a-f0-9]{64}$/.test(token)) return jsonResponse({status: "invalid"}, 400);
    try {
        return jsonResponse({state: await new NewsletterApiClient().confirm(token)}, 200);
    } catch (error) {
        return unavailable(error);
    }
}
