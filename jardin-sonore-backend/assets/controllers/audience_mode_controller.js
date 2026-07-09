import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['radiusFieldset', 'locationFieldset', 'polygonTools'];

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
        const geographicMode = this.currentGeographicMode();
        const municipalitiesMode = geographicMode === 'municipalities';

        this.disableFieldset(this.radiusFieldsetTarget, municipalitiesMode);
        this.disableFieldset(this.locationFieldsetTarget, !municipalitiesMode);
        this.togglePolygonTools(municipalitiesMode);
        this.isUpdating = false;
    }

    disableFieldset(fieldsetElement, disabled) {
        if (!(fieldsetElement instanceof HTMLElement)) {
            return;
        }

        if (fieldsetElement instanceof HTMLFieldSetElement) {
            fieldsetElement.disabled = disabled;
        }

        fieldsetElement.querySelectorAll('input, select, textarea, button').forEach((fieldElement) => {
            if (fieldElement instanceof HTMLButtonElement && this.hasPolygonToolsTarget && this.polygonToolsTarget.contains(fieldElement)) {
                return;
            }

            if (fieldElement instanceof HTMLInputElement
                || fieldElement instanceof HTMLSelectElement
                || fieldElement instanceof HTMLTextAreaElement
                || fieldElement instanceof HTMLButtonElement) {
                fieldElement.disabled = disabled;
            }
        });

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

    currentGeographicMode() {
        const checkedModeElement = this.element.querySelector('input[name$="[geographicMode]"]:checked');

        return checkedModeElement instanceof HTMLInputElement ? checkedModeElement.value : null;
    }

    togglePolygonTools(visible) {
        if (!this.hasPolygonToolsTarget) {
            return;
        }

        this.polygonToolsTarget.hidden = !visible;
    }
}

/* stimulusFetch: 'lazy' */
