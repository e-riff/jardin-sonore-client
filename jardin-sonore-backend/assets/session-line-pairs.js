export function splitPairedLines(lyrics, gestures) {
    const textLines = lyrics === '' ? [] : lyrics.replace(/\r\n?/g, '\n').split('\n');
    const gestureLines = gestures === '' ? [] : gestures.replace(/\r\n?/g, '\n').split('\n');
    const count = Math.max(1, textLines.length, gestureLines.length);
    return Array.from({ length: count }, (_, index) => ({
        lyrics: textLines[index] ?? '',
        gestures: gestureLines[index] ?? '',
        hasLyrics: index < textLines.length,
        hasGestures: index < gestureLines.length,
    }));
}

export function joinPairedLines(rows) {
    const join = (valueKey, presentKey) => {
        let last = -1;
        rows.forEach((row, index) => {
            if (row[presentKey] || row[valueKey] !== '') last = index;
        });
        return rows.slice(0, last + 1).map((row) => row[valueKey]).join('\n');
    };
    return { lyrics: join('lyrics', 'hasLyrics'), gestures: join('gestures', 'hasGestures') };
}
