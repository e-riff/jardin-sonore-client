import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['entries'];
    static values = { prototype: String, index: Number };

    add() {
        const entryHtml = this.prototypeValue.replaceAll('__name__', String(this.indexValue));
        this.indexValue += 1;

        const entry = document.createElement('div');
        entry.className = 'portal-user-access';
        entry.dataset.accessEntry = '';
        entry.innerHTML = `${entryHtml}<button type="button" class="internal-button internal-button--secondary internal-button--compact" data-action="portal-user-access-collection#remove">${this.removeLabel}</button>`;
        this.entriesTarget.append(entry);
    }

    remove(event) {
        event.currentTarget.closest('[data-access-entry]')?.remove();
    }

    get removeLabel() {
        return this.element.dataset.removeLabel;
    }
}
