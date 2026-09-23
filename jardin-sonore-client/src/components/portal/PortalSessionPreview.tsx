import type {JSX} from "react";
import {getTranslations} from "@/i18n/server";

type Sequence = Record<string, unknown>;

function stringValue(value: unknown): string | null {
    return typeof value === "string" && value.trim() !== "" ? value : null;
}

function sequenceTypeLabel(value: unknown, labels: Record<string, string>): string | null {
    const type = stringValue(value);

    return type ? (labels[type] ?? type.replaceAll("_", " ")) : null;
}

function youtubeEmbedUrl(url: string): string | null {
    const videoId = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([A-Za-z0-9_-]{11})/)?.[1];

    return videoId ? `https://www.youtube-nocookie.com/embed/${videoId}` : null;
}

function SequenceMedia({media, resourceFallback}: {media: unknown; resourceFallback: string}): JSX.Element | null {
    if (!Array.isArray(media)) return null;
    const displayedMedia = media.filter((item): item is Record<string, unknown> => typeof item === "object" && item !== null && (item.displayOnSession === true || item.featured === true));
    if (displayedMedia.length === 0) return null;

    return <div className="mt-5 grid gap-4 sm:grid-cols-2">{displayedMedia.map((item, index) => {
        const url = stringValue(item.url);
        const label = stringValue(item.label) ?? resourceFallback;
        const embedUrl = url ? youtubeEmbedUrl(url) : null;
        if (embedUrl) return <iframe allowFullScreen className="aspect-video w-full rounded-lg border border-outline-variant" key={`${url}-${index}`} loading="lazy" referrerPolicy="strict-origin-when-cross-origin" src={embedUrl} title={label} />;
        return url ? <a className="rounded-lg border border-outline-variant p-4 font-semibold text-primary underline" href={url} key={`${url}-${index}`} rel="noopener noreferrer" target="_blank">{label}</a> : null;
    })}</div>;
}

function SequenceLyrics({sequence, title}: {sequence: Sequence; title: string}): JSX.Element | null {
    const contentBlocks = Array.isArray(sequence.contentBlocks) ? sequence.contentBlocks.filter((block): block is Record<string, unknown> => typeof block === "object" && block !== null) : [];
    const lyrics = stringValue(sequence.lyrics);
    const gestures = stringValue(sequence.gestures);
    if (contentBlocks.length === 0 && !lyrics && !gestures) return null;

    return <details className="mt-5 rounded-lg bg-surface-container-low p-4" open={sequence.showLyricsByDefault === true}>
        <summary className="cursor-pointer font-semibold">{title}</summary>
        <div className="mt-4 grid gap-3 whitespace-pre-wrap text-sm leading-6">{contentBlocks.length > 0 ? contentBlocks.map((block, index) => { const gesture = stringValue(block.gesture); return block.kind === "section" ? <p className="text-center font-semibold" key={index}>{stringValue(block.text)}</p> : gesture ? <div className="grid gap-2 text-center sm:grid-cols-2 sm:gap-4" key={index}><p className="sm:text-right">{stringValue(block.text)}</p><p className="border-primary/20 italic text-on-surface-variant sm:border-l sm:pl-4 sm:text-left">{gesture}</p></div> : <p className="text-center" key={index}>{stringValue(block.text)}</p>; }) : gestures ? <div className="grid gap-2 text-center sm:grid-cols-2 sm:gap-4"><p className="sm:text-right">{lyrics}</p><p className="border-primary/20 italic text-on-surface-variant sm:border-l sm:pl-4 sm:text-left">{gestures}</p></div> : <p className="text-center">{lyrics}</p>}</div>
    </details>;
}

export default async function PortalSessionPreview({sequences}: {sequences: Sequence[]}): Promise<JSX.Element | null> {
    if (sequences.length === 0) return null;
    const content = (await getTranslations()).portal.preview;
    const sequenceTypeLabels = content.types as Record<string, string>;

    return <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.title}</h2><div className="mt-5 grid gap-5">{sequences.map((sequence, index) => <article className="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm" key={`${stringValue(sequence.uuid) ?? "sequence"}-${index}`}><div className="flex gap-4"><span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{index + 1}</span><div className="min-w-0 flex-1">{sequenceTypeLabel(sequence.type, sequenceTypeLabels) && <p className="portal-eyebrow">{sequenceTypeLabel(sequence.type, sequenceTypeLabels)}</p>}{stringValue(sequence.role) && <p className="text-sm font-semibold text-primary">{stringValue(sequence.role)}</p>}<h3 className="font-serif text-xl font-semibold">{stringValue(sequence.title) ?? content.sequenceFallback}</h3>{stringValue(sequence.subtitle) && <p className="mt-1 text-on-surface-variant">{stringValue(sequence.subtitle)}</p>}{stringValue(sequence.body) && stringValue(sequence.body) !== stringValue(sequence.lyrics) && <p className="mt-4 whitespace-pre-wrap leading-7">{stringValue(sequence.body)}</p>}{stringValue(sequence.generalInstructions) && <p className="mt-4 whitespace-pre-wrap rounded-lg border-l-4 border-primary/35 pl-4 text-sm leading-6">{stringValue(sequence.generalInstructions)}</p>}<SequenceLyrics sequence={sequence} title={content.lyricsAndGestures} /><SequenceMedia media={sequence.documentMedia ?? sequence.media} resourceFallback={content.resourceFallback} /></div></div></article>)}</div></section>;
}
