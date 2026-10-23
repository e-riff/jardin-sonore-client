import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'list', 'empty'];
    static values = { visibleLabel: String, shortVisibleLabel: String, openLabel: String };

    connect() {
        this.sync();
    }

    sync() {
        if (!this.hasSelectTarget || !this.hasListTarget) {
            return;
        }

        const currentVisibility = new Map(Array.from(this.listTarget.querySelectorAll('input[data-media-uuid]'))
            .map((input) => [input.dataset.mediaUuid, input.checked]));
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
            const title = this.escapeHtml(option.dataset.mediaChoiceLabel ?? option.dataset.mediaTitle ?? option.textContent?.trim() ?? '');
            const type = this.escapeHtml(option.dataset.mediaTypeLabel ?? '');
            const url = option.dataset.mediaUrl ?? '';
            const urlHtml = url === '' || !this.isSafeUrl(url)
                ? ''
                : `<a class="internal-icon-button repertoire-media-linker__open" href="${this.escapeHtml(url)}" target="_blank" rel="noopener noreferrer" title="${this.escapeHtml(this.openLabelValue)}" aria-label="${this.escapeHtml(this.openLabelValue)}">↗</a>`;
            const thumbnail = option.dataset.mediaThumbnail ?? '';
            const thumbnailHtml = thumbnail !== '' && this.isSafeUrl(thumbnail)
                ? `<img class="repertoire-media-linker__thumbnail" src="${this.escapeHtml(thumbnail)}" alt="" loading="lazy">`
                : '';
            const visible = currentVisibility.get(option.value) ?? option.dataset.mediaVisible !== '0';
            const visibilityHtml = `<label class="repertoire-media-linker__visibility"><input class="session-publication-switch__input internal-visually-hidden" type="checkbox" name="mediaVisibility[${this.escapeHtml(option.value)}]" value="1" data-media-uuid="${this.escapeHtml(option.value)}" aria-label="${this.escapeHtml(this.visibleLabelValue)}" ${visible ? 'checked' : ''}><span class="session-publication-switch"><span class="session-publication-switch__track" aria-hidden="true"><span class="session-publication-switch__thumb"></span></span></span><span>${this.escapeHtml(this.shortVisibleLabelValue)}</span></label>`;

            return `<article class="repertoire-media-linker__item">
                ${thumbnailHtml}
                <div class="repertoire-media-linker__description">
                    <strong>${title}</strong>
                    ${type === '' ? '' : `<div class="internal-table-note">${type}</div>`}
                </div>
                ${urlHtml}
                ${visibilityHtml}
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

    isSafeUrl(value) {
        try {
            const url = new URL(value, window.location.origin);
            return ['http:', 'https:'].includes(url.protocol);
        } catch {
            return false;
        }
    }
}
