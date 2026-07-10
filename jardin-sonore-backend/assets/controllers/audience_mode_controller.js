import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['radiusPanel', 'polygonTools', 'status'];
    static values = {
        geographicModeId: String,
        geographicModeName: String,
        municipalitySelectId: String,
        radiusKilometersId: String,
        latitudeFieldId: String,
        longitudeFieldId: String,
        applyRadiusUrl: String,
        messagesJson: String,
    };

    connect() {
        this.restoreStoredCustomPoint();
        this.updateState();
        requestAnimationFrame(() => this.updateState());
    }

    handleGeographicModeChange(event) {
        if (!this.isGeographicModeField(event.target)) {
            return;
        }

        this.updateState();
    }

    handleCustomPointSelected(event) {
        const latitude = Number(event.detail?.latitude);
        const longitude = Number(event.detail?.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        this.lastCustomLatitude = latitude;
        this.lastCustomLongitude = longitude;
        this.storeCustomPoint(latitude, longitude);
    }

    updateState() {
        const geographicMode = this.currentGeographicMode();
        const municipalitiesModeActive = geographicMode === 'municipalities';
        const radiusModeActive = geographicMode === 'home_radius' || geographicMode === 'custom_radius';

        if (this.hasRadiusPanelTarget) {
            this.radiusPanelTarget.hidden = !radiusModeActive;
        }

        if (this.hasPolygonToolsTarget) {
            this.polygonToolsTarget.hidden = !municipalitiesModeActive;
        }

        if (!radiusModeActive) {
            this.clearRadiusFields();
        }
    }

    async applyRadius() {
        const radiusKilometersElement = this.radiusKilometersElement();

        if (!(radiusKilometersElement instanceof HTMLInputElement) || radiusKilometersElement.value.trim() === '') {
            this.updateStatus(this.message('tool_missing_selection'));

            return;
        }

        this.updateStatus(this.message('tool_loading'));

        try {
            const response = await fetch(this.applyRadiusUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    radiusKilometers: Number(radiusKilometersElement.value),
                    radiusOrigin: this.currentGeographicMode() === 'custom_radius' ? 'custom' : 'home',
                    latitude: this.currentGeographicMode() === 'custom_radius' ? this.resolvedCustomLatitudeValue() : null,
                    longitude: this.currentGeographicMode() === 'custom_radius' ? this.resolvedCustomLongitudeValue() : null,
                }),
            });

            if (!response.ok) {
                throw new Error(`Unexpected response status: ${response.status}`);
            }

            const responsePayload = await response.json();
            const municipalities = Array.isArray(responsePayload.results) ? responsePayload.results : [];

            this.applyMunicipalityChoices(municipalities);
            this.element.dispatchEvent(new CustomEvent('mailing:audience-radius-applied', {
                bubbles: true,
            }));
            this.setGeographicMode('municipalities');
            this.updateState();
            this.updateStatus(this.message('tool_applied').replace('%count%', `${municipalities.length}`));
        } catch (error) {
            console.error('Unable to apply audience radius tool.', error);
            this.updateStatus('');
        }
    }

    applyMunicipalityChoices(municipalities) {
        const municipalitySelectElement = this.municipalitySelectElement();

        if (!(municipalitySelectElement instanceof HTMLSelectElement)) {
            return;
        }

        const nextValues = new Set(this.selectedValues(municipalitySelectElement));

        municipalities.forEach((municipality) => {
            if (typeof municipality?.value === 'string' && municipality.value !== '') {
                nextValues.add(municipality.value);
            }
        });

        if ('tomselect' in municipalitySelectElement && municipalitySelectElement.tomselect) {
            municipalities.forEach((municipality) => {
                if (typeof municipality?.value !== 'string' || municipality.value === '') {
                    return;
                }

                municipalitySelectElement.tomselect.addOption({
                    value: municipality.value,
                    text: municipality.label ?? municipality.value,
                });
            });
            municipalitySelectElement.tomselect.setValue(Array.from(nextValues), true);
            this.dispatchFormInput(municipalitySelectElement);

            return;
        }

        municipalities.forEach((municipality) => {
            if (typeof municipality?.value !== 'string' || municipality.value === '') {
                return;
            }

            let optionElement = Array.from(municipalitySelectElement.options)
                .find((candidateOptionElement) => candidateOptionElement.value === municipality.value);

            if (!(optionElement instanceof HTMLOptionElement)) {
                optionElement = new Option(
                    municipality.label ?? municipality.value,
                    municipality.value,
                    true,
                    true,
                );
                municipalitySelectElement.add(optionElement);
            }

            optionElement.selected = true;
        });

        this.dispatchFormInput(municipalitySelectElement);
    }

    clearRadiusFields() {
        const radiusKilometersElement = this.radiusKilometersElement();

        if (radiusKilometersElement instanceof HTMLInputElement && this.currentGeographicMode() !== 'home_radius' && radiusKilometersElement.value !== '') {
            radiusKilometersElement.value = '';
            this.dispatchFormInput(radiusKilometersElement);
        }
    }

    currentGeographicMode() {
        const checkedGeographicModeField = this.checkedGeographicModeField();

        return checkedGeographicModeField instanceof HTMLInputElement || checkedGeographicModeField instanceof HTMLSelectElement
            ? checkedGeographicModeField.value
            : null;
    }

    setGeographicMode(mode) {
        const geographicModeField = this.geographicModeField(mode);

        if (!(geographicModeField instanceof HTMLInputElement) && !(geographicModeField instanceof HTMLSelectElement)) {
            return;
        }

        if (geographicModeField instanceof HTMLInputElement) {
            if (geographicModeField.checked) {
                return;
            }

            geographicModeField.checked = true;
            this.dispatchFormInput(geographicModeField);

            return;
        }

        if (geographicModeField.value === mode) {
            return;
        }

        geographicModeField.value = mode;
        this.dispatchFormInput(geographicModeField);
    }

    geographicModeElement() {
        return document.getElementById(this.geographicModeIdValue);
    }

    isGeographicModeField(fieldElement) {
        if (fieldElement instanceof HTMLSelectElement) {
            return fieldElement === this.geographicModeElement();
        }

        return fieldElement instanceof HTMLInputElement
            && fieldElement.name === this.geographicModeNameValue;
    }

    checkedGeographicModeField() {
        const geographicModeElement = this.geographicModeElement();

        if (geographicModeElement instanceof HTMLSelectElement) {
            return geographicModeElement;
        }

        return Array.from(document.querySelectorAll(`input[name="${this.geographicModeNameValue}"]`))
            .find((fieldElement) => fieldElement instanceof HTMLInputElement && fieldElement.checked) ?? null;
    }

    geographicModeField(mode) {
        const geographicModeElement = this.geographicModeElement();

        if (geographicModeElement instanceof HTMLSelectElement) {
            return geographicModeElement;
        }

        return Array.from(document.querySelectorAll(`input[name="${this.geographicModeNameValue}"]`))
            .find((fieldElement) => fieldElement instanceof HTMLInputElement && fieldElement.value === mode) ?? null;
    }

    municipalitySelectElement() {
        return document.getElementById(this.municipalitySelectIdValue);
    }

    radiusKilometersElement() {
        return document.getElementById(this.radiusKilometersIdValue);
    }

    customLatitudeValue() {
        const latitudeFieldElement = document.getElementById(this.latitudeFieldIdValue);

        return latitudeFieldElement instanceof HTMLInputElement && latitudeFieldElement.value.trim() !== ''
            ? Number(latitudeFieldElement.value)
            : null;
    }

    customLongitudeValue() {
        const longitudeFieldElement = document.getElementById(this.longitudeFieldIdValue);

        return longitudeFieldElement instanceof HTMLInputElement && longitudeFieldElement.value.trim() !== ''
            ? Number(longitudeFieldElement.value)
            : null;
    }

    resolvedCustomLatitudeValue() {
        const latitude = this.customLatitudeValue();

        return latitude ?? this.lastCustomLatitude ?? null;
    }

    resolvedCustomLongitudeValue() {
        const longitude = this.customLongitudeValue();

        return longitude ?? this.lastCustomLongitude ?? null;
    }

    restoreStoredCustomPoint() {
        try {
            const storedCustomPoint = sessionStorage.getItem(this.customPointStorageKey());

            if (typeof storedCustomPoint !== 'string' || storedCustomPoint === '') {
                return;
            }

            const parsedCustomPoint = JSON.parse(storedCustomPoint);
            const latitude = Number(parsedCustomPoint?.latitude);
            const longitude = Number(parsedCustomPoint?.longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            this.lastCustomLatitude = latitude;
            this.lastCustomLongitude = longitude;
        } catch (error) {
            console.warn('Unable to restore stored custom audience point.', error);
        }
    }

    storeCustomPoint(latitude, longitude) {
        try {
            sessionStorage.setItem(this.customPointStorageKey(), JSON.stringify({
                latitude,
                longitude,
            }));
        } catch (error) {
            console.warn('Unable to store custom audience point.', error);
        }
    }

    customPointStorageKey() {
        return `mailing-audience-custom-point:${this.latitudeFieldIdValue}:${this.longitudeFieldIdValue}`;
    }

    selectedValues(selectElement) {
        if (!(selectElement instanceof HTMLSelectElement)) {
            return [];
        }

        if ('tomselect' in selectElement && selectElement.tomselect) {
            const selectedValues = selectElement.tomselect.getValue();
            const normalizedValues = Array.isArray(selectedValues) ? selectedValues : [selectedValues];

            return normalizedValues.filter((value) => typeof value === 'string' && value !== '');
        }

        return Array.from(selectElement.selectedOptions)
            .map((optionElement) => optionElement.value)
            .filter((value) => value !== '');
    }

    updateStatus(message) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
    }

    dispatchFormInput(fieldElement) {
        fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
        fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
    }

    message(key) {
        const messages = this.messages();

        return typeof messages[key] === 'string' ? messages[key] : '';
    }

    messages() {
        if (this.parsedMessages) {
            return this.parsedMessages;
        }

        if (typeof this.messagesJsonValue !== 'string' || this.messagesJsonValue === '') {
            this.parsedMessages = {};

            return this.parsedMessages;
        }

        try {
            const parsedMessages = JSON.parse(this.messagesJsonValue);
            this.parsedMessages = typeof parsedMessages === 'object' && parsedMessages !== null ? parsedMessages : {};
        } catch (error) {
            console.warn('Unable to parse audience mode messages.', error);
            this.parsedMessages = {};
        }

        return this.parsedMessages;
    }
}

/* stimulusFetch: 'lazy' */
