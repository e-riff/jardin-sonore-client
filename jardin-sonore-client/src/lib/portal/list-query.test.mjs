import assert from "node:assert/strict";
import {readFile} from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("./list-query.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {compilerOptions: {module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022}}).outputText;
const {parsePortalListQuery, portalListQueryToSearchParams, updatePortalListQuery, applyPortalListQueryPatch, togglePortalListTheme, selectPortalListSort, hasActivePortalListFilters, portalCategoryPreview} = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString("base64")}`);

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

test("category toggle preserves other filters and updates the shareable URL", () => {
    const current = parsePortalListQuery(new URLSearchParams("q=pluie&type=fingerplay&theme%5B%5D=rain&page=3"), "repertoire");
    const selected = togglePortalListTheme(current, "night");
    assert.equal(portalListQueryToSearchParams(selected).toString(), "q=pluie&theme%5B%5D=rain&theme%5B%5D=night&type=fingerplay");
    const deselected = togglePortalListTheme(selected, "rain");
    assert.equal(portalListQueryToSearchParams(deselected).toString(), "q=pluie&theme%5B%5D=night&type=fingerplay");
});

test("clicking the active sort reverses it and selecting another sort uses its natural order", () => {
    const current = parsePortalListQuery(new URLSearchParams("q=pluie&theme%5B%5D=rain&page=3"), "sessions");
    const oldestFirst = selectPortalListSort(current, "date");
    assert.equal(portalListQueryToSearchParams(oldestFirst).toString(), "q=pluie&theme%5B%5D=rain&direction=asc");
    const alphabetical = selectPortalListSort(oldestFirst, "title");
    assert.equal(portalListQueryToSearchParams(alphabetical).toString(), "q=pluie&theme%5B%5D=rain&sort=title&direction=asc");
    const reverseAlphabetical = selectPortalListSort(alphabetical, "title");
    assert.equal(portalListQueryToSearchParams(reverseAlphabetical).toString(), "q=pluie&theme%5B%5D=rain&sort=title");
});

test("reset is offered only when filters or sort differ from the list defaults", () => {
    assert.equal(hasActivePortalListFilters(parsePortalListQuery(new URLSearchParams("page=2"), "sessions"), "sessions"), false);
    assert.equal(hasActivePortalListFilters(parsePortalListQuery(new URLSearchParams("q=pluie"), "sessions"), "sessions"), true);
    assert.equal(hasActivePortalListFilters(parsePortalListQuery(new URLSearchParams("theme%5B%5D=rain"), "repertoire"), "repertoire"), true);
    assert.equal(hasActivePortalListFilters(parsePortalListQuery(new URLSearchParams("sort=title"), "repertoire"), "repertoire"), true);
});

test("desktop category preview stays compact until expanded without changing option order", () => {
    const themes = Array.from({length: 9}, (_, index) => ({uuid: String(index)}));
    assert.deepEqual(portalCategoryPreview(themes, false).map((theme) => theme.uuid), ["0", "1", "2", "3", "4", "5"]);
    assert.deepEqual(portalCategoryPreview(themes, true).map((theme) => theme.uuid), ["0", "1", "2", "3", "4", "5", "6", "7", "8"]);
});
