import assert from "node:assert/strict";
import test from "node:test";
import {pbkdf2, solveChallenge} from "altcha/lib";
import {createAltchaChallenge} from "../src/lib/altcha.ts";
import {NewsletterApiClient} from "../src/lib/newsletter/api-client.ts";
import {handleNewsletterSubscription, handleNewsletterConfirmation} from "../src/lib/newsletter/request-handlers.ts";

const token = "a".repeat(64);

test("newsletter client sends secret only to backend and disables caching", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    const requests: Array<{url: string; init?: RequestInit}> = [];
    globalThis.fetch = async (url, init) => {
        requests.push({url: String(url), init});
        return Response.json({status: "accepted"}, {status: 202});
    };
    try {
        assert.equal((await new NewsletterApiClient("https://backend.example.test").requestSubscription("fixture@example.test", "192.0.2.1")).status, 202);
        assert.equal(requests[0].url, "https://backend.example.test/api/newsletter/subscription-requests");
        const headers = new Headers(requests[0].init?.headers);
        assert.equal(headers.get("X-Portal-Bff-Secret"), "test-only-secret");
        assert.equal(headers.get("X-Portal-Client-IP"), "192.0.2.1");
        assert.equal(headers.get("Authorization"), null);
        assert.equal(requests[0].init?.cache, "no-store");
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
    }
});

test("reading confirmation calls GET while confirming calls POST", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    const methods: string[] = [];
    globalThis.fetch = async (_url, init) => {
        methods.push(init?.method ?? "GET");
        return Response.json({state: init?.method === "POST" ? "confirmed" : "ready"});
    };
    try {
        const client = new NewsletterApiClient("https://backend.example.test");
        assert.equal(await client.confirmationState(token), "ready");
        assert.equal(await client.confirm(token), "confirmed");
        assert.deepEqual(methods, ["GET", "POST"]);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
    }
});

test("foreign origin is refused before subscription parsing or backend access", async () => {
    const response = await handleNewsletterSubscription(new Request("http://localhost:3000/api/newsletter/subscriptions", {
        method: "POST", headers: {origin: "https://foreign.example.test"}, body: "{",
    }));
    assert.equal(response.status, 403);
});

test("invalid captcha is refused without contacting backend", async () => {
    const response = await handleNewsletterSubscription(new Request("http://localhost:3000/api/newsletter/subscriptions", {
        method: "POST", headers: {origin: "http://localhost:3000", "Content-Type": "application/json"},
        body: JSON.stringify({emailAddress: "fixture@example.test", altcha: "invalid"}),
    }));
    assert.equal(response.status, 403);
});

test("malformed JSON and overlong addresses are rejected", async () => {
    for (const body of ["{", JSON.stringify({emailAddress: ["fixture@example.test"]}), JSON.stringify({emailAddress: "a".repeat(250) + "@example.test"})]) {
        const response = await handleNewsletterSubscription(new Request("http://localhost:3000/api/newsletter/subscriptions", {
            method: "POST", headers: {origin: "http://localhost:3000", "Content-Type": "application/json"}, body,
        }));
        assert.equal(response.status, 400);
    }
});

test("confirmation mutation refuses foreign origins and invalid tokens", async () => {
    assert.equal((await handleNewsletterConfirmation(new Request("http://localhost:3000/api/newsletter/confirmations/token", {
        method: "POST", headers: {origin: "https://foreign.example.test"},
    }), token)).status, 403);
    assert.equal((await handleNewsletterConfirmation(new Request("http://localhost:3000/api/newsletter/confirmations/token", {
        method: "POST", headers: {origin: "http://localhost:3000"},
    }), "invalid")).status, 400);
});

test("valid captcha permits one subscription and its replay never reaches backend", async () => {
    const challenge = await createAltchaChallenge();
    const solution = await solveChallenge({challenge, deriveKey: pbkdf2.deriveKey, timeout: 10_000});
    assert.ok(solution);
    const altcha = Buffer.from(JSON.stringify({challenge, solution})).toString("base64");
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    const originalBaseUrl = process.env.PORTAL_API_BASE_URL;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    process.env.PORTAL_API_BASE_URL = "https://backend.example.test";
    let backendRequests = 0;
    globalThis.fetch = async () => {
        backendRequests++;
        return Response.json({status: "accepted"}, {status: 202});
    };
    const request = () => new Request("http://localhost:3000/api/newsletter/subscriptions", {
        method: "POST", headers: {origin: "http://localhost:3000", "Content-Type": "application/json"},
        body: JSON.stringify({emailAddress: "fixture@example.test", altcha}),
    });
    try {
        assert.equal((await handleNewsletterSubscription(request())).status, 202);
        assert.equal(backendRequests, 1);
        assert.equal((await handleNewsletterSubscription(request())).status, 403);
        assert.equal(backendRequests, 1);
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
        if (originalBaseUrl === undefined) delete process.env.PORTAL_API_BASE_URL;
        else process.env.PORTAL_API_BASE_URL = originalBaseUrl;
    }
});

test("backend failures expose only a generic response and disable caching", async () => {
    const originalFetch = globalThis.fetch;
    const originalSecret = process.env.PORTAL_BFF_SHARED_SECRET;
    const originalBaseUrl = process.env.PORTAL_API_BASE_URL;
    process.env.PORTAL_BFF_SHARED_SECRET = "test-only-secret";
    process.env.PORTAL_API_BASE_URL = "https://backend.example.test";
    try {
        for (const status of [429, 500]) {
            globalThis.fetch = async () => new Response("private backend details", {status});
            const response = await handleNewsletterConfirmation(new Request("http://localhost:3000/api/newsletter/confirmations/token", {
                method: "POST", headers: {origin: "http://localhost:3000"},
            }), token);
            assert.equal(response.status, status === 429 ? 429 : 503);
            assert.equal(response.headers.get("Cache-Control"), "no-store");
            assert.deepEqual(await response.json(), {status: "unavailable"});
        }
    } finally {
        globalThis.fetch = originalFetch;
        if (originalSecret === undefined) delete process.env.PORTAL_BFF_SHARED_SECRET;
        else process.env.PORTAL_BFF_SHARED_SECRET = originalSecret;
        if (originalBaseUrl === undefined) delete process.env.PORTAL_API_BASE_URL;
        else process.env.PORTAL_API_BASE_URL = originalBaseUrl;
    }
});
