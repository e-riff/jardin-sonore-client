import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        originSelectId: String,
        latitudeFieldId: String,
        longitudeFieldId: String,
        municipalityShapesJson: String,
        interactive: Boolean,
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

        this.municipalityShapeLayer?.remove();
    }

    handleMapConnect(event) {
        if (this.mapClickHandler && this.mapInstance) {
            this.mapInstance.off('click', this.mapClickHandler);
        }

        this.mapInstance = event.detail.map;
        this.markerInstance = event.detail.markers[0] ?? null;
        this.circleInstance = event.detail.circles[0] ?? null;
        this.renderMunicipalityShapes();

        if (!this.interactiveValue) {
            return;
        }

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

    municipalityShapesJsonValueChanged() {
        this.renderMunicipalityShapes();
    }

    renderMunicipalityShapes() {
        if (!this.mapInstance) {
            return;
        }

        this.municipalityShapeLayer?.remove();
        this.municipalityShapeLayer = null;

        const leaflet = globalThis.L;
        const municipalityShapes = this.parseMunicipalityShapes();

        if (!leaflet || municipalityShapes.length === 0) {
            return;
        }

        const features = municipalityShapes
            .map((municipalityShape) => this.toFeature(municipalityShape))
            .filter((feature) => feature !== null);

        if (features.length === 0) {
            return;
        }

        this.municipalityShapeLayer = leaflet.geoJSON(features, {
            style: {
                color: '#47664B',
                weight: 1.5,
                fillColor: '#7FB089',
                fillOpacity: 0.18,
            },
            onEachFeature: (feature, layer) => {
                const label = feature?.properties?.label;

                if (typeof label === 'string' && label !== '') {
                    layer.bindTooltip(label, {
                        sticky: true,
                        direction: 'top',
                    });
                }
            },
        }).addTo(this.mapInstance);
    }

    parseMunicipalityShapes() {
        if (typeof this.municipalityShapesJsonValue !== 'string' || this.municipalityShapesJsonValue === '') {
            return [];
        }

        try {
            const parsedShapes = JSON.parse(this.municipalityShapesJsonValue);

            return Array.isArray(parsedShapes) ? parsedShapes : [];
        } catch (error) {
            console.warn('Unable to parse audience municipality shapes.', error);

            return [];
        }
    }

    toFeature(municipalityShape) {
        if (typeof municipalityShape !== 'object' || municipalityShape === null) {
            return null;
        }

        const geoShape = municipalityShape.geoShape;

        if (typeof geoShape !== 'object' || geoShape === null) {
            return null;
        }

        return {
            type: 'Feature',
            geometry: geoShape,
            properties: {
                inseeCode: municipalityShape.inseeCode ?? null,
                label: municipalityShape.label ?? null,
            },
        };
    }
}
