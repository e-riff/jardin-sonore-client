export type PortalMediaDisplay = {
    kind: "embed" | "audio" | "video" | "file" | "link";
    provider: "YouTube" | "YouTube Music" | "Vimeo" | "Dailymotion" | "Spotify" | "Deezer" | null;
    url: string;
    previewUrl?: string;
    format: "video" | "audio" | "file" | "link";
};

const VIDEO_FILE = /\.(?:mp4|webm|ogv)$/i;
const AUDIO_FILE = /\.(?:mp3|m4a|aac|ogg|oga|wav|flac)$/i;
const DOWNLOAD_FILE = /\.(?:pdf|doc|docx|odt|ppt|pptx|xls|xlsx|zip|png|jpe?g|webp|gif|svg|txt)$/i;

function videoEmbed(url: URL): PortalMediaDisplay | null {
    const host = url.hostname.toLowerCase();
    const parts = url.pathname.split("/").filter(Boolean);
    if (["youtube.com", "www.youtube.com", "m.youtube.com", "music.youtube.com", "www.youtube-nocookie.com", "youtu.be"].includes(host)) {
        const id = host === "youtu.be" ? parts[0] : url.pathname === "/watch" ? url.searchParams.get("v") : ["embed", "shorts", "live"].includes(parts[0]) ? parts[1] : null;
        if (id && /^[A-Za-z0-9_-]{11}$/.test(id)) {
            return {kind: "embed", provider: host === "music.youtube.com" ? "YouTube Music" : "YouTube", url: `https://www.youtube-nocookie.com/embed/${id}`, previewUrl: `https://i.ytimg.com/vi/${id}/hqdefault.jpg`, format: "video"};
        }
    }
    if (["vimeo.com", "www.vimeo.com", "player.vimeo.com"].includes(host)) {
        const videoPart = host === "player.vimeo.com" && parts[0] === "video" ? parts[1] : parts[0];
        const hash = url.searchParams.get("h") ?? (host === "player.vimeo.com" ? parts[2] : parts[1]);
        if (videoPart && /^\d+$/.test(videoPart)) {
            const privacyHash = hash && /^[a-fA-F0-9]+$/.test(hash) ? `?h=${hash}` : "";
            return {kind: "embed", provider: "Vimeo", url: `https://player.vimeo.com/video/${videoPart}${privacyHash}`, format: "video"};
        }
    }
    if (["dailymotion.com", "www.dailymotion.com", "dai.ly"].includes(host)) {
        const videoPart = host === "dai.ly" ? parts[0] : parts[0] === "video" ? parts[1] : null;
        const id = videoPart?.split("_")[0];
        if (id && /^[A-Za-z0-9]+$/.test(id)) {
            return {kind: "embed", provider: "Dailymotion", url: `https://www.dailymotion.com/embed/video/${id}`, format: "video"};
        }
    }

    return null;
}

function musicEmbed(url: URL): PortalMediaDisplay | null {
    const host = url.hostname.toLowerCase();
    const parts = url.pathname.split("/").filter(Boolean);
    if (host === "open.spotify.com") {
        const offset = parts[0]?.startsWith("intl-") ? 1 : 0;
        const type = parts[offset];
        const id = parts[offset + 1];
        if (["track", "album", "playlist", "episode", "show"].includes(type) && id && /^[A-Za-z0-9]{22}$/.test(id)) {
            return {kind: "embed", provider: "Spotify", url: `https://open.spotify.com/embed/${type}/${id}`, format: "audio"};
        }
    }
    if (["deezer.com", "www.deezer.com"].includes(host)) {
        const offset = parts[0] && /^[a-z]{2}(?:-[a-z]{2})?$/i.test(parts[0]) ? 1 : 0;
        const type = parts[offset];
        const id = parts[offset + 1];
        if (["track", "album", "playlist", "artist", "podcast"].includes(type) && id && /^\d+$/.test(id)) {
            return {kind: "embed", provider: "Deezer", url: `https://widget.deezer.com/widget/light/${type}/${id}`, format: "audio"};
        }
    }

    return null;
}

export function resolvePortalMediaDisplay(url: string, declaredType: string): PortalMediaDisplay | null {
    if (!url.startsWith("/") && !/^https?:\/\//i.test(url)) return null;
    const relative = url.startsWith("/") && !url.startsWith("//");
    let parsedUrl: URL;
    try {
        parsedUrl = new URL(url, "https://portal.invalid");
    } catch {
        return null;
    }
    if (relative ? parsedUrl.origin !== "https://portal.invalid" : !["https:", "http:"].includes(parsedUrl.protocol)) return null;
    if (!relative && parsedUrl.protocol === "https:") {
        const embed = videoEmbed(parsedUrl) ?? musicEmbed(parsedUrl);
        if (embed) return embed;
    }
    if (VIDEO_FILE.test(parsedUrl.pathname)) return {kind: "video", provider: null, url, format: "video"};
    if (AUDIO_FILE.test(parsedUrl.pathname)) return {kind: "audio", provider: null, url, format: "audio"};
    if (DOWNLOAD_FILE.test(parsedUrl.pathname)) return {kind: "file", provider: null, url, format: "file"};

    return {kind: "link", provider: null, url, format: declaredType === "video" ? "video" : declaredType === "soundtrack" ? "audio" : "link"};
}
