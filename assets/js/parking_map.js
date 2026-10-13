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
        html: '<div style="width: 24px; height: 24px; border: 2px solid #0284c7; border-radius: 50%; background: rgba(2, 132, 199, 0.2); box-shadow: 0 0 8px rgba(2, 132, 199, 0.5);"></div>',
        iconSize: [24, 24],
        iconAnchor: [12, 12]
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

  // Render parking pins on the map and populate directory
  renderParkingSpaces(spaces) {
    if (!this.parkingLayer) return;
    this.parkingLayer.clearLayers();

    const listContainer = document.getElementById('parking-spaces-list');
    let listHtml = '';

    if (!spaces || spaces.length === 0) {
      if (listContainer) {
        listContainer.innerHTML = '<div class="empty-spaces-notice">No parking spaces registered yet. Use "Offer Parking Space" to add one.</div>';
      }
      this.updateStatusBadge(0);
      return;
    }

    spaces.forEach((s, idx) => {
      const statusClass = s.status === 'available' ? 'status-available' : (s.status === 'full' ? 'status-full' : 'status-closed');
      const latFixed = parseFloat(s.latitude).toFixed(5);
      const lngFixed = parseFloat(s.longitude).toFixed(5);

      const pinIcon = L.divIcon({
        className: 'custom-parking-pin',
        html: `
          <div class="parking-pin-bubble ${statusClass}" title="${s.name}">
            <span>P</span>
          </div>
        `,
        iconSize: [36, 36],
        iconAnchor: [18, 18]
      });

      const isOwner = Boolean(window.currentUser && s.owner_id && s.owner_id === window.currentUser.id);
      const isCommunity = !s.owner_id;
      const isAvailable = (s.status === 'available' && parseInt(s.available_spaces, 10) > 0);

      const marker = L.marker([s.latitude, s.longitude], { icon: pinIcon });

      // Marker Popup Actions:
      let popupActionsHtml = '';
      if (isOwner) {
        popupActionsHtml = `
          <div style="display: flex; gap: 6px; margin-top: 8px;">
            <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-gold btn-edit-space" data-id="${s.id}" style="flex: 1;">
              Edit
            </button>
            <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-space" data-id="${s.id}" data-name="${s.name}" style="flex: 1;">
              Delete
            </button>
          </div>
        `;
      } else if (isCommunity) {
        popupActionsHtml = `
          <div style="margin-top: 8px; text-align: center; font-size: 0.75rem; color: #1e40af; font-weight: 700; background: #dbeafe; padding: 6px; border-radius: 4px; border: 1px solid #bfdbfe;">
            Public Community Space<br><span style="font-weight: normal; font-size: 0.7rem; color: #3b82f6;">First-come, first-served (No approval needed)</span>
          </div>
        `;
      } else if (isAvailable) {
        popupActionsHtml = `
          <div style="display: flex; gap: 6px; margin-top: 8px;">
            <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-reserve-space" data-id="${s.id}" data-name="${s.name}" data-available="${s.available_spaces}" style="flex: 1;">
              Request Spot
            </button>
          </div>
        `;
      } else {
        popupActionsHtml = `
          <div style="margin-top: 8px; text-align: center; font-size: 0.75rem; color: #ef4444; font-weight: 700; background: #fee2e2; padding: 4px; border-radius: 4px;">
            ${s.status === 'closed' ? 'Lot Closed' : 'Lot Full (0 spots)'}
          </div>
        `;
      }

      const popupHtml = `
        <div style="font-family: inherit; min-width: 190px;">
          <h4 style="margin: 0 0 6px; font-size: 1rem; color: #0f172a; font-weight: 800; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px;">${s.name}</h4>
          <div style="font-size: 0.85rem; color: #334155; display: flex; flex-direction: column; gap: 4px;">
            <div><strong>Location:</strong> <span style="font-family: monospace;">${latFixed}, ${lngFixed}</span></div>
            <div><strong>Capacity:</strong> ${s.capacity}</div>
            <div><strong>Available spaces:</strong> ${s.available_spaces}</div>
            <div><strong>Status:</strong> <span class="status-pill ${statusClass}">${s.status.toUpperCase()}</span></div>
          </div>
          ${popupActionsHtml}
        </div>
      `;
      marker.bindPopup(popupHtml);
      this.parkingLayer.addLayer(marker);

      // Directory Card Actions:
      let cardActionsHtml = '';
      if (isOwner) {
        cardActionsHtml = `
          <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-gold btn-edit-space" data-id="${s.id}" style="flex: 1;">
            Edit
          </button>
          <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-space" data-id="${s.id}" data-name="${s.name}" style="flex: 1;">
            Delete
          </button>
        `;
      } else if (isCommunity) {
        cardActionsHtml = `
          <span style="font-size: 0.75rem; color: #1e40af; font-weight: 700; background: #dbeafe; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; flex: 1; border: 1px solid #bfdbfe;">
            Public Community Space
          </span>
        `;
      } else if (isAvailable) {
        cardActionsHtml = `
          <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-reserve-space" data-id="${s.id}" data-name="${s.name}" data-available="${s.available_spaces}" style="flex: 1;">
            Request Spot
          </button>
        `;
      } else {
        cardActionsHtml = `
          <span style="font-size: 0.75rem; color: #ef4444; font-weight: 700; background: #fee2e2; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; flex: 1;">
            ${s.status === 'closed' ? 'Closed' : 'Lot Full (0 spots)'}
          </span>
        `;
      }

      listHtml += `
        <article class="space-card" id="space-card-${idx}">
          <div>
            <div class="space-card-header">
              <h4 class="space-card-title">${s.name}</h4>
              <span class="status-pill ${statusClass}">${s.status.toUpperCase()}</span>
            </div>
            <div class="space-card-body" style="margin-top: 10px;">
              <div><strong>Location:</strong> <span style="font-family: monospace;">${latFixed}, ${lngFixed}</span></div>
              <div><strong>Capacity:</strong> ${s.capacity} vehicles</div>
              <div><strong>Available spaces:</strong> ${s.available_spaces} spots</div>
              <div><strong>Status:</strong> ${s.status}</div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; margin-top: 12px;">
            <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-view-pin" data-lat="${s.latitude}" data-lng="${s.longitude}" style="flex: 1;">
              View on Map
            </button>
            ${cardActionsHtml}
          </div>
        </article>
      `;
    });

    // Cache spaces list for quick lookup by ID
    this.spacesData = {};
    spaces.forEach(s => {
      this.spacesData[s.id] = s;
    });

    if (listContainer) {
      listContainer.innerHTML = listHtml;

      // Bind View on Map buttons
      listContainer.querySelectorAll('.btn-view-pin').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const lat = parseFloat(e.currentTarget.dataset.lat);
          const lng = parseFloat(e.currentTarget.dataset.lng);
          this.map.flyTo([lat, lng], 15, { duration: 1.0 });

          // Find marker and open its popup
          this.parkingLayer.eachLayer(layer => {
            if (layer.getLatLng && layer.getLatLng().lat === lat && layer.getLatLng().lng === lng) {
              layer.openPopup();
            }
          });
        });
      });
    }

    this.updateStatusBadge(spaces.length);
  }

  // Open Edit Modal for a selected parking space
  openEditModal(spaceId) {
    const space = this.spacesData ? this.spacesData[spaceId] : null;
    if (!space) {
      console.warn('Parking space not found for edit:', spaceId);
      return;
    }

    if (!window.currentUser || !space.owner_id || space.owner_id !== window.currentUser.id) {
      this.showMapAlert('error', 'Forbidden: You do not have permission to edit this parking space.');
      return;
    }

    const modal = document.getElementById('edit-space-modal');
    const alertBox = document.getElementById('edit-space-alert');
    if (alertBox) {
      alertBox.className = 'alert-message';
      alertBox.innerHTML = '';
      alertBox.style.display = 'none';
    }

    // Populate form fields (ID is strictly read-only and non-editable)
    const idField = document.getElementById('edit-space-id');
    const idDisplay = document.getElementById('edit-space-id-display');
    const nameField = document.getElementById('edit-space-name');
    const capField = document.getElementById('edit-space-capacity');
    const availField = document.getElementById('edit-space-available');
    const statusField = document.getElementById('edit-space-status');

    if (idField) idField.value = space.id;
    if (idDisplay) idDisplay.value = space.id;
    if (nameField) nameField.value = space.name || '';
    if (capField) capField.value = space.capacity;
    if (availField) availField.value = space.available_spaces;
    if (statusField) statusField.value = space.status || 'available';

    if (modal) {
      modal.classList.add('active');
    }
  }

  // Close Edit Modal
  closeEditModal() {
    const modal = document.getElementById('edit-space-modal');
    if (modal) {
      modal.classList.remove('active');
    }
  }

  // Show Action Alert Notification on Map Section
  showMapAlert(type, message) {
    const alertBox = document.getElementById('map-action-alert');
    if (alertBox) {
      alertBox.className = `alert-message ${type}`;
      alertBox.innerHTML = message;
      alertBox.style.display = 'block';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  // Delete parking space with confirmation
  async deleteParkingSpace(spaceId, spaceName) {
    if (!spaceId) return;

    const space = this.spacesData ? this.spacesData[spaceId] : null;
    if (!window.currentUser || !space || !space.owner_id || space.owner_id !== window.currentUser.id) {
      this.showMapAlert('error', 'Forbidden: You do not have permission to delete this parking space.');
      return;
    }

    const displayName = spaceName || 'this parking space';

    // 1. Before deleting: Ask for confirmation
    const confirmed = window.confirm(`Are you sure you want to delete "${displayName}"?\n\nThis will permanently delete the parking space from Supabase.`);
    if (!confirmed) return;

    // 2. After confirmation: Delete record from Supabase
    try {
      const res = await fetch('api/delete_parking_space.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || ''
        },
        body: JSON.stringify({ id: spaceId })
      });

      const json = await res.json();

      if (res.ok && json.success) {
        // Show success message
        this.showMapAlert('success', `
          <strong>Parking Space Deleted Successfully!</strong><br>
          <span>"${displayName}" has been permanently removed from Supabase and the map.</span>
        `);

        // Refresh data: removes it from the website and removes its map marker
        await this.loadParkingSpaces();
      } else {
        this.showMapAlert('error', `<strong>Failed to delete space:</strong> ${json.error || 'Server error'}`);
      }
    } catch (err) {
      this.showMapAlert('error', `<strong>Network error:</strong> ${err.message}`);
    }
  }

  // Reserve parking space (Phase 2 Owner Approval workflow)
  async reserveParkingSpace(spaceId, spaceName, availableSpots) {
    if (!spaceId) return;

    if (!window.currentUser) {
      alert('Please log in to your account before requesting a parking spot.');
      window.location.hash = '#home';
      return;
    }

    const space = this.spacesData ? this.spacesData[spaceId] : null;
    if (space && !space.owner_id) {
      this.showMapAlert('error', 'Public community spaces operate on a first-come, first-served basis and do not require reservation requests.');
      return;
    }

    if (availableSpots <= 0) {
      this.showMapAlert('error', 'This parking space is currently full.');
      return;
    }

    // Retrieve user's registered vehicles from Supabase
    let selectedCarId = null;
    let selectedCarInfo = '';
    try {
      const carsRes = await fetch('api/cars.php');
      const carsJson = await carsRes.json();
      if (carsJson.success && Array.isArray(carsJson.data) && carsJson.data.length > 0) {
        const cars = carsJson.data;
        if (cars.length === 1) {
          selectedCarId = cars[0].id;
          selectedCarInfo = `Vehicle: ${cars[0].license_plate} (${cars[0].model || 'Standard'})\n`;
        } else {
          const carOptions = cars.map((c, i) => `${i + 1}. ${c.license_plate} (${c.model || 'Standard'})`).join('\n');
          const choice = window.prompt(
            `Select which vehicle to request parking at "${spaceName}":\n\n${carOptions}\n\nEnter number (1-${cars.length}):`,
            '1'
          );
          if (choice === null) return; // cancelled
          const idx = parseInt(choice, 10) - 1;
          if (cars[idx]) {
            selectedCarId = cars[idx].id;
            selectedCarInfo = `Vehicle: ${cars[idx].license_plate}\n`;
          }
        }
      }
    } catch (e) {}

    if (!selectedCarId) {
      this.showMapAlert('error', 'You must register a vehicle in "My Cars" before requesting a parking spot.');
      window.location.hash = '#dashboard';
      return;
    }

    const displayName = spaceName || 'this parking lot';
    const confirmed = window.confirm(
      `Submit Flood Parking Request\n\n` +
      `Parking Lot: ${displayName}\n` +
      selectedCarInfo +
      `Spots Available: ${availableSpots}\n\n` +
      `Your request will be sent to the parking lot owner for approval. Proceed?`
    );
    if (!confirmed) return;

    try {
      const res = await fetch('api/create_reservation.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || ''
        },
        body: JSON.stringify({ 
          parking_space_id: spaceId,
          car_id: selectedCarId
        })
      });

      const json = await res.json();

      if (res.ok && json.success) {
        this.showMapAlert('success', `
          <strong>Reservation Request Submitted!</strong><br>
          <span>Your request is pending host approval at "${displayName}". Available capacity remains open until the host approves. Track progress in <a href="#my-reservations" style="color: #0284c7; font-weight: bold; text-decoration: underline;">My Reservations</a>.</span>
        `);

        // Refresh spaces and reservations
        await this.loadParkingSpaces();
        if (typeof window.loadReservations === 'function') {
          window.loadReservations();
        }
        if (typeof window.loadDashboard === 'function') {
          window.loadDashboard();
        }
      } else {
        this.showMapAlert('error', `<strong>Request Failed:</strong> ${json.error || 'Server error'}`);
      }
    } catch (err) {
      this.showMapAlert('error', `<strong>Network error:</strong> ${err.message}`);
    }
  }

  updateStatusBadge(count) {
    const badge = document.getElementById('map-status-badge');
    if (badge) {
      badge.innerHTML = `Active Parking Spaces: <strong style="color: #0284c7; margin-left: 4px;">${count}</strong>`;
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

  // Delegated click handler for actions (handles both directory cards and Leaflet map popups)
  document.body.addEventListener('click', (e) => {
    // Reserve action
    const reserveBtn = e.target.closest('.btn-reserve-space');
    if (reserveBtn && window.parkingMap) {
      const spaceId = reserveBtn.dataset.id;
      const spaceName = reserveBtn.dataset.name;
      const available = parseInt(reserveBtn.dataset.available || '0', 10);
      if (spaceId) {
        window.parkingMap.reserveParkingSpace(spaceId, spaceName, available);
      }
    }

    // Edit action
    const editBtn = e.target.closest('.btn-edit-space');
    if (editBtn && window.parkingMap) {
      const spaceId = editBtn.dataset.id;
      if (spaceId) {
        window.parkingMap.openEditModal(spaceId);
      }
    }

    // Delete action
    const deleteBtn = e.target.closest('.btn-delete-space');
    if (deleteBtn && window.parkingMap) {
      const spaceId = deleteBtn.dataset.id;
      const spaceName = deleteBtn.dataset.name;
      if (spaceId) {
        window.parkingMap.deleteParkingSpace(spaceId, spaceName);
      }
    }

    // Close button on modal
    if (e.target.closest('#btn-close-edit-modal') || e.target.closest('#btn-cancel-edit')) {
      if (window.parkingMap) {
        window.parkingMap.closeEditModal();
      }
    }

    // Click outside modal dialog to dismiss
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
      if (window.parkingMap) {
        window.parkingMap.closeEditModal();
      }
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && window.parkingMap) {
      window.parkingMap.closeEditModal();
    }
  });
});


