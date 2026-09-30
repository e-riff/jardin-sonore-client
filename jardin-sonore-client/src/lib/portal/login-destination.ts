const SESSION_LIST_DESTINATION = "/portail/seances";
const SESSION_DETAIL_PREFIX = `${SESSION_LIST_DESTINATION}/`;

export function resolvePortalLoginDestination(value: unknown): string {
    if (typeof value !== "string" || !value.startsWith(SESSION_DETAIL_PREFIX)) {
        return SESSION_LIST_DESTINATION;
    }

    const encodedSlug = value.slice(SESSION_DETAIL_PREFIX.length);
    let slug: string;
    try {
        slug = decodeURIComponent(encodedSlug);
    } catch {
        return SESSION_LIST_DESTINATION;
    }

    if (!slug || slug === "." || slug === ".."
        || /[\\/?#%]/.test(slug)
        || Array.from(slug).some((character) => character.charCodeAt(0) <= 32 || character.charCodeAt(0) === 127)
        || encodeURIComponent(slug) !== encodedSlug) {
        return SESSION_LIST_DESTINATION;
    }

    return value;
}
