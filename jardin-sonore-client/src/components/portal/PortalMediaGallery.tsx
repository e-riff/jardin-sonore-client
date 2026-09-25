"use client";

import {useState} from "react";
import Image from "next/image";
import {ArrowTopRightOnSquareIcon, DocumentArrowDownIcon, LinkIcon, PlayIcon} from "@heroicons/react/24/outline";
import type {Dictionary} from "@/i18n/types";
import {resolvePortalMediaDisplay, type PortalMediaDisplay} from "@/lib/portal/media-display";
import type {PortalRepertoireMedia} from "@/lib/portal/types";

type Content = Dictionary["portal"]["repertoire"];

function MediaCard({media, display, content}: {media: PortalRepertoireMedia; display: PortalMediaDisplay; content: Content}): React.JSX.Element {
    const [playerOpen, setPlayerOpen] = useState(false);
    const isVideo = display.format === "video";
    const providerLabel = display.provider ?? (display.kind === "file" ? content.fileLabel : isVideo ? content.videoLabel : display.kind === "audio" ? content.audioLabel : content.linkLabel);
    const externalLabel = display.kind === "file" ? content.openFile : content.externalLink;

    return <li className="flex min-w-0 flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
        <div className="relative aspect-video shrink-0 overflow-hidden bg-primary-fixed/35">
            {display.kind === "embed" ? playerOpen ? <iframe allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowFullScreen className="absolute inset-0 h-full w-full" loading="lazy" referrerPolicy="strict-origin-when-cross-origin" src={display.url} title={media.title || providerLabel} /> : <button aria-label={`${content.playHere} : ${media.title || providerLabel}`} className="relative flex h-full w-full flex-col items-center justify-center gap-3 p-4 text-center text-on-surface transition hover:bg-primary-fixed/60 focus-visible:outline-2 focus-visible:outline-primary" onClick={() => setPlayerOpen(true)} type="button">{display.previewUrl ? <><Image alt="" className="object-cover" fill sizes="(max-width: 640px) 100vw, 50vw" src={display.previewUrl} unoptimized /><span aria-hidden="true" className="absolute inset-0 bg-black/30" /></> : null}<span className="relative rounded-full bg-primary p-4 text-white shadow-md"><PlayIcon aria-hidden="true" className="h-6 w-6" /></span><span className={`relative text-sm font-semibold ${display.previewUrl ? "text-white drop-shadow-md" : ""}`}>{content.playHere}</span></button>
                : display.kind === "video" ? <video className="h-full w-full bg-black object-contain" controls preload="metadata" src={display.url}>{content.videoUnavailable}</video>
                    : display.kind === "audio" ? <div className="flex h-full items-center p-4"><audio className="w-full" controls preload="none" src={display.url}>{content.audioUnavailable}</audio></div>
                        : <a aria-label={`${externalLabel} : ${media.title || providerLabel}`} className="flex h-full w-full items-center justify-center text-primary transition hover:bg-primary-fixed/60 focus-visible:outline-2 focus-visible:outline-primary" href={media.url} rel="noopener noreferrer" target="_blank" title={externalLabel}>{display.kind === "file" ? <DocumentArrowDownIcon aria-hidden="true" className="h-10 w-10" /> : <LinkIcon aria-hidden="true" className="h-10 w-10" />}</a>}
        </div>
        <div className="flex flex-1 items-start justify-between gap-3 p-4"><div className="min-w-0"><p className="text-xs font-semibold uppercase tracking-wide text-secondary">{providerLabel}</p><p className="mt-1 break-words font-semibold">{media.title || content.externalLink}</p></div><a aria-label={`${externalLabel} : ${media.title || providerLabel}`} className="shrink-0 rounded-full p-2 text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href={media.url} rel="noopener noreferrer" target="_blank" title={externalLabel}>{display.kind === "file" ? <DocumentArrowDownIcon aria-hidden="true" className="h-5 w-5" /> : <ArrowTopRightOnSquareIcon aria-hidden="true" className="h-5 w-5" />}</a></div>
    </li>;
}

export default function PortalMediaGallery({media, content}: {media: PortalRepertoireMedia[]; content: Content}): React.JSX.Element | null {
    const playableMedia = media.flatMap((item) => {
        const display = resolvePortalMediaDisplay(item.url, item.type);

        return display ? [{item, display}] : [];
    });
    if (playableMedia.length === 0) return null;

    return <ul className="mt-4 grid gap-4 sm:grid-cols-2">{playableMedia.map(({item, display}, index) => <MediaCard content={content} display={display} key={`${item.url}-${index}`} media={item} />)}</ul>;
}
