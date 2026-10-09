import assert from "node:assert/strict";
import test from "node:test";
import {pbkdf2, solveChallenge} from "altcha/lib";
import {createAltchaChallenge} from "../src/lib/altcha.ts";
import {handleContactRequest} from "../src/lib/contact/request-handler.ts";

const request = (body: object | string, origin = "http://localhost:3000"): Request => new Request("http://localhost:3000/api/contact", {
    method: "POST",
    headers: {origin, "Content-Type": "application/json"},
    body: typeof body === "string" ? body : JSON.stringify(body),
});

const solvedAltcha = async (): Promise<string> => {
    const challenge = await createAltchaChallenge();
    const solution = await solveChallenge({challenge, deriveKey: pbkdf2.deriveKey, timeout: 10_000});
    assert.ok(solution);
    return Buffer.from(JSON.stringify({challenge, solution})).toString("base64");
};

const payload = (altcha: string): object => ({
    name: "Claire Martin",
    email: "claire@example.test",
    message: "Bonjour",
    organization: "Crèche des Lilas",
    city: "Mornant",
    phone: "06 11 22 33 44",
    submissionKey: "a3f21d91-5287-4f63-a8ea-19f63a887e4b",
    altcha,
});

test("foreign origin and invalid captcha never reach the backend", async () => {
    const originalFetch = globalThis.fetch;
    let calls = 0;
    globalThis.fetch = async () => {
        calls++;
        return Response.json({requestId: 1}, {status: 202});
    };
    try {
        assert.equal((await handleContactRequest(request("{", "https://foreign.example.test"))).status, 403);
        assert.equal((await handleContactRequest(request(payload("invalid")))).status, 403);
        assert.equal(calls, 0);
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("valid contact reaches the backend with its stable submission key", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    const originalBaseUrl = process.env.PORTAL_API_BASE_URL;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    process.env.PORTAL_API_BASE_URL = "https://backend.example.test";
    const calls: Array<{url: string; init?: RequestInit}> = [];
    globalThis.fetch = async (url, init) => {
        calls.push({url: String(url), init});
        return Response.json({requestId: 42}, {status: 202});
    };
    try {
        const response = await handleContactRequest(request(payload(await solvedAltcha())));
        assert.equal(response.status, 200);
        assert.deepEqual(await response.json(), {ok: true});
        assert.equal(calls.length, 1);
        assert.equal(calls[0].url, "https://backend.example.test/api/commercial/contact-requests");
        assert.equal(new Headers(calls[0].init?.headers).get("X-Portal-Bff-Secret"), "test-only-secret");
        assert.equal(calls[0].init?.cache, "no-store");
        const body = JSON.parse(String(calls[0].init?.body));
        assert.equal(body.submissionKey, "a3f21d91-5287-4f63-a8ea-19f63a887e4b");
        assert.equal(body.organizationName, "Crèche des Lilas");
        assert.equal(body.altcha, undefined);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
        if (originalBaseUrl === undefined) delete process.env.PORTAL_API_BASE_URL;
        else process.env.PORTAL_API_BASE_URL = originalBaseUrl;
    }
});

test("contact requests from an already open form without a submission key remain accepted", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    const originalBaseUrl = process.env.PORTAL_API_BASE_URL;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    process.env.PORTAL_API_BASE_URL = "https://backend.example.test";
    const submittedBodies: Array<Record<string, unknown>> = [];
    globalThis.fetch = async (_url, init) => {
        submittedBodies.push(JSON.parse(String(init?.body)) as Record<string, unknown>);
        return Response.json({requestId: 42}, {status: 202});
    };
    try {
        const formPayload = payload(await solvedAltcha()) as Record<string, unknown>;
        delete formPayload.submissionKey;

        const response = await handleContactRequest(request(formPayload));

        assert.equal(response.status, 200);
        assert.equal(submittedBodies.length, 1);
        assert.match(String(submittedBodies[0].submissionKey), /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
        if (originalBaseUrl === undefined) delete process.env.PORTAL_API_BASE_URL;
        else process.env.PORTAL_API_BASE_URL = originalBaseUrl;
    }
});

test("backend failure does not announce a successful contact request", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    const originalBaseUrl = process.env.PORTAL_API_BASE_URL;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    process.env.PORTAL_API_BASE_URL = "https://backend.example.test";
    globalThis.fetch = async () => new Response("Backend unavailable", {status: 503});
    try {
        const response = await handleContactRequest(request(payload(await solvedAltcha())));
        assert.equal(response.status, 502);
        assert.deepEqual(await response.json(), {error: "Unable to process contact request"});
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
        if (originalBaseUrl === undefined) delete process.env.PORTAL_API_BASE_URL;
        else process.env.PORTAL_API_BASE_URL = originalBaseUrl;
    }
});
