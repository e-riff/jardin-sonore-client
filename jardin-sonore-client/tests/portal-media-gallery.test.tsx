import assert from "node:assert/strict";
import test from "node:test";
import {renderToStaticMarkup} from "react-dom/server";
import PortalMediaGallery from "../src/components/portal/PortalMediaGallery";
import fr from "../src/i18n/dictionaries/fr";

test("shows a YouTube preview before loading its player", () => {
    const html = renderToStaticMarkup(<PortalMediaGallery content={fr.portal.repertoire} media={[{type: "video", title: "Une vidéo", url: "https://youtu.be/dQw4w9WgXcQ"}]} />);

    assert.match(html, /hqdefault\.jpg/);
    assert.match(html, /aspect-video/);
    assert.doesNotMatch(html, /<iframe/);
});

test("uses the same media area for files and links and makes it actionable", () => {
    const html = renderToStaticMarkup(<PortalMediaGallery content={fr.portal.repertoire} media={[
        {type: "link", title: "Partition", url: "https://example.test/partition.pdf"},
        {type: "link", title: "Page externe", url: "https://example.test/page"},
    ]} />);

    assert.equal((html.match(/aspect-video/g) ?? []).length, 2);
    assert.match(html, /<a[^>]*href="https:\/\/example\.test\/partition\.pdf"[^>]*><svg/);
    assert.match(html, /<a[^>]*href="https:\/\/example\.test\/page"[^>]*><svg/);
});
