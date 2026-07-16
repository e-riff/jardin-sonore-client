import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'list', 'empty'];

    connect() {
        this.sync();
    }

    sync() {
        if (!this.hasSelectTarget || !this.hasListTarget) {
            return;
        }

        const selectedOptions = Array.from(this.selectTarget.selectedOptions)
            .filter((option) => option.value !== '');

        if (selectedOptions.length === 0) {
            this.listTarget.innerHTML = '';
            this.listTarget.hidden = true;

            if (this.hasEmptyTarget) {
                this.emptyTarget.hidden = false;
            }

            return;
        }

        const itemsHtml = selectedOptions.map((option) => {
            const title = this.escapeHtml(option.dataset.mediaTitle ?? option.textContent?.trim() ?? '');
            const type = this.escapeHtml(option.dataset.mediaTypeLabel ?? '');
            const url = option.dataset.mediaUrl ?? '';
            const urlHtml = url === ''
                ? ''
                : `<a class="internal-link" href="${this.escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${this.escapeHtml(url)}</a>`;

            return `<article class="repertoire-media-linker__item">
                <div>
                    <strong>${title}</strong>
                    ${type === '' ? '' : `<div class="internal-table-note">${type}</div>`}
                </div>
                ${urlHtml}
            </article>`;
        }).join('');

        this.listTarget.innerHTML = itemsHtml;
        this.listTarget.hidden = false;

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = true;
        }
    }

    escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }
}
