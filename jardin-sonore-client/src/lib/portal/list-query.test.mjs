import assert from "node:assert/strict";
import {readFile} from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("./list-query.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {compilerOptions: {module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022}}).outputText;
const {parsePortalListQuery, portalListQueryToSearchParams} = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString("base64")}`);

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
