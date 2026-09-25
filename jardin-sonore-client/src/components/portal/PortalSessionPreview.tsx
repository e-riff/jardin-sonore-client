import type {JSX} from "react";
import {ArrowTopRightOnSquareIcon} from "@heroicons/react/24/outline";
import PortalLyrics from "@/components/portal/PortalLyrics";
import type {PortalRepertoireBlock} from "@/lib/portal/types";

type Sequence = Record<string, unknown>;

function stringValue(value: unknown): string | null {
    return typeof value === "string" && value.trim() !== "" ? value : null;
}

function sequenceTypeLabel(value: unknown, labels: Record<string, string>): string | null {
    const type = stringValue(value);

    return type ? (labels[type] ?? type.replaceAll("_", " ")) : null;
}

function instructionLines(value: unknown): string[] {
    const instructions = stringValue(value);

    return instructions ? instructions.split("\n").map((instruction) => instruction.trim()).filter(Boolean) : [];
}

function youtubeEmbedUrl(url: string): string | null {
    const videoId = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([A-Za-z0-9_-]{11})/)?.[1];

    return videoId ? `https://www.youtube-nocookie.com/embed/${videoId}` : null;
}

export function SequenceMedia({media, resourceFallback}: {media: unknown; resourceFallback: string}): JSX.Element | null {
    if (!Array.isArray(media)) return null;
    const displayedMedia = media.filter((item): item is Record<string, unknown> => typeof item === "object" && item !== null && (item.displayOnSession === true || item.featured === true));
    if (displayedMedia.length === 0) return null;

    return <div className="mt-5 grid gap-4 sm:grid-cols-2">{displayedMedia.map((item, index) => {
        const url = stringValue(item.url);
        const label = stringValue(item.label) ?? resourceFallback;
        const embedUrl = url ? youtubeEmbedUrl(url) : null;
        if (embedUrl) return <iframe allowFullScreen className="aspect-video w-full rounded-lg border border-outline-variant" key={`${url}-${index}`} loading="lazy" referrerPolicy="strict-origin-when-cross-origin" src={embedUrl} title={label} />;
        return url ? <a className="inline-flex items-start gap-2 rounded-lg border border-outline-variant p-4 font-semibold text-primary underline underline-offset-4" href={url} key={`${url}-${index}`} rel="noopener noreferrer" target="_blank"><ArrowTopRightOnSquareIcon aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0" /><span>{label}</span></a> : null;
    })}</div>;
}

function SequenceLyrics({sequence, title}: {sequence: Sequence; title: string}): JSX.Element | null {
    const contentBlocks: PortalRepertoireBlock[] = Array.isArray(sequence.contentBlocks) ? sequence.contentBlocks.flatMap((block) => {
        if (typeof block !== "object" || block === null) return [];
        return [{kind: stringValue(block.kind) ?? "line", text: stringValue(block.text) ?? undefined, gesture: stringValue(block.gesture) ?? undefined}];
    }) : [];
    const lyrics = stringValue(sequence.lyrics);
    const gestures = stringValue(sequence.gestures);
    if (contentBlocks.length === 0 && !lyrics && !gestures) return null;

    return <details className="mt-5 rounded-lg bg-surface-container-low p-4" open={sequence.showLyricsByDefault === true}>
        <summary className="cursor-pointer font-semibold">{title}</summary>
        <div className="mt-4"><PortalLyrics blocks={contentBlocks} body={lyrics ?? ""} compact gestures={gestures ?? ""} /></div>
    </details>;
}

export default async function PortalSessionPreview({sequences}: {sequences: Sequence[]}): Promise<JSX.Element | null> {
    if (sequences.length === 0) return null;
    const {getTranslations} = await import("@/i18n/server");
    const content = (await getTranslations()).portal.preview;
    const sequenceTypeLabels = content.types as Record<string, string>;

    return <section className="mt-8 border-t border-outline-variant pt-6"><h2 className="font-serif text-2xl">{content.title}</h2><div className="mt-5 grid gap-5">{sequences.map((sequence, index) => { const body = stringValue(sequence.body); const bodyInstructions = instructionLines(sequence.body); const instructions = instructionLines(sequence.generalInstructions); const role = stringValue(sequence.role); const type = sequenceTypeLabel(sequence.type, sequenceTypeLabels); return <article className="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm" key={`${stringValue(sequence.uuid) ?? "sequence"}-${index}`}><div className="flex gap-4"><span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{index + 1}</span><div className="min-w-0 flex-1">{role && <p className="text-base font-bold text-primary">{role}</p>}{type && <p className="mt-0.5 text-sm italic text-on-surface-variant">{type}</p>}<h3 className="mt-1 font-serif text-xl font-semibold">{stringValue(sequence.title) ?? content.sequenceFallback}</h3>{stringValue(sequence.subtitle) && <p className="mt-1 text-on-surface-variant">{stringValue(sequence.subtitle)}</p>}{body && body !== stringValue(sequence.lyrics) && (bodyInstructions.length > 1 ? <InstructionList instructions={bodyInstructions} /> : <p className="mt-4 whitespace-pre-wrap leading-7">{body}</p>)}{instructions.length > 0 && <InstructionList instructions={instructions} />}<SequenceLyrics sequence={sequence} title={content.lyricsAndGestures} /><SequenceMedia media={sequence.documentMedia ?? sequence.media} resourceFallback={content.resourceFallback} /></div></div></article>; })}</div></section>;
}

function InstructionList({instructions}: {instructions: string[]}): JSX.Element {
    return <ul className="mt-4 space-y-1 rounded-lg border-l-4 border-primary/35 px-4 py-3 text-sm leading-6">{instructions.map((instruction, instructionIndex) => <li className="flex gap-2" key={instructionIndex}><span aria-hidden="true" className="pt-0.5 text-primary">●</span><span>{instruction}</span></li>)}</ul>;
}
