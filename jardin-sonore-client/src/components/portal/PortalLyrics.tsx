import type {PortalRepertoireBlock} from "@/lib/portal/types";

interface PortalLyricsProps {
    blocks: PortalRepertoireBlock[];
    body?: string;
    gestures?: string;
    compact?: boolean;
}

function LyricLine({text, gesture}: {text: string; gesture?: string}): React.JSX.Element {
    return <p className="flex flex-col items-center justify-center gap-0 sm:flex-row sm:gap-3">
        <span className="max-w-full whitespace-pre-wrap">{text}</span>
        {gesture ? <em className="max-w-full whitespace-pre-wrap text-on-surface-variant sm:border-l-2 sm:border-primary/35 sm:pl-3">{gesture}</em> : null}
    </p>;
}

export default function PortalLyrics({blocks, body = "", gestures = "", compact = false}: PortalLyricsProps): React.JSX.Element | null {
    if (blocks.length === 0 && !body.trim() && !gestures.trim()) return null;

    return <div className={`mx-auto max-w-3xl space-y-4 text-center sm:space-y-3 ${compact ? "text-sm leading-5 sm:leading-6" : "leading-6 sm:leading-7"}`}>
        {blocks.length > 0 ? blocks.map((block, index) => {
            if (block.kind === "break") return <div aria-hidden="true" className="h-3" key={index} />;
            if (block.kind === "section") return <h3 className="pt-2 font-semibold" key={index}>{block.text}</h3>;
            return <LyricLine gesture={block.gesture} key={index} text={block.text ?? ""} />;
        }) : <LyricLine gesture={gestures} text={body} />}
    </div>;
}
