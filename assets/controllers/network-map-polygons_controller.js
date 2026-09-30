import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';

export default class extends Controller {
    static values = {
        polygonsUrl: String,
        showAccessPoints: { type: Boolean, default: false },
        markerIcon: { type: String, default: '' },
        zoomThreshold: { type: Number, default: 12 }
    };

    connect() {
        this.currentFetchId = 0;
        this.element.addEventListener('ux:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
        if (this.map && this.moveEndHandler) {
            this.map.off('moveend', this.moveEndHandler);
        }
        if (this.fetchTimeout) clearTimeout(this.fetchTimeout);
    }

    onMapConnect = async (event) => {
        this.map = event.detail.map;
        window.L = L;

        if (!L.markerClusterGroup) {
            await import('leaflet.markercluster');
        }

        this.polygonGroup = L.layerGroup().addTo(this.map);

        this.clusterGroup = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 80,
            chunkedLoading: false,
            animate: false,
            iconCreateFunction: (cluster) => {
                const childCount = cluster.getChildCount();

                return L.divIcon({
                    html: `<div style="
                        width: 40px;
                        height: 40px;
                        background-color: #7c3aed;
                        color: #ffffff;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 15px;
                        font-weight: bold;
                        border: 3px solid #ffffff;
                        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                        box-sizing: border-box;
                        z-index: 999999;
                    ">${childCount}</div>`,
                    className: 'custom-standalone-cluster coverage-network-marker',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                });
            }
        }).addTo(this.map);

        this.moveEndHandler = () => {
            clearTimeout(this.fetchTimeout);
            this.fetchTimeout = setTimeout(() => {
                this.fetchAndRender();
            }, 300);
        };

        this.map.on('moveend', this.moveEndHandler);
        this.fetchAndRender();
    };

    async fetchAndRender() {
        if (!this.map || !this.polygonGroup || !this.clusterGroup) return;

        const fetchId = ++this.currentFetchId;
        const bounds = this.map.getBounds();
        const params = new URLSearchParams({
            minLat: bounds.getSouth(),
            minLng: bounds.getWest(),
            maxLat: bounds.getNorth(),
            maxLng: bounds.getEast(),
        });

        let data;
        try {
            const response = await fetch(`${this.polygonsUrlValue}?${params}`);
            if (!response.ok) return;
            data = await response.json();
        } catch (e) {
            return;
        }

        if (this.currentFetchId !== fetchId) return;

        this.polygonGroup.clearLayers();
        this.clusterGroup.clearLayers();
        this.pendingMarkers = [];

        const currentZoom = this.map.getZoom();
        const showPolygons = currentZoom >= this.zoomThresholdValue;

        (data.networks ?? []).forEach((network) => {
            try { this.drawNetwork(network, showPolygons); } catch (e) {}
        });

        if (this.showAccessPointsValue && showPolygons) {
            (data.accessPoints ?? []).forEach((ap) => {
                try { this.drawAccessPoint(ap); } catch (e) {}
            });
        }

        const validMarkers = this.pendingMarkers.filter(marker => {
            const latLng = marker.getLatLng();
            return latLng && this._isValidCoord(latLng.lat) && this._isValidCoord(latLng.lng);
        });

        if (validMarkers.length > 0) {
            this.clusterGroup.addLayers(validMarkers);
        }
    }

    drawNetwork(network, showPolygons) {
        const geometry = network.geometry;
        if (!geometry || !geometry.type || !geometry.coordinates) return;

        const toLatLngRing = (ring) => {
            if (!Array.isArray(ring)) return [];
            return ring
                .map(([lng, lat]) => [parseFloat(lat), parseFloat(lng)])
                .filter(([lat, lng]) => this._isValidCoord(lat) && this._isValidCoord(lng));
        };

        if (showPolygons) {
            const drawSinglePolygon = (rings) => {
                if (!rings || rings.length === 0) return;
                L.polygon(rings, {
                    weight: 2,
                    dashArray: '6, 8',
                    color: '#7c3aed',
                    fillColor: '#8b5cf6',
                    fillOpacity: 0.18,
                    fillRule: 'nonzero',
                })
                    .addTo(this.polygonGroup)
                    .bindPopup(network.name || 'Network Area');
            };

            if (geometry.type === 'Polygon') drawSinglePolygon(geometry.coordinates.map(toLatLngRing));
            else if (geometry.type === 'MultiPolygon') geometry.coordinates.forEach((polygonRings) => drawSinglePolygon(polygonRings.map(toLatLngRing)));
        } else {
            const drawCentroidMarker = (polygonCoordinates) => {
                try {
                    const outerRing = polygonCoordinates[0];
                    if (!outerRing || outerRing.length === 0) return;
                    const latLngs = outerRing.map(([lng, lat]) => [parseFloat(lat), parseFloat(lng)]).filter(([lat, lng]) => this._isValidCoord(lat) && this._isValidCoord(lng));
                    if (latLngs.length === 0) return;
                    const center = L.polygon(latLngs).getBounds().getCenter();
                    if (!this._isValidCoord(center.lat) || !this._isValidCoord(center.lng)) return;

                    const marker = L.marker([center.lat, center.lng], { icon: this._getIcon() }).bindPopup(`<b>${network.name || 'Network'}</b>`);
                    this.pendingMarkers.push(marker);
                } catch (e) {}
            };

            if (geometry.type === 'Polygon') drawCentroidMarker(geometry.coordinates);
            else if (geometry.type === 'MultiPolygon') geometry.coordinates.forEach((polygonCoordinates) => drawCentroidMarker(polygonCoordinates));
        }
    }

    drawAccessPoint(ap) {
        const lat = parseFloat(ap.lat);
        const lng = parseFloat(ap.lng);
        if (!this._isValidCoord(lat) || !this._isValidCoord(lng)) return;
        const marker = L.marker([lat, lng], { icon: this._getIcon() }).bindPopup(`<b>${ap.name || 'Access Point'}</b>`);
        this.pendingMarkers.push(marker);
    }

    _isValidCoord(val) {
        return typeof val === 'number' && !isNaN(val) && isFinite(val);
    }

    _getIcon() {
        const defaultPinHtml = `
            <svg width="30" height="36" viewBox="0 0 24 24" fill="#7c3aed" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="#7c3aed"/>
            </svg>
        `;

        return L.divIcon({
            html: this.hasMarkerIconValue && this.markerIconValue ? this.markerIconValue : defaultPinHtml,
            className: 'custom-pin-icon coverage-network-marker',
            iconSize: [30, 36],
            iconAnchor: [15, 36],
            popupAnchor: [0, -32],
        });
    }
}
