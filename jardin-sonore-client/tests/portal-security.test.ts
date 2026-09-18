import assert from "node:assert/strict";
import test from "node:test";
import {NextResponse} from "next/server";
import {PortalApiClient, PortalApiUnavailableError} from "../src/lib/portal/api-client";
import {expirePortalSession, writePortalSession} from "../src/lib/portal/cookie-options";

test("attaches the bearer token only to an authenticated server request", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});
        return Response.json({email: "structure@example.test", organizations: []});
    };

    try {
        await new PortalApiClient("opaque-access-token", "https://admin.example.test").me();
    } finally {
        globalThis.fetch = originalFetch;
    }

    assert.equal(requests.length, 1);
    assert.equal(requests[0].input, "https://admin.example.test/api/portal/me");
    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), "Bearer opaque-access-token");
    assert.equal(requests[0].init?.cache, "no-store");
});

test("does not attach a bearer token while submitting login credentials", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});
        return Response.json({token: "opaque-access-token"});
    };

    try {
        await new PortalApiClient(undefined, "https://admin.example.test").login({
            email: "structure@example.test",
            password: "Une phrase de passe solide",
        });
    } finally {
        globalThis.fetch = originalFetch;
    }

    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), null);
    assert.equal(requests[0].init?.method, "POST");
});

test("translates a portal API transport failure into an unavailable error", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (): Promise<Response> => {
        throw new TypeError("network unavailable");
    };

    try {
        await assert.rejects(
            new PortalApiClient(undefined, "https://admin.example.test").login({
                email: "structure@example.test",
                password: "Une phrase de passe solide",
            }),
            PortalApiUnavailableError,
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("writes a secure host-only cookie with the regular session lifetime", () => {
    const calls: Array<{name: string; value: string; options: object}> = [];

    writePortalSession({
        set(name, value, options): void {
            calls.push({name, value, options});
        },
    }, "opaque-access-token");

    assert.deepEqual(calls, [{
        name: "__Host-portal_session",
        value: "opaque-access-token",
        options: {
            httpOnly: true,
            maxAge: 60 * 60 * 24 * 7,
            path: "/",
            sameSite: "lax",
            secure: true,
        },
    }]);
});

test("writes an impersonation cookie without a persistent lifetime", () => {
    const calls: Array<{name: string; value: string; options: object}> = [];

    writePortalSession({
        set(name, value, options): void {
            calls.push({name, value, options});
        },
    }, "opaque-access-token", {impersonating: true});

    assert.deepEqual(calls[0]?.options, {
        httpOnly: true,
        path: "/",
        sameSite: "lax",
        secure: true,
    });
});

test("expires the host-only cookie with the attributes required by browsers", () => {
    const response = new NextResponse();

    expirePortalSession(response.cookies);

    const setCookie = response.headers.get("set-cookie");
    assert.ok(setCookie);
    assert.match(setCookie, /^__Host-portal_session=;/);
    assert.match(setCookie, /HttpOnly/i);
    assert.match(setCookie, /Max-Age=0/i);
    assert.match(setCookie, /Path=\//i);
    assert.match(setCookie, /SameSite=Lax/i);
    assert.match(setCookie, /Secure/i);
    assert.doesNotMatch(setCookie, /Domain=/i);
});
