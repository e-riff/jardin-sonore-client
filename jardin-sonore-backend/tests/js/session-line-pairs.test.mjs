import test from 'node:test';
import assert from 'node:assert/strict';
import { splitPairedLines, joinPairedLines } from '../../assets/session-line-pairs.js';

test('preserves empty lines and unmatched gestures when reopening', () => {
    const lyrics = '\nBonjour\n';
    const gestures = 'Geste seul\n\nTourner\n';
    assert.deepEqual(joinPairedLines(splitPairedLines(lyrics, gestures)), { lyrics, gestures });
});

test('keeps lyrics and gesture together when moving or removing a row', () => {
    const rows = splitPairedLines('Un\nDeux', 'Lever\nTourner');
    rows.reverse();
    assert.deepEqual(joinPairedLines(rows), { lyrics: 'Deux\nUn', gestures: 'Tourner\nLever' });
    rows.pop();
    assert.deepEqual(joinPairedLines(rows), { lyrics: 'Deux', gestures: 'Tourner' });
});

test('preserves distinct lengths while adding a gesture-only line', () => {
    const rows = splitPairedLines('Bonjour', '');
    rows.push({ lyrics: '', gestures: 'Taper', hasLyrics: false, hasGestures: true });
    assert.deepEqual(joinPairedLines(rows), { lyrics: 'Bonjour', gestures: '\nTaper' });
});
