import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.handleMaskFormSubmit = this.handleMaskFormSubmit.bind(this);
        this.maskForm = document.querySelector('[data-mailing-audience-mask-form="true"]');
        this.maskSnapshotField = this.maskForm?.querySelector('[data-mailing-audience-target="maskSnapshot"]');
        this.maskForm?.addEventListener('submit', this.handleMaskFormSubmit);
    }

    disconnect() {
        this.maskForm?.removeEventListener('submit', this.handleMaskFormSubmit);
    }

    handleAudienceSaved(event) {
        const redirectUrl = event.detail?.url;

        if (typeof redirectUrl === 'string' && redirectUrl !== '') {
            window.location.href = redirectUrl;
        }
    }

    handleMaskFormSubmit() {
        if (!(this.maskSnapshotField instanceof HTMLInputElement)) {
            return;
        }

        this.maskSnapshotField.value = JSON.stringify(this.serializeAudienceForm());
    }

    serializeAudienceForm() {
        const serializedData = {};

        for (const [fieldName, fieldValue] of new FormData(this.element).entries()) {
            const match = fieldName.match(/^mailing_audience\[([^\]]+)\](\[\])?$/);

            if (!match) {
                continue;
            }

            const [, key, isArrayField] = match;

            if (key === '_token') {
                continue;
            }

            if (isArrayField) {
                if (!Array.isArray(serializedData[key])) {
                    serializedData[key] = [];
                }

                serializedData[key].push(fieldValue);
                continue;
            }

            serializedData[key] = fieldValue;
        }

        this.element.querySelectorAll('select[name^="mailing_audience["]').forEach((selectElement) => {
            if (!(selectElement instanceof HTMLSelectElement)) {
                return;
            }

            const match = selectElement.name.match(/^mailing_audience\[([^\]]+)\](\[\])?$/);

            if (!match) {
                return;
            }

            const [, key, isArrayField] = match;
            const selectedValues = this.selectedSelectValues(selectElement);

            if (isArrayField || selectElement.multiple) {
                serializedData[key] = selectedValues;

                return;
            }

            serializedData[key] = selectedValues[0] ?? '';
        });

        return serializedData;
    }

    selectedSelectValues(selectElement) {
        if ('tomselect' in selectElement && selectElement.tomselect) {
            const selectedValues = selectElement.tomselect.getValue();
            const normalizedValues = Array.isArray(selectedValues) ? selectedValues : [selectedValues];

            return normalizedValues.filter((value) => typeof value === 'string' && value !== '');
        }

        if (selectElement.multiple) {
            return Array.from(selectElement.selectedOptions)
                .map((optionElement) => optionElement.value)
                .filter((value) => value !== '');
        }

        return selectElement.value !== '' ? [selectElement.value] : [];
    }
}
