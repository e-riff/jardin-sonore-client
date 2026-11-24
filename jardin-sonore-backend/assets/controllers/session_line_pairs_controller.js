import { Controller } from '@hotwired/stimulus';
import { joinPairedLines, splitPairedLines } from '../session-line-pairs.js';

export default class extends Controller {
    static targets = ['fallback', 'editor', 'list', 'template', 'lyrics', 'gestures'];

    connect() {
        if (this.editorTarget.dataset.initialized === 'true') return;
        splitPairedLines(this.lyricsTarget.value, this.gesturesTarget.value).forEach((row) => this.appendRow(row));
        this.editorTarget.dataset.initialized = 'true';
        this.fallbackTarget.hidden = true;
        this.editorTarget.hidden = false;
    }

    appendRow(row) {
        const element = this.templateTarget.content.firstElementChild.cloneNode(true);
        element.dataset.hasLyrics = String(row.hasLyrics);
        element.dataset.hasGestures = String(row.hasGestures);
        element.querySelector('[data-line-lyrics]').value = row.lyrics;
        element.querySelector('[data-line-gestures]').value = row.gestures;
        this.listTarget.append(element);
    }

    add() {
        this.appendRow({ lyrics: '', gestures: '', hasLyrics: true, hasGestures: true });
        this.sync();
        this.listTarget.lastElementChild.querySelector('[data-line-lyrics]').focus();
    }

    change(event) {
        const row = event.target.closest('[data-line-row]');
        if (event.target.hasAttribute('data-line-lyrics')) row.dataset.hasLyrics = 'true';
        if (event.target.hasAttribute('data-line-gestures')) row.dataset.hasGestures = 'true';
        this.sync();
    }

    remove(event) {
        event.currentTarget.closest('[data-line-row]').remove();
        this.sync();
    }

    moveUp(event) {
        const row = event.currentTarget.closest('[data-line-row]');
        if (row.previousElementSibling) row.previousElementSibling.before(row);
        this.sync();
        event.currentTarget.focus();
    }

    moveDown(event) {
        const row = event.currentTarget.closest('[data-line-row]');
        if (row.nextElementSibling) row.nextElementSibling.after(row);
        this.sync();
        event.currentTarget.focus();
    }

    sync() {
        const rows = Array.from(this.listTarget.children, (row) => ({
            lyrics: row.querySelector('[data-line-lyrics]').value,
            gestures: row.querySelector('[data-line-gestures]').value,
            hasLyrics: row.dataset.hasLyrics === 'true',
            hasGestures: row.dataset.hasGestures === 'true',
        }));
        const values = joinPairedLines(rows);
        this.lyricsTarget.value = values.lyrics;
        this.gesturesTarget.value = values.gestures;
        this.lyricsTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
