import assert from "node:assert/strict";
import test from "node:test";
import {renderToStaticMarkup} from "react-dom/server";
import PortalLyrics from "../src/components/portal/PortalLyrics";

test("keeps each gesture with its lyric and preserves section and break blocks", () => {
    const html = renderToStaticMarkup(PortalLyrics({blocks: [
        {kind: "section", text: "Refrain"},
        {kind: "line", text: "Brille petite étoile", gesture: "montrer le ciel"},
        {kind: "break"},
        {kind: "line", text: "Dans la nuit"},
    ]}));

    assert.match(html, /Refrain/);
    assert.match(html, /Brille petite étoile[\s\S]*montrer le ciel/);
    assert.match(html, /montrer le ciel[\s\S]*Dans la nuit/);
    assert.match(html, /sm:border-l-2/);
    assert.match(html, /text-on-surface-variant/);
});

test("renders legacy lyrics and gestures as one associated pair", () => {
    const html = renderToStaticMarkup(PortalLyrics({blocks: [], body: "Une parole", gestures: "Lever les mains", compact: true}));

    assert.match(html, /Une parole[\s\S]*Lever les mains/);
    assert.match(html, /text-sm/);
});

test("groups a gesture more tightly with its lyric than with the following lyric on mobile", () => {
    const html = renderToStaticMarkup(PortalLyrics({blocks: [
        {kind: "line", text: "Première parole", gesture: "Premier geste"},
        {kind: "line", text: "Parole suivante", gesture: "Geste suivant"},
    ]}));

    assert.match(html, /space-y-4/);
    assert.match(html, /gap-0(?:\s|\")/);
    assert.match(html, /Premier geste[\s\S]*Parole suivante/);
});
