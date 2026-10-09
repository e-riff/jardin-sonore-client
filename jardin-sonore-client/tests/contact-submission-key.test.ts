import assert from "node:assert/strict";
import test from "node:test";
import {nextContactSubmission} from "../src/lib/contact/submission-key.ts";

test("a retry keeps its key while a changed form starts a new submission", () => {
    let generated = 0;
    const createKey = (): string => `key-${++generated}`;
    const fields = {name: "Claire", email: "claire@example.test", message: "Bonjour"};

    const first = nextContactSubmission(null, fields, createKey);
    const retry = nextContactSubmission(first, fields, createKey);
    const changed = nextContactSubmission(retry, {...fields, message: "Autre demande"}, createKey);

    assert.equal(first.key, "key-1");
    assert.equal(retry.key, first.key);
    assert.equal(changed.key, "key-2");
    assert.equal(generated, 2);
});
