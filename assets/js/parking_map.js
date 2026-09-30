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

    const latStr = this.selectedCoords.lat.toFixed(5);
    const lngStr = this.selectedCoords.lng.toFixed(5);

    if (latEl) latEl.textContent = latStr;
    if (lngEl) lngEl.textContent = lngStr;
    if (fullEl) {
      fullEl.textContent = `${latStr}, ${lngStr}`;
    }

    // Sync coordinates to Offer Parking Space form inputs
    const offerLat = document.getElementById('offer-latitude');
    const offerLng = document.getElementById('offer-longitude');
    if (offerLat) offerLat.value = latStr;
    if (offerLng) offerLng.value = lngStr;
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

  // Count active parking pins on the map
  getParkingCount() {
    if (!this.parkingLayer) return 0;
    return this.parkingLayer.getLayers().length;
  }

  // Fetch parking spaces from database/API
  async loadParkingSpaces() {
    try {
      const res = await fetch('api/get_parking_spaces.php');
      const json = await res.json();
      if (json.success && Array.isArray(json.data)) {
        this.renderParkingSpaces(json.data);
      }
    } catch (e) {
      console.warn('Could not load parking spaces:', e);
    }
  }

  // Render parking pins on the map
  renderParkingSpaces(spaces) {
    if (!this.parkingLayer) return;
    this.parkingLayer.clearLayers();

    spaces.forEach(s => {
      const statusClass = s.status === 'available' ? 'status-available' : (s.status === 'full' ? 'status-full' : 'status-closed');
      const pinIcon = L.divIcon({
        className: 'custom-parking-pin',
        html: `
          <div class="parking-pin-bubble ${statusClass}" title="${s.name}">
            <span>🅿️</span>
          </div>
        `,
        iconSize: [36, 36],
        iconAnchor: [18, 18]
      });

      const marker = L.marker([s.latitude, s.longitude], { icon: pinIcon });
      const popupHtml = `
        <div style="font-family: inherit; min-width: 170px;">
          <h4 style="margin: 0 0 6px; font-size: 1rem; color: #0f172a; font-weight: 800;">${s.name}</h4>
          <p style="margin: 0 0 4px; font-size: 0.85rem; color: #334155;"><strong>Available:</strong> ${s.available_spaces} / ${s.capacity} spots</p>
          <p style="margin: 0; font-size: 0.82rem; color: #64748b;"><strong>Status:</strong> <span style="font-weight: 700; text-transform: uppercase;">${s.status}</span></p>
        </div>
      `;
      marker.bindPopup(popupHtml);
      this.parkingLayer.addLayer(marker);
    });

    this.updateStatusBadge(spaces.length);
  }

  updateStatusBadge(count) {
    const badge = document.getElementById('map-status-badge');
    if (badge) {
      badge.innerHTML = `<span>🅿️</span> Active Parking Spaces: <strong style="color: #0284c7; margin-left: 4px;">${count}</strong>`;
    }
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
  window.parkingMap.loadParkingSpaces();
});
