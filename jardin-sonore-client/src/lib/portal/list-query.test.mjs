import assert from "node:assert/strict";
import {readFile} from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("./list-query.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {compilerOptions: {module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022}}).outputText;
const {parsePortalListQuery, portalListQueryToSearchParams, updatePortalListQuery, applyPortalListQueryPatch} = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString("base64")}`);

test("restores multiple themes and every repertoire filter from a shared URL", () => {
    const query = parsePortalListQuery(new URLSearchParams("q=pluie&organization=org&theme%5B%5D=rain&theme%5B%5D=night&type=fingerplay&sort=title&direction=asc&page=2"), "repertoire");
    assert.deepEqual(query, {query: "pluie", organizationUuid: "org", themeUuids: ["rain", "night"], type: "fingerplay", sort: "title", direction: "asc", page: 2});
    assert.equal(portalListQueryToSearchParams(query).toString(), "q=pluie&organization=org&theme%5B%5D=rain&theme%5B%5D=night&type=fingerplay&sort=title&direction=asc&page=2");
});

test("normalizes invalid state and omits defaults", () => {
    const query = parsePortalListQuery(new URLSearchParams("type=activity&sort=theme&direction=up&page=-3"), "sessions");
    assert.deepEqual(query, {query: "", organizationUuid: "", themeUuids: [], type: "", sort: "date", direction: "desc", page: 1});
    assert.equal(portalListQueryToSearchParams(query).toString(), "");
});

test("changing a session category keeps the other filters and returns to page one", () => {
    const current = parsePortalListQuery(new URLSearchParams("q=soleil&theme%5B%5D=rain&organization=org&page=4"), "sessions");
    const next = updatePortalListQuery(current, {themeUuids: ["rain", "night"]});
    assert.equal(portalListQueryToSearchParams(next).toString(), "q=soleil&organization=org&theme%5B%5D=rain&theme%5B%5D=night");
    assert.equal(next.page, 1);
});

test("two category clicks before a server response accumulate", () => {
    const queryRef = {current: parsePortalListQuery(new URLSearchParams("page=3"), "sessions")};
    applyPortalListQueryPatch(queryRef, {themeUuids: ["rain"]});
    const next = applyPortalListQueryPatch(queryRef, {themeUuids: [...queryRef.current.themeUuids, "night"]});
    assert.deepEqual(next.themeUuids, ["rain", "night"]);
    assert.equal(next.page, 1);
});
