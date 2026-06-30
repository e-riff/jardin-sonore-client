import LeafletMapController from '@symfony/ux-leaflet-map';

export default class extends LeafletMapController {
    connect() {
        super.connect();

        this.handleOriginChange = this.handleOriginChange.bind(this);

        const originSelectElement = this.originSelectElement();

        if (originSelectElement instanceof HTMLSelectElement) {
            originSelectElement.addEventListener('change', this.handleOriginChange);
        }

        if (this.map && originSelectElement instanceof HTMLSelectElement) {
            this.mapClickHandler = (leafletEvent) => this.selectCustomPoint(leafletEvent.latlng);
            this.map.on('click', this.mapClickHandler);
        }
    }

    disconnect() {
        const originSelectElement = this.originSelectElement();

        if (originSelectElement instanceof HTMLSelectElement) {
            originSelectElement.removeEventListener('change', this.handleOriginChange);
        }

        if (this.map && this.mapClickHandler) {
            this.map.off('click', this.mapClickHandler);
        }

        if (super.disconnect) {
            super.disconnect();
        }
    }

    handleOriginChange() {
        const originSelectElement = this.originSelectElement();

        if (!(originSelectElement instanceof HTMLSelectElement) || originSelectElement.value === 'custom') {
            return;
        }

        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (latitudeFieldElement instanceof HTMLInputElement) {
            latitudeFieldElement.value = '';
        }

        if (longitudeFieldElement instanceof HTMLInputElement) {
            longitudeFieldElement.value = '';
        }
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

        originSelectElement.dispatchEvent(new Event('change', { bubbles: true }));
        latitudeFieldElement.dispatchEvent(new Event('change', { bubbles: true }));
        longitudeFieldElement.dispatchEvent(new Event('change', { bubbles: true }));
    }

    originSelectElement() {
        const elementId = this.element.dataset.audienceMapOriginSelectId;

        return elementId ? document.getElementById(elementId) : null;
    }

    latitudeFieldElement() {
        const elementId = this.element.dataset.audienceMapLatitudeFieldId;

        return elementId ? document.getElementById(elementId) : null;
    }

    longitudeFieldElement() {
        const elementId = this.element.dataset.audienceMapLongitudeFieldId;

        return elementId ? document.getElementById(elementId) : null;
    }
}
