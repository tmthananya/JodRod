/**
 * JodRod - Real Interactive Map Controller
 * Uses Leaflet.js with CartoDB Voyager tiles.
 * Supports: Zoom, Pan, Click Location (lat/lng), City Shortcuts.
 * Zero parking data initially (strict compliance with BRIEF.md).
 */

class ParkingMapController {
  constructor(containerId) {
    this.containerId = containerId;
    this.map = null;
    this.parkingLayer = null;
    this.selectionMarker = null;
    this.selectedCoords = { lat: 18.7883, lng: 98.9853 }; // Initial: Chiang Mai

    // City Coordinates Dictionary
    this.cities = {
      'chiangmai': { name: 'Chiang Mai', lat: 18.7883, lng: 98.9853, zoom: 13 },
      'bangkok':   { name: 'Bangkok',    lat: 13.7563, lng: 100.5018, zoom: 13 }
    };

    this.initMap();
    this.bindEvents();
    this.updateCoordsDisplay();
  }

  initMap() {
    const container = document.getElementById(this.containerId);
    if (!container || !window.L) {
      console.warn('Map container or Leaflet library not found.');
      return;
    }

    // Initialize Leaflet map with zoom and drag enabled
    this.map = L.map(this.containerId, {
      center: [this.selectedCoords.lat, this.selectedCoords.lng],
      zoom: 13,
      zoomControl: true,
      scrollWheelZoom: true,
      dragging: true
    });

    // Real Geographic Tile Layer (OpenStreetMap - Free & No API Key Required)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(this.map);

    // Layer group for parking pins - explicitly initialized with ZERO records
    this.parkingLayer = L.layerGroup().addTo(this.map);

    // Map Click Listener -> capture real GPS coordinates
    this.map.on('click', (e) => {
      this.handleMapClick(e.latlng.lat, e.latlng.lng);
    });

    // Initial crosshair marker at default center
    this.placeSelectionMarker(this.selectedCoords.lat, this.selectedCoords.lng);
  }

  bindEvents() {
    // City Navigation Shortcut Buttons
    document.querySelectorAll('.btn-city-jump').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const cityKey = e.currentTarget.dataset.city;
        this.goToCity(cityKey);
      });
    });
  }

  handleMapClick(lat, lng) {
    this.selectedCoords = { lat: parseFloat(lat), lng: parseFloat(lng) };
    this.placeSelectionMarker(this.selectedCoords.lat, this.selectedCoords.lng);
    this.updateCoordsDisplay();
  }

  placeSelectionMarker(lat, lng) {
    if (!this.map) return;

    if (this.selectionMarker) {
      this.selectionMarker.setLatLng([lat, lng]);
    } else {
      const crosshairIcon = L.divIcon({
        className: 'crosshair-marker',
        html: '<span>🎯</span>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });

      this.selectionMarker = L.marker([lat, lng], {
        icon: crosshairIcon,
        title: 'Selected Location'
      }).addTo(this.map);
    }
  }

  updateCoordsDisplay() {
    const latEl = document.getElementById('selected-lat');
    const lngEl = document.getElementById('selected-lng');
    const fullEl = document.getElementById('selected-coords-full');

    if (latEl) latEl.textContent = this.selectedCoords.lat.toFixed(5);
    if (lngEl) lngEl.textContent = this.selectedCoords.lng.toFixed(5);
    if (fullEl) {
      fullEl.textContent = `${this.selectedCoords.lat.toFixed(5)}, ${this.selectedCoords.lng.toFixed(5)}`;
    }
  }

  goToCity(cityKey) {
    const city = this.cities[cityKey];
    if (!city || !this.map) return;

    this.map.flyTo([city.lat, city.lng], city.zoom, {
      duration: 1.2
    });

    // Also update selection crosshair to the city center
    this.handleMapClick(city.lat, city.lng);
  }

  // Count active parking pins on the map (confirms zero data)
  getParkingCount() {
    if (!this.parkingLayer) return 0;
    return this.parkingLayer.getLayers().length;
  }

  // Refresh map canvas size when tab or container is shown
  invalidateSize() {
    if (this.map) {
      setTimeout(() => {
        this.map.invalidateSize();
      }, 100);
    }
  }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.parkingMap = new ParkingMapController('real-map');
});
