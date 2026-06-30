import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        originSelectId: String,
        latitudeFieldId: String,
        longitudeFieldId: String,
    };

    connect() {
        this.handleMapConnect = this.handleMapConnect.bind(this);
        this.handleOriginChange = this.handleOriginChange.bind(this);

        this.element.addEventListener('ux:map:connect', this.handleMapConnect);
        this.originSelectElement()?.addEventListener('change', this.handleOriginChange);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.handleMapConnect);
        this.originSelectElement()?.removeEventListener('change', this.handleOriginChange);

        if (this.mapClickHandler && this.mapInstance) {
            this.mapInstance.off('click', this.mapClickHandler);
        }
    }

    handleMapConnect(event) {
        if (this.mapClickHandler && this.mapInstance) {
            this.mapInstance.off('click', this.mapClickHandler);
        }

        this.mapInstance = event.detail.map;
        this.markerInstance = event.detail.markers[0] ?? null;
        this.circleInstance = event.detail.circles[0] ?? null;
        this.mapClickHandler = (leafletEvent) => this.selectCustomPoint(leafletEvent.latlng);
        this.mapInstance.on('click', this.mapClickHandler);
    }

    handleOriginChange() {
        const originSelectElement = this.originSelectElement();
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (!(originSelectElement instanceof HTMLSelectElement)
            || !(latitudeFieldElement instanceof HTMLInputElement)
            || !(longitudeFieldElement instanceof HTMLInputElement)
            || originSelectElement.value === 'custom') {
            return;
        }

        latitudeFieldElement.value = '';
        longitudeFieldElement.value = '';
    }

    selectCustomPoint(latlng) {
        const originSelectElement = this.originSelectElement();
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (!(originSelectElement instanceof HTMLSelectElement)
            || !(latitudeFieldElement instanceof HTMLInputElement)
            || !(longitudeFieldElement instanceof HTMLInputElement)) {
            return;
        }

        originSelectElement.value = 'custom';
        latitudeFieldElement.value = latlng.lat.toFixed(6);
        longitudeFieldElement.value = latlng.lng.toFixed(6);

        this.markerInstance?.setLatLng(latlng);
        this.circleInstance?.setLatLng(latlng);

        this.dispatchFormInput(originSelectElement);
        this.dispatchFormInput(latitudeFieldElement);
        this.dispatchFormInput(longitudeFieldElement);
    }

    originSelectElement() {
        return document.getElementById(this.originSelectIdValue);
    }

    latitudeFieldElement() {
        return document.getElementById(this.latitudeFieldIdValue);
    }

    longitudeFieldElement() {
        return document.getElementById(this.longitudeFieldIdValue);
    }

    dispatchFormInput(fieldElement) {
        fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
        fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
