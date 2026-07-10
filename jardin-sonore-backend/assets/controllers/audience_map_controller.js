import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['mapContainer', 'status'];
    static values = {
        geographicModeId: String,
        geographicModeName: String,
        homeLatitude: Number,
        homeLongitude: Number,
        radiusKilometersId: String,
        latitudeFieldId: String,
        longitudeFieldId: String,
        municipalitySelectId: String,
        municipalityShapesJson: String,
        municipalityPointsJson: String,
        polygonResolveUrl: String,
        municipalitiesVisualizationUrl: String,
        messagesJson: String,
        interactive: Boolean,
    };

    connect() {
        this.drawnPolygonPoints = [];
        this.isDrawingPolygon = false;
        this.restoreStoredCustomPoint();
        this.handleMapConnect = this.handleMapConnect.bind(this);
        this.handleGeographicModeChange = this.handleGeographicModeChange.bind(this);
        this.handleRadiusKilometersChange = this.handleRadiusKilometersChange.bind(this);
        this.handleRadiusApplied = this.handleRadiusApplied.bind(this);
        this.handleMunicipalitySelectionChange = this.handleMunicipalitySelectionChange.bind(this);

        this.element.addEventListener('ux:map:connect', this.handleMapConnect);
        this.element.addEventListener('mailing:audience-radius-applied', this.handleRadiusApplied);
        this.geographicModeFields().forEach((fieldElement) => fieldElement.addEventListener('change', this.handleGeographicModeChange));
        this.radiusKilometersElement()?.addEventListener('input', this.handleRadiusKilometersChange);
        this.radiusKilometersElement()?.addEventListener('change', this.handleRadiusKilometersChange);
        const municipalitySelectElement = this.municipalitySelectElement();
        municipalitySelectElement?.addEventListener('change', this.handleMunicipalitySelectionChange);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.handleMapConnect);
        this.element.removeEventListener('mailing:audience-radius-applied', this.handleRadiusApplied);
        this.geographicModeFields().forEach((fieldElement) => fieldElement.removeEventListener('change', this.handleGeographicModeChange));
        this.radiusKilometersElement()?.removeEventListener('input', this.handleRadiusKilometersChange);
        this.radiusKilometersElement()?.removeEventListener('change', this.handleRadiusKilometersChange);
        const municipalitySelectElement = this.municipalitySelectElement();
        municipalitySelectElement?.removeEventListener('change', this.handleMunicipalitySelectionChange);

        if (this.mapClickHandler && this.mapInstance) {
            this.mapInstance.off('click', this.mapClickHandler);
        }

        this.municipalityShapeLayer?.remove();
        this.municipalityPointLayer?.remove();
        this.clearRadiusPreview();
        this.clearDrawnPolygonVisuals();
    }

    handleMapConnect(event) {
        if (this.hasMapContainerTarget && event.target instanceof Node && !this.mapContainerTarget.contains(event.target)) {
            return;
        }

        if (this.mapClickHandler && this.mapInstance) {
            this.mapInstance.off('click', this.mapClickHandler);
        }

        this.mapInstance = event.detail.map;
        this.markerInstance = event.detail.markers[0] ?? null;
        this.circleInstance = event.detail.circles[0] ?? null;
        this.markerInstance?.remove?.();
        this.circleInstance?.remove?.();
        this.markerInstance = null;
        this.circleInstance = null;
        this.renderMunicipalityShapes();
        this.renderMunicipalityPoints();
        this.updateSelectionToolState();
        requestAnimationFrame(() => this.updateSelectionToolState());

        if (!this.interactiveValue) {
            return;
        }

        this.mapClickHandler = (leafletEvent) => this.handleMapClick(leafletEvent.latlng);
        this.mapInstance.on('click', this.mapClickHandler);
    }

    handleGeographicModeChange() {
        this.updateSelectionToolState();
    }

    handleRadiusKilometersChange() {
        if (this.radiusModeIsActive()) {
            this.renderRadiusPreviewForCurrentMode();
        }
    }

    handleRadiusApplied() {
        this.drawnPolygonPoints = [];
        this.clearDrawnPolygonVisuals();
        this.clearRadiusPreview();
    }

    handleMunicipalitySelectionChange() {
        this.refreshMunicipalityVisualization();
    }

    handleMapClick(latlng) {
        if (this.isDrawingPolygon) {
            this.addPolygonPoint(latlng);
            return;
        }

        if (this.currentGeographicMode() === 'custom_radius') {
            this.selectCustomPoint(latlng);
        }
    }

    startPolygonDrawing() {
        if (!this.mapInstance) {
            return;
        }

        const switchedFromRadius = this.radiusModeIsActive();

        this.switchToMunicipalitiesMode();
        this.isDrawingPolygon = true;
        this.drawnPolygonPoints = [];
        this.clearRadiusPreview();
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
            this.isDrawingPolygon = true;
            this.drawnPolygonPoints = [];
            this.clearDrawnPolygonVisuals();
            this.updateStatus(this.message('polygon_applied').replace('%count%', `${municipalities.length}`));
        } catch (error) {
            console.error('Unable to resolve municipalities from polygon.', error);
            this.updateStatus(this.message('polygon_resolve_failed'));
        }
    }

    clearPolygonDrawing() {
        this.isDrawingPolygon = this.currentGeographicMode() === 'municipalities';
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

        this.clearDrawnPolygonVisuals();

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

    municipalityShapesJsonValueChanged() {
        this.renderMunicipalityShapes();
    }

    municipalityPointsJsonValueChanged() {
        this.renderMunicipalityPoints();
    }

    async refreshMunicipalityVisualization() {
        if (!this.mapInstance || typeof this.municipalitiesVisualizationUrlValue !== 'string' || this.municipalitiesVisualizationUrlValue === '') {
            return;
        }

        const municipalitySelectElement = this.municipalitySelectElement();

        if (!(municipalitySelectElement instanceof HTMLSelectElement)) {
            return;
        }

        const municipalityInseeCodes = this.selectedMunicipalityValues(municipalitySelectElement);

        try {
            const response = await fetch(this.municipalitiesVisualizationUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    municipalityInseeCodes,
                }),
            });

            if (!response.ok) {
                throw new Error(`Unexpected response status: ${response.status}`);
            }

            const payload = await response.json();
            this.municipalityShapesJsonValue = JSON.stringify(Array.isArray(payload.shapes) ? payload.shapes : []);
            this.municipalityPointsJsonValue = JSON.stringify(Array.isArray(payload.points) ? payload.points : []);
            this.renderMunicipalityShapes();
            this.renderMunicipalityPoints();
        } catch (error) {
            console.error('Unable to refresh selected municipality visualization.', error);
        }
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
            this.renderMunicipalityPoints();

            return;
        }

        const features = municipalityShapes
            .map((municipalityShape) => this.toFeature(municipalityShape))
            .filter((feature) => feature !== null);

        if (features.length === 0) {
            return;
        }

        this.municipalityShapeLayer = leaflet.geoJSON(features, {
            interactive: false,
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
        this.fitLayerBounds(this.municipalityShapeLayer);
        this.renderMunicipalityPoints();
    }

    renderMunicipalityPoints() {
        if (!this.mapInstance) {
            return;
        }

        this.municipalityPointLayer?.remove();
        this.municipalityPointLayer = null;

        const leaflet = globalThis.L;
        const municipalityPoints = this.parseMunicipalityPoints();

        if (!leaflet || municipalityPoints.length === 0) {
            return;
        }

        this.municipalityPointLayer = leaflet.layerGroup(
            municipalityPoints.map((municipalityPoint) => {
                const marker = leaflet.circleMarker([municipalityPoint.latitude, municipalityPoint.longitude], {
                    radius: 4,
                    color: '#47664B',
                    weight: 1.5,
                    fillColor: '#7FB089',
                    fillOpacity: 0.8,
                    interactive: false,
                });

                if (typeof municipalityPoint.label === 'string' && municipalityPoint.label !== '') {
                    marker.bindTooltip(municipalityPoint.label, {
                        sticky: true,
                        direction: 'top',
                    });
                }

                return marker;
            }),
        ).addTo(this.mapInstance);
        this.fitLayerBounds(this.municipalityPointLayer);
    }

    parseMunicipalityShapes() {
        if (typeof this.municipalityShapesJsonValue !== 'string' || this.municipalityShapesJsonValue === '') {
            return [];
        }

        try {
            const parsedShapes = JSON.parse(this.municipalityShapesJsonValue);

            return Array.isArray(parsedShapes)
                ? parsedShapes.filter((municipalityShape) => this.isValidMunicipalityShape(municipalityShape))
                : [];
        } catch (error) {
            console.warn('Unable to parse audience municipality shapes.', error);

            return [];
        }
    }

    parseMunicipalityPoints() {
        if (typeof this.municipalityPointsJsonValue !== 'string' || this.municipalityPointsJsonValue === '') {
            return [];
        }

        try {
            const parsedPoints = JSON.parse(this.municipalityPointsJsonValue);

            return Array.isArray(parsedPoints)
                ? parsedPoints.filter((municipalityPoint) => this.isValidMunicipalityPoint(municipalityPoint))
                : [];
        } catch (error) {
            console.warn('Unable to parse audience municipality points.', error);

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

    isValidMunicipalityShape(municipalityShape) {
        if (typeof municipalityShape !== 'object' || municipalityShape === null) {
            return false;
        }

        const geoShape = municipalityShape.geoShape;

        if (typeof geoShape !== 'object' || geoShape === null || typeof geoShape.type !== 'string') {
            return false;
        }

        return this.hasValidGeoJsonCoordinates(geoShape.coordinates);
    }

    isValidMunicipalityPoint(municipalityPoint) {
        if (typeof municipalityPoint !== 'object' || municipalityPoint === null) {
            return false;
        }

        return Number.isFinite(Number(municipalityPoint.latitude))
            && Number.isFinite(Number(municipalityPoint.longitude));
    }

    hasValidGeoJsonCoordinates(coordinates) {
        if (!Array.isArray(coordinates) || coordinates.length === 0) {
            return false;
        }

        if (coordinates.length >= 2
            && Number.isFinite(Number(coordinates[0]))
            && Number.isFinite(Number(coordinates[1]))) {
            return true;
        }

        return coordinates.every((coordinate) => Array.isArray(coordinate) && this.hasValidGeoJsonCoordinates(coordinate));
    }

    applyMunicipalityChoices(municipalities) {
        const municipalitySelectElement = this.municipalitySelectElement();

        if (!(municipalitySelectElement instanceof HTMLSelectElement)) {
            return;
        }

        this.switchToMunicipalitiesMode();
        const nextValues = new Set(this.selectedMunicipalityValues(municipalitySelectElement));

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

    geographicModeFields() {
        const geographicModeElement = document.getElementById(this.geographicModeIdValue);

        if (geographicModeElement instanceof HTMLSelectElement) {
            return [geographicModeElement];
        }

        return Array.from(document.querySelectorAll(`input[name="${this.geographicModeNameValue}"]`))
            .filter((fieldElement) => fieldElement instanceof HTMLInputElement);
    }

    currentGeographicMode() {
        const geographicModeField = this.geographicModeFields()[0];

        if (geographicModeField instanceof HTMLSelectElement) {
            return geographicModeField.value;
        }

        const checkedGeographicModeField = this.geographicModeFields().find((fieldElement) => fieldElement.checked);

        return checkedGeographicModeField instanceof HTMLInputElement
            ? checkedGeographicModeField.value
            : null;
    }

    setGeographicMode(mode) {
        const geographicModeField = this.geographicModeFields().find((fieldElement) => fieldElement.value === mode)
            ?? this.geographicModeFields()[0];

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

        if (geographicModeField.value !== mode) {
            geographicModeField.value = mode;
            this.dispatchFormInput(geographicModeField);
        }
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

    selectedMunicipalityValues(selectElement) {
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

    radiusKilometersElement() {
        return document.getElementById(this.radiusKilometersIdValue);
    }

    customLatitudeValue() {
        const latitudeFieldElement = this.latitudeFieldElement();

        return latitudeFieldElement instanceof HTMLInputElement && latitudeFieldElement.value.trim() !== ''
            ? Number(latitudeFieldElement.value)
            : null;
    }

    customLongitudeValue() {
        const longitudeFieldElement = this.longitudeFieldElement();

        return longitudeFieldElement instanceof HTMLInputElement && longitudeFieldElement.value.trim() !== ''
            ? Number(longitudeFieldElement.value)
            : null;
    }

    resolvedCustomLatitudeValue() {
        const latitude = this.customLatitudeValue();

        return latitude ?? this.storedCustomLatitude ?? null;
    }

    resolvedCustomLongitudeValue() {
        const longitude = this.customLongitudeValue();

        return longitude ?? this.storedCustomLongitude ?? null;
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

            this.storedCustomLatitude = latitude;
            this.storedCustomLongitude = longitude;
        } catch (error) {
            console.warn('Unable to restore stored custom audience point.', error);
        }
    }

    storeCustomPoint(latitude, longitude) {
        this.storedCustomLatitude = latitude;
        this.storedCustomLongitude = longitude;

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
        const geographicMode = this.currentGeographicMode();

        return geographicMode === 'home_radius' || geographicMode === 'custom_radius';
    }

    updateSelectionToolState() {
        const geographicMode = this.currentGeographicMode();
        this.drawnPolygonPoints = [];
        this.clearDrawnPolygonVisuals();

        if (geographicMode === 'home_radius' || geographicMode === 'custom_radius') {
            this.isDrawingPolygon = false;
            this.refreshMunicipalityVisualization();
            this.renderRadiusPreviewForCurrentMode();
            this.updateStatus(geographicMode === 'custom_radius'
                ? this.message('custom_radius_ready')
                : this.message('home_radius_ready'));

            return;
        }

        if (geographicMode === 'municipalities') {
            this.isDrawingPolygon = true;
            this.clearRadiusPreview();
            this.updateStatus(this.message('polygon_drawing_started'));

            return;
        }

        this.isDrawingPolygon = false;
        this.clearRadiusPreview();
        this.updateStatus('');
    }

    renderRadiusPreviewForCurrentMode() {
        const geographicMode = this.currentGeographicMode();

        if (geographicMode === 'home_radius') {
            this.renderRadiusPreview(this.homeLatitudeValue, this.homeLongitudeValue);

            return;
        }

        if (geographicMode === 'custom_radius') {
            const latitude = this.resolvedCustomLatitudeValue();
            const longitude = this.resolvedCustomLongitudeValue();

            if (latitude !== null && longitude !== null) {
                this.renderRadiusPreview(latitude, longitude);
            } else {
                this.clearRadiusPreview();
            }

            return;
        }

        this.clearRadiusPreview();
    }

    renderRadiusPreview(latitude, longitude) {
        if (!this.mapInstance) {
            return;
        }

        const leaflet = globalThis.L;

        if (!leaflet || !Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const latlng = [latitude, longitude];
        const radiusMeters = this.radiusMeters();

        this.radiusPreviewMarkerLayer?.remove?.();
        this.radiusPreviewCircleLayer?.remove?.();

        this.radiusPreviewMarkerLayer = leaflet.circleMarker(latlng, {
            radius: 6,
            color: '#A64D43',
            weight: 2,
            fillColor: '#F7D7C4',
            fillOpacity: 1,
            interactive: false,
        }).addTo(this.mapInstance);

        if (radiusMeters > 0) {
            this.radiusPreviewCircleLayer = leaflet.circle(latlng, {
                radius: radiusMeters,
                color: '#A64D43',
                weight: 2,
                fillColor: '#F3C9B2',
                fillOpacity: 0.14,
                interactive: false,
            }).addTo(this.mapInstance);

            return;
        }

        this.radiusPreviewCircleLayer = null;
    }

    clearRadiusPreview() {
        this.radiusPreviewMarkerLayer?.remove?.();
        this.radiusPreviewCircleLayer?.remove?.();
        this.radiusPreviewMarkerLayer = null;
        this.radiusPreviewCircleLayer = null;
    }

    radiusMeters() {
        const radiusKilometersElement = this.radiusKilometersElement();

        if (!(radiusKilometersElement instanceof HTMLInputElement)) {
            return 0;
        }

        const radiusKilometers = Number(radiusKilometersElement.value);

        return Number.isFinite(radiusKilometers) && radiusKilometers > 0
            ? radiusKilometers * 1000
            : 0;
    }

    clearRadiusMode() {
        const radiusKilometersElement = this.radiusKilometersElement();
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (radiusKilometersElement instanceof HTMLInputElement) {
            radiusKilometersElement.value = '';
            this.dispatchFormInput(radiusKilometersElement);
        }

        if (latitudeFieldElement instanceof HTMLInputElement) {
            latitudeFieldElement.value = '';
        }

        if (longitudeFieldElement instanceof HTMLInputElement) {
            longitudeFieldElement.value = '';
        }

        this.clearRadiusPreview();
    }

    selectCustomPoint(latlng) {
        const latitudeFieldElement = this.latitudeFieldElement();
        const longitudeFieldElement = this.longitudeFieldElement();

        if (!(latitudeFieldElement instanceof HTMLInputElement)
            || !(longitudeFieldElement instanceof HTMLInputElement)) {
            return;
        }

        latitudeFieldElement.value = latlng.lat.toFixed(6);
        longitudeFieldElement.value = latlng.lng.toFixed(6);

        this.storeCustomPoint(latlng.lat, latlng.lng);
        this.element.dispatchEvent(new CustomEvent('mailing:audience-custom-point-selected', {
            bubbles: true,
            detail: {
                latitude: latlng.lat,
                longitude: latlng.lng,
            },
        }));
        this.renderRadiusPreview(latlng.lat, latlng.lng);
        this.updateStatus(this.message('custom_radius_ready'));
    }

    switchToMunicipalitiesMode() {
        this.setGeographicMode('municipalities');
    }

    clearDrawnPolygonVisuals() {
        this.drawnPolygonLayer?.remove();
        this.drawnPolygonGuideLayer?.remove();
        this.drawnPolygonMarkerLayer?.remove();
        this.drawnPolygonLayer = null;
        this.drawnPolygonGuideLayer = null;
        this.drawnPolygonMarkerLayer = null;
    }

    fitLayerBounds(layer) {
        if (!this.mapInstance || !layer?.getBounds) {
            return;
        }

        const bounds = layer.getBounds();

        if (bounds?.isValid?.()) {
            this.mapInstance.fitBounds(bounds.pad(0.12), {
                animate: false,
            });
        }
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
