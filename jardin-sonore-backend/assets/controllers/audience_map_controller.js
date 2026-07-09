import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['mapContainer', 'status'];
    static values = {
        originSelectId: String,
        radiusKilometersId: String,
        radiusOriginMunicipalityId: String,
        latitudeFieldId: String,
        longitudeFieldId: String,
        municipalitySelectId: String,
        municipalityShapesJson: String,
        polygonResolveUrl: String,
        messagesJson: String,
        interactive: Boolean,
    };

    connect() {
        this.drawnPolygonPoints = [];
        this.isDrawingPolygon = false;
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
        this.drawnPolygonLayer?.remove();
        this.drawnPolygonGuideLayer?.remove();
        this.drawnPolygonMarkerLayer?.remove();
    }

    handleMapConnect(event) {
        if (this.hasMapContainerTarget && event.target !== this.mapContainerTarget.firstElementChild) {
            return;
        }

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

        this.mapClickHandler = (leafletEvent) => this.handleMapClick(leafletEvent.latlng);
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

        this.clearDrawnPolygonVisuals();
        latitudeFieldElement.value = '';
        longitudeFieldElement.value = '';
    }

    handleMapClick(latlng) {
        if (this.isDrawingPolygon) {
            this.addPolygonPoint(latlng);

            return;
        }

        this.selectCustomPoint(latlng);
    }

    startPolygonDrawing() {
        if (!this.mapInstance) {
            return;
        }

        const switchedFromRadius = this.radiusModeIsActive();

        this.clearRadiusMode();
        this.isDrawingPolygon = true;
        this.drawnPolygonPoints = [];
        this.clearDrawnPolygonVisuals();
        this.updateStatus(
            switchedFromRadius
                ? this.message('polygon_switches_from_radius')
                : this.message('polygon_drawing_started'),
        );
    }

    async finishPolygonDrawing() {
        if (!this.isDrawingPolygon || this.drawnPolygonPoints.length < 3) {
            this.updateStatus(this.message('polygon_needs_three_points'));

            return;
        }

        this.isDrawingPolygon = false;
        this.renderDrawnPolygon();
        this.updateStatus(this.message('polygon_resolving'));

        try {
            const response = await fetch(this.polygonResolveUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    points: this.drawnPolygonPoints.map((polygonPoint) => ({
                        lat: polygonPoint.lat,
                        lng: polygonPoint.lng,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error(`Unexpected response status: ${response.status}`);
            }

            const payload = await response.json();
            const municipalities = Array.isArray(payload.results) ? payload.results : [];

            if (municipalities.length === 0) {
                this.updateStatus(this.message('polygon_empty'));

                return;
            }

            this.applyMunicipalityChoices(municipalities);
            this.updateStatus(this.message('polygon_applied').replace('%count%', `${municipalities.length}`));
        } catch (error) {
            console.error('Unable to resolve municipalities from polygon.', error);
            this.updateStatus(this.message('polygon_resolve_failed'));
        }
    }

    clearPolygonDrawing() {
        this.isDrawingPolygon = false;
        this.drawnPolygonPoints = [];
        this.clearDrawnPolygonVisuals();
        this.updateStatus(this.message('polygon_cleared'));
    }

    addPolygonPoint(latlng) {
        this.drawnPolygonPoints = [...this.drawnPolygonPoints, latlng];
        this.renderDrawnPolygon();
        this.updateStatus(this.message('polygon_points_count').replace('%count%', `${this.drawnPolygonPoints.length}`));
    }

    renderDrawnPolygon() {
        if (!this.mapInstance) {
            return;
        }

        const leaflet = globalThis.L;

        if (!leaflet) {
            return;
        }

        this.drawnPolygonLayer?.remove();
        this.drawnPolygonGuideLayer?.remove();
        this.drawnPolygonMarkerLayer?.remove();
        this.drawnPolygonLayer = null;
        this.drawnPolygonGuideLayer = null;
        this.drawnPolygonMarkerLayer = null;

        if (this.drawnPolygonPoints.length === 0) {
            return;
        }

        this.drawnPolygonMarkerLayer = leaflet.layerGroup(
            this.drawnPolygonPoints.map((polygonPoint) => leaflet.circleMarker(polygonPoint, {
                radius: 4,
                color: '#A64D43',
                weight: 2,
                fillColor: '#F7D7C4',
                fillOpacity: 1,
            })),
        ).addTo(this.mapInstance);

        if (this.drawnPolygonPoints.length >= 2) {
            this.drawnPolygonGuideLayer = leaflet.polyline(this.drawnPolygonPoints, {
                color: '#A64D43',
                weight: 2,
                dashArray: '6 6',
            }).addTo(this.mapInstance);
        }

        if (this.drawnPolygonPoints.length >= 3) {
            this.drawnPolygonLayer = leaflet.polygon(this.drawnPolygonPoints, {
                color: '#A64D43',
                weight: 2,
                fillColor: '#F3C9B2',
                fillOpacity: 0.18,
            }).addTo(this.mapInstance);
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

        this.isDrawingPolygon = false;
        this.clearDrawnPolygonVisuals();
        originSelectElement.value = 'custom';
        latitudeFieldElement.value = latlng.lat.toFixed(6);
        longitudeFieldElement.value = latlng.lng.toFixed(6);

        this.markerInstance?.setLatLng(latlng);
        this.circleInstance?.setLatLng(latlng);

        this.dispatchFormInput(originSelectElement);
        this.dispatchFormInput(latitudeFieldElement);
        this.dispatchFormInput(longitudeFieldElement);
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

    applyMunicipalityChoices(municipalities) {
        const municipalitySelectElement = this.municipalitySelectElement();
        const originSelectElement = this.originSelectElement();
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (!(municipalitySelectElement instanceof HTMLSelectElement)) {
            return;
        }

        if (originSelectElement instanceof HTMLSelectElement) {
            originSelectElement.value = '';
            this.dispatchFormInput(originSelectElement);
        }

        if (latitudeFieldElement instanceof HTMLInputElement) {
            latitudeFieldElement.value = '';
            this.dispatchFormInput(latitudeFieldElement);
        }

        if (longitudeFieldElement instanceof HTMLInputElement) {
            longitudeFieldElement.value = '';
            this.dispatchFormInput(longitudeFieldElement);
        }

        const nextValues = new Set();

        Array.from(municipalitySelectElement.options).forEach((optionElement) => {
            if (optionElement.selected) {
                nextValues.add(optionElement.value);
            }
        });

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

    originSelectElement() {
        return document.getElementById(this.originSelectIdValue);
    }

    latitudeFieldElement() {
        return document.getElementById(this.latitudeFieldIdValue);
    }

    longitudeFieldElement() {
        return document.getElementById(this.longitudeFieldIdValue);
    }

    municipalitySelectElement() {
        return document.getElementById(this.municipalitySelectIdValue);
    }

    radiusKilometersElement() {
        return document.getElementById(this.radiusKilometersIdValue);
    }

    radiusOriginMunicipalityElement() {
        return document.getElementById(this.radiusOriginMunicipalityIdValue);
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

    radiusModeIsActive() {
        const originSelectElement = this.originSelectElement();

        return originSelectElement instanceof HTMLSelectElement && originSelectElement.value.trim() !== '';
    }

    clearRadiusMode() {
        const originSelectElement = this.originSelectElement();
        const radiusKilometersElement = this.radiusKilometersElement();
        const radiusOriginMunicipalityElement = this.radiusOriginMunicipalityElement();
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (originSelectElement instanceof HTMLSelectElement && originSelectElement.value.trim() !== '') {
            originSelectElement.value = '';
            this.dispatchFormInput(originSelectElement);
        }

        if (radiusKilometersElement instanceof HTMLInputElement) {
            radiusKilometersElement.value = '';
            this.dispatchFormInput(radiusKilometersElement);
        }

        this.clearSelectValue(radiusOriginMunicipalityElement);

        if (latitudeFieldElement instanceof HTMLInputElement) {
            latitudeFieldElement.value = '';
            this.dispatchFormInput(latitudeFieldElement);
        }

        if (longitudeFieldElement instanceof HTMLInputElement) {
            longitudeFieldElement.value = '';
            this.dispatchFormInput(longitudeFieldElement);
        }

        this.circleInstance?.remove?.();
        this.circleInstance = null;
    }

    clearSelectValue(selectElement) {
        if (!(selectElement instanceof HTMLSelectElement)) {
            return;
        }

        if ('tomselect' in selectElement && selectElement.tomselect) {
            selectElement.tomselect.clear(true);
            this.dispatchFormInput(selectElement);

            return;
        }

        selectElement.value = '';
        this.dispatchFormInput(selectElement);
    }

    clearDrawnPolygonVisuals() {
        this.drawnPolygonLayer?.remove();
        this.drawnPolygonGuideLayer?.remove();
        this.drawnPolygonMarkerLayer?.remove();
        this.drawnPolygonLayer = null;
        this.drawnPolygonGuideLayer = null;
        this.drawnPolygonMarkerLayer = null;
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
            console.warn('Unable to parse audience map messages.', error);
            this.parsedMessages = {};
        }

        return this.parsedMessages;
    }
}
