import assert from "node:assert/strict";
import test from "node:test";
import {portalDocumentState} from "../src/lib/portal/document-status.ts";

test("keeps a ready document available for download", () => {
    assert.equal(portalDocumentState("ready"), "ready");
});

test("groups queued and generating documents into the calm pending state", () => {
    assert.equal(portalDocumentState("pending"), "pending");
    assert.equal(portalDocumentState("generating"), "pending");
});

test("groups a failed document into the unavailable state", () => {
    assert.equal(portalDocumentState("failed"), "unavailable");
});
