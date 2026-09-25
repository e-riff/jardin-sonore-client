import assert from "node:assert/strict";
import test from "node:test";
import {renderToStaticMarkup} from "react-dom/server";
import PortalRepertoireDetail from "../src/components/portal/PortalRepertoireDetail";
import {SequenceMedia} from "../src/components/portal/PortalSessionPreview";
import fr from "../src/i18n/dictionaries/fr";
import type {PortalRepertoireDetail as RepertoireDetail} from "../src/lib/portal/types";

test("the repertoire back link uses the session return button style and keeps filters", () => {
    const item: RepertoireDetail = {
        slug: "ma-comptine", title: "Ma comptine", type: "nursery_rhyme", updatedAt: "2026-09-25", organizations: [], themes: [], thumbnailUrl: null,
        source: null, body: "", generalInstructions: null, contentBlocks: [], notes: null, media: [],
    };
    const html = renderToStaticMarkup(<PortalRepertoireDetail content={fr.portal.repertoire} filters={fr.portal.filters} item={item} query={{query: "nid", organizationUuid: "", themeUuids: [], type: "", sort: "updatedAt", direction: "desc", page: 1}} />);

    assert.match(html, /href="\/portail\/comptines\?q=nid"/);
    assert.match(html, /rounded-full border border-primary/);
    assert.match(html, /<svg[^>]*>[\s\S]*?<\/svg>Retour/);
});

test("a session preview marks an external media title with a link icon", () => {
    const html = renderToStaticMarkup(SequenceMedia({media: [{url: "https://example.test/partition.pdf", label: "Partition", displayOnSession: true}], resourceFallback: "Média"}));

    assert.match(html, /href="https:\/\/example\.test\/partition\.pdf"/);
    assert.match(html, /<svg[^>]*>[\s\S]*?<\/svg>\s*<span[^>]*>Partition<\/span>/);
});
