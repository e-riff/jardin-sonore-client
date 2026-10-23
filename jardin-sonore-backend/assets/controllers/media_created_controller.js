import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { selectId: String, uuid: String, label: String, title: String, typeLabel: String, url: String, thumbnail: String };

    connect() {
        const select = document.getElementById(this.selectIdValue);
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        let option = Array.from(select.options).find((candidate) => candidate.value === this.uuidValue);
        if (!option) {
            option = new Option(this.labelValue, this.uuidValue, true, true);
            select.add(option);
        }
        option.dataset.mediaTitle = this.titleValue;
        option.dataset.mediaChoiceLabel = this.labelValue;
        option.dataset.mediaTypeLabel = this.typeLabelValue;
        option.dataset.mediaUrl = this.urlValue;
        option.dataset.mediaThumbnail = this.thumbnailValue;
        option.dataset.mediaVisible = '1';

        if (select.tomselect) {
            select.tomselect.addOption({ value: this.uuidValue, text: this.labelValue });
            select.tomselect.addItem(this.uuidValue);
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));

        this.element.closest('dialog')?.close();
    }
}
