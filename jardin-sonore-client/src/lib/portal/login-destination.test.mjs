import assert from "node:assert/strict";
import {readFile} from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("./login-destination.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {compilerOptions: {module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022}}).outputText;
const {resolvePortalLoginDestination} = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString("base64")}`);

test("preserves canonical session destinations including encoded Unicode", () => {
    for (const destination of ["/portail/seances/les-sons-2026", "/portail/seances/%C3%A9veil-musical"]) {
        assert.equal(resolvePortalLoginDestination(destination), destination);
    }
});

test("rejects external URLs, traversal, separators, double encoding and malformed input", () => {
    for (const value of [
        undefined, null, 1, {}, ["/portail/seances/sons"], "", "/portail/seances", "/portail/seances/",
        "https://example.test", "//example.test", "/portail/compte", "/portail/seances/..", "/portail/seances/.",
        "/portail/seances/../compte", "/portail/seances/%2e%2e", "/portail/seances/a/b", "/portail/seances/a%2Fb",
        "/portail/seances/a\\b", "/portail/seances/a%5Cb", "/portail/seances/a%252Fb", "/portail/seances/a%25",
        "/portail/seances/a?next=https://example.test", "/portail/seances/a#fragment", "/portail/seances/a%3Fb",
        "/portail/seances/a%23b", "/portail/seances/a%00b", "/portail/seances/a%0Ab", "/portail/seances/a%20b",
        "/portail/seances/a%7Fb", "/portail/seances/%", "/portail/seances/%C3", "/portail/seances/%61",
    ]) {
        assert.equal(resolvePortalLoginDestination(value), "/portail/seances", `Invalid input accepted: ${JSON.stringify(value)}`);
    }
});
