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

      <!-- Action Feedback Alert Box (for Delete/Update feedback) -->
      <div id="map-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 16px;"></div>

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
        <h2 class="section-title"><span>👤</span> Community Profiles</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Manage your community member profile (Driver & Parking Owner).</p>

      <!-- Profile Action Alert Box -->
      <div id="profile-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 20px;"></div>

      <div class="actions-grid" style="align-items: start; margin-bottom: 24px;">
        <!-- Left: Create Profile Form -->
        <div class="form-card">
          <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
            <span>➕</span> Create Profile
          </h3>
          <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 18px;">
            Add a new member profile to the community.
          </p>

          <form id="create-profile-form" novalidate>
            <div class="form-group">
              <label for="create-profile-name" class="form-label">Full Name or Nickname *</label>
              <input type="text" id="create-profile-name" name="name" class="form-input" placeholder="e.g. Somsak P." required>
              <div class="form-help">Your display name for flood parking.</div>
            </div>

            <div class="form-group">
              <label class="form-label">Choose Character Avatar *</label>
              <div class="avatar-select-grid" id="create-avatar-picker">
                <button type="button" class="avatar-opt-btn active" data-avatar="volunteer" title="Amanda (Relief Volunteer)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/volunteer.jpg" alt="Amanda" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Amanda</div>
                  <div class="avatar-char-role">Volunteer</div>
                </button>
                <button type="button" class="avatar-opt-btn" data-avatar="driver" title="Dan (Rescue Driver)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/driver.jpg" alt="Dan" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Dan</div>
                  <div class="avatar-char-role">Rescue Driver</div>
                </button>
                <button type="button" class="avatar-opt-btn" data-avatar="host" title="Uncle Som (Parking Space Host)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/host.jpg" alt="Uncle Som" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Uncle Som</div>
                  <div class="avatar-char-role">Space Host</div>
                </button>
                <button type="button" class="avatar-opt-btn" data-avatar="mechanic" title="Kai (Vehicle Mechanic)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/mechanic.jpg" alt="Kai" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Kai</div>
                  <div class="avatar-char-role">Mechanic</div>
                </button>
                <button type="button" class="avatar-opt-btn" data-avatar="rescuer" title="Maya (Flood Coordinator)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/rescuer.jpg" alt="Maya" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Maya</div>
                  <div class="avatar-char-role">Coordinator</div>
                </button>
                <button type="button" class="avatar-opt-btn" data-avatar="scout" title="Lin (Flood Patrol Scout)">
                  <div class="avatar-thumb-wrapper">
                    <img src="assets/avatars/scout.jpg" alt="Lin" class="avatar-thumb-img">
                  </div>
                  <div class="avatar-char-name">Lin</div>
                  <div class="avatar-char-role">Patrol Scout</div>
                </button>
              </div>
              <input type="hidden" id="create-profile-avatar" name="avatar" value="volunteer">
            </div>

            <button type="submit" id="btn-submit-profile" class="pixel-btn pixel-btn-green" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 8px;">
              <span>💾</span> Save Profile
            </button>
          </form>
        </div>

        <!-- Right: Profiles List (View Profile) -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
              <span>👥</span> Community Profiles
            </h3>
            <span class="status-pill status-available" id="profile-count-pill">0 Profiles</span>
          </div>

          <div id="profiles-list-container">
            <div class="empty-spaces-notice">Loading profiles...</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ========================================================
       EDIT PARKING SPACE MODAL DIALOG
       Strictly allows editing ONLY:
       - Parking name
       - Capacity
       - Available spaces
       - Status
       Record ID is fixed / non-editable.
       ======================================================== -->
  <div id="edit-space-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="edit-modal-title" class="modal-title"><span>✏️</span> Edit Parking Space</h3>
        <button type="button" class="modal-close-btn" id="btn-close-edit-modal" title="Close">&times;</button>
      </div>

      <!-- Edit Form Alert Box -->
      <div id="edit-space-alert" class="alert-message" role="alert" style="display: none;"></div>

      <form id="edit-parking-form" novalidate>
        <!-- Record ID (Fixed & Non-editable) -->
        <input type="hidden" id="edit-space-id" name="id">
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.85rem; color: #64748b;">Record ID (Non-editable)</label>
          <input type="text" id="edit-space-id-display" class="form-input" style="background: #f1f5f9; color: #64748b; font-family: monospace; font-size: 0.85rem; cursor: not-allowed;" readonly disabled>
        </div>

        <!-- 1. Parking Name -->
        <div class="form-group">
          <label for="edit-space-name" class="form-label">Parking Space Name *</label>
          <input type="text" id="edit-space-name" name="name" class="form-input" placeholder="e.g. Elevated Driveway near Nimman Soi 7" required>
          <div class="form-help">A recognizable landmark or address.</div>
        </div>

        <!-- 2. Capacity & 3. Available Spaces -->
        <div class="form-row">
          <div class="form-group">
            <label for="edit-space-capacity" class="form-label">Total Capacity *</label>
            <input type="number" id="edit-space-capacity" name="capacity" class="form-input" min="1" max="1000" required>
            <div class="form-help">Total vehicles the space can hold.</div>
          </div>

          <div class="form-group">
            <label for="edit-space-available" class="form-label">Available Spaces *</label>
            <input type="number" id="edit-space-available" name="available_spaces" class="form-input" min="0" max="1000" required>
            <div class="form-help">Current open spots (&le; capacity).</div>
          </div>
        </div>

        <!-- 4. Status -->
        <div class="form-group">
          <label for="edit-space-status" class="form-label">Status *</label>
          <select id="edit-space-status" name="status" class="form-select" required>
            <option value="available">Available (Ready for parking)</option>
            <option value="full">Full (At capacity)</option>
            <option value="closed">Closed (Temporarily unavailable)</option>
          </select>
        </div>

        <div class="modal-actions">
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-edit">Cancel</button>
          <button type="submit" class="pixel-btn pixel-btn-green" id="btn-save-edit-space">
            <span>💾</span> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================
       EDIT PROFILE MODAL DIALOG
       Strictly allows editing ONLY:
       - Name
       - Avatar
       Record ID is fixed / non-editable.
       ======================================================== -->
  <div id="edit-profile-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="edit-profile-modal-title">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="edit-profile-modal-title" class="modal-title"><span>✏️</span> Edit Profile</h3>
        <button type="button" class="modal-close-btn" id="btn-close-edit-profile-modal" title="Close">&times;</button>
      </div>

      <div id="edit-profile-alert" class="alert-message" role="alert" style="display: none;"></div>

      <form id="edit-profile-form" novalidate>
        <!-- Record ID (Fixed & Non-editable) -->
        <input type="hidden" id="edit-profile-id" name="id">
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.85rem; color: #64748b;">Profile ID (Non-editable)</label>
          <input type="text" id="edit-profile-id-display" class="form-input" style="background: #f1f5f9; color: #64748b; font-family: monospace; font-size: 0.85rem; cursor: not-allowed;" readonly disabled>
        </div>

        <div class="form-group">
          <label for="edit-profile-name" class="form-label">Full Name or Nickname *</label>
          <input type="text" id="edit-profile-name" name="name" class="form-input" required>
        </div>

        <div class="form-group">
          <label class="form-label">Choose Character Avatar *</label>
          <div class="avatar-select-grid" id="edit-avatar-picker">
            <button type="button" class="avatar-opt-btn" data-avatar="volunteer" title="Amanda (Relief Volunteer)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/volunteer.jpg" alt="Amanda" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Amanda</div>
              <div class="avatar-char-role">Volunteer</div>
            </button>
            <button type="button" class="avatar-opt-btn" data-avatar="driver" title="Dan (Rescue Driver)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/driver.jpg" alt="Dan" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Dan</div>
              <div class="avatar-char-role">Rescue Driver</div>
            </button>
            <button type="button" class="avatar-opt-btn" data-avatar="host" title="Uncle Som (Parking Space Host)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/host.jpg" alt="Uncle Som" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Uncle Som</div>
              <div class="avatar-char-role">Space Host</div>
            </button>
            <button type="button" class="avatar-opt-btn" data-avatar="mechanic" title="Kai (Vehicle Mechanic)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/mechanic.jpg" alt="Kai" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Kai</div>
              <div class="avatar-char-role">Mechanic</div>
            </button>
            <button type="button" class="avatar-opt-btn" data-avatar="rescuer" title="Maya (Flood Coordinator)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/rescuer.jpg" alt="Maya" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Maya</div>
              <div class="avatar-char-role">Coordinator</div>
            </button>
            <button type="button" class="avatar-opt-btn" data-avatar="scout" title="Lin (Flood Patrol Scout)">
              <div class="avatar-thumb-wrapper">
                <img src="assets/avatars/scout.jpg" alt="Lin" class="avatar-thumb-img">
              </div>
              <div class="avatar-char-name">Lin</div>
              <div class="avatar-char-role">Patrol Scout</div>
            </button>
          </div>
          <input type="hidden" id="edit-profile-avatar" name="avatar" value="volunteer">
        </div>

        <div class="modal-actions">
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-edit-profile">Cancel</button>
          <button type="submit" class="pixel-btn pixel-btn-green" id="btn-save-edit-profile">
            <span>💾</span> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

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

      // Handle Edit Parking Space Form Submit
      const editForm = document.getElementById('edit-parking-form');
      const editAlert = document.getElementById('edit-space-alert');
      const btnSaveEdit = document.getElementById('btn-save-edit-space');

      function showEditAlert(type, message) {
        if (!editAlert) return;
        editAlert.className = `alert-message ${type}`;
        editAlert.innerHTML = message;
        editAlert.style.display = 'block';
      }

      if (editForm) {
        editForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const id = document.getElementById('edit-space-id').value;
          const name = document.getElementById('edit-space-name').value.trim();
          const capacity = parseInt(document.getElementById('edit-space-capacity').value, 10);
          const available_spaces = parseInt(document.getElementById('edit-space-available').value, 10);
          const status = document.getElementById('edit-space-status').value;

          if (!id) {
            showEditAlert('error', '⚠️ Missing parking space ID.');
            return;
          }

          if (!name) {
            showEditAlert('error', '⚠️ Please enter a parking space name.');
            document.getElementById('edit-space-name').focus();
            return;
          }

          if (isNaN(capacity) || capacity <= 0) {
            showEditAlert('error', '⚠️ Total capacity must be greater than 0.');
            document.getElementById('edit-space-capacity').focus();
            return;
          }

          if (isNaN(available_spaces) || available_spaces < 0) {
            showEditAlert('error', '⚠️ Available spaces cannot be negative.');
            document.getElementById('edit-space-available').focus();
            return;
          }

          if (available_spaces > capacity) {
            showEditAlert('error', '⚠️ Available spaces cannot exceed total capacity (' + capacity + ').');
            document.getElementById('edit-space-available').focus();
            return;
          }

          const payload = {
            id,
            name,
            capacity,
            available_spaces,
            status
          };

          try {
            btnSaveEdit.disabled = true;
            btnSaveEdit.innerHTML = '<span>⏳</span> Saving...';

            const response = await fetch('api/update_parking_space.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
              showEditAlert('success', `
                ✅ <strong>Parking Space Updated Successfully!</strong><br>
                <span>Changes saved: "${result.data.name}" (${result.data.available_spaces}/${result.data.capacity} spots, status: ${result.data.status}).</span>
              `);

              // Refresh displayed data and map information
              if (window.parkingMap) {
                await window.parkingMap.loadParkingSpaces();
              }

              // Auto dismiss modal after short delay so user sees confirmation
              setTimeout(() => {
                if (window.parkingMap) {
                  window.parkingMap.closeEditModal();
                }
              }, 1200);
            } else {
              showEditAlert('error', `❌ <strong>Failed to update:</strong> ${result.error || 'Server error'}`);
            }
          } catch (err) {
            showEditAlert('error', `❌ <strong>Network error:</strong> ${err.message}`);
          } finally {
            btnSaveEdit.disabled = false;
            btnSaveEdit.innerHTML = '<span>💾</span> Save Changes';
          }
        });
      }

      // ========================================================
      // PROFILE CRUD CONTROLLER (CHARACTER AVATARS)
      // ========================================================
      let profilesData = {};

      const characterAvatars = {
        volunteer: { name: 'Amanda', role: 'Relief Volunteer', img: 'assets/avatars/volunteer.jpg' },
        driver:    { name: 'Dan', role: 'Rescue Driver', img: 'assets/avatars/driver.jpg' },
        host:      { name: 'Uncle Som', role: 'Parking Space Host', img: 'assets/avatars/host.jpg' },
        mechanic:  { name: 'Kai', role: 'Vehicle Mechanic', img: 'assets/avatars/mechanic.jpg' },
        rescuer:   { name: 'Maya', role: 'Flood Coordinator', img: 'assets/avatars/rescuer.jpg' },
        scout:     { name: 'Lin', role: 'Flood Patrol Scout', img: 'assets/avatars/scout.jpg' }
      };

      function renderAvatarPortrait(avatarKey) {
        const char = characterAvatars[avatarKey];
        if (char) {
          return `<div class="profile-avatar-portrait"><img src="${char.img}" alt="${char.name}"></div>`;
        }
        return `<div class="profile-avatar-portrait"><span class="profile-avatar-fallback">${avatarKey || '👤'}</span></div>`;
      }

      function getAvatarRoleBadge(avatarKey) {
        const char = characterAvatars[avatarKey];
        if (char) {
          return `<span class="profile-card-role-tag">${char.name} &bull; ${char.role}</span>`;
        }
        return '';
      }

      function showProfileAlert(type, message) {
        const el = document.getElementById('profile-action-alert');
        if (!el) return;
        el.className = `alert-message ${type}`;
        el.innerHTML = message;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      async function loadProfiles() {
        const container = document.getElementById('profiles-list-container');
        const countPill = document.getElementById('profile-count-pill');

        try {
          const res = await fetch('api/get_profiles.php');
          const json = await res.json();

          if (json.success && Array.isArray(json.data)) {
            profilesData = {};
            json.data.forEach(p => {
              profilesData[p.id] = p;
            });

            if (countPill) {
              countPill.textContent = `${json.data.length} ${json.data.length === 1 ? 'Profile' : 'Profiles'}`;
            }

            if (!container) return;

            if (json.data.length === 0) {
              container.innerHTML = '<div class="empty-spaces-notice">No profiles created yet. Use the form on the left to create one.</div>';
              return;
            }

            let html = '';
            json.data.forEach(p => {
              const avatarKey = p.avatar || 'volunteer';
              html += `
                <div class="profile-card" id="profile-card-${p.id}">
                  <div class="profile-card-left">
                    ${renderAvatarPortrait(avatarKey)}
                    <div>
                      <div class="profile-card-name">${p.name}</div>
                      ${getAvatarRoleBadge(avatarKey)}
                      <div class="profile-card-id">ID: ${p.id}</div>
                    </div>
                  </div>
                  <div class="profile-card-actions">
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-gold btn-edit-profile" data-id="${p.id}">
                      ✏️ Edit
                    </button>
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-profile" data-id="${p.id}" data-name="${p.name}">
                      🗑️ Delete
                    </button>
                  </div>
                </div>
              `;
            });
            container.innerHTML = html;
          }
        } catch (err) {
          if (container) {
            container.innerHTML = `<div class="empty-spaces-notice">Failed to load profiles: ${err.message}</div>`;
          }
        }
      }

      loadProfiles();

      // Avatar Picker handlers for Create
      document.querySelectorAll('#create-avatar-picker .avatar-opt-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('#create-avatar-picker .avatar-opt-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('create-profile-avatar').value = btn.dataset.avatar;
        });
      });

      // Avatar Picker handlers for Edit
      document.querySelectorAll('#edit-avatar-picker .avatar-opt-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('#edit-avatar-picker .avatar-opt-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('edit-profile-avatar').value = btn.dataset.avatar;
        });
      });

      // Create Profile Submit
      const createProfileForm = document.getElementById('create-profile-form');
      if (createProfileForm) {
        createProfileForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const name = document.getElementById('create-profile-name').value.trim();
          const avatar = document.getElementById('create-profile-avatar').value || 'volunteer';

          if (!name) {
            showProfileAlert('error', '⚠️ Please enter a profile name.');
            document.getElementById('create-profile-name').focus();
            return;
          }

          const btn = document.getElementById('btn-submit-profile');
          try {
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Saving...';

            const res = await fetch('api/create_profile.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ name, avatar })
            });

            const json = await res.json();
            if (res.ok && json.success) {
              showProfileAlert('success', `🎉 <strong>Profile Created Successfully!</strong> Welcome, ${json.data.name}!`);
              document.getElementById('create-profile-name').value = '';
              await loadProfiles();
            } else {
              showProfileAlert('error', `❌ <strong>Failed to create profile:</strong> ${json.error || 'Server error'}`);
            }
          } catch (err) {
            showProfileAlert('error', `❌ <strong>Network error:</strong> ${err.message}`);
          } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span> Save Profile';
          }
        });
      }

      // Open Edit Profile Modal
      function openEditProfileModal(id) {
        const profile = profilesData[id];
        if (!profile) return;

        document.getElementById('edit-profile-id').value = profile.id;
        document.getElementById('edit-profile-id-display').value = profile.id;
        document.getElementById('edit-profile-name').value = profile.name;
        const currentAvatar = profile.avatar || 'volunteer';
        document.getElementById('edit-profile-avatar').value = currentAvatar;

        document.querySelectorAll('#edit-avatar-picker .avatar-opt-btn').forEach(b => {
          if (b.dataset.avatar === currentAvatar) {
            b.classList.add('active');
          } else {
            b.classList.remove('active');
          }
        });

        const alertBox = document.getElementById('edit-profile-alert');
        if (alertBox) alertBox.style.display = 'none';

        const modal = document.getElementById('edit-profile-modal');
        if (modal) modal.classList.add('active');
      }

      function closeEditProfileModal() {
        const modal = document.getElementById('edit-profile-modal');
        if (modal) modal.classList.remove('active');
      }

      // Edit Profile Submit
      const editProfileForm = document.getElementById('edit-profile-form');
      if (editProfileForm) {
        editProfileForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const id = document.getElementById('edit-profile-id').value;
          const name = document.getElementById('edit-profile-name').value.trim();
          const avatar = document.getElementById('edit-profile-avatar').value || 'volunteer';

          if (!name) {
            const alertBox = document.getElementById('edit-profile-alert');
            alertBox.className = 'alert-message error';
            alertBox.innerHTML = '⚠️ Please enter a profile name.';
            alertBox.style.display = 'block';
            return;
          }

          const btn = document.getElementById('btn-save-edit-profile');
          try {
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Saving...';

            const res = await fetch('api/update_profile.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id, name, avatar })
            });

            const json = await res.json();
            if (res.ok && json.success) {
              const alertBox = document.getElementById('edit-profile-alert');
              alertBox.className = 'alert-message success';
              alertBox.innerHTML = `✅ <strong>Profile Updated!</strong> Changes saved for "${json.data.name}".`;
              alertBox.style.display = 'block';

              await loadProfiles();
              setTimeout(() => {
                closeEditProfileModal();
              }, 1200);
            } else {
              const alertBox = document.getElementById('edit-profile-alert');
              alertBox.className = 'alert-message error';
              alertBox.innerHTML = `❌ <strong>Failed to update:</strong> ${json.error || 'Server error'}`;
              alertBox.style.display = 'block';
            }
          } catch (err) {
            const alertBox = document.getElementById('edit-profile-alert');
            alertBox.className = 'alert-message error';
            alertBox.innerHTML = `❌ <strong>Network error:</strong> ${err.message}`;
            alertBox.style.display = 'block';
          } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span> Save Changes';
          }
        });
      }

      // Delete Profile Handler
      async function deleteProfile(id, name) {
        if (!id) return;
        const displayName = name || 'this profile';
        const confirmed = window.confirm(`Are you sure you want to delete profile "${displayName}"?\n\nThis will permanently delete the record from Supabase.`);
        if (!confirmed) return;

        try {
          const res = await fetch('api/delete_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
          });

          const json = await res.json();
          if (res.ok && json.success) {
            showProfileAlert('success', `🗑️ <strong>Profile Deleted!</strong> "${displayName}" has been permanently removed from Supabase.`);
            await loadProfiles();
          } else {
            showProfileAlert('error', `❌ <strong>Failed to delete profile:</strong> ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showProfileAlert('error', `❌ <strong>Network error:</strong> ${err.message}`);
        }
      }

      // Delegated events for Edit and Delete buttons on profile cards
      document.body.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit-profile');
        if (editBtn) {
          openEditProfileModal(editBtn.dataset.id);
        }

        const deleteBtn = e.target.closest('.btn-delete-profile');
        if (deleteBtn) {
          deleteProfile(deleteBtn.dataset.id, deleteBtn.dataset.name);
        }

        if (e.target.closest('#btn-close-edit-profile-modal') || e.target.closest('#btn-cancel-edit-profile')) {
          closeEditProfileModal();
        }
      });
    });
  </script>

</body>
</html>
