import assert from "node:assert/strict";
import test from "node:test";
import {PortalApiClient, PortalApiUnavailableError} from "../src/lib/portal/api-client-core.ts";

test("sends all selected filters as encoded authenticated server query parameters", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});

        return Response.json({items: [], pagination: {page: 2, pageSize: 20, total: 0}});
    };

    try {
        await new PortalApiClient("opaque-access-token", "https://admin.example.test").sessions({query: "pluie", organizationUuid: "structure/avec espace", themeUuids: ["rain", "night"], type: "", sort: "title", direction: "asc", page: 2});
    } finally {
        globalThis.fetch = originalFetch;
    }

    assert.equal(requests.length, 1);
    assert.equal(requests[0].input, "https://admin.example.test/api/portal/sessions?q=pluie&organization=structure%2Favec+espace&theme%5B%5D=rain&theme%5B%5D=night&sort=title&direction=asc&page=2");
    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), "Bearer opaque-access-token");
    assert.equal(requests[0].init?.cache, "no-store");
});

test("keeps the repertoire token in the authorization header", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});
        return Response.json({items: [], pagination: {page: 1, pageSize: 20, total: 0}, availableThemes: []});
    };
    try {
        const client = new PortalApiClient("opaque-access-token", "https://admin.example.test");
        await client.repertoire({query: "", organizationUuid: "", themeUuids: [], type: "fingerplay", sort: "updatedAt", direction: "desc", page: 1});
        await client.repertoireItem("comptine/à voir");
    } finally {
        globalThis.fetch = originalFetch;
    }
    assert.equal(requests[0].input, "https://admin.example.test/api/portal/repertoire?type=fingerplay");
    assert.equal(requests[1].input, "https://admin.example.test/api/portal/repertoire/comptine%2F%C3%A0%20voir");
    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), "Bearer opaque-access-token");
    assert.equal(new Headers(requests[1].init?.headers).get("authorization"), "Bearer opaque-access-token");
});

test("encodes a session identifier before loading its authenticated detail", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});

        return Response.json({uuid: "session", organizations: [], sequences: []});
    };

    try {
        await new PortalApiClient("opaque-access-token", "https://admin.example.test").session("session/à voir");
    } finally {
        globalThis.fetch = originalFetch;
    }

    assert.equal(requests[0].input, "https://admin.example.test/api/portal/sessions/session%2F%C3%A0%20voir");
    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), "Bearer opaque-access-token");
    assert.equal(requests[0].init?.cache, "no-store");
});

test("authenticates profile updates", async () => {
    const requests: Array<{input: string | URL | Request; init?: RequestInit}> = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (input, init): Promise<Response> => {
        requests.push({input, init});

        return Response.json({email: "structure@example.test", organizations: []});
    };

    try {
        await new PortalApiClient("opaque-access-token", "https://admin.example.test").updateProfile({firstName: "Anaïs", lastName: "Martin", newSessionNotificationsEnabled: true});
    } finally {
        globalThis.fetch = originalFetch;
    }

    assert.equal(requests[0].input, "https://admin.example.test/api/portal/me/profile");
    assert.equal(requests[0].init?.method, "PATCH");
    assert.equal(new Headers(requests[0].init?.headers).get("authorization"), "Bearer opaque-access-token");
});

test("turns a sessions transport failure into the safe unavailable error", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (): Promise<Response> => {
        throw new TypeError("network unavailable");
    };

    try {
        await assert.rejects(
            new PortalApiClient("opaque-access-token", "https://admin.example.test").sessions(),
            PortalApiUnavailableError,
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});
