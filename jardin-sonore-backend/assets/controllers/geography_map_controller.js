import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { geoShape: String };

    connect() {
        this.handleMapConnect = this.handleMapConnect.bind(this);
        this.element.addEventListener('ux:map:connect', this.handleMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.handleMapConnect);
        this.shapeLayer?.remove();
    }

    handleMapConnect(event) {
        if (!(event.target instanceof Node) || !this.element.contains(event.target)) {
            return;
        }

        this.shapeLayer?.remove();
        const geoShape = this.parseGeoShape();
        const leaflet = globalThis.L;
        if (!leaflet || !this.isGeoJsonGeometry(geoShape)) {
            return;
        }

        this.shapeLayer = leaflet.geoJSON(geoShape, {
            style: {
                color: '#bc4b3f',
                fillColor: '#bc4b3f',
                fillOpacity: 0.12,
                weight: 2,
            },
        }).addTo(event.detail.map);

        const bounds = this.shapeLayer.getBounds();
        if (bounds.isValid()) {
            event.detail.map.fitBounds(bounds, { padding: [18, 18], maxZoom: 13 });
        }
    }

    parseGeoShape() {
        try {
            return JSON.parse(this.geoShapeValue);
        } catch {
            return null;
        }
    }

    isGeoJsonGeometry(geoShape) {
        return geoShape !== null
            && typeof geoShape === 'object'
            && ['Polygon', 'MultiPolygon'].includes(geoShape.type)
            && Array.isArray(geoShape.coordinates);
    }
}
