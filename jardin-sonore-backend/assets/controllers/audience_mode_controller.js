import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['radiusFieldset', 'locationFieldset'];

    connect() {
        this.isUpdating = false;
        this.handleChange = this.handleChange.bind(this);
        this.element.addEventListener('change', this.handleChange);
        this.updateState();
    }

    disconnect() {
        this.element.removeEventListener('change', this.handleChange);
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

            return;
        }

        this.disableFieldset(this.radiusFieldsetTarget, false);
        this.disableFieldset(this.locationFieldsetTarget, false);
        this.isUpdating = false;
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
}

/* stimulusFetch: 'lazy' */
