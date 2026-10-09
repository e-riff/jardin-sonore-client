import {verifyAltchaPayload} from "../altcha.ts";
import {isAllowedRequestOrigin} from "../request-origin.ts";
import {CommercialContactApiClient, type ContactSubmission} from "./api-client.ts";
import {randomUUID} from "node:crypto";

const jsonResponse = (data: object, status: number): Response => Response.json(data, {status, headers: {"Cache-Control": "no-store"}});
const trimValue = (value: unknown): string => typeof value === "string" ? value.trim() : "";
const isValidEmail = (value: string): boolean => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
const isUuid = (value: string): boolean => /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(value);

interface ContactBody {
    altcha?: unknown;
    city?: unknown;
    email?: unknown;
    message?: unknown;
    name?: unknown;
    organization?: unknown;
    phone?: unknown;
    submissionKey?: unknown;
}

export async function handleContactRequest(request: Request): Promise<Response> {
    if (!isAllowedRequestOrigin(request)) return jsonResponse({error: "Forbidden"}, 403);

    let body: ContactBody;
    try {
        const text = await request.text();
        if (text.length > 16_384) return jsonResponse({error: "Invalid contact data"}, 400);
        const parsed: unknown = JSON.parse(text);
        if (typeof parsed !== "object" || parsed === null) return jsonResponse({error: "Invalid contact data"}, 400);
        body = parsed as ContactBody;
    } catch {
        return jsonResponse({error: "Invalid contact data"}, 400);
    }

    const submission: ContactSubmission = {
        name: trimValue(body.name),
        emailAddress: trimValue(body.email).toLowerCase(),
        message: trimValue(body.message),
        organizationName: trimValue(body.organization),
        city: trimValue(body.city),
        phone: trimValue(body.phone),
        submissionKey: trimValue(body.submissionKey) || randomUUID(),
    };
    if (!submission.name || submission.name.length > 255 || !isValidEmail(submission.emailAddress) || submission.emailAddress.length > 255 ||
        !submission.message || submission.message.length > 10_000 || !isUuid(submission.submissionKey) ||
        submission.organizationName.length > 255 || submission.city.length > 255 || submission.phone.length > 64) {
        return jsonResponse({error: "Invalid contact data"}, 400);
    }
    if (!await verifyAltchaPayload(typeof body.altcha === "string" ? body.altcha : null)) {
        return jsonResponse({error: "Invalid captcha"}, 403);
    }

    try {
        await new CommercialContactApiClient().record(submission);
        return jsonResponse({ok: true}, 200);
    } catch {
        return jsonResponse({error: "Unable to process contact request"}, 502);
    }
}
