'use client';

import {CheckIcon, ShareIcon} from "@heroicons/react/24/outline";
import {JSX, useState} from "react";

interface ShareButtonProps {
    label: string;
    copiedLabel: string;
    title: string;
    text: string;
}

export default function ShareButton({label, copiedLabel, title, text}: ShareButtonProps): JSX.Element {
    const [copied, setCopied] = useState<boolean>(false);

    const copyUrl = async (url: string): Promise<void> => {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(url);
            return;
        }

        const input = document.createElement("input");
        input.value = url;
        input.setAttribute("readonly", "true");
        input.className = "fixed -left-full top-0";
        document.body.append(input);
        input.select();
        document.execCommand("copy");
        input.remove();
    };

    const share = async (): Promise<void> => {
        const url = window.location.href;

        try {
            if (navigator.share) {
                await navigator.share({title, text, url});
                return;
            }

            await copyUrl(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        } catch (error) {
            if (error instanceof DOMException && error.name === "AbortError") {
                return;
            }
        }
    };

    return (
        <button
            className="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-primary/10 bg-background px-4 font-sans text-sm font-bold text-primary transition hover:bg-primary hover:text-on-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-container-high"
            type="button"
            aria-label={copied ? copiedLabel : label}
            title={copied ? copiedLabel : label}
            onClick={() => void share()}
        >
            {copied ? <CheckIcon className="h-5 w-5" aria-hidden="true" /> : <ShareIcon className="h-5 w-5" aria-hidden="true" />}
            <span>{copied ? copiedLabel : label}</span>
            <span className="sr-only" aria-live="polite">{copied ? copiedLabel : ""}</span>
        </button>
    );
}
