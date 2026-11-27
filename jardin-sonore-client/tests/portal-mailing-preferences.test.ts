import assert from "node:assert/strict";
import test from "node:test";
import {changedMailingPreferences} from "../src/lib/portal/mailing-preferences.ts";

test("saving unchanged profile preferences does not restore a refusal made after opening", () => {
    const form = new FormData();
    form.set("initialNewsletterSubscribed", "true");
    form.set("newsletterSubscribed", "on");
    form.set("initialOutreachAllowed", "true");
    form.set("outreachAllowed", "on");
    assert.deepEqual(changedMailingPreferences(form), {});
});

test("only an explicitly changed preference is sent", () => {
    const form = new FormData();
    form.set("initialNewsletterSubscribed", "true");
    form.set("initialOutreachAllowed", "false");
    assert.deepEqual(changedMailingPreferences(form), {newsletterSubscribed: false});
    form.set("outreachAllowed", "on");
    assert.deepEqual(changedMailingPreferences(form), {newsletterSubscribed: false, outreachAllowed: true});
});
