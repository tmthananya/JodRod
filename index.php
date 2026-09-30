<?php
/**
 * JodRod (จอดรถ) - Flood Parking Community
 * Index / Home Page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JodRod - Flood Parking Community</title>
  <meta name="description" content="Find and offer safe parking spaces during flood emergencies.">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚗</text></svg>">
</head>
<body>

  <!-- ========================================================
       PERSISTENT NAVIGATION BAR
       Strictly contains ONLY: Map, My Car, My Spaces, Points, Profile
       ======================================================== -->
  <header class="site-header">
    <nav class="navbar" aria-label="Main Navigation">
      <a href="index.php" class="brand">
        <div class="brand-icon">🚗</div>
        <div class="brand-text">
          <span class="brand-title">JODROD</span>
          <span class="brand-tagline">Flood Parking Community</span>
        </div>
      </a>

      <!-- Strictly: Map, My Car, My Spaces, Points, Profile -->
      <ul class="nav-links">
        <li>
          <a href="#map" class="nav-link" id="nav-map">
            <span>🗺️</span> Map
          </a>
        </li>
        <li>
          <a href="#my-car" class="nav-link" id="nav-my-car">
            <span>🚙</span> My Car
          </a>
        </li>
        <li>
          <a href="#my-spaces" class="nav-link" id="nav-my-spaces">
            <span>🅿️</span> My Spaces
          </a>
        </li>
        <li>
          <a href="#points" class="nav-link" id="nav-points">
            <span>⭐</span> Points
          </a>
        </li>
        <li>
          <a href="#profile" class="nav-link" id="nav-profile">
            <span>👤</span> Profile
          </a>
        </li>
      </ul>
    </nav>
  </header>

  <!-- ========================================================
       MAIN CONTENT CONTAINER
       ======================================================== -->
  <main class="container">

    <!-- HOME / OVERVIEW SECTION (DEFAULT ACTIVE) -->
    <div id="section-home">

      <!-- Hero Banner -->
      <section class="hero-card">
        <div class="hero-badge">🌊 Real-World Flood Relief &bull; Safe Parking Network</div>
        <h1 class="brand-font hero-title">
          Safe Parking During Floods
        </h1>
        <p class="hero-desc">
          Connect drivers who need safe high ground with neighbors who have available parking space.
        </p>
      </section>

      <!-- Two Main Actions -->
      <section aria-label="Main Community Actions">
        <div class="actions-grid">
          
          <!-- Action 1: Find Parking -->
          <article class="action-card find-parking" id="card-find-parking">
            <div>
              <div class="action-card-header">
                <div class="action-card-icon">🔍</div>
                <div>
                  <span class="action-badge">FOR DRIVERS</span>
                  <h2 class="action-title">1. Find Parking</h2>
                </div>
              </div>
              <p class="action-desc">
                Find safe, elevated parking spaces nearby to protect your vehicle from rising floodwaters.
              </p>
              <ul class="action-points-list">
                <li>✅ View safe parking locations on the real map</li>
                <li>✅ See live available space counts</li>
                <li>✅ Request parking with mutual confirmation</li>
              </ul>
            </div>
            <div>
              <a href="#map" class="pixel-btn pixel-btn-blue action-btn" style="width: 100%;" id="btn-action-find">
                <span>🚗</span> Find Safe Parking
              </a>
            </div>
          </article>

          <!-- Action 2: Offer Parking Space -->
          <article class="action-card offer-parking" id="card-offer-parking">
            <div>
              <div class="action-card-header">
                <div class="action-card-icon">🅿️</div>
                <div>
                  <span class="action-badge">FOR PARKING OWNERS</span>
                  <h2 class="action-title">2. Offer Parking Space</h2>
                </div>
              </div>
              <p class="action-desc">
                Share your elevated driveway, yard, or lot with neighbors whose vehicles are at risk.
              </p>
              <ul class="action-points-list">
                <li>✅ Pin your parking location on the map</li>
                <li>✅ Set your total vehicle capacity</li>
                <li>✅ Review and confirm parking requests</li>
              </ul>
            </div>
            <div>
              <a href="#my-spaces" class="pixel-btn pixel-btn-green action-btn" style="width: 100%;" id="btn-action-offer">
                <span>🅿️</span> Offer Parking Space
              </a>
            </div>
          </article>

        </div>
      </section>

      <!-- Realistic Community Concept & Flow -->
      <section class="concept-section">
        <h3 class="section-title">
          <span>🔄</span> How Flood Parking Works
        </h3>
        <p class="section-subtitle">
          A clear, community-driven workflow powered by mutual confirmation.
        </p>

        <div class="steps-grid">
          <div class="step-box">
            <div class="step-num">STEP 01</div>
            <div class="step-heading">Find or Offer</div>
            <div class="step-text">Owners list safe elevated spaces; drivers discover available spots on the real map.</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 02</div>
            <div class="step-heading">Request Spot</div>
            <div class="step-text">The driver sends a parking request with vehicle details to the space owner.</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 03</div>
            <div class="step-heading">Mutual Confirm</div>
            <div class="step-text">Both owner and driver confirm arrival and safe parking in person.</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 04</div>
            <div class="step-heading">Earn Points</div>
            <div class="step-text">Community reward points are granted after successfully completed parking.</div>
          </div>
        </div>
      </section>

    </div>

    <!-- ========================================================
         NAVIGATION TARGET SECTIONS (MODULAR PLACEHOLDERS)
         ======================================================== -->
    
    <!-- 1. Map Section -->
    <section id="section-map" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🗺️</span> Real-World Flood Map</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Explore real geographic flood areas, pan, zoom, or click anywhere to capture GPS coordinates.</p>

      <!-- Map Toolbar: City Jump Shortcuts & Zero Data Status -->
      <div class="map-toolbar">
        <div class="city-jump-group">
          <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">📍 Quick Jump:</span>
          <button type="button" class="city-btn btn-city-jump" data-city="chiangmai">Chiang Mai</button>
          <button type="button" class="city-btn btn-city-jump" data-city="bangkok">Bangkok</button>
        </div>
        <div class="map-status-pill" id="map-status-badge">
          <span>🅿️</span> Active Parking Spaces: <strong style="color: #0284c7; margin-left: 4px;">0 (Zero Data)</strong>
        </div>
      </div>

      <!-- Real Map Canvas Container -->
      <div class="real-map-wrapper">
        <div id="real-map"></div>
      </div>

      <!-- Live Coordinates Panel -->
      <div class="coords-panel">
        <div class="coords-data">
          <span class="coords-label">Selected Location:</span>
          <span id="selected-coords-full" class="coords-badge">18.78830, 98.98530</span>
          <span style="font-size: 0.82rem; color: #64748b;">(Lat: <span id="selected-lat">18.78830</span>, Lng: <span id="selected-lng">98.98530</span>)</span>
        </div>
        <div style="font-size: 0.82rem; color: #475569; font-weight: 600;">
          💡 Click anywhere on the map to change the selected coordinates.
        </div>
        <a href="#my-spaces" class="pixel-btn pixel-btn-green pixel-btn-sm" id="btn-offer-from-map">
          🅿️ Offer Space at Selected Location &rarr;
        </a>
      </div>

      <!-- Active Parking Spaces Directory (Loaded from Supabase) -->
      <div style="margin-top: 24px;">
        <h3 class="section-title">
          <span>🅿️</span> Active Parking Spaces
        </h3>
        <p class="section-subtitle">Retrieved live from the database. Click "View on Map" to locate any space.</p>
        <div id="parking-spaces-list" class="spaces-list-container">
          <div class="empty-spaces-notice">Loading parking spaces...</div>
        </div>
      </div>
    </section>

    <!-- 2. My Car Section -->
    <section id="section-my-car" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🚙</span> My Car & Requests</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Track your vehicle and ongoing parking requests.</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">🚙 Driver Dashboard Ready</p>
        <p>Parking request status and arrival confirmations will appear here.</p>
      </div>
    </section>

    <!-- 3. My Spaces Section: OFFER PARKING SPACE FORM -->
    <section id="section-my-spaces" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🅿️</span> Offer & Manage Parking Spaces</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Register your safe elevated space to help neighbors protect their cars from flooding.</p>

      <!-- Offer Parking Space Form Card -->
      <div class="form-card">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
          <span>🅿️</span> Offer Parking Space
        </h3>
        <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 20px;">
          Fill in your space details. The location is automatically captured from the interactive map.
        </p>

        <!-- Dynamic Success & Error Alert Box -->
        <div id="offer-alert" class="alert-message" role="alert"></div>

        <form id="offer-parking-form" novalidate>
          
          <!-- 1. Parking Name -->
          <div class="form-group">
            <label for="offer-name" class="form-label">Parking Space Name *</label>
            <input type="text" id="offer-name" name="name" class="form-input" placeholder="e.g. Elevated Driveway near Nimman Soi 7" required>
            <div class="form-help">A recognizable landmark or address so drivers can locate it easily.</div>
          </div>

          <!-- 2. Location from Real Map -->
          <div class="form-group">
            <label class="form-label">Location (Selected from Real Map) *</label>
            <div class="coords-pick-box">
              <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div>
                  <span style="font-size: 0.75rem; color: #64748b; font-weight: 700;">Latitude:</span>
                  <input type="text" id="offer-latitude" name="latitude" class="form-input" style="padding: 6px 10px; font-size: 0.88rem;" readonly required>
                </div>
                <div>
                  <span style="font-size: 0.75rem; color: #64748b; font-weight: 700;">Longitude:</span>
                  <input type="text" id="offer-longitude" name="longitude" class="form-input" style="padding: 6px 10px; font-size: 0.88rem;" readonly required>
                </div>
              </div>
              <button type="button" id="btn-pick-location" class="pixel-btn pixel-btn-blue coords-pick-btn">
                🗺️ Select on Map
              </button>
            </div>
            <div class="form-help">Click anywhere on the real map to set new coordinates.</div>
          </div>

          <!-- 3. Capacity & 4. Available Spaces -->
          <div class="form-row">
            <div class="form-group">
              <label for="offer-capacity" class="form-label">Total Capacity *</label>
              <input type="number" id="offer-capacity" name="capacity" class="form-input" min="1" max="1000" value="5" required>
              <div class="form-help">Total vehicles the space can hold.</div>
            </div>

            <div class="form-group">
              <label for="offer-available-spaces" class="form-label">Available Spaces *</label>
              <input type="number" id="offer-available-spaces" name="available_spaces" class="form-input" min="0" max="1000" value="5" required>
              <div class="form-help">Current open spots (must be &le; capacity).</div>
            </div>
          </div>

          <!-- 5. Status -->
          <div class="form-group">
            <label for="offer-status" class="form-label">Status *</label>
            <select id="offer-status" name="status" class="form-select" required>
              <option value="available" selected>Available (Ready for parking)</option>
              <option value="full">Full (At capacity)</option>
              <option value="closed">Closed (Temporarily unavailable)</option>
            </select>
          </div>

          <button type="submit" id="btn-submit-space" class="pixel-btn pixel-btn-green" style="width: 100%; padding: 14px; font-size: 1.05rem;">
            <span>🅿️</span> Save & Offer Parking Space
          </button>

        </form>
      </div>
    </section>

    <!-- 4. Points Section -->
    <section id="section-points" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>⭐</span> Community Points</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Your volunteer points and community helper score.</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">⭐ Points Reward Ready</p>
        <p>Points will be awarded following verified mutual confirmations.</p>
      </div>
    </section>

    <!-- 5. Profile Section -->
    <section id="section-profile" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>👤</span> Community Profile</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Your profile and roles (Driver & Parking Owner).</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">👤 Profile Overview Ready</p>
        <p>Profile summary and active role status.</p>
      </div>
    </section>

  </main>

  <!-- ========================================================
       FOOTER
       ======================================================== -->
  <footer>
    <p>
      🚗 <strong>JodRod &bull; Flood Parking Community</strong> &bull; Civic Disaster Relief Initiative<br>
      Connecting neighbors to keep vehicles safe during flood emergencies.
    </p>
  </footer>

  <!-- ========================================================
       LEAFLET & MAP ENGINE
       ======================================================== -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="assets/js/parking_map.js"></script>

  <!-- ========================================================
       PERSISTENT NAVIGATION CONTROLLER
       ======================================================== -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const navLinks = {
        '#map': document.getElementById('nav-map'),
        '#my-car': document.getElementById('nav-my-car'),
        '#my-spaces': document.getElementById('nav-my-spaces'),
        '#points': document.getElementById('nav-points'),
        '#profile': document.getElementById('nav-profile')
      };

      const sections = {
        '#home': document.getElementById('section-home'),
        '#map': document.getElementById('section-map'),
        '#my-car': document.getElementById('section-my-car'),
        '#my-spaces': document.getElementById('section-my-spaces'),
        '#points': document.getElementById('section-points'),
        '#profile': document.getElementById('section-profile')
      };

      function handleRoute() {
        const hash = window.location.hash || '#home';

        // Update nav active states
        Object.keys(navLinks).forEach(key => {
          if (navLinks[key]) {
            if (key === hash) {
              navLinks[key].classList.add('active');
            } else {
              navLinks[key].classList.remove('active');
            }
          }
        });

        // Show/hide sections
        if (hash === '#home' || !sections[hash]) {
          sections['#home'].style.display = 'block';
          Object.keys(sections).forEach(key => {
            if (key !== '#home' && sections[key]) {
              sections[key].classList.remove('active');
            }
          });
        } else {
          sections['#home'].style.display = 'none';
          Object.keys(sections).forEach(key => {
            if (key !== '#home' && sections[key]) {
              if (key === hash) {
                sections[key].classList.add('active');
              } else {
                sections[key].classList.remove('active');
              }
            }
          });
        }

        // Refresh map dimensions if map section is shown
        if (hash === '#map' && window.parkingMap) {
          window.parkingMap.invalidateSize();
        }
      }

      window.addEventListener('hashchange', handleRoute);
      handleRoute();

      // Back to home buttons
      document.querySelectorAll('.btn-back-home').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          window.location.hash = '#home';
        });
      });

      // Jump to map to pick location
      const btnPick = document.getElementById('btn-pick-location');
      if (btnPick) {
        btnPick.addEventListener('click', () => {
          window.location.hash = '#map';
        });
      }

      // Handle Offer Parking Space Form Submit
      const offerForm = document.getElementById('offer-parking-form');
      const offerAlert = document.getElementById('offer-alert');
      const btnSubmit = document.getElementById('btn-submit-space');

      function showAlert(type, message) {
        if (!offerAlert) return;
        offerAlert.className = `alert-message ${type}`;
        offerAlert.innerHTML = message;
        offerAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      if (offerForm) {
        offerForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const name = document.getElementById('offer-name').value.trim();
          const latitude = parseFloat(document.getElementById('offer-latitude').value);
          const longitude = parseFloat(document.getElementById('offer-longitude').value);
          const capacity = parseInt(document.getElementById('offer-capacity').value, 10);
          const available_spaces = parseInt(document.getElementById('offer-available-spaces').value, 10);
          const status = document.getElementById('offer-status').value;

          // Client-side Validation
          if (!name) {
            showAlert('error', '⚠️ Please enter a parking space name.');
            document.getElementById('offer-name').focus();
            return;
          }

          if (isNaN(latitude) || isNaN(longitude)) {
            showAlert('error', '⚠️ Please select a location on the map first.');
            return;
          }

          if (isNaN(capacity) || capacity <= 0) {
            showAlert('error', '⚠️ Total capacity must be greater than 0.');
            document.getElementById('offer-capacity').focus();
            return;
          }

          if (isNaN(available_spaces) || available_spaces < 0) {
            showAlert('error', '⚠️ Available spaces cannot be negative.');
            document.getElementById('offer-available-spaces').focus();
            return;
          }

          if (available_spaces > capacity) {
            showAlert('error', '⚠️ Available spaces cannot exceed total capacity (' + capacity + ').');
            document.getElementById('offer-available-spaces').focus();
            return;
          }

          const payload = {
            name,
            latitude,
            longitude,
            capacity,
            available_spaces,
            status
          };

          // Submit to backend API
          try {
            btnSubmit.disabled = true;
            btnSubmit.textContent = '⏳ Saving Parking Space...';

            const response = await fetch('api/create_parking_space.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
              showAlert('success', `
                🎉 <strong>Parking Space Offered Successfully!</strong><br>
                <span>"${result.data.name}" (${result.data.available_spaces}/${result.data.capacity} spots, status: ${result.data.status}) is now active on the map.</span>
              `);

              // Clear form inputs
              document.getElementById('offer-name').value = '';
              document.getElementById('offer-capacity').value = '5';
              document.getElementById('offer-available-spaces').value = '5';
              document.getElementById('offer-status').value = 'available';

              // Refresh map pins so newly created space appears immediately
              if (window.parkingMap) {
                await window.parkingMap.loadParkingSpaces();
              }
            } else {
              showAlert('error', `❌ <strong>Failed to create space:</strong> ${result.error || 'Server error'}`);
            }
          } catch (err) {
            showAlert('error', `❌ <strong>Network error:</strong> ${err.message}`);
          } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<span>🅿️</span> Save & Offer Parking Space';
          }
        });
      }
    });
  </script>

</body>
</html>
