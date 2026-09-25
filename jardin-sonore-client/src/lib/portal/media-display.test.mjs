import assert from "node:assert/strict";
import {readFile} from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("./media-display.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {compilerOptions: {module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022}}).outputText;
const {resolvePortalMediaDisplay} = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString("base64")}`);

test("embeds trusted video hosts and YouTube Music without accepting a lookalike host", () => {
    assert.deepEqual(resolvePortalMediaDisplay("https://music.youtube.com/watch?v=dQw4w9WgXcQ", "link"), {kind: "embed", provider: "YouTube Music", url: "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ", previewUrl: "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("https://youtu.be/dQw4w9WgXcQ", "video"), {kind: "embed", provider: "YouTube", url: "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ", previewUrl: "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("https://vimeo.com/123456789/abcdef1234", "video"), {kind: "embed", provider: "Vimeo", url: "https://player.vimeo.com/video/123456789?h=abcdef1234", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("https://player.vimeo.com/video/123456789", "video"), {kind: "embed", provider: "Vimeo", url: "https://player.vimeo.com/video/123456789", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("https://www.dailymotion.com/video/x84sh87", "video"), {kind: "embed", provider: "Dailymotion", url: "https://www.dailymotion.com/embed/video/x84sh87", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ", "video"), {kind: "link", provider: null, url: "https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ", format: "video"});
});

test("embeds canonical Spotify and Deezer music links", () => {
    assert.deepEqual(resolvePortalMediaDisplay("https://open.spotify.com/intl-fr/track/0Lr4kGOYn9l83EjuK6cZFQ?si=abc", "soundtrack"), {kind: "embed", provider: "Spotify", url: "https://open.spotify.com/embed/track/0Lr4kGOYn9l83EjuK6cZFQ", format: "audio"});
    assert.deepEqual(resolvePortalMediaDisplay("https://www.deezer.com/fr/track/2636103722", "link"), {kind: "embed", provider: "Deezer", url: "https://widget.deezer.com/widget/light/track/2636103722", format: "audio"});
});

test("uses native playback for direct files and a safe link for other resources", () => {
    assert.deepEqual(resolvePortalMediaDisplay("https://files.example.test/song.mp3?signature=abc", "soundtrack"), {kind: "audio", provider: null, url: "https://files.example.test/song.mp3?signature=abc", format: "audio"});
    assert.deepEqual(resolvePortalMediaDisplay("https://files.example.test/demo.mp4", "video"), {kind: "video", provider: null, url: "https://files.example.test/demo.mp4", format: "video"});
    assert.deepEqual(resolvePortalMediaDisplay("/documents/paroles.pdf", "link"), {kind: "file", provider: null, url: "/documents/paroles.pdf", format: "file"});
    assert.equal(resolvePortalMediaDisplay("javascript:alert(1)", "link"), null);
    assert.equal(resolvePortalMediaDisplay("song.mp3", "soundtrack"), null);
});
