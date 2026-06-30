import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['radiusFieldset', 'locationFieldset'];
    static values = {
        radiusOriginMunicipalityInseeCode: String,
    };

    connect() {
        this.isUpdating = false;
        this.syncTimeoutId = null;
        this.handleChange = this.handleChange.bind(this);
        this.element.addEventListener('change', this.handleChange);
        this.updateState();
        this.scheduleRadiusOriginMunicipalitySync();
    }

    disconnect() {
        this.element.removeEventListener('change', this.handleChange);
        this.clearSyncTimeout();
    }

    handleChange() {
        if (this.isUpdating) {
            return;
        }

        this.updateState();
    }

    updateState() {
        this.isUpdating = true;

        const radiusActive = this.hasFilledValue('select[name$="[radiusOrigin]"]');

        if (radiusActive) {
            this.clearFieldset(this.locationFieldsetTarget);
            this.disableFieldset(this.locationFieldsetTarget, true);
            this.disableFieldset(this.radiusFieldsetTarget, false);
            this.isUpdating = false;
            this.scheduleRadiusOriginMunicipalitySync();

            return;
        }

        this.disableFieldset(this.radiusFieldsetTarget, false);
        this.disableFieldset(this.locationFieldsetTarget, false);
        this.isUpdating = false;
        this.scheduleRadiusOriginMunicipalitySync();
    }

    hasFilledValue(selector) {
        const fieldElement = this.element.querySelector(selector);

        return fieldElement instanceof HTMLInputElement || fieldElement instanceof HTMLSelectElement
            ? '' !== fieldElement.value.trim()
            : false;
    }

    disableFieldset(fieldsetElement, disabled) {
        if (!(fieldsetElement instanceof HTMLFieldSetElement)) {
            return;
        }

        fieldsetElement.disabled = disabled;

        fieldsetElement.querySelectorAll('select').forEach((selectElement) => {
            if (!(selectElement instanceof HTMLSelectElement) || !('tomselect' in selectElement)) {
                return;
            }

            if (disabled) {
                selectElement.tomselect.disable();

                return;
            }

            selectElement.tomselect.enable();
        });
    }

    clearFieldset(fieldsetElement) {
        if (!(fieldsetElement instanceof HTMLFieldSetElement)) {
            return;
        }

        fieldsetElement.querySelectorAll('input, select, textarea').forEach((fieldElement) => {
            if (!(fieldElement instanceof HTMLInputElement)
                && !(fieldElement instanceof HTMLSelectElement)
                && !(fieldElement instanceof HTMLTextAreaElement)) {
                return;
            }

            if (fieldElement instanceof HTMLSelectElement && 'tomselect' in fieldElement) {
                fieldElement.tomselect.clear(true);
            } else if (fieldElement instanceof HTMLSelectElement && fieldElement.multiple) {
                Array.from(fieldElement.options).forEach((optionElement) => {
                    optionElement.selected = false;
                });
            } else if (fieldElement instanceof HTMLInputElement || fieldElement instanceof HTMLTextAreaElement) {
                fieldElement.value = '';
            } else {
                fieldElement.selectedIndex = 0;
            }

            fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    scheduleRadiusOriginMunicipalitySync(attempt = 0) {
        this.clearSyncTimeout();

        if (!this.hasRadiusOriginMunicipalityInseeCodeValue || '' === this.radiusOriginMunicipalityInseeCodeValue.trim()) {
            return;
        }

        this.syncTimeoutId = window.setTimeout(() => {
            const synced = this.syncRadiusOriginMunicipalitySelection();

            if (!synced && attempt < 10) {
                this.scheduleRadiusOriginMunicipalitySync(attempt + 1);
            }
        }, 80);
    }

    syncRadiusOriginMunicipalitySelection() {
        const selectElement = this.element.querySelector('select[name$="[radiusOriginMunicipalityInseeCode]"]');

        if (!(selectElement instanceof HTMLSelectElement)) {
            return true;
        }

        const inseeCode = this.radiusOriginMunicipalityInseeCodeValue.trim();

        if ('' === inseeCode) {
            return true;
        }

        const optionElement = Array.from(selectElement.options).find((candidateOptionElement) => candidateOptionElement.value === inseeCode);

        if (selectElement.value !== inseeCode) {
            selectElement.value = inseeCode;
        }

        if (!('tomselect' in selectElement) || !selectElement.tomselect) {
            return false;
        }

        if (optionElement instanceof HTMLOptionElement) {
            selectElement.tomselect.addOption({
                value: inseeCode,
                text: optionElement.text,
            });
        }

        if (selectElement.tomselect.getValue() !== inseeCode) {
            selectElement.tomselect.setValue(inseeCode, true);
        }

        return true;
    }

    clearSyncTimeout() {
        if (null === this.syncTimeoutId) {
            return;
        }

        window.clearTimeout(this.syncTimeoutId);
        this.syncTimeoutId = null;
    }
}

/* stimulusFetch: 'lazy' */
