<?php
/**
 * JodRod (จอดรถ) - Flood Parking Community
 * Index / Home Page
 */
require_once __DIR__ . '/config.php';
$initialUser = null;
$initialCsrf = '';
if (session_status() === PHP_SESSION_NONE) {
    jodrod_session_start();
}
if (!empty($_SESSION['auth_user'])) {
    $initialUser = $_SESSION['auth_user'];
    $initialCsrf = $_SESSION['csrf_token'] ?? '';
}
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
  <script>
    window.initialUser = <?= json_encode($initialUser, JSON_UNESCAPED_UNICODE) ?>;
    window.initialCsrf = <?= json_encode($initialCsrf) ?>;
  </script>
</head>
<body>

  <!-- ========================================================
       PERSISTENT NAVIGATION BAR
       Strictly contains ONLY: Map, My Car, My Spaces, Points, Profile
       ======================================================== -->
  <header class="site-header">
    <nav class="navbar" aria-label="Main Navigation">
      <a href="#map" class="brand">
        <div class="brand-icon">🚗</div>
        <div class="brand-text">
          <span class="brand-title">JODROD</span>
          <span class="brand-tagline">Flood Parking Community</span>
        </div>
      </a>

      <!-- Strictly: Find Parking (Main), My Parking Lots, My Reservations, My Profile -->
      <ul class="nav-links" id="site-nav-links" style="display: none;">
        <li>
          <a href="#map" class="nav-link" id="nav-map">
            Find Parking
          </a>
        </li>
        <li>
          <a href="#my-spaces" class="nav-link" id="nav-my-spaces">
            My Parking Lots
          </a>
        </li>
        <li>
          <a href="#my-reservations" class="nav-link" id="nav-my-reservations">
            My Reservations
          </a>
        </li>
        <li>
          <a href="#vehicles" class="nav-link" id="nav-vehicles">
            My Vehicles
          </a>
        </li>
        <li>
          <a href="#profile" class="nav-link" id="nav-profile">
            My Profile
          </a>
        </li>
      </ul>

      <!-- User Auth State in Navbar -->
      <div class="user-auth-badge" id="nav-user-badge" style="display: none;">
        <span class="user-status-dot"></span>
        <span class="user-email-text" id="nav-user-email"></span>
        <button type="button" class="pixel-btn pixel-btn-sm btn-logout" id="btn-logout" title="Sign out of account">Logout</button>
      </div>
    </nav>
  </header>

  <!-- ========================================================
       MAIN CONTENT CONTAINER
       ======================================================== -->
  <main class="container">

    <!-- ========================================================
         AUTHENTICATION GATE / PROTECTED VIEW
         Displayed when user is not logged in.
         ======================================================== -->
    <section id="auth-gate" class="auth-gate-section" style="display: flex;">
      <div class="auth-card">
        <div class="auth-header">
          
          <div class="auth-badge">COMMUNITY AUTHENTICATION</div>
          <h1 class="auth-title">Flood Relief Access</h1>
          <p class="auth-subtitle">Sign in or create an account to access safe flood parking spaces, coordinate relief, and manage your profile.</p>
        </div>

        <!-- Auth Tabs: Login vs Sign Up -->
        <div class="auth-tabs-row">
          <button type="button" class="auth-tab-btn active" id="tab-auth-login">Log In</button>
          <button type="button" class="auth-tab-btn" id="tab-auth-signup">Sign Up</button>
        </div>

        <!-- Feedback Alert Box -->
        <div id="auth-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 16px;"></div>

        <!-- Authentication Form -->
        <form id="auth-form" novalidate>
          <div class="form-group">
            <label for="auth-email" class="form-label">
              Email Address
            </label>
            <input 
              type="email" 
              id="auth-email" 
              name="email" 
              class="form-control" 
              placeholder="e.g. volunteer@relief.org" 
              required 
              autocomplete="email"
            />
          </div>

          <div class="form-group" style="margin-top: 14px;">
            <label for="auth-password" class="form-label">
              Password
            </label>
            <input 
              type="password" 
              id="auth-password" 
              name="password" 
              class="form-control" 
              placeholder="Minimum 6 characters" 
              minlength="6"
              required 
              autocomplete="current-password"
            />
          </div>

          <div style="margin-top: 20px;">
            <button type="submit" class="pixel-btn pixel-btn-blue pixel-btn-lg" id="btn-auth-submit" style="width: 100%;">
              Log In
            </button>
          </div>

          <div class="auth-switch-text" id="auth-switch-prompt" style="margin-top: 14px; text-align: center; font-size: 0.88rem; color: #64748b;">
            Don't have an account? <a href="#" id="link-switch-to-signup" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Sign up now</a>
          </div>
        </form>
      </div>
    </section>

    <!-- ========================================================
         MAIN APPLICATION CONTENT (PROTECTED)
         Only accessible when user is authenticated.
         ======================================================== -->
    <div id="main-app-content" style="display: none;">

    <!-- HOME / OVERVIEW SECTION (DEFAULT ACTIVE) -->
    <div id="section-home">

      <!-- Hero Banner -->
      <section class="hero-card">
        <div class="hero-badge">Real-World Flood Relief &bull; Safe Parking Network</div>
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
                <div class="action-card-icon">FIND</div>
                <div>
                  <span class="action-badge">FOR DRIVERS</span>
                  <h2 class="action-title">1. Find Parking</h2>
                </div>
              </div>
              <p class="action-desc">
                Find safe, elevated parking spaces nearby to protect your vehicle from rising floodwaters.
              </p>
              <ul class="action-points-list">
                <li>• View safe parking locations on the real map</li>
                <li>• See live available space counts</li>
                <li>• Request parking with mutual confirmation</li>
              </ul>
            </div>
            <div>
              <a href="#map" class="pixel-btn pixel-btn-blue action-btn" style="width: 100%;" id="btn-action-find">
                Find Safe Parking
              </a>
            </div>
          </article>

          <!-- Action 2: Offer Parking Space -->
          <article class="action-card offer-parking" id="card-offer-parking">
            <div>
              <div class="action-card-header">
                <div class="action-card-icon">OFFER</div>
                <div>
                  <span class="action-badge">FOR PARKING OWNERS</span>
                  <h2 class="action-title">2. Offer Parking Space</h2>
                </div>
              </div>
              <p class="action-desc">
                Share your elevated driveway, yard, or lot with neighbors whose vehicles are at risk.
              </p>
              <ul class="action-points-list">
                <li>• Pin your parking location on the map</li>
                <li>• Set your total vehicle capacity</li>
                <li>• Review and confirm parking requests</li>
              </ul>
            </div>
            <div>
              <a href="#my-spaces" class="pixel-btn pixel-btn-green action-btn" style="width: 100%;" id="btn-action-offer">
                Offer Parking Space
              </a>
            </div>
          </article>

        </div>
      </section>

      <!-- Realistic Community Concept & Flow -->
      <section class="concept-section">
        <h3 class="section-title">
          How Flood Parking Works
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
    
    <!-- 1. Map Section: Find Safe Parking (Main Page) -->
    <section id="section-map" class="nav-content-section active">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <div>
          <h2 class="section-title" style="margin-bottom: 2px;">Find Safe Parking</h2>
          <p class="section-subtitle" style="margin: 0;">Explore real flood-safe parking locations, find elevated high ground, and request emergency parking.</p>
        </div>
      </div>

      <!-- Action Feedback Alert Box (for Delete/Update feedback) -->
      <div id="map-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 16px;"></div>

      <!-- Map Toolbar: City Jump Shortcuts & Zero Data Status -->
      <div class="map-toolbar">
        <div class="city-jump-group">
          <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Quick Jump:</span>
          <button type="button" class="city-btn btn-city-jump" data-city="chiangmai">Chiang Mai</button>
          <button type="button" class="city-btn btn-city-jump" data-city="bangkok">Bangkok</button>
        </div>
        <div class="map-status-pill" id="map-status-badge">
          Active Parking Spaces: <strong style="color: #0284c7; margin-left: 4px;">0 (Zero Data)</strong>
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
          Click anywhere on the map to change the selected coordinates.
        </div>
        <a href="#my-spaces?action=offer" class="pixel-btn pixel-btn-green pixel-btn-sm" id="btn-offer-from-map">
          Offer Space at Selected Location &rarr;
        </a>
      </div>

      <!-- Active Parking Spaces Directory (Loaded from Supabase) -->
      <div style="margin-top: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px; margin-bottom: 8px;">
          <div>
            <h3 class="section-title" style="margin-bottom: 2px;">
              Active Parking Spaces
            </h3>
            <p class="section-subtitle" style="margin: 0;">Retrieved live from the database. Search parking spaces by name or owner.</p>
          </div>
          <div style="display: flex; gap: 8px; align-items: center; flex: 1; max-width: 440px; min-width: 260px;">
            <div style="position: relative; flex: 1;">
              <input 
                type="text" 
                id="spaces-search-input" 
                class="form-input" 
                placeholder="Search by parking lot name or owner..." 
                style="padding-right: 28px; width: 100%; margin: 0; min-height: 38px;"
              />
              <button 
                type="button" 
                id="btn-clear-spaces-search" 
                style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1rem; cursor: pointer; color: #94a3b8;" 
                title="Clear search"
              >&times;</button>
            </div>
          </div>
        </div>
        <div id="spaces-search-meta" style="font-size: 0.8rem; color: #64748b; margin-bottom: 12px; font-weight: 600;"></div>
        <div id="parking-spaces-list" class="spaces-list-container">
          <div class="empty-spaces-notice">Loading parking spaces...</div>
        </div>
      </div>
    </section>

    <!-- 2. My Reservations Section: TWO-STEP DRIVER EXPERIENCE -->
    <section id="section-my-reservations" class="nav-content-section">
      <!-- Header Bar -->
      <div class="my-res-header-bar">
        <div>
          <h2 class="section-title">My Reservations</h2>
          <p class="section-subtitle">View and manage your safe flood parking reservations, status updates, and vehicles.</p>
        </div>
        <div class="my-res-header-actions">
          <a href="#map" class="pixel-btn pixel-btn-blue" id="btn-header-find-parking">
            Find Parking
          </a>
          <button type="button" class="pixel-btn" id="btn-refresh-my-reservations" title="Reload live data from Supabase">
            Refresh
          </button>
          <a href="#home" class="pixel-btn btn-back-home">&larr; Back to Home</a>
        </div>
      </div>

      <!-- Reservation Action Alert Box -->
      <div id="reservation-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 20px;"></div>

      <!-- My Vehicles Card (Preserved in full with original behavior & design) -->
      <div class="form-card my-res-vehicles-box">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
              My Vehicles (Cars)
            </h3>
            <p style="font-size: 0.88rem; color: #64748b; margin: 0;">
              Register your cars to link them with parking reservations. One account can own multiple cars.
            </p>
          </div>
          <button type="button" class="pixel-btn pixel-btn-green pixel-btn-sm" id="btn-toggle-add-car">
            Add Vehicle
          </button>
        </div>

        <!-- Add Car Form (Collapsible) -->
        <form id="add-car-form" style="display: none; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 4px; padding: 14px; margin-bottom: 14px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
            <div>
              <label class="form-label" for="car-plate">License Plate *</label>
              <input type="text" id="car-plate" class="form-input" placeholder="e.g. 1กข-9999" required>
            </div>
            <div>
              <label class="form-label" for="car-model">Car Model / Color</label>
              <input type="text" id="car-model" class="form-input" placeholder="e.g. Toyota Hilux (White)">
            </div>
          </div>
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-add-car">Cancel</button>
            <button type="submit" class="pixel-btn pixel-btn-green pixel-btn-sm" id="btn-submit-car">Save Vehicle</button>
          </div>
        </form>

        <div id="cars-list-container">
          <div style="font-size: 0.88rem; color: #64748b;">Loading registered vehicles...</div>
        </div>
      </div>

      <!-- ========================================================
           STEP 1: LIST / GRID OF MY RESERVATIONS
           ======================================================== -->
      <div id="my-res-step1-view">
        <!-- Compact Summary Metrics Bar -->
        <div class="my-res-metrics-bar" id="my-res-summary-metrics" style="display: none;">
          <div class="my-res-metric-item">
            <span class="my-res-metric-num" id="res-metric-total">0</span>
            <span class="my-res-metric-label">Total Reservations</span>
          </div>
          <div class="my-res-metric-item">
            <span class="my-res-metric-num" id="res-metric-pending" style="color: #b45309;">0</span>
            <span class="my-res-metric-label">Pending Approval</span>
          </div>
          <div class="my-res-metric-item">
            <span class="my-res-metric-num" id="res-metric-confirmed" style="color: #15803d;">0</span>
            <span class="my-res-metric-label">Confirmed Spots</span>
          </div>
          <div class="my-res-metric-item">
            <span class="my-res-metric-num" id="res-metric-history" style="color: #64748b;">0</span>
            <span class="my-res-metric-label">Past / Historical</span>
          </div>
        </div>

        <!-- Status Filter Tabs (All, Pending, Confirmed, Past/Cancelled) -->
        <div class="my-res-filter-bar" id="my-res-filter-controls">
          <button type="button" class="pixel-btn pixel-btn-sm filter-btn active" data-filter="all" id="btn-filter-all">
            All (<span id="filter-count-all">0</span>)
          </button>
          <button type="button" class="pixel-btn pixel-btn-sm filter-btn" data-filter="pending" id="btn-filter-pending">
            Pending (<span id="filter-count-pending">0</span>)
          </button>
          <button type="button" class="pixel-btn pixel-btn-sm filter-btn" data-filter="confirmed" id="btn-filter-confirmed">
            Confirmed (<span id="filter-count-confirmed">0</span>)
          </button>
          <button type="button" class="pixel-btn pixel-btn-sm filter-btn" data-filter="past" id="btn-filter-past">
            Past / Cancelled (<span id="filter-count-past">0</span>)
          </button>
        </div>

        <!-- Reservations List / Grid Container -->
        <div id="reservations-list-container">
          <div class="empty-spaces-notice">
            <span>⏳</span> Loading your reservations...
          </div>
        </div>
      </div>

      <!-- ========================================================
           STEP 2: DETAIL VIEW FOR SPECIFIC RESERVATION
           ======================================================== -->
      <div id="my-res-step2-view" style="display: none;">
        <div style="margin-bottom: 16px;">
          <button type="button" class="pixel-btn pixel-btn-sm btn-back-to-reservations" id="btn-back-to-my-reservations">
            &larr; Back to My Reservations
          </button>
        </div>
        <div id="my-reservation-detail-content"></div>
      </div>
    </section>

    <!-- 3. My Parking Lots Section: TWO-STEP OWNER EXPERIENCE -->
    <section id="section-my-spaces" class="nav-content-section">
      <!-- Header Bar -->
      <div class="my-spaces-header-bar">
        <div>
          <h2 class="section-title">My Parking Lots</h2>
          <p class="section-subtitle">Manage your flood-safe elevated spaces, review driver requests, and offer new lots.</p>
        </div>
        <div class="my-spaces-header-actions">
          <button type="button" class="pixel-btn pixel-btn-green" id="btn-open-offer-lot">
            Offer Parking Lot
          </button>
          <button type="button" class="pixel-btn" id="btn-refresh-my-lots" title="Reload live data from Supabase">
            Refresh
          </button>
          <a href="#home" class="pixel-btn btn-back-home">&larr; Back to Home</a>
        </div>
      </div>

      <!-- Action Feedback Alert Box -->
      <div id="my-spaces-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 20px;"></div>

      <!-- Offer Parking Space Form Card (Preserved in full with original behavior & design) -->
      <div id="offer-parking-form-container" class="form-card my-spaces-offer-box" style="display: none; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <h3 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
            Offer Parking Space
          </h3>
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-close-offer-form" title="Close offer form">
            Close Form
          </button>
        </div>
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
                Select on Map
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
              <option value="closed">Closed (Temporarily unavailable)</option>
            </select>
            <div class="form-help">Status is automatic based on capacity (reaches 0 spots &rarr; Full).</div>
          </div>

          <button type="submit" id="btn-submit-space" class="pixel-btn pixel-btn-green" style="width: 100%; padding: 14px; font-size: 1.05rem;">
            Save & Offer Parking Space
          </button>

        </form>
      </div>

      <!-- Hidden compatibility elements for existing legacy hooks -->
      <div id="owner-incoming-requests-container" style="display: none;"><!-- Incoming Parking Requests (Owner Approval) --></div>
      <div id="owner-requests-alert" style="display: none;"></div>

      <!-- ========================================================
           STEP 1: LIST / GRID OF OWNED PARKING LOTS
           ======================================================== -->
      <div id="my-lots-step1-view">
        <div id="my-lots-list-container">
          <div class="empty-spaces-notice">Loading your parking lots...</div>
        </div>
      </div>

      <!-- ========================================================
           STEP 2: DETAIL VIEW FOR SPECIFIC PARKING LOT
           ======================================================== -->
      <div id="my-lots-step2-view" style="display: none;">
        <div style="margin-bottom: 16px;">
          <button type="button" class="pixel-btn pixel-btn-sm btn-back-to-lots" id="btn-back-to-my-lots">
            &larr; Back to My Parking Lots
          </button>
        </div>
        <div id="my-lot-detail-content"></div>
      </div>
    </section>

    <!-- 4. Points Section -->
    <section id="section-points" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>⭐</span> Community Points</h2>
        <a href="#home" class="pixel-btn btn-back-home">&larr; Back to Home</a>
      </div>
      <p class="section-subtitle">Your volunteer points and community helper score.</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">⭐ Points Reward Ready</p>
        <p>Points will be awarded following verified mutual confirmations.</p>
      </div>
    </section>

    <!-- 5. Profile Section -->
    <section id="section-profile" class="nav-content-section">
      <div class="profile-header-bar">
        <div>
          <h2 class="section-title">My Profile</h2>
          <p class="section-subtitle">Manage your player identity and custom 3D avatar for flood parking.</p>
        </div>
        <a href="#home" class="pixel-btn btn-back-home">&larr; Back to Home</a>
      </div>

      <!-- Profile Action Alert Box -->
      <div id="profile-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 20px;"></div>

      <div class="actions-grid" style="align-items: start; margin-bottom: 24px;">
        <!-- Left: My 3D Avatar & Profile (View Summary & Dedicated Editor) -->
        <div class="form-card" id="left-profile-container">
          <!-- 1. View Mode: Active Profile Summary -->
          <div id="my-profile-view-box" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">
                Player Profile
              </h3>
              <span class="status-badge status-available" style="font-size: 0.72rem; padding: 3px 8px;">Active</span>
            </div>
            <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 16px;">
              Your active flood parking identity and custom 3D avatar.
            </p>

            <div class="profile-card" id="my-profile-summary-card" style="border: 2px solid #3b82f6; background: #f8fafc; margin-bottom: 18px; padding: 14px;">
              <div class="profile-card-left" style="align-items: center; gap: 14px;">
                <div id="my-profile-view-avatar" style="flex-shrink: 0;"></div>
                <div style="flex: 1; min-width: 0;">
                  <div class="profile-card-name" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                    <span id="my-profile-view-name">...</span>
                    <span class="status-badge status-available" style="font-size: 0.65rem; padding: 2px 6px;">You</span>
                  </div>
                  <div id="my-profile-view-specs" style="margin-top: 5px;"></div>
                  <div style="margin-top: 6px;">
                    <span class="profile-clean-id" id="my-profile-view-id-badge" style="font-size: 0.75rem;">
                      <span class="id-hash">#</span><span id="my-profile-view-id-text">...</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <button type="button" id="btn-open-my-profile-editor" class="pixel-btn pixel-btn-blue" style="width: 100%; padding: 12px; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
              Edit My Profile
            </button>
          </div>

          <!-- 2. Editor Mode: Editable details form -->
          <div id="my-profile-editor-box">
            <h3 id="create-profile-card-title" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
              Edit Profile
            </h3>
            <p id="create-profile-card-desc" style="font-size: 0.88rem; color: #64748b; margin-bottom: 18px;">
              Customize your personal 3D avatar and flood rescue display name.
            </p>

            <form id="create-profile-form" novalidate>
              <input type="hidden" id="my-profile-id" name="id" value="">
              <div id="my-profile-badge-bar" style="display: none; align-items: center; justify-content: space-between; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 6px 12px; margin-bottom: 14px;">
                <span style="font-size: 0.8rem; font-weight: 700; color: #166534;">Editing Active Profile</span>
                <span style="font-size: 0.75rem; font-family: monospace; color: #475569;" id="my-profile-id-tag">#...</span>
              </div>

              <div class="form-group">
                <label for="create-profile-name" class="form-label">Display Name *</label>
                <input type="text" id="create-profile-name" name="name" class="form-input" placeholder="e.g. Somsak P." required>
                <div class="form-help">Your public display name for flood parking.</div>
              </div>

              <!-- 3D Character Customizer Studio -->
              <div class="form-group" style="margin-top: 18px;">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                  <span>3D Character Studio</span>
                  <span style="font-size: 0.72rem; color: #0284c7; font-weight: 700;">LIVE 3D VIEWPORT</span>
                </label>

                <!-- 3D Viewport Frame -->
                <div class="character-3d-viewport" id="create-3d-viewport">
                  <canvas id="create-character-canvas"></canvas>
                  <div class="viewport-hint">Drag to rotate 360°</div>
                  <div class="viewport-badge">3D AVATAR</div>
                </div>

                <!-- Logical Customizer Category Tabs -->
                <div class="customizer-tabs-nav" id="create-customizer-tabs" role="tablist">
                  <button type="button" class="customizer-tab-btn active" data-tab="body" role="tab" aria-selected="true">Face & Body</button>
                  <button type="button" class="customizer-tab-btn" data-tab="hair" role="tab" aria-selected="false">Hairstyle</button>
                  <button type="button" class="customizer-tab-btn" data-tab="outfit" role="tab" aria-selected="false">Outfit</button>
                </div>

                <!-- Customizer Category Panels Container -->
                <div class="character-customizer-controls" id="create-customizer-panels">
                  
                  <!-- Category 1: Face & Body -->
                  <div class="customizer-tab-panel" id="create-tab-body" data-tab="body">
                    <!-- Gender Choice -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Gender</span></div>
                      <div class="gender-options-row" id="create-gender-options">
                        <button type="button" class="gender-chip-btn active" data-gender="female">Female</button>
                        <button type="button" class="gender-chip-btn" data-gender="male">Male</button>
                      </div>
                    </div>

                    <!-- Gender Feature (Feminine Accent or Facial Feature) -->
                    <div class="customizer-group" id="create-feature-group">
                      <div class="customizer-label"><span id="create-feature-label">Feminine Accent</span></div>
                      <div class="customizer-options-row" id="create-feature-options"></div>
                    </div>

                    <!-- Skin Tone -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Skin Tone</span></div>
                      <div class="color-swatch-row" id="create-skin-colors">
                        <button type="button" class="color-swatch-btn" data-color="#ffedd5" style="background: #ffedd5;" title="Fair"></button>
                        <button type="button" class="color-swatch-btn active" data-color="#fed7aa" style="background: #fed7aa;" title="Warm Peach"></button>
                        <button type="button" class="color-swatch-btn" data-color="#fcd34d" style="background: #fcd34d;" title="Golden"></button>
                        <button type="button" class="color-swatch-btn" data-color="#d97706" style="background: #d97706;" title="Tan"></button>
                        <button type="button" class="color-swatch-btn" data-color="#78350f" style="background: #78350f;" title="Deep"></button>
                      </div>
                    </div>
                  </div>

                  <!-- Category 2: Hairstyle -->
                  <div class="customizer-tab-panel" id="create-tab-hair" data-tab="hair" style="display: none;">
                    <!-- Hair Style (Dynamically tailored to gender) -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Hair Style</span></div>
                      <div class="customizer-options-row" id="create-hair-styles"></div>
                    </div>

                    <!-- Hair Color -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Hair Color</span></div>
                      <div class="color-swatch-row" id="create-hair-colors">
                        <button type="button" class="color-swatch-btn active" data-color="#261e1b" style="background: #261e1b;" title="Black"></button>
                        <button type="button" class="color-swatch-btn" data-color="#5c3a21" style="background: #5c3a21;" title="Brown"></button>
                        <button type="button" class="color-swatch-btn" data-color="#d97706" style="background: #d97706;" title="Blonde"></button>
                        <button type="button" class="color-swatch-btn" data-color="#991b1b" style="background: #991b1b;" title="Red"></button>
                        <button type="button" class="color-swatch-btn" data-color="#0284c7" style="background: #0284c7;" title="Blue"></button>
                        <button type="button" class="color-swatch-btn" data-color="#94a3b8" style="background: #94a3b8;" title="Silver"></button>
                        <button type="button" class="color-swatch-btn" data-color="#ec4899" style="background: #ec4899;" title="Pink"></button>
                      </div>
                    </div>
                  </div>

                  <!-- Category 3: Outfit -->
                  <div class="customizer-tab-panel" id="create-tab-outfit" data-tab="outfit" style="display: none;">
                    <!-- Clothes Style (10 Relief Outfits) -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Relief Outfit</span></div>
                      <div class="customizer-options-row" id="create-clothes-styles">
                        <button type="button" class="customizer-chip-btn active" data-clothes="vest">Rescue Vest</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="jacket">Flood Jacket</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="raincoat">Rain Poncho</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="wetsuit">Wetsuit</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="parka">Storm Parka</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="scrubs">Medic Scrubs</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="overalls">Overalls</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="tee">Casual Tee</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="uniform">Rescue Uniform</button>
                        <button type="button" class="customizer-chip-btn" data-clothes="hoodie">Hoodie</button>
                      </div>
                    </div>

                    <!-- Clothes Color -->
                    <div class="customizer-group">
                      <div class="customizer-label"><span>Outfit Color</span></div>
                      <div class="color-swatch-row" id="create-clothes-colors">
                        <button type="button" class="color-swatch-btn active" data-color="#ea580c" style="background: #ea580c;" title="Hi-Vis Orange"></button>
                        <button type="button" class="color-swatch-btn" data-color="#eab308" style="background: #eab308;" title="Safety Yellow"></button>
                        <button type="button" class="color-swatch-btn" data-color="#0284c7" style="background: #0284c7;" title="Flood Blue"></button>
                        <button type="button" class="color-swatch-btn" data-color="#059669" style="background: #059669;" title="Rescue Green"></button>
                        <button type="button" class="color-swatch-btn" data-color="#dc2626" style="background: #dc2626;" title="Emergency Red"></button>
                        <button type="button" class="color-swatch-btn" data-color="#1e293b" style="background: #1e293b;" title="Navy Blue"></button>
                      </div>
                    </div>
                  </div>

                </div>

                <input type="hidden" id="create-profile-avatar" name="avatar">
              </div>

              <div style="display: flex; gap: 10px; margin-top: 16px;">
                <button type="submit" id="btn-submit-profile" class="pixel-btn pixel-btn-green" style="flex: 1; padding: 12px; font-size: 1rem;">
                  Save Profile
                </button>
                <button type="button" id="btn-cancel-profile-edit" class="pixel-btn pixel-btn-sm" style="display: none; padding: 12px 18px; background: #f1f5f9; border-color: #cbd5e1; color: #475569;">
                  Cancel
                </button>
                <button type="button" id="btn-delete-my-profile" class="pixel-btn pixel-btn-sm pixel-btn-red" style="display: none; padding: 12px 14px;" title="Permanently delete your profile">
                  Delete Profile
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Right: Profiles List (View Profile) -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
              Community Members
            </h3>
            <span class="status-pill status-available" id="profile-count-pill">0 Members</span>
          </div>

          <div id="profiles-list-container">
            <div class="empty-spaces-notice">Loading profiles...</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================
         6. VEHICLES SECTION: 3D CAR CUSTOMIZER STUDIO
         Matches the exact 16-bit retro UX/UI patterns of My Profile & 3D Avatar
         ======================================================== -->
    <section id="section-vehicles" class="nav-content-section">
      <div class="profile-header-bar">
        <div>
          <h2 class="section-title">My Vehicles & 3D Customizer</h2>
          <p class="section-subtitle">Register, inspect, and customize your 3D flood rescue vehicles with real-time 3D preview.</p>
        </div>
        <a href="#home" class="pixel-btn btn-back-home">&larr; Back to Home</a>
      </div>

      <!-- Vehicle Action Alert Box -->
      <div id="vehicle-action-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 20px;"></div>

      <div class="actions-grid" style="align-items: start; margin-bottom: 24px;">
        <!-- Left: 3D Vehicle Customizer Studio (Dedicated Studio & Registration) -->
        <div class="form-card" id="left-vehicle-container">
          <h3 id="vehicle-studio-title" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
            Register Vehicle
          </h3>
          <p id="vehicle-studio-desc" style="font-size: 0.88rem; color: #64748b; margin-bottom: 18px;">
            Customize your 3D flood vehicle and link your license plate for reservations.
          </p>

          <form id="vehicle-studio-form" novalidate>
            <input type="hidden" id="vehicle-studio-id" name="id" value="">

            <div id="vehicle-badge-bar" style="display: none; align-items: center; justify-content: space-between; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 6px 12px; margin-bottom: 14px;">
              <span style="font-size: 0.8rem; font-weight: 700; color: #166534;">Editing Active Vehicle</span>
              <span style="font-size: 0.75rem; font-family: monospace; color: #475569;" id="vehicle-id-tag">#...</span>
            </div>

            <div class="form-group">
              <label for="vehicle-studio-plate" class="form-label">License Plate *</label>
              <input type="text" id="vehicle-studio-plate" name="license_plate" class="form-input" placeholder="e.g. 1กข-9999" required autocomplete="off">
              <div class="form-help">Vehicle registration license plate. Must be unique to your account.</div>
            </div>

            <div class="form-group" style="margin-top: 14px;">
              <label for="vehicle-studio-name" class="form-label">Vehicle Label (Optional)</label>
              <input type="text" id="vehicle-studio-name" name="custom_label" class="form-input" placeholder="e.g. Toyota Hilux Rescue" autocomplete="off">
              <div class="form-help">Custom description or friendly name to identify your car in parking lots.</div>
            </div>

            <!-- 3D Vehicle Studio Viewport -->
            <div class="form-group" style="margin-top: 18px;">
              <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                <span>3D Car Studio</span>
                <span style="font-size: 0.72rem; color: #0284c7; font-weight: 700;">LIVE 3D VIEWPORT</span>
              </label>

              <!-- 3D Viewport Frame -->
              <div class="character-3d-viewport vehicle-3d-viewport" id="car-3d-viewport">
                <canvas id="car-studio-canvas"></canvas>
                <div class="viewport-hint">Drag to rotate 360°</div>
                <div class="viewport-badge">3D VEHICLE</div>
              </div>

              <!-- Logical Customizer Category Tabs -->
              <div class="customizer-tabs-nav" id="car-customizer-tabs" role="tablist">
                <button type="button" class="customizer-tab-btn active" data-tab="model" role="tab" aria-selected="true">Body Style</button>
                <button type="button" class="customizer-tab-btn" data-tab="paint" role="tab" aria-selected="false">Paint Color</button>
                <button type="button" class="customizer-tab-btn" data-tab="wheels" role="tab" aria-selected="false">Wheels</button>
                <button type="button" class="customizer-tab-btn" data-tab="details" role="tab" aria-selected="false">Flood Gear</button>
              </div>

              <!-- Customizer Category Panels Container -->
              <div class="character-customizer-controls" id="car-customizer-panels">
                
                <!-- Category 1: Body Style -->
                <div class="customizer-tab-panel" id="car-tab-model" data-tab="model">
                  <div class="customizer-group">
                    <div class="customizer-label"><span>Vehicle Body Style</span></div>
                    <div class="customizer-options-row" id="car-model-options">
                      <button type="button" class="customizer-chip-btn active" data-style="pickup">Rescue Pickup</button>
                      <button type="button" class="customizer-chip-btn" data-style="suv">4x4 Offroad SUV</button>
                      <button type="button" class="customizer-chip-btn" data-style="van">Emergency Van</button>
                      <button type="button" class="customizer-chip-btn" data-style="compact">Compact Car</button>
                    </div>
                  </div>
                  <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" class="pixel-btn pixel-btn-sm" id="btn-reset-car-appearance" style="font-size: 0.75rem; padding: 6px 10px;">
                      Reset Appearance
                    </button>
                  </div>
                </div>

                <!-- Category 2: Paint Color -->
                <div class="customizer-tab-panel" id="car-tab-paint" data-tab="paint" style="display: none;">
                  <div class="customizer-group">
                    <div class="customizer-label"><span>Body Paint Finish</span></div>
                    <div class="color-swatch-row" id="car-paint-colors">
                      <button type="button" class="color-swatch-btn active" data-color="#ea580c" style="background: #ea580c;" title="Hi-Vis Rescue Orange"></button>
                      <button type="button" class="color-swatch-btn" data-color="#f8fafc" style="background: #f8fafc;" title="Flood Relief White"></button>
                      <button type="button" class="color-swatch-btn" data-color="#eab308" style="background: #eab308;" title="Safety Yellow"></button>
                      <button type="button" class="color-swatch-btn" data-color="#dc2626" style="background: #dc2626;" title="Emergency Red"></button>
                      <button type="button" class="color-swatch-btn" data-color="#0284c7" style="background: #0284c7;" title="Flood Blue"></button>
                      <button type="button" class="color-swatch-btn" data-color="#15803d" style="background: #15803d;" title="Tactical Green"></button>
                      <button type="button" class="color-swatch-btn" data-color="#475569" style="background: #475569;" title="Matte Slate"></button>
                      <button type="button" class="color-swatch-btn" data-color="#1e293b" style="background: #1e293b;" title="Deep Onyx"></button>
                    </div>
                  </div>
                </div>

                <!-- Category 3: Wheels -->
                <div class="customizer-tab-panel" id="car-tab-wheels" data-tab="wheels" style="display: none;">
                  <div class="customizer-group">
                    <div class="customizer-label"><span>Wheel & Suspension Setup</span></div>
                    <div class="customizer-options-row" id="car-wheels-options">
                      <button type="button" class="customizer-chip-btn active" data-wheels="flood">High-Clearance Flood</button>
                      <button type="button" class="customizer-chip-btn" data-wheels="offroad">All-Terrain Offroad</button>
                      <button type="button" class="customizer-chip-btn" data-wheels="steelie">Heavy Steelies</button>
                      <button type="button" class="customizer-chip-btn" data-wheels="sport">Sport Alloys</button>
                    </div>
                  </div>
                </div>

                <!-- Category 4: Flood Gear / Details -->
                <div class="customizer-tab-panel" id="car-tab-details" data-tab="details" style="display: none;">
                  <div class="customizer-group">
                    <div class="customizer-label"><span>Flood Relief Equipment</span></div>
                    <div class="customizer-options-row" id="car-details-options">
                      <button type="button" class="customizer-chip-btn active" data-detail="snorkel">Snorkel & Lift</button>
                      <button type="button" class="customizer-chip-btn" data-detail="roofrack">Roof Rack & Lights</button>
                      <button type="button" class="customizer-chip-btn" data-detail="beacon">Emergency Beacon</button>
                      <button type="button" class="customizer-chip-btn" data-detail="standard">Standard Clean</button>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 16px;">
              <button type="submit" id="btn-submit-car-studio" class="pixel-btn pixel-btn-green" style="flex: 1; padding: 12px; font-size: 1rem;">
                Save Vehicle
              </button>
              <button type="button" id="btn-cancel-car-studio" class="pixel-btn pixel-btn-sm" style="display: none; padding: 12px 18px; background: #f1f5f9; border-color: #cbd5e1; color: #475569;">
                Cancel
              </button>
              <button type="button" id="btn-delete-car-studio" class="pixel-btn pixel-btn-sm pixel-btn-red" style="display: none; padding: 12px 14px;" title="Permanently delete vehicle">
                Delete Vehicle
              </button>
            </div>
          </form>
        </div>

        <!-- Right: My Registered Vehicles List -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
              My Registered Vehicles
            </h3>
            <span class="status-pill status-available" id="vehicle-count-pill">0 Vehicles</span>
          </div>

          <div id="vehicle-studio-list-container">
            <div class="empty-spaces-notice">Loading registered vehicles...</div>
          </div>
        </div>
      </div>
    </section>

    </div> <!-- /#main-app-content -->

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
        <h3 id="edit-modal-title" class="modal-title">Edit Parking Space</h3>
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
            <option value="closed">Closed (Temporarily unavailable)</option>
          </select>
          <div class="form-help">Status is automatic based on capacity (reaches 0 spots &rarr; Full).</div>
        </div>

        <div class="modal-actions">
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-edit">Cancel</button>
          <button type="submit" class="pixel-btn pixel-btn-green" id="btn-save-edit-space">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Register & Edit Vehicle (Car) -->
  <div id="modal-car" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-car-title">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="modal-car-title" class="modal-title"><span id="modal-car-title-text">Register Vehicle</span></h3>
        <button type="button" class="modal-close-btn" id="btn-close-car-modal" title="Close">&times;</button>
      </div>
      <div id="modal-car-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 14px;"></div>
      <form id="form-car-modal" novalidate>
        <input type="hidden" id="car-modal-id" name="id">
        <div class="form-group">
          <label for="car-modal-plate" class="form-label">License Plate *</label>
          <input type="text" id="car-modal-plate" class="form-input" placeholder="e.g. 1กข-9999" required autocomplete="off">
          <div class="form-help">Vehicle registration license plate. Must be unique to your account.</div>
        </div>
        <div class="form-group" style="margin-top: 14px;">
          <label for="car-modal-model" class="form-label">Car Make & Model / Color</label>
          <input type="text" id="car-modal-model" class="form-input" placeholder="e.g. Toyota Hilux (White)" autocomplete="off">
          <div class="form-help">Optional model description to identify your vehicle in flood relief lots.</div>
        </div>
        <div style="margin-top: 14px; padding: 10px; background: #eff6ff; border: 1px dashed #93c5fd; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
          <span style="font-size: 0.8rem; color: #1e40af; font-weight: 700;">Customize appearance in 3D:</span>
          <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue" id="btn-modal-open-3d-studio" style="font-size: 0.76rem; padding: 6px 10px;">
            Open 3D Studio
          </button>
        </div>
        <div class="modal-actions" style="margin-top: 20px;">
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-car-modal">Cancel</button>
          <button type="submit" class="pixel-btn pixel-btn-green" id="btn-submit-car-modal">
            Save Vehicle
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Reserve Parking Spot for Vehicle -->
  <div id="modal-reserve-car" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-reserve-title">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="modal-reserve-title" class="modal-title">Request Parking Reservation</h3>
        <button type="button" class="modal-close-btn" id="btn-close-reserve-modal" title="Close">&times;</button>
      </div>
      <div id="modal-reserve-alert" class="alert-message" role="alert" style="display: none; margin-bottom: 14px;"></div>
      <form id="form-reserve-car-modal" novalidate>
        <div class="form-group">
          <label for="reserve-modal-car-select" class="form-label">Select Your Car *</label>
          <select id="reserve-modal-car-select" class="form-select" required>
            <option value="">Select your car</option>
          </select>
          <div id="reserve-modal-car-help" class="form-help">Select one of your registered flood evacuation vehicles.</div>
          <!-- Helpful guidance when no cars are registered -->
          <div id="reserve-modal-no-cars-notice" class="alert-message warning" style="display: none; margin-top: 10px;">
            You haven't added a car yet.
            <div style="margin-top: 8px;">
              <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue" id="btn-modal-go-add-car">
                Add a Car in My Vehicles
              </button>
            </div>
          </div>
        </div>
        <div class="form-group" style="margin-top: 14px;">
          <label for="reserve-modal-lot-select" class="form-label">Select Available Parking Lot *</label>
          <select id="reserve-modal-lot-select" class="form-select" required>
            <option value="">-- Choose Parking Lot --</option>
          </select>
          <div class="form-help">Only host-offered parking spaces with open capacity (&gt; 0) can receive requests. The owner must approve before spots are locked.</div>
        </div>
        <div class="form-group" style="margin-top: 14px;">
          <label for="reserve-modal-notes" class="form-label">Reservation Notes (Optional)</label>
          <textarea id="reserve-modal-notes" class="form-input" rows="2" placeholder="e.g. Expected parking until flood water recedes..."></textarea>
        </div>
        <div class="modal-actions" style="margin-top: 20px;">
          <button type="button" class="pixel-btn pixel-btn-sm" id="btn-cancel-reserve-modal">Cancel</button>
          <button type="submit" class="pixel-btn pixel-btn-green" id="btn-submit-reserve-modal">
            Request Reservation
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
      <strong>JodRod &bull; Flood Parking Community</strong> &bull; Civic Disaster Relief Initiative<br>
      Connecting neighbors to keep vehicles safe during flood emergencies.
    </p>
  </footer>

  <!-- ========================================================
       LEAFLET & MAP ENGINE & 3D CHARACTER ENGINE
       ======================================================== -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="assets/js/character_3d.js"></script>
  <script src="assets/js/car_3d.js"></script>
  <script src="assets/js/parking_map.js"></script>

  <!-- ========================================================
       PERSISTENT NAVIGATION CONTROLLER
       ======================================================== -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // ========================================================
      // AUTHENTICATION STATE & CONTROLLER
      // ========================================================
      let currentUser = window.initialUser || null;
      window.csrfToken = window.initialCsrf || '';
      let currentAuthMode = 'login'; // 'login' or 'signup'

      const authGate = document.getElementById('auth-gate');
      const mainAppContent = document.getElementById('main-app-content');
      const siteNavLinks = document.getElementById('site-nav-links');
      const navUserBadge = document.getElementById('nav-user-badge');
      const navUserEmail = document.getElementById('nav-user-email');
      const authAlert = document.getElementById('auth-alert');
      const authForm = document.getElementById('auth-form');
      const tabLogin = document.getElementById('tab-auth-login');
      const tabSignup = document.getElementById('tab-auth-signup');
      const btnAuthSubmit = document.getElementById('btn-auth-submit');
      const authSwitchPrompt = document.getElementById('auth-switch-prompt');

      function showAuthAlert(type, message) {
        if (!authAlert) return;
        authAlert.className = `alert-message ${type}`;
        authAlert.innerHTML = message;
        authAlert.style.display = 'block';
      }

      function hideAuthAlert() {
        if (authAlert) authAlert.style.display = 'none';
      }

      function setAuthMode(mode) {
        currentAuthMode = mode;
        hideAuthAlert();
        if (mode === 'signup') {
          tabSignup.classList.add('active');
          tabLogin.classList.remove('active');
          btnAuthSubmit.innerHTML = 'Create Account';
          btnAuthSubmit.className = 'pixel-btn pixel-btn-green pixel-btn-lg';
          authSwitchPrompt.innerHTML = `Already have an account? <a href="#" id="link-switch-to-login" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Log in</a>`;
          document.getElementById('link-switch-to-login')?.addEventListener('click', (e) => {
            e.preventDefault();
            setAuthMode('login');
          });
        } else {
          tabLogin.classList.add('active');
          tabSignup.classList.remove('active');
          btnAuthSubmit.innerHTML = 'Log In';
          btnAuthSubmit.className = 'pixel-btn pixel-btn-blue pixel-btn-lg';
          authSwitchPrompt.innerHTML = `Don't have an account? <a href="#" id="link-switch-to-signup" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Sign up now</a>`;
          document.getElementById('link-switch-to-signup')?.addEventListener('click', (e) => {
            e.preventDefault();
            setAuthMode('signup');
          });
        }
      }

      tabLogin?.addEventListener('click', () => setAuthMode('login'));
      tabSignup?.addEventListener('click', () => setAuthMode('signup'));
      document.getElementById('link-switch-to-signup')?.addEventListener('click', (e) => {
        e.preventDefault();
        setAuthMode('signup');
      });

      function updateAuthUI() {
        window.currentUser = currentUser;
        if (currentUser) {
          authGate.style.display = 'none';
          mainAppContent.style.display = 'block';
          siteNavLinks.style.display = 'flex';
          navUserBadge.style.display = 'inline-flex';
          navUserEmail.textContent = currentUser.email;
          navUserEmail.title = `Logged in as: ${currentUser.email}`;
          if (!window.location.hash || window.location.hash === '#' || window.location.hash === '#dashboard' || window.location.hash === '#home') {
            window.location.hash = '#map';
          } else {
            handleRoute();
          }
          if (typeof loadProfiles === 'function') {
            loadProfiles();
          }
        } else {
          authGate.style.display = 'flex';
          mainAppContent.style.display = 'none';
          siteNavLinks.style.display = 'none';
          navUserBadge.style.display = 'none';
          navUserEmail.textContent = '';
          if (typeof loadProfiles === 'function') {
            loadProfiles();
          }
        }
      }

      async function checkAuthSession() {
        try {
          const res = await fetch('api/auth.php?action=user');
          const json = await res.json();
          if (json && json.csrf_token) {
            window.csrfToken = json.csrf_token;
          }
          if (res.ok && json.logged_in && json.user) {
            currentUser = json.user;
          } else {
            currentUser = null;
          }
        } catch (e) {
          currentUser = null;
        } finally {
          updateAuthUI();
        }
      }

      // Handle Auth Form Submission
      authForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('auth-email').value.trim();
        const password = document.getElementById('auth-password').value;

        if (!email || !password) {
          showAuthAlert('error', 'Please enter both email and password.');
          return;
        }

        try {
          btnAuthSubmit.disabled = true;
          btnAuthSubmit.innerHTML = '<span>⏳</span> Processing...';

          const res = await fetch(`api/auth.php?action=${currentAuthMode}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
          });
          const json = await res.json();

          if (res.ok && json.success) {
            if (json.csrf_token) {
              window.csrfToken = json.csrf_token;
            }
            if (json.auto_logged_in || currentAuthMode === 'login') {
              currentUser = json.user;
              document.getElementById('auth-password').value = '';
              hideAuthAlert();
              window.location.hash = '#map';
              updateAuthUI();
            } else if (json.requires_confirmation) {
              showAuthAlert('success', `<strong>Account Created!</strong> ${json.message}<br><small>If you have auto-confirm enabled or confirmed via email, click "Log In" above.</small>`);
              setAuthMode('login');
            }
          } else {
            showAuthAlert('error', `<strong>${currentAuthMode === 'login' ? 'Login Failed' : 'Sign Up Failed'}:</strong> ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showAuthAlert('error', `<strong>Network error:</strong> ${err.message}`);
        } finally {
          btnAuthSubmit.disabled = false;
          btnAuthSubmit.innerHTML = (currentAuthMode === 'login') ? 'Log In' : 'Create Account';
        }
      });

      // Handle Logout
      document.getElementById('btn-logout')?.addEventListener('click', async () => {
        try {
          const res = await fetch('api/auth.php?action=logout', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.csrfToken || '' }
          });
          const json = await res.json();
          if (json && json.csrf_token) {
            window.csrfToken = json.csrf_token;
          }
        } catch (e) {}
        currentUser = null;
        updateAuthUI();
        showAuthAlert('success', '<strong>Logged out.</strong> You have successfully signed out.');
      });

      const navLinks = {
        '#map': document.getElementById('nav-map'),
        '#my-spaces': document.getElementById('nav-my-spaces'),
        '#my-reservations': document.getElementById('nav-my-reservations'),
        '#vehicles': document.getElementById('nav-vehicles'),
        '#profile': document.getElementById('nav-profile')
      };

      const sections = {
        '#home': document.getElementById('section-home'),
        '#map': document.getElementById('section-map'),
        '#my-spaces': document.getElementById('section-my-spaces'),
        '#my-reservations': document.getElementById('section-my-reservations'),
        '#vehicles': document.getElementById('section-vehicles'),
        '#points': document.getElementById('section-points'),
        '#profile': document.getElementById('section-profile')
      };

      function handleRoute() {
        if (!currentUser) {
          // Protected gate
          authGate.style.display = 'flex';
          mainAppContent.style.display = 'none';
          return;
        }

        // Find Parking (#map) is the main page once logged in
        let rawHash = window.location.hash || '';
        let [hashPath, hashQuery] = rawHash.split('?');
        let hash = hashPath;
        if (!hash || hash === '#' || hash === '#dashboard' || hash === '#home') {
          hash = '#map';
          window.location.hash = '#map';
          return;
        }

        if (hash === '#my-car' || hash === '#my-cars' || hash === '#my-vehicles' || hash === '#cars' || hash === '#car' || hash === '#create-vehicle' || hash === '#vehicle-studio') {
          hash = '#vehicles';
        }

        const isOfferAction = (hash === '#offer-parking' || hash === '#offer-lot' || hash === '#offer-space' || (hash === '#my-spaces' && hashQuery && hashQuery.includes('action=offer')));
        if (isOfferAction) {
          hash = '#my-spaces';
        }

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
        if (!sections[hash]) {
          hash = '#map';
        }

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

        // Refresh map dimensions if map section is shown
        if (hash === '#map' && window.parkingMap) {
          window.parkingMap.invalidateSize();
          window.parkingMap.loadParkingSpaces();
        }

        // Load cars and reservations if my-reservations section is shown
        if (hash === '#my-reservations') {
          loadCars();
          loadReservations();
        }

        // Load vehicles and resize 3D car studio if vehicles section is shown
        if (hash === '#vehicles') {
          if (typeof loadStudioCars === 'function') {
            loadStudioCars();
          }
          setTimeout(() => {
            if (typeof initCarStudio3D === 'function') {
              initCarStudio3D();
            }
            if (carStudio3D && typeof carStudio3D.resize === 'function') {
              carStudio3D.resize();
            }
          }, 60);
        }

        // Load owner incoming requests if my-spaces section is shown
        if (hash === '#my-spaces') {
          if (typeof loadOwnerRequests === 'function') {
            loadOwnerRequests();
          }
          if (isOfferAction) {
            const offerContainer = document.getElementById('offer-parking-form-container');
            if (offerContainer) {
              offerContainer.style.display = 'block';
              if (typeof backToLotsList === 'function') {
                backToLotsList();
              }
              // Sync coordinates from map if available
              if (window.parkingMap && window.parkingMap.selectedCoords) {
                const offerLat = document.getElementById('offer-latitude');
                const offerLng = document.getElementById('offer-longitude');
                const latStr = window.parkingMap.selectedCoords.lat.toFixed(5);
                const lngStr = window.parkingMap.selectedCoords.lng.toFixed(5);
                if (offerLat && (!offerLat.value || offerLat.value === '18.7883' || offerLat.value === '18.78830')) {
                  offerLat.value = latStr;
                }
                if (offerLng && (!offerLng.value || offerLng.value === '98.9853' || offerLng.value === '98.98530')) {
                  offerLng.value = lngStr;
                }
              }
              setTimeout(() => {
                offerContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                const nameInput = document.getElementById('offer-name');
                if (nameInput) nameInput.focus();
              }, 60);
            }
          }
        }

        // Load profiles if profile section is shown
        if (hash === '#profile') {
          if (typeof loadProfiles === 'function') {
            loadProfiles();
          }
          setTimeout(() => {
            if (createChar3D && typeof createChar3D.resize === 'function') {
              createChar3D.resize();
            }
            if (editChar3D && typeof editChar3D.resize === 'function') {
              editChar3D.resize();
            }
          }, 60);
        }
      }

      window.addEventListener('hashchange', handleRoute);

      // Immediately synchronize UI if initial session was restored from PHP
      if (currentUser) {
        updateAuthUI();
      }

      // Kick off background authentication check with Supabase Auth
      checkAuthSession();

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

      // Jump from map directly to offer form
      const btnOfferFromMap = document.getElementById('btn-offer-from-map');
      if (btnOfferFromMap) {
        btnOfferFromMap.addEventListener('click', () => {
          window.offerOrigin = 'map';
          if (window.parkingMap && window.parkingMap.selectedCoords) {
            const offerLat = document.getElementById('offer-latitude');
            const offerLng = document.getElementById('offer-longitude');
            const latStr = window.parkingMap.selectedCoords.lat.toFixed(5);
            const lngStr = window.parkingMap.selectedCoords.lng.toFixed(5);
            if (offerLat) offerLat.value = latStr;
            if (offerLng) offerLng.value = lngStr;
          }
          const offerContainer = document.getElementById('offer-parking-form-container');
          if (offerContainer) {
            offerContainer.style.display = 'block';
          }
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
          const statusInput = document.getElementById('offer-status').value;
          const status = (statusInput === 'closed') ? 'closed' : ((available_spaces === 0) ? 'full' : 'available');

          // Client-side Validation
          if (!name) {
            showAlert('error', 'Please enter a parking space name.');
            document.getElementById('offer-name').focus();
            return;
          }

          if (isNaN(latitude) || isNaN(longitude)) {
            showAlert('error', 'Please select a location on the map first.');
            return;
          }

          if (isNaN(capacity) || capacity <= 0) {
            showAlert('error', 'Total capacity must be greater than 0.');
            document.getElementById('offer-capacity').focus();
            return;
          }

          if (isNaN(available_spaces) || available_spaces < 0) {
            showAlert('error', 'Available spaces cannot be negative.');
            document.getElementById('offer-available-spaces').focus();
            return;
          }

          if (available_spaces > capacity) {
            showAlert('error', 'Available spaces cannot exceed total capacity (' + capacity + ').');
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
              headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
              showAlert('success', `
                <strong>Parking Space Offered Successfully!</strong><br>
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
              if (typeof loadDashboard === 'function') {
                loadDashboard();
              }
              if (typeof loadMyParkingLots === 'function') {
                await loadMyParkingLots();
              }
              if (typeof backToLotsList === 'function') {
                backToLotsList();
              }
              const offerContainer = document.getElementById('offer-parking-form-container');
              if (offerContainer) {
                setTimeout(() => {
                  offerContainer.style.display = 'none';
                }, 1500);
              }
              if (window.offerOrigin === 'map') {
                window.offerOrigin = null;
                setTimeout(() => {
                  window.location.hash = '#map';
                }, 1600);
              }
            } else {
              showAlert('error', `<strong>Failed to create space:</strong> ${result.error || 'Server error'}`);
            }
          } catch (err) {
            showAlert('error', `<strong>Network error:</strong> ${err.message}`);
          } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Save & Offer Parking Space';
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
          const statusInput = document.getElementById('edit-space-status').value;
          const status = (statusInput === 'closed') ? 'closed' : ((available_spaces === 0) ? 'full' : 'available');

          if (!id) {
            showEditAlert('error', 'Missing parking space ID.');
            return;
          }

          if (!name) {
            showEditAlert('error', 'Please enter a parking space name.');
            document.getElementById('edit-space-name').focus();
            return;
          }

          if (isNaN(capacity) || capacity <= 0) {
            showEditAlert('error', 'Total capacity must be greater than 0.');
            document.getElementById('edit-space-capacity').focus();
            return;
          }

          if (isNaN(available_spaces) || available_spaces < 0) {
            showEditAlert('error', 'Available spaces cannot be negative.');
            document.getElementById('edit-space-available').focus();
            return;
          }

          if (available_spaces > capacity) {
            showEditAlert('error', 'Available spaces cannot exceed total capacity (' + capacity + ').');
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
              headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
              showEditAlert('success', `
                <strong>Parking Space Updated Successfully!</strong><br>
                <span>Changes saved: "${result.data.name}" (${result.data.available_spaces}/${result.data.capacity} spots, status: ${result.data.status}).</span>
              `);

              // Refresh displayed data and map information
              if (window.parkingMap) {
                await window.parkingMap.loadParkingSpaces();
              }
              if (typeof loadDashboard === 'function') {
                loadDashboard();
              }

              // Auto dismiss modal after short delay so user sees confirmation
              setTimeout(() => {
                if (window.parkingMap) {
                  window.parkingMap.closeEditModal();
                }
              }, 1200);
            } else {
              showEditAlert('error', `<strong>Failed to update:</strong> ${result.error || 'Server error'}`);
            }
          } catch (err) {
            showEditAlert('error', `<strong>Network error:</strong> ${err.message}`);
          } finally {
            btnSaveEdit.disabled = false;
            btnSaveEdit.innerHTML = 'Save Changes';
          }
        });
      }

      // ========================================================
      // PROFILE CRUD CONTROLLER (3D GAME CHARACTER CUSTOMIZER)
      // ========================================================
      let profilesData = {};
      let createChar3D = null;
      let editChar3D = null;

      // Gender-specific hairstyles and features (clean text labels)
      const GENDER_DATA = {
        female: {
          defaultHair: 'ponytail',
          defaultFeature: 'natural',
          hairstyles: [
            { id: 'ponytail', label: 'Ponytail' },
            { id: 'bob', label: 'Bob' },
            { id: 'twintails', label: 'Twin Tails' },
            { id: 'wavy', label: 'Long Wavy' },
            { id: 'cap', label: 'Cap' },
            { id: 'beanie', label: 'Beanie' },
            { id: 'helmet', label: 'Helmet' }
          ],
          featureLabel: 'Feminine Accent',
          features: [
            { id: 'natural', label: 'Natural Glow' },
            { id: 'ribbon', label: 'Hair Ribbon' },
            { id: 'flower', label: 'Relief Flower' },
            { id: 'headband', label: 'Sport Headband' }
          ]
        },
        male: {
          defaultHair: 'spiky',
          defaultFeature: 'stubble',
          hairstyles: [
            { id: 'spiky', label: 'Spiky' },
            { id: 'short', label: 'Short Crop' },
            { id: 'quiff', label: 'Quiff' },
            { id: 'buzz', label: 'Buzz Cut' },
            { id: 'cap', label: 'Cap' },
            { id: 'beanie', label: 'Beanie' },
            { id: 'helmet', label: 'Helmet' }
          ],
          featureLabel: 'Facial Feature',
          features: [
            { id: 'clean', label: 'Clean Shaven' },
            { id: 'stubble', label: 'Rescuer Stubble' },
            { id: 'mustache', label: 'Hero Mustache' },
            { id: 'goatee', label: 'Goatee' }
          ]
        }
      };

      // Current builder states
      let createCharConfig = {
        gender: 'female',
        feature: 'natural',
        hair: 'ponytail',
        hairColor: '#261e1b',
        clothes: 'vest',
        clothesColor: '#ea580c',
        skinColor: '#fed7aa'
      };

      let editCharConfig = {
        gender: 'female',
        feature: 'natural',
        hair: 'ponytail',
        hairColor: '#261e1b',
        clothes: 'vest',
        clothesColor: '#ea580c',
        skinColor: '#fed7aa'
      };

      function initCreate3D() {
        const canvas = document.getElementById('create-character-canvas');
        if (!canvas || !window.Character3D) return;
        if (!createChar3D) {
          createChar3D = new window.Character3D(canvas, createCharConfig);
        } else {
          createChar3D.updateConfig(createCharConfig);
        }
      }

      function initEdit3D() {
        const canvas = document.getElementById('edit-character-canvas');
        if (!canvas || !window.Character3D) return;
        if (!editChar3D) {
          editChar3D = new window.Character3D(canvas, editCharConfig);
        } else {
          editChar3D.updateConfig(editCharConfig);
        }
      }

      function renderGenderDependentControls(prefix, configObj, charInstanceGetter) {
        const gender = configObj.gender || 'female';
        const gData = GENDER_DATA[gender] || GENDER_DATA.female;

        // 1. Gender active button
        document.querySelectorAll(`#${prefix}-gender-options .gender-chip-btn`).forEach(b => {
          b.classList.toggle('active', b.dataset.gender === gender);
        });

        // 2. Feature Label and Buttons
        const featureLabelEl = document.getElementById(`${prefix}-feature-label`);
        if (featureLabelEl) featureLabelEl.textContent = gData.featureLabel;

        const featureContainer = document.getElementById(`${prefix}-feature-options`);
        if (featureContainer) {
          const validFeatureIds = gData.features.map(f => f.id);
          if (!validFeatureIds.includes(configObj.feature)) {
            configObj.feature = gData.defaultFeature;
          }
          featureContainer.innerHTML = gData.features.map(f => `
            <button type="button" class="customizer-chip-btn ${f.id === configObj.feature ? 'active' : ''}" data-feature="${f.id}">${f.label}</button>
          `).join('');

          featureContainer.querySelectorAll('.customizer-chip-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              featureContainer.querySelectorAll('.customizer-chip-btn').forEach(b => b.classList.remove('active'));
              btn.classList.add('active');
              configObj.feature = btn.dataset.feature;
              const instance = charInstanceGetter();
              if (instance) instance.updateConfig({ feature: configObj.feature });
            });
          });
        }

        // 3. Hairstyle Buttons
        const hairContainer = document.getElementById(`${prefix}-hair-styles`);
        if (hairContainer) {
          const validHairIds = gData.hairstyles.map(h => h.id);
          if (!validHairIds.includes(configObj.hair)) {
            configObj.hair = gData.defaultHair;
          }
          hairContainer.innerHTML = gData.hairstyles.map(h => `
            <button type="button" class="customizer-chip-btn ${h.id === configObj.hair ? 'active' : ''}" data-hair="${h.id}">${h.label}</button>
          `).join('');

          hairContainer.querySelectorAll('.customizer-chip-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              hairContainer.querySelectorAll('.customizer-chip-btn').forEach(b => b.classList.remove('active'));
              btn.classList.add('active');
              configObj.hair = btn.dataset.hair;
              const instance = charInstanceGetter();
              if (instance) instance.updateConfig({ hair: configObj.hair });
            });
          });
        }
      }

      function setupCustomizerListeners(prefix, configObj, charInstanceGetter) {
        // Initial populate of gender-dependent controls
        renderGenderDependentControls(prefix, configObj, charInstanceGetter);

        // Gender choice click listener
        document.querySelectorAll(`#${prefix}-gender-options .gender-chip-btn`).forEach(btn => {
          btn.addEventListener('click', () => {
            if (configObj.gender === btn.dataset.gender) return;
            configObj.gender = btn.dataset.gender;
            renderGenderDependentControls(prefix, configObj, charInstanceGetter);
            const instance = charInstanceGetter();
            if (instance) {
              instance.updateConfig({
                gender: configObj.gender,
                hair: configObj.hair,
                feature: configObj.feature
              });
            }
          });
        });

        // Hair colors
        document.querySelectorAll(`#${prefix}-hair-colors .color-swatch-btn`).forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll(`#${prefix}-hair-colors .color-swatch-btn`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            configObj.hairColor = btn.dataset.color;
            const instance = charInstanceGetter();
            if (instance) instance.updateConfig({ hairColor: configObj.hairColor });
          });
        });

        // Clothes styles
        document.querySelectorAll(`#${prefix}-clothes-styles .customizer-chip-btn`).forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll(`#${prefix}-clothes-styles .customizer-chip-btn`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            configObj.clothes = btn.dataset.clothes;
            const instance = charInstanceGetter();
            if (instance) instance.updateConfig({ clothes: configObj.clothes });
          });
        });

        // Clothes colors
        document.querySelectorAll(`#${prefix}-clothes-colors .color-swatch-btn`).forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll(`#${prefix}-clothes-colors .color-swatch-btn`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            configObj.clothesColor = btn.dataset.color;
            const instance = charInstanceGetter();
            if (instance) instance.updateConfig({ clothesColor: configObj.clothesColor });
          });
        });

        // Skin colors
        document.querySelectorAll(`#${prefix}-skin-colors .color-swatch-btn`).forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll(`#${prefix}-skin-colors .color-swatch-btn`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            configObj.skinColor = btn.dataset.color;
            const instance = charInstanceGetter();
            if (instance) instance.updateConfig({ skinColor: configObj.skinColor });
          });
        });
      }

      setupCustomizerListeners('create', createCharConfig, () => createChar3D);
      setupCustomizerListeners('edit', editCharConfig, () => editChar3D);

      // Category tab switcher for 3D Customizer Studio
      const customizerTabButtons = document.querySelectorAll('#create-customizer-tabs .customizer-tab-btn');
      if (customizerTabButtons.length > 0) {
        customizerTabButtons.forEach(btn => {
          btn.addEventListener('click', () => {
            const targetTab = btn.dataset.tab;
            customizerTabButtons.forEach(b => {
              b.classList.toggle('active', b === btn);
              b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            document.querySelectorAll('#create-customizer-panels .customizer-tab-panel').forEach(panel => {
              if (panel.dataset.tab === targetTab) {
                panel.style.display = 'block';
              } else {
                panel.style.display = 'none';
              }
            });
            if (createChar3D && typeof createChar3D.handleResize === 'function') {
              createChar3D.handleResize();
            }
          });
        });
      }

      // Initialize 3D when on profile section
      setTimeout(initCreate3D, 200);

      window.addEventListener('hashchange', () => {
        if (window.location.hash === '#profile') {
          setTimeout(initCreate3D, 150);
        }
      });

      function parseAvatarData(avatarRaw) {
        if (!avatarRaw) return null;
        if (typeof avatarRaw === 'object') return avatarRaw;
        try {
          let str = avatarRaw;
          if (str.includes('&quot;')) {
            str = str.replace(/&quot;/g, '"');
          }
          if (str.startsWith('{')) {
            return JSON.parse(str);
          }
        } catch (e) {}
        return null;
      }

      function renderAvatarPortrait(avatarRaw) {
        const data = parseAvatarData(avatarRaw);
        if (data && data.snapshot) {
          return `<div class="profile-avatar-portrait"><img src="${data.snapshot}" alt="3D Character"></div>`;
        }
        // Legacy fallback
        return `<div class="profile-avatar-portrait"><span class="profile-avatar-fallback">PIC</span></div>`;
      }

      function getAvatarSpecsBadge(avatarRaw) {
        const data = parseAvatarData(avatarRaw);
        if (data) {
          const outfitLabels = {
            vest: 'Rescue Vest',
            jacket: 'Flood Jacket',
            raincoat: 'Rain Poncho',
            wetsuit: 'Diver Wetsuit',
            parka: 'Storm Parka',
            scrubs: 'Medic Scrubs',
            overalls: 'Overalls',
            tee: 'Casual Tee',
            uniform: 'Rescue Uniform',
            hoodie: 'Shelter Hoodie'
          };
          const featureLabels = {
            natural: 'Natural',
            ribbon: 'Ribbon',
            flower: 'Flower',
            headband: 'Headband',
            clean: 'Clean Shaven',
            stubble: 'Stubble',
            mustache: 'Mustache',
            goatee: 'Goatee'
          };
          const outfitName = outfitLabels[data.clothes] || (data.clothes ? data.clothes.charAt(0).toUpperCase() + data.clothes.slice(1) : 'Rescue');
          const featureName = featureLabels[data.feature] ? ` &bull; ${featureLabels[data.feature]}` : '';
          const genderName = data.gender === 'female' ? 'Female' : 'Male';
          return `<span class="profile-card-specs-tag">3D &bull; ${genderName} &bull; ${outfitName}${featureName}</span>`;
        }
        return `<span class="profile-card-specs-tag">3D Community Member</span>`;
      }

      function showProfileAlert(type, message) {
        const el = document.getElementById('profile-action-alert');
        if (!el) return;
        el.className = `alert-message ${type}`;
        el.innerHTML = message;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      let isEditingProfile = false;

      async function loadProfiles() {
        const container = document.getElementById('profiles-list-container');
        const countPill = document.getElementById('profile-count-pill');
        const viewBox = document.getElementById('my-profile-view-box');
        const editorBox = document.getElementById('my-profile-editor-box');
        const createTitle = document.getElementById('create-profile-card-title');
        const createDesc = document.getElementById('create-profile-card-desc');
        const idInput = document.getElementById('my-profile-id');
        const nameInput = document.getElementById('create-profile-name');
        const badgeBar = document.getElementById('my-profile-badge-bar');
        const idTag = document.getElementById('my-profile-id-tag');
        const submitBtn = document.getElementById('btn-submit-profile');
        const cancelBtn = document.getElementById('btn-cancel-profile-edit');
        const deleteBtn = document.getElementById('btn-delete-my-profile');

        const viewAvatar = document.getElementById('my-profile-view-avatar');
        const viewName = document.getElementById('my-profile-view-name');
        const viewSpecs = document.getElementById('my-profile-view-specs');
        const viewIdText = document.getElementById('my-profile-view-id-text');

        try {
          const res = await fetch('api/get_profiles.php?_t=' + Date.now());
          const json = await res.json();

          if (json.success && Array.isArray(json.data)) {
            profilesData = {};
            let myProfile = null;
            json.data.forEach(p => {
              profilesData[p.id] = p;
              if (currentUser && p.user_id && p.user_id === currentUser.id) {
                myProfile = p;
              }
            });

            // 1. Sync Left Panel (My Profile: View Summary & Dedicated Editor)
            if (myProfile) {
              // Populate View Mode
              if (viewName) viewName.textContent = myProfile.name;
              if (viewAvatar) viewAvatar.innerHTML = renderAvatarPortrait(myProfile.avatar);
              if (viewSpecs) viewSpecs.innerHTML = getAvatarSpecsBadge(myProfile.avatar);
              if (viewIdText) viewIdText.textContent = `${myProfile.id.slice(0, 8)} (${myProfile.id.slice(-4)})`;

              // Populate Editor Mode fields
              if (idInput) idInput.value = myProfile.id;
              if (nameInput) nameInput.value = myProfile.name;
              if (badgeBar) badgeBar.style.display = 'flex';
              if (idTag) idTag.textContent = `#${myProfile.id.slice(0, 8)} (${myProfile.id.slice(-4)})`;
              if (submitBtn) submitBtn.innerHTML = 'Save Profile';
              if (cancelBtn) cancelBtn.style.display = 'inline-block';
              if (deleteBtn) deleteBtn.style.display = 'inline-block';

              // Load user's saved avatar config into customizer
              const defaultCharConfig = {
                gender: 'female',
                feature: 'natural',
                hair: 'ponytail',
                hairColor: '#261e1b',
                clothes: 'vest',
                clothesColor: '#ea580c',
                skinColor: '#fed7aa'
              };
              const parsed = parseAvatarData(myProfile.avatar) || {};
              Object.assign(createCharConfig, defaultCharConfig);
              Object.keys(defaultCharConfig).forEach(k => {
                if (parsed[k]) createCharConfig[k] = parsed[k];
              });
              if (parsed.snapshot) createCharConfig.snapshot = parsed.snapshot;

              if (isEditingProfile) {
                if (viewBox) viewBox.style.display = 'none';
                if (editorBox) editorBox.style.display = 'block';
                if (createTitle) createTitle.innerHTML = 'Edit My Profile';
                if (createDesc) createDesc.textContent = 'Modify your display name, 3D avatar, and relief style.';
                syncCustomizerUI('create', createCharConfig, () => createChar3D);
                if (createChar3D) {
                  createChar3D.updateConfig(createCharConfig);
                } else {
                  setTimeout(initCreate3D, 150);
                }
              } else {
                if (viewBox) viewBox.style.display = 'block';
                if (editorBox) editorBox.style.display = 'none';
              }
            } else {
              // User does not have a profile yet: Show create form directly
              isEditingProfile = false;
              if (viewBox) viewBox.style.display = 'none';
              if (editorBox) editorBox.style.display = 'block';
              if (createTitle) createTitle.innerHTML = 'Create My Profile';
              if (createDesc) createDesc.textContent = 'Customize your 3D avatar and set your display name for flood parking.';
              if (idInput) idInput.value = '';
              if (badgeBar) badgeBar.style.display = 'none';
              if (submitBtn) submitBtn.innerHTML = 'Save Profile';
              if (cancelBtn) cancelBtn.style.display = 'none';
              if (deleteBtn) deleteBtn.style.display = 'none';

              syncCustomizerUI('create', createCharConfig, () => createChar3D);
              if (createChar3D) {
                createChar3D.updateConfig(createCharConfig);
              } else {
                setTimeout(initCreate3D, 150);
              }
            }

            // 2. Sync Right Panel (Community Profiles)
            // Displays public profile info. Current user appears at top with "You" badge.
            // Other members are strictly view-only with NO edit or delete controls.
            const sortedProfiles = [...json.data].sort((a, b) => {
              const aIsMe = Boolean(currentUser && a.user_id && a.user_id === currentUser.id);
              const bIsMe = Boolean(currentUser && b.user_id && b.user_id === currentUser.id);
              if (aIsMe) return -1;
              if (bIsMe) return 1;
              return (a.name || '').localeCompare(b.name || '');
            });

            if (countPill) {
              countPill.textContent = `${sortedProfiles.length} ${sortedProfiles.length === 1 ? 'Member' : 'Members'}`;
            }

            if (!container) return;

            if (sortedProfiles.length === 0) {
              container.innerHTML = '<div class="empty-spaces-notice">No community members registered yet.</div>';
              return;
            }

            let html = '';
            sortedProfiles.forEach(p => {
              const isMe = Boolean(currentUser && p.user_id && p.user_id === currentUser.id);
              html += `
                <div class="profile-card" id="profile-card-${p.id}" style="${isMe ? 'border: 2px solid #3b82f6; background: #f8fafc;' : ''}">
                  <div class="profile-card-left">
                    ${renderAvatarPortrait(p.avatar)}
                    <div>
                      <div class="profile-card-name">
                        ${p.name}
                        ${isMe ? '<span class="status-badge status-available" style="font-size: 0.65rem; padding: 2px 6px; margin-left: 6px;">You</span>' : ''}
                      </div>
                      ${getAvatarSpecsBadge(p.avatar)}
                      <div style="margin-top: 5px;">
                        <span class="profile-clean-id" title="Full UUID: ${p.id} (Click to copy)" onclick="navigator.clipboard?.writeText('${p.id}'); const h = this.querySelector('.id-copy-hint'); if(h) { h.textContent='Copied!'; setTimeout(()=>h.textContent='Copy', 1500); }">
                          <span class="id-hash">#</span>${p.id.slice(0, 8)}
                          <span class="id-copy-hint" title="Copy UUID">Copy</span>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="profile-card-actions">
                    <span style="font-size: 0.75rem; color: ${isMe ? '#2563eb' : '#64748b'}; font-weight: 600; padding: 4px 8px; background: ${isMe ? '#eff6ff' : '#f8fafc'}; border-radius: 4px; border: 1px solid ${isMe ? '#bfdbfe' : '#e2e8f0'};">
                      ${isMe ? '👤 Your Active Profile' : '👤 Community Member'}
                    </span>
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

      // "Edit My Profile" button handler (Opens editable details on the left)
      const btnOpenMyProfileEditor = document.getElementById('btn-open-my-profile-editor');
      if (btnOpenMyProfileEditor) {
        btnOpenMyProfileEditor.addEventListener('click', () => {
          isEditingProfile = true;
          const viewBox = document.getElementById('my-profile-view-box');
          const editorBox = document.getElementById('my-profile-editor-box');
          const createTitle = document.getElementById('create-profile-card-title');
          const createDesc = document.getElementById('create-profile-card-desc');
          const cancelBtn = document.getElementById('btn-cancel-profile-edit');
          const deleteBtn = document.getElementById('btn-delete-my-profile');
          const submitBtn = document.getElementById('btn-submit-profile');

          if (viewBox) viewBox.style.display = 'none';
          if (editorBox) editorBox.style.display = 'block';
          if (createTitle) createTitle.innerHTML = 'Edit My Profile';
          if (createDesc) createDesc.textContent = 'Modify your display name, 3D avatar, and relief style.';
          if (submitBtn) submitBtn.innerHTML = 'Save Profile';
          if (cancelBtn) cancelBtn.style.display = 'inline-block';
          if (deleteBtn) deleteBtn.style.display = 'inline-block';

          syncCustomizerUI('create', createCharConfig, () => createChar3D);
          if (createChar3D) {
            createChar3D.updateConfig(createCharConfig);
            if (typeof createChar3D.handleResize === 'function') {
              createChar3D.handleResize();
            }
          } else {
            setTimeout(initCreate3D, 150);
          }

          const nameEl = document.getElementById('create-profile-name');
          if (nameEl) nameEl.focus();
        });
      }

      // "Cancel" button handler (Returns to view mode)
      const btnCancelProfileEdit = document.getElementById('btn-cancel-profile-edit');
      if (btnCancelProfileEdit) {
        btnCancelProfileEdit.addEventListener('click', () => {
          isEditingProfile = false;
          const viewBox = document.getElementById('my-profile-view-box');
          const editorBox = document.getElementById('my-profile-editor-box');
          if (viewBox) viewBox.style.display = 'block';
          if (editorBox) editorBox.style.display = 'none';
        });
      }

      // Profile Form Submit (Validates and saves changes to Supabase)
      const createProfileForm = document.getElementById('create-profile-form');
      if (createProfileForm) {
        createProfileForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const name = document.getElementById('create-profile-name').value.trim();
          const existingId = document.getElementById('my-profile-id')?.value.trim();

          if (!name) {
            showProfileAlert('error', 'Please enter a profile name.');
            document.getElementById('create-profile-name').focus();
            return;
          }

          // Generate 3D snapshot
          const snapshot = createChar3D ? createChar3D.getSnapshot(0.82) : '';
          const avatarPayload = JSON.stringify({
            gender: createCharConfig.gender || 'female',
            feature: createCharConfig.feature || 'natural',
            hair: createCharConfig.hair,
            hairColor: createCharConfig.hairColor,
            clothes: createCharConfig.clothes,
            clothesColor: createCharConfig.clothesColor,
            skinColor: createCharConfig.skinColor,
            snapshot: snapshot
          });

          const btn = document.getElementById('btn-submit-profile');
          const isUpdate = Boolean(existingId);

          try {
            btn.disabled = true;
            btn.innerHTML = 'Saving...';

            const endpoint = isUpdate ? 'api/update_profile.php' : 'api/create_profile.php';
            const payload = isUpdate 
              ? { id: existingId, name, avatar: avatarPayload }
              : { name, avatar: avatarPayload };

            const res = await fetch(endpoint, {
              method: 'POST',
              headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (res.ok && json.success) {
              const msg = isUpdate
                ? `<strong>Profile Saved!</strong> Updated changes for "${json.data.name}".`
                : `<strong>Profile Created Successfully!</strong> Welcome, ${json.data.name}!`;
              showProfileAlert('success', msg);
              isEditingProfile = false;
              await loadProfiles();
            } else {
              showProfileAlert('error', `<strong>Failed to save profile:</strong> ${json.error || 'Server error'}`);
            }
          } catch (err) {
            showProfileAlert('error', `<strong>Network error:</strong> ${err.message}`);
          } finally {
            btn.disabled = false;
            btn.innerHTML = 'Save Profile';
          }
        });
      }

      // Left Delete Button Handler
      const btnDeleteMyProfile = document.getElementById('btn-delete-my-profile');
      if (btnDeleteMyProfile) {
        btnDeleteMyProfile.addEventListener('click', async () => {
          const existingId = document.getElementById('my-profile-id')?.value.trim();
          const name = document.getElementById('create-profile-name')?.value.trim() || 'your profile';
          if (!existingId) return;

          const confirmed = window.confirm(`Are you sure you want to delete profile "${name}"?\n\nThis will permanently remove your profile and 3D avatar from Supabase.`);
          if (!confirmed) return;

          try {
            btnDeleteMyProfile.disabled = true;
            btnDeleteMyProfile.innerHTML = 'Deleting...';

            const res = await fetch('api/delete_profile.php', {
              method: 'POST',
              headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
              },
              body: JSON.stringify({ id: existingId })
            });
            const json = await res.json();
            if (res.ok && json.success) {
              showProfileAlert('success', `<strong>Profile Deleted!</strong> Your profile has been removed.`);
              document.getElementById('my-profile-id').value = '';
              document.getElementById('create-profile-name').value = '';
              isEditingProfile = false;
              await loadProfiles();
            } else {
              showProfileAlert('error', `<strong>Failed to delete profile:</strong> ${json.error || 'Server error'}`);
            }
          } catch (err) {
            showProfileAlert('error', `<strong>Network error:</strong> ${err.message}`);
          } finally {
            btnDeleteMyProfile.disabled = false;
            btnDeleteMyProfile.innerHTML = 'Delete Profile';
          }
        });
      }

      // Sync active UI buttons to a config
      function syncCustomizerUI(prefix, config, charInstanceGetter) {
        renderGenderDependentControls(prefix, config, charInstanceGetter);

        // Hair color
        document.querySelectorAll(`#${prefix}-hair-colors .color-swatch-btn`).forEach(b => {
          b.classList.toggle('active', (b.dataset.color || '').toLowerCase() === (config.hairColor || '').toLowerCase());
        });
        // Clothes style
        document.querySelectorAll(`#${prefix}-clothes-styles .customizer-chip-btn`).forEach(b => {
          b.classList.toggle('active', b.dataset.clothes === config.clothes);
        });
        // Clothes color
        document.querySelectorAll(`#${prefix}-clothes-colors .color-swatch-btn`).forEach(b => {
          b.classList.toggle('active', (b.dataset.color || '').toLowerCase() === (config.clothesColor || '').toLowerCase());
        });
        // Skin color
        document.querySelectorAll(`#${prefix}-skin-colors .color-swatch-btn`).forEach(b => {
          b.classList.toggle('active', (b.dataset.color || '').toLowerCase() === (config.skinColor || '').toLowerCase());
        });
      }

      // Safe Profile Edit Trigger (Directs to the Left Editor ONLY if target is user's own profile)
      function openEditProfileModal(id) {
        const profile = profilesData[id];
        if (!profile) return;

        if (!currentUser || !profile.user_id || profile.user_id !== currentUser.id) {
          showProfileAlert('error', 'Forbidden: You can edit only your own profile.');
          return;
        }

        const openBtn = document.getElementById('btn-open-my-profile-editor');
        if (openBtn) {
          openBtn.click();
          openBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }

      // ========================================================
      // RESERVATION MANAGEMENT CONTROLLER: TWO-STEP UX
      // ========================================================
      function escapeHtml(str) {
        if (!str) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function showReservationAlert(type, message) {
        const alertBox = document.getElementById('reservation-action-alert');
        if (alertBox) {
          alertBox.className = `alert-message ${type}`;
          alertBox.innerHTML = message;
          alertBox.style.display = 'block';
          alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }

      let myResState = {
        reservations: [],
        selectedReservationId: null,
        activeFilter: 'all'
      };

      function setReservationFilter(filter) {
        myResState.activeFilter = filter;
        document.querySelectorAll('#my-res-filter-controls .filter-btn').forEach(btn => {
          btn.classList.toggle('active', btn.dataset.filter === filter);
        });
        renderMyReservationsList(myResState.reservations);
      }
      window.setReservationFilter = setReservationFilter;

      async function loadReservations() {
        const container = document.getElementById('reservations-list-container');
        if (!container) return;

        if (!currentUser) {
          container.innerHTML = `
            <div class="empty-spaces-notice">
              Please log in to view and manage your reservations.
            </div>
          `;
          const metricsBox = document.getElementById('my-res-summary-metrics');
          if (metricsBox) metricsBox.style.display = 'none';
          return;
        }

        try {
          container.innerHTML = '<div class="empty-spaces-notice"><span>⏳</span> Loading your reservations...</div>';
          const res = await fetch('api/get_reservations.php');
          const json = await res.json();

          if (!res.ok || !json.success) {
            container.innerHTML = `
              <div class="empty-spaces-notice" style="border-color: #ef4444; color: #b91c1c;">
                Could not load reservations: ${json.error || 'Server error'}
              </div>
            `;
            return;
          }

          myResState.reservations = json.data || [];

          // Compute compact summary metrics & filter counts
          const total = myResState.reservations.length;
          const pending = myResState.reservations.filter(r => (r.status || 'pending').toLowerCase() === 'pending').length;
          const confirmed = myResState.reservations.filter(r => {
            const s = (r.status || '').toLowerCase();
            return s === 'confirmed' || s === 'active';
          }).length;
          const history = myResState.reservations.filter(r => {
            const s = (r.status || '').toLowerCase();
            return s === 'cancelled' || s === 'rejected' || s === 'completed';
          }).length;

          const elTotal = document.getElementById('res-metric-total');
          const elPending = document.getElementById('res-metric-pending');
          const elConfirmed = document.getElementById('res-metric-confirmed');
          const elHistory = document.getElementById('res-metric-history');
          const metricsBox = document.getElementById('my-res-summary-metrics');

          if (elTotal) elTotal.textContent = total;
          if (elPending) elPending.textContent = pending;
          if (elConfirmed) elConfirmed.textContent = confirmed;
          if (elHistory) elHistory.textContent = history;
          if (metricsBox) metricsBox.style.display = total > 0 ? 'flex' : 'none';

          // Update filter badge counts
          const fAll = document.getElementById('filter-count-all');
          const fPending = document.getElementById('filter-count-pending');
          const fConfirmed = document.getElementById('filter-count-confirmed');
          const fPast = document.getElementById('filter-count-past');
          if (fAll) fAll.textContent = total;
          if (fPending) fPending.textContent = pending;
          if (fConfirmed) fConfirmed.textContent = confirmed;
          if (fPast) fPast.textContent = history;

          // If currently in detail view and reservation still exists, re-render detail; else render list
          if (myResState.selectedReservationId) {
            const currentItem = myResState.reservations.find(r => r.id === myResState.selectedReservationId);
            if (currentItem) {
              renderMyReservationDetail(myResState.selectedReservationId);
              return;
            }
          }

          // Otherwise render list view
          myResState.selectedReservationId = null;
          renderMyReservationsList(myResState.reservations);

        } catch (err) {
          container.innerHTML = `
            <div class="empty-spaces-notice" style="border-color: #ef4444; color: #b91c1c;">
              Network error while loading reservations: ${err.message}
            </div>
          `;
        }
      }

      function renderMyReservationsList(reservations) {
        const step1View = document.getElementById('my-res-step1-view');
        const step2View = document.getElementById('my-res-step2-view');
        const container = document.getElementById('reservations-list-container');
        if (!container) return;

        if (step1View) step1View.style.display = 'block';
        if (step2View) step2View.style.display = 'none';

        if (!reservations || reservations.length === 0) {
          container.innerHTML = `
            <div class="empty-spaces-notice">
              
              <strong>No flood parking reservations found.</strong><br>
              Browse available high-ground parking lots on the interactive map to protect your vehicle.
              <div style="margin-top: 14px;">
                <a href="#map" class="pixel-btn pixel-btn-blue">
                  Find Parking on Map &rarr;
                </a>
              </div>
            </div>
          `;
          return;
        }

        // Apply active filter (All, Pending, Confirmed, Past/Cancelled)
        const filter = myResState.activeFilter || 'all';
        let filtered = reservations;
        if (filter === 'pending') {
          filtered = reservations.filter(r => (r.status || 'pending').toLowerCase() === 'pending');
        } else if (filter === 'confirmed') {
          filtered = reservations.filter(r => {
            const s = (r.status || '').toLowerCase();
            return s === 'confirmed' || s === 'active';
          });
        } else if (filter === 'past') {
          filtered = reservations.filter(r => {
            const s = (r.status || '').toLowerCase();
            return s === 'cancelled' || s === 'rejected' || s === 'completed';
          });
        }

        // Update active class on filter tab buttons
        document.querySelectorAll('#my-res-filter-controls .filter-btn').forEach(btn => {
          btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        if (filtered.length === 0) {
          const filterLabels = {
            pending: 'pending approval',
            confirmed: 'confirmed',
            past: 'past or cancelled'
          };
          container.innerHTML = `
            <div class="empty-spaces-notice">
              
              <strong>No ${filterLabels[filter] || filter} reservations found.</strong>
              <div style="margin-top: 10px;">
                <button type="button" class="pixel-btn pixel-btn-sm" onclick="setReservationFilter('all')">
                  Show All Reservations (${reservations.length})
                </button>
              </div>
            </div>
          `;
          return;
        }

        let html = '<div class="my-res-grid">';
        filtered.forEach(r => {
          const space = r.parking_spaces || {};
          const spaceName = space.name || 'Flood Safe Parking Lot';
          const status = (r.status || 'pending').toLowerCase();
          const isConfirmed = (status === 'confirmed' || status === 'active');
          const isPending = (status === 'pending');
          const isRejected = (status === 'rejected');
          const isCancelled = (status === 'cancelled');
          const isCompleted = (status === 'completed');

          let statusPill = '';
          if (isConfirmed) {
            statusPill = '<span class="status-pill status-available">CONFIRMED</span>';
          } else if (isPending) {
            statusPill = '<span class="status-pill" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">PENDING APPROVAL</span>';
          } else if (isRejected) {
            statusPill = '<span class="status-pill status-closed" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;">REJECTED</span>';
          } else if (isCancelled) {
            statusPill = '<span class="status-pill" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">CANCELLED</span>';
          } else if (isCompleted) {
            statusPill = '<span class="status-pill" style="background:#f0fdf4; color:#15803d; border-color:#86efac;">COMPLETED</span>';
          } else {
            statusPill = `<span class="status-pill">${escapeHtml(status.toUpperCase())}</span>`;
          }

          const car = r.cars || {};
          const plate = car.license_plate || 'Unlinked Vehicle';
          const model = car.model ? ` (${car.model})` : '';
          const createdAtFormatted = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';
          const lat = space.latitude ? parseFloat(space.latitude).toFixed(4) : null;
          const lng = space.longitude ? parseFloat(space.longitude).toFixed(4) : null;

          html += `
            <article class="my-res-card clickable-res-card" id="my-res-card-${r.id}" data-res-id="${r.id}" title="Click to view full reservation details">
              <div>
                <div class="my-res-card-header">
                  <h4 class="my-res-card-title">${escapeHtml(spaceName)}</h4>
                  ${statusPill}
                </div>
                <div class="my-res-card-body" style="margin-top: 10px;">
                  <div><strong>Vehicle:</strong> <span class="dash-car-plate" style="font-size: 0.74rem;">${escapeHtml(plate)}</span>${escapeHtml(model)}</div>
                  <div><strong>Requested:</strong> ${createdAtFormatted}</div>
                  ${lat && lng ? `<div><strong>Location:</strong> <span style="font-family: monospace; font-size: 0.8rem;">${lat}, ${lng}</span></div>` : ''}
                </div>
              </div>
              <div class="my-res-card-actions">
                <button type="button" class="pixel-btn pixel-btn-blue btn-select-res" data-res-id="${r.id}" style="width: 100%;">
                  View Details & Manage &rarr;
                </button>
              </div>
            </article>
          `;
        });
        html += '</div>';

        container.innerHTML = html;
      }

      function selectReservationDetail(resId) {
        myResState.selectedReservationId = resId;
        renderMyReservationDetail(resId);
        const section = document.getElementById('section-my-reservations');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      window.selectReservationDetail = selectReservationDetail;

      function backToReservationsList() {
        myResState.selectedReservationId = null;
        renderMyReservationsList(myResState.reservations);
        const section = document.getElementById('section-my-reservations');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      window.backToReservationsList = backToReservationsList;

      function renderMyReservationDetail(resId) {
        const step1View = document.getElementById('my-res-step1-view');
        const step2View = document.getElementById('my-res-step2-view');
        const detailContainer = document.getElementById('my-reservation-detail-content');
        if (!detailContainer) return;

        const r = (myResState.reservations || []).find(item => item.id === resId);
        if (!r) {
          backToReservationsList();
          return;
        }

        if (step1View) step1View.style.display = 'none';
        if (step2View) step2View.style.display = 'block';

        const space = r.parking_spaces || {};
        const spaceName = space.name || 'Flood Safe Parking Lot';
        const car = r.cars || {};
        const plate = car.license_plate || 'Unlinked Vehicle';
        const model = car.model || 'Standard Vehicle';
        const status = (r.status || 'pending').toLowerCase();
        const isConfirmed = (status === 'confirmed' || status === 'active');
        const isPending = (status === 'pending');
        const isRejected = (status === 'rejected');
        const isCancelled = (status === 'cancelled');
        const isCompleted = (status === 'completed');

        const owner = r.owner_profile || {};
        const ownerName = owner.name || (space.owner_id ? 'Community Lot Owner' : 'Community Emergency Lot');

        const createdAtFormatted = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';
        const updatedAtFormatted = r.updated_at ? new Date(r.updated_at).toLocaleString() : createdAtFormatted;
        const lat = space.latitude ? parseFloat(space.latitude).toFixed(5) : 'N/A';
        const lng = space.longitude ? parseFloat(space.longitude).toFixed(5) : 'N/A';

        let statusPill = '';
        if (isConfirmed) {
          statusPill = '<span class="status-pill status-available">CONFIRMED</span>';
        } else if (isPending) {
          statusPill = '<span class="status-pill" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">PENDING APPROVAL</span>';
        } else if (isRejected) {
          statusPill = '<span class="status-pill status-closed" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;">REJECTED</span>';
        } else if (isCancelled) {
          statusPill = '<span class="status-pill" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">CANCELLED</span>';
        } else if (isCompleted) {
          statusPill = '<span class="status-pill" style="background:#f0fdf4; color:#15803d; border-color:#86efac;">COMPLETED</span>';
        } else {
          statusPill = `<span class="status-pill">${escapeHtml(status.toUpperCase())}</span>`;
        }

        // Timeline Step 2 & 3 calculation
        let step2Class = 'my-res-timeline-step';
        let step2Dot = '2';
        let step2Title = 'Owner Review';
        let step2Desc = 'Awaiting decision';

        let step3Class = 'my-res-timeline-step';
        let step3Dot = '3';
        let step3Title = 'Safe Parking Spot';
        let step3Desc = 'High ground verification';

        if (isPending) {
          step2Class += ' pending active';
          step2Dot = '⏳';
          step2Title = 'Under Review';
          step2Desc = 'Owner reviewing request';
          step3Title = 'Pending Approval';
          step3Desc = 'Spot held once approved';
        } else if (isConfirmed) {
          step2Class += ' completed';
          step2Dot = '✓';
          step2Title = 'Approved';
          step2Desc = 'Owner accepted request';
          step3Class += ' completed';
          step3Dot = '3';
          step3Title = 'Spot Secured';
          step3Desc = 'Protected high ground';
        } else if (isRejected) {
          step2Class += ' declined';
          step2Dot = '✕';
          step2Title = 'Declined';
          step2Desc = 'Owner declined request';
          step3Class += ' declined';
          step3Dot = '✕';
          step3Title = 'Request Closed';
          step3Desc = 'Vehicle released';
        } else if (isCancelled) {
          step2Class += ' cancelled';
          step2Dot = '—';
          step2Title = 'Cancelled';
          step2Desc = 'Request cancelled';
          step3Class += ' cancelled';
          step3Dot = '3';
          step3Title = 'Space Returned';
          step3Desc = 'Restored to community';
        } else if (isCompleted) {
          step2Class += ' completed';
          step2Dot = '✓';
          step2Title = 'Completed';
          step2Desc = 'Assistance verified';
          step3Class += ' completed';
          step3Dot = '⭐';
          step3Title = 'Points Awarded';
          step3Desc = 'Relief logged';
        }

        // Action card content based on current status
        let actionBannerHtml = '';
        let actionButtonsHtml = '';

        if (isPending) {
          actionBannerHtml = `
            <div class="my-res-action-banner banner-pending">
              ⏳ <strong>Awaiting Owner Approval:</strong> Your request has been sent to the parking lot owner. 
              Once approved, your vehicle spot will be confirmed. You may cancel this request anytime if your evacuation route changes.
            </div>
          `;
          actionButtonsHtml = `
            <button type="button" class="pixel-btn pixel-btn-red btn-cancel-reservation" data-id="${r.id}" data-name="${escapeHtml(spaceName)}">
              Cancel Request
            </button>
            ${space.latitude && space.longitude ? `
              <button type="button" class="pixel-btn pixel-btn-blue btn-view-reservation-map" data-lat="${space.latitude}" data-lng="${space.longitude}">
                View Lot on Map
              </button>
            ` : ''}
          `;
        } else if (isConfirmed) {
          actionBannerHtml = `
            <div class="my-res-action-banner banner-confirmed">
              <strong>Reservation Confirmed:</strong> Your flood parking space is secured on high ground. 
              If you no longer need this space, please cancel promptly so your spot is returned to neighbors in need.
            </div>
          `;
          actionButtonsHtml = `
            <button type="button" class="pixel-btn pixel-btn-red btn-cancel-reservation" data-id="${r.id}" data-name="${escapeHtml(spaceName)}">
              Cancel Spot
            </button>
            ${space.latitude && space.longitude ? `
              <button type="button" class="pixel-btn pixel-btn-blue btn-view-reservation-map" data-lat="${space.latitude}" data-lng="${space.longitude}">
                View Lot on Map
              </button>
            ` : ''}
          `;
        } else if (isRejected) {
          actionBannerHtml = `
            <div class="my-res-action-banner banner-rejected">
              <strong>Request Declined:</strong> The parking lot owner declined this reservation request 
              (the lot may have filled or become inaccessible). Your vehicle has been released so you can find another high-ground parking space immediately.
            </div>
          `;
          actionButtonsHtml = `
            <a href="#map" class="pixel-btn pixel-btn-blue">
              Find Another Parking Lot on Map &rarr;
            </a>
          `;
        } else if (isCancelled) {
          actionBannerHtml = `
            <div class="my-res-action-banner banner-cancelled">
              <strong>Reservation Cancelled:</strong> This reservation was cancelled and the parking spot was returned to open community capacity.
            </div>
          `;
          actionButtonsHtml = `
            <a href="#map" class="pixel-btn pixel-btn-blue">
              Browse Available Parking Lots on Map &rarr;
            </a>
          `;
        } else if (isCompleted) {
          actionBannerHtml = `
            <div class="my-res-action-banner banner-confirmed">
              ⭐ <strong>Parking Stay Completed:</strong> Flood emergency assistance was successfully completed and logged.
            </div>
          `;
          actionButtonsHtml = `
            <a href="#map" class="pixel-btn pixel-btn-blue">
              Return to Map &rarr;
            </a>
          `;
        }

        detailContainer.innerHTML = `
          <!-- Header Detail Card -->
          <article class="my-res-detail-header-card">
            <div class="my-res-detail-title-row">
              <div>
                <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
                  ${escapeHtml(spaceName)}
                </h3>
                <div style="font-size: 0.8rem; color: #64748b; font-family: monospace;">
                  Coordinates: ${lat}, ${lng}
                </div>
              </div>
              <div>
                ${statusPill}
              </div>
            </div>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.82rem; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 6px;">
              <span><strong>Requested:</strong> ${createdAtFormatted}</span>
              ${r.updated_at ? `<span><strong>Last Updated:</strong> ${updatedAtFormatted}</span>` : ''}
              <span><strong>Reservation ID:</strong> <span style="font-family: monospace;">#${r.id.substring(0, 8)}</span></span>
            </div>
          </article>

          <!-- 2-Column Info Grid -->
          <div class="my-res-detail-grid">
            <!-- Card 1: Reserved Vehicle Details -->
            <div class="my-res-detail-card">
              <h4 class="my-res-detail-card-title">
                Reserved Vehicle
              </h4>
              <div class="my-res-info-list">
                <div class="my-res-info-row">
                  <span class="my-res-info-label">License Plate</span>
                  <span class="my-res-info-value"><span class="dash-car-plate">${escapeHtml(plate)}</span></span>
                </div>
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Car Model</span>
                  <span class="my-res-info-value">${escapeHtml(model)}</span>
                </div>
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Spaces Requested</span>
                  <span class="my-res-info-value">1 Vehicle Spot</span>
                </div>
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Lot Status</span>
                  <span class="my-res-info-value">${space.status ? escapeHtml(space.status.toUpperCase()) : 'ACTIVE'}</span>
                </div>
              </div>
            </div>

            <!-- Card 2: Parking Space & Owner Details -->
            <div class="my-res-detail-card">
              <h4 class="my-res-detail-card-title">
                Parking Space & Owner
              </h4>
              <div class="my-res-info-list">
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Offered By</span>
                  <span class="my-res-info-value">${escapeHtml(ownerName)}</span>
                </div>
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Lot Capacity</span>
                  <span class="my-res-info-value">${space.available_spaces ?? '?'} / ${space.capacity ?? '?'} spaces open</span>
                </div>
                <div class="my-res-info-row">
                  <span class="my-res-info-label">Emergency Notes</span>
                  <span class="my-res-info-value">${r.notes ? escapeHtml(r.notes) : '<em>None provided</em>'}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline Card -->
          <div class="my-res-timeline-card">
            <h4 class="my-res-detail-card-title">
              <span>⏱️</span> Reservation Status Timeline
            </h4>
            <div class="my-res-timeline">
              <!-- Step 1: Requested -->
              <div class="my-res-timeline-step completed">
                <div class="my-res-timeline-dot">✓</div>
                <div class="my-res-timeline-title">Request Sent</div>
                <div class="my-res-timeline-desc">${createdAtFormatted}</div>
              </div>

              <!-- Step 2: Owner Review -->
              <div class="${step2Class}">
                <div class="my-res-timeline-dot">${step2Dot}</div>
                <div class="my-res-timeline-title">${step2Title}</div>
                <div class="my-res-timeline-desc">${step2Desc}</div>
              </div>

              <!-- Step 3: Spot Secured -->
              <div class="${step3Class}">
                <div class="my-res-timeline-dot">${step3Dot}</div>
                <div class="my-res-timeline-title">${step3Title}</div>
                <div class="my-res-timeline-desc">${step3Desc}</div>
              </div>
            </div>
          </div>

          <!-- Action Card -->
          <div class="my-res-action-card">
            ${actionBannerHtml}
            <div class="my-res-action-buttons-row">
              ${actionButtonsHtml}
            </div>
          </div>
        `;
      }
      window.renderMyReservationDetail = renderMyReservationDetail;
      window.loadReservations = loadReservations;

      // ========================================================
      // 3D CAR CUSTOMIZER STUDIO & VEHICLES CONTROLLER
      // ========================================================
      let carStudio3D = null;
      let carStudioConfig = {
        bodyStyle: 'pickup',
        paintColor: '#ea580c',
        wheelStyle: 'flood',
        detail: 'snorkel'
      };
      let registeredCarsData = {};

      function parseCarData(modelRaw) {
        if (!modelRaw) return null;
        let str = modelRaw;
        if (typeof str === 'string' && str.includes('&quot;')) {
          str = str.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&');
        }
        if (typeof str === 'object' && str !== null) return str;
        try {
          if (typeof str === 'string' && str.trim().startsWith('{')) {
            return JSON.parse(str);
          }
        } catch (e) {}
        return null;
      }
      window.parseCarData = parseCarData;

      function getCarDisplayLabel(modelRaw) {
        const data = parseCarData(modelRaw);
        if (data) {
          if (data.label && data.label.trim()) return data.label.trim();
          const styleLabels = {
            pickup: 'Rescue Pickup',
            suv: '4x4 Offroad SUV',
            van: 'Emergency Van',
            compact: 'Compact Car'
          };
          const colorLabels = {
            '#ea580c': 'Hi-Vis Orange',
            '#f8fafc': 'White',
            '#eab308': 'Yellow',
            '#dc2626': 'Red',
            '#0284c7': 'Blue',
            '#15803d': 'Green',
            '#475569': 'Slate',
            '#1e293b': 'Black'
          };
          const s = styleLabels[data.bodyStyle] || 'Rescue Vehicle';
          const c = colorLabels[data.paintColor] ? ` (${colorLabels[data.paintColor]})` : '';
          return `${s}${c}`;
        }
        return modelRaw || 'Standard Vehicle';
      }
      window.getCarDisplayLabel = getCarDisplayLabel;

      function renderCarPortrait(modelRaw) {
        const data = parseCarData(modelRaw);
        if (data && data.snapshot) {
          return `<div class="car-avatar-portrait"><img src="${data.snapshot}" alt="3D Vehicle"></div>`;
        }
        return `<div class="car-avatar-portrait"><span style="font-size: 1.8rem;">🚗</span></div>`;
      }
      window.renderCarPortrait = renderCarPortrait;

      function getCarSpecsBadge(modelRaw) {
        const data = parseCarData(modelRaw);
        if (data) {
          const styleLabels = { pickup: 'Pickup', suv: '4x4 SUV', van: 'Van', compact: 'Compact' };
          const wheelLabels = { flood: 'Flood Wheels', offroad: 'All-Terrain', steelie: 'Steelies', sport: 'Sport Alloys' };
          const detailLabels = { snorkel: 'Snorkel', roofrack: 'Roof Rack', beacon: 'Beacon', standard: 'Clean' };

          const styleName = styleLabels[data.bodyStyle] || '3D Car';
          const wheelName = wheelLabels[data.wheelStyle] || '';
          const detailName = detailLabels[data.detail] ? ` &bull; ${detailLabels[data.detail]}` : '';
          return `<span class="profile-card-specs-tag">3D &bull; ${styleName}${wheelName ? ' &bull; ' + wheelName : ''}${detailName}</span>`;
        }
        return `<span class="profile-card-specs-tag">Vehicle</span>`;
      }
      window.getCarSpecsBadge = getCarSpecsBadge;

      function showVehicleAlert(type, message) {
        const el = document.getElementById('vehicle-action-alert');
        if (!el) return;
        el.className = `alert-message ${type}`;
        el.innerHTML = message;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function initCarStudio3D() {
        const canvas = document.getElementById('car-studio-canvas');
        if (!canvas || !window.Car3D) return;
        if (!carStudio3D) {
          carStudio3D = new window.Car3D(canvas, carStudioConfig);
        } else {
          carStudio3D.updateConfig(carStudioConfig);
          carStudio3D.resize();
        }
      }

      function syncCarCustomizerUI() {
        // 1. Body style active state
        document.querySelectorAll('#car-model-options .customizer-chip-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.style === carStudioConfig.bodyStyle);
        });

        // 2. Paint color swatch active state
        document.querySelectorAll('#car-paint-colors .color-swatch-btn').forEach(b => {
          b.classList.toggle('active', (b.dataset.color || '').toLowerCase() === (carStudioConfig.paintColor || '').toLowerCase());
        });

        // 3. Wheel style active state
        document.querySelectorAll('#car-wheels-options .customizer-chip-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.wheels === carStudioConfig.wheelStyle);
        });

        // 4. Flood gear active state
        document.querySelectorAll('#car-details-options .customizer-chip-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.detail === carStudioConfig.detail);
        });
      }

      function setupCarCustomizerListeners() {
        // Tab switching
        const tabBtns = document.querySelectorAll('#car-customizer-tabs .customizer-tab-btn');
        const panels = document.querySelectorAll('#car-customizer-panels .customizer-tab-panel');

        tabBtns.forEach(btn => {
          btn.addEventListener('click', () => {
            const targetTab = btn.dataset.tab;
            tabBtns.forEach(b => {
              const isActive = (b === btn);
              b.classList.toggle('active', isActive);
              b.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach(p => {
              p.style.display = (p.dataset.tab === targetTab) ? 'block' : 'none';
            });
          });
        });

        // Model selection
        document.querySelectorAll('#car-model-options .customizer-chip-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll('#car-model-options .customizer-chip-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            carStudioConfig.bodyStyle = btn.dataset.style;
            if (carStudio3D) carStudio3D.updateConfig({ bodyStyle: carStudioConfig.bodyStyle });
          });
        });

        // Paint color swatches
        document.querySelectorAll('#car-paint-colors .color-swatch-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll('#car-paint-colors .color-swatch-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            carStudioConfig.paintColor = btn.dataset.color;
            if (carStudio3D) carStudio3D.updateConfig({ paintColor: carStudioConfig.paintColor });
          });
        });

        // Wheel options
        document.querySelectorAll('#car-wheels-options .customizer-chip-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll('#car-wheels-options .customizer-chip-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            carStudioConfig.wheelStyle = btn.dataset.wheels;
            if (carStudio3D) carStudio3D.updateConfig({ wheelStyle: carStudioConfig.wheelStyle });
          });
        });

        // Flood gear options
        document.querySelectorAll('#car-details-options .customizer-chip-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll('#car-details-options .customizer-chip-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            carStudioConfig.detail = btn.dataset.detail;
            if (carStudio3D) carStudio3D.updateConfig({ detail: carStudioConfig.detail });
          });
        });

        // Reset Appearance button
        document.getElementById('btn-reset-car-appearance')?.addEventListener('click', () => {
          carStudioConfig = {
            bodyStyle: 'pickup',
            paintColor: '#ea580c',
            wheelStyle: 'flood',
            detail: 'snorkel'
          };
          syncCarCustomizerUI();
          if (carStudio3D) carStudio3D.resetToDefaults();
        });
      }

      function resetCarStudioForm() {
        const titleEl = document.getElementById('vehicle-studio-title');
        const descEl = document.getElementById('vehicle-studio-desc');
        const badgeBar = document.getElementById('vehicle-badge-bar');
        const idInput = document.getElementById('vehicle-studio-id');
        const plateInput = document.getElementById('vehicle-studio-plate');
        const nameInput = document.getElementById('vehicle-studio-name');
        const cancelBtn = document.getElementById('btn-cancel-car-studio');
        const deleteBtn = document.getElementById('btn-delete-car-studio');
        const submitBtn = document.getElementById('btn-submit-car-studio');

        if (titleEl) titleEl.textContent = 'Register Vehicle';
        if (descEl) descEl.textContent = 'Customize your 3D flood vehicle and link your license plate for reservations.';
        if (badgeBar) badgeBar.style.display = 'none';
        if (idInput) idInput.value = '';
        if (plateInput) plateInput.value = '';
        if (nameInput) nameInput.value = '';
        if (cancelBtn) cancelBtn.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
        if (submitBtn) submitBtn.innerHTML = 'Save Vehicle';

        carStudioConfig = {
          bodyStyle: 'pickup',
          paintColor: '#ea580c',
          wheelStyle: 'flood',
          detail: 'snorkel'
        };
        syncCarCustomizerUI();
        if (carStudio3D) carStudio3D.resetToDefaults();
      }
      window.resetCarStudioForm = resetCarStudioForm;

      function editVehicleInStudio(carId) {
        const car = registeredCarsData[carId];
        if (!car) return;

        window.location.hash = '#vehicles';
        const titleEl = document.getElementById('vehicle-studio-title');
        const descEl = document.getElementById('vehicle-studio-desc');
        const badgeBar = document.getElementById('vehicle-badge-bar');
        const idTag = document.getElementById('vehicle-id-tag');
        const idInput = document.getElementById('vehicle-studio-id');
        const plateInput = document.getElementById('vehicle-studio-plate');
        const nameInput = document.getElementById('vehicle-studio-name');
        const cancelBtn = document.getElementById('btn-cancel-car-studio');
        const deleteBtn = document.getElementById('btn-delete-car-studio');
        const submitBtn = document.getElementById('btn-submit-car-studio');

        if (titleEl) titleEl.textContent = 'Edit Vehicle Details';
        if (descEl) descEl.textContent = 'Update your 3D customization and registration details.';
        if (badgeBar) badgeBar.style.display = 'flex';
        if (idTag) idTag.textContent = `#${car.id.slice(0, 8)} (${car.id.slice(-4)})`;
        if (idInput) idInput.value = car.id;
        if (plateInput) plateInput.value = car.license_plate || '';
        if (submitBtn) submitBtn.innerHTML = 'Save Vehicle';

        // Parse customizer options
        const parsed = parseCarData(car.model);
        if (parsed) {
          if (nameInput) nameInput.value = parsed.label || '';
          carStudioConfig = {
            bodyStyle: parsed.bodyStyle || 'pickup',
            paintColor: parsed.paintColor || '#ea580c',
            wheelStyle: parsed.wheelStyle || 'flood',
            detail: parsed.detail || 'snorkel'
          };
        } else {
          if (nameInput) nameInput.value = car.model || '';
          carStudioConfig = {
            bodyStyle: 'pickup',
            paintColor: '#ea580c',
            wheelStyle: 'flood',
            detail: 'snorkel'
          };
        }

        syncCarCustomizerUI();
        if (carStudio3D) {
          carStudio3D.updateConfig(carStudioConfig);
        } else {
          setTimeout(initCarStudio3D, 120);
        }

        if (cancelBtn) cancelBtn.style.display = 'inline-block';
        if (deleteBtn) deleteBtn.style.display = 'inline-block';
        document.getElementById('left-vehicle-container')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      window.editVehicleInStudio = editVehicleInStudio;

      document.getElementById('btn-cancel-car-studio')?.addEventListener('click', resetCarStudioForm);

      document.getElementById('btn-delete-car-studio')?.addEventListener('click', async () => {
        const idInput = document.getElementById('vehicle-studio-id');
        const plateInput = document.getElementById('vehicle-studio-plate');
        const carId = idInput ? idInput.value.trim() : '';
        const plate = plateInput ? plateInput.value.trim() : 'this vehicle';
        if (!carId) return;

        await deleteCar(carId, plate);
        resetCarStudioForm();
        await loadStudioCars();
      });

      // Submit Studio Form
      document.getElementById('vehicle-studio-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const idInput = document.getElementById('vehicle-studio-id');
        const plateInput = document.getElementById('vehicle-studio-plate');
        const nameInput = document.getElementById('vehicle-studio-name');
        const submitBtn = document.getElementById('btn-submit-car-studio');

        const carId = idInput ? idInput.value.trim() : '';
        const plate = plateInput ? plateInput.value.trim() : '';
        const customName = nameInput ? nameInput.value.trim() : '';

        if (!plate) {
          showVehicleAlert('error', 'Please enter a vehicle license plate.');
          if (plateInput) plateInput.focus();
          return;
        }

        // Generate 3D snapshot from canvas
        const snapshot = carStudio3D ? carStudio3D.getSnapshot(0.82) : '';
        const modelPayload = JSON.stringify({
          bodyStyle: carStudioConfig.bodyStyle,
          paintColor: carStudioConfig.paintColor,
          wheelStyle: carStudioConfig.wheelStyle,
          detail: carStudioConfig.detail,
          label: customName || getCarDisplayLabel(JSON.stringify(carStudioConfig)),
          snapshot: snapshot
        });

        const isUpdate = Boolean(carId);
        const endpoint = isUpdate ? 'api/update_car.php' : 'api/create_car.php';
        const payload = isUpdate
          ? { id: carId, license_plate: plate, model: modelPayload }
          : { license_plate: plate, model: modelPayload };

        try {
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
          }

          const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify(payload)
          });

          const json = await res.json();
          if (res.ok && json.success) {
            showVehicleAlert('success', isUpdate
              ? `<strong>Vehicle Saved:</strong> "${plate}" customization updated successfully.`
              : `<strong>Vehicle Registered:</strong> "${plate}" customized and saved in Supabase.`
            );
            resetCarStudioForm();
            await loadStudioCars();
            if (typeof loadCars === 'function') await loadCars();
            if (typeof loadDashboard === 'function') await loadDashboard();
          } else {
            showVehicleAlert('error', `<strong>Failed to save vehicle:</strong> ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showVehicleAlert('error', `<strong>Network error:</strong> ${err.message}`);
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Save Vehicle';
          }
        }
      });

      async function loadStudioCars() {
        const container = document.getElementById('vehicle-studio-list-container');
        const countPill = document.getElementById('vehicle-count-pill');
        if (!container) return;

        if (!currentUser) {
          container.innerHTML = '<div class="empty-spaces-notice">Please sign in to view and customize your vehicles.</div>';
          return;
        }

        try {
          container.innerHTML = '<div class="empty-spaces-notice"><span>⏳</span> Loading your registered vehicles...</div>';
          const res = await fetch('api/get_cars.php?_t=' + Date.now());
          const json = await res.json();

          if (!res.ok || !json.success) {
            container.innerHTML = `<div class="empty-spaces-notice" style="color: #ef4444;">Could not load vehicles: ${json.error || 'Database error'}</div>`;
            return;
          }

          const cars = json.data || [];
          registeredCarsData = {};
          cars.forEach(c => {
            registeredCarsData[c.id] = c;
          });

          if (countPill) {
            countPill.textContent = `${cars.length} ${cars.length === 1 ? 'Vehicle' : 'Vehicles'}`;
          }

          if (cars.length === 0) {
            container.innerHTML = `
              <div class="empty-spaces-notice">
                No vehicles registered yet. Use the 3D Car Studio on the left to customize and register your vehicle.
              </div>
            `;
            return;
          }

          let html = '';
          cars.forEach(c => {
            const displayLabel = getCarDisplayLabel(c.model);
            const specsBadge = getCarSpecsBadge(c.model);
            const portraitHtml = renderCarPortrait(c.model);

            html += `
              <div class="profile-card" id="studio-car-${c.id}" style="margin-bottom: 12px; background: #ffffff;">
                <div class="profile-card-left" style="align-items: center; gap: 14px;">
                  ${portraitHtml}
                  <div style="flex: 1; min-width: 0;">
                    <div class="profile-card-name" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                      <span>${c.license_plate}</span>
                    </div>
                    <div style="font-size: 0.84rem; color: #475569; font-weight: 700; margin-bottom: 3px;">
                      ${displayLabel}
                    </div>
                    ${specsBadge}
                    <div style="margin-top: 6px;">
                      <span class="profile-clean-id" title="Full UUID: ${c.id} (Click to copy)" onclick="navigator.clipboard?.writeText('${c.id}'); const h = this.querySelector('.id-copy-hint'); if(h) { h.textContent='Copied!'; setTimeout(()=>h.textContent='Copy', 1500); }">
                        <span class="id-hash">#</span>${c.id.slice(0, 8)}
                        <span class="id-copy-hint" title="Copy UUID">Copy</span>
                      </span>
                    </div>
                  </div>
                </div>
                <div style="display: flex; gap: 6px; align-items: center; justify-content: flex-end; margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 10px;">
                  <button type="button" class="pixel-btn pixel-btn-sm btn-edit-studio-car" data-id="${c.id}" title="Edit in 3D Studio">
                    Edit in 3D
                  </button>
                  <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-studio-car" data-id="${c.id}" data-plate="${c.license_plate}" title="Delete Vehicle">
                    Delete
                  </button>
                </div>
              </div>
            `;
          });

          container.innerHTML = html;

          container.querySelectorAll('.btn-edit-studio-car').forEach(btn => {
            btn.addEventListener('click', () => {
              editVehicleInStudio(btn.dataset.id);
            });
          });

          container.querySelectorAll('.btn-delete-studio-car').forEach(btn => {
            btn.addEventListener('click', async () => {
              await deleteCar(btn.dataset.id, btn.dataset.plate);
              await loadStudioCars();
            });
          });

        } catch (err) {
          container.innerHTML = `<div class="empty-spaces-notice" style="color: #ef4444;">Network error: ${err.message}</div>`;
        }
      }
      window.loadStudioCars = loadStudioCars;

      setupCarCustomizerListeners();
      setTimeout(() => {
        initCarStudio3D();
      }, 200);

      // ========================================================
      // VEHICLES (CARS) MANAGEMENT CONTROLLER (MY RESERVATIONS SYNC)
      // ========================================================
      async function loadCars() {
        const container = document.getElementById('cars-list-container');
        if (!container || !currentUser) return;

        try {
          container.innerHTML = '<div style="font-size: 0.88rem; color: #64748b;">⏳ Loading vehicles from Supabase...</div>';
          const res = await fetch('api/get_cars.php');
          const json = await res.json();

          if (!res.ok || !json.success) {
            container.innerHTML = `<div style="font-size: 0.88rem; color: #ef4444;">Could not load vehicles: ${json.error || 'Database error'}</div>`;
            return;
          }

          const cars = json.data || [];
          cars.forEach(c => {
            registeredCarsData[c.id] = c;
          });

          if (cars.length === 0) {
            container.innerHTML = `
              <div style="font-size: 0.88rem; color: #64748b; background: #f8fafc; padding: 12px; border-radius: 4px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>No vehicles registered yet. Click "Add Vehicle" to customize in 3D.</span>
                <a href="#vehicles" class="pixel-btn pixel-btn-sm pixel-btn-green">Open 3D Car Studio</a>
              </div>
            `;
            return;
          }

          let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">';
          cars.forEach(c => {
            const portraitHtml = renderCarPortrait(c.model);
            const displayLabel = getCarDisplayLabel(c.model);
            const specsBadge = getCarSpecsBadge(c.model);

            html += `
              <div style="background: #ffffff; border: 2px solid var(--border-pixel); border-radius: 4px; padding: 12px; display: flex; gap: 12px; align-items: center; box-shadow: var(--shadow-pixel-sm);">
                ${portraitHtml}
                <div style="flex: 1; min-width: 0;">
                  <div style="font-weight: 800; font-size: 1.05rem; color: #0f172a;">${c.license_plate}</div>
                  <div style="font-size: 0.84rem; color: #475569; font-weight: 700; margin-bottom: 2px;">${displayLabel}</div>
                  ${specsBadge}
                  <div style="margin-top: 6px; display: flex; gap: 6px;">
                    <button type="button" class="pixel-btn pixel-btn-sm btn-edit-car" data-id="${c.id}" data-plate="${c.license_plate}" data-model="${c.model || ''}" title="Edit in 3D Studio">
                      Edit
                    </button>
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-car" data-id="${c.id}" data-plate="${c.license_plate}" title="Delete Vehicle">
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            `;
          });
          html += '</div>';
          container.innerHTML = html;
        } catch (err) {
          container.innerHTML = `<div style="font-size: 0.88rem; color: #ef4444;">Network error: ${err.message}</div>`;
        }
      }

      window.loadCars = loadCars;

      document.getElementById('btn-toggle-add-car')?.addEventListener('click', () => {
        window.location.hash = '#vehicles';
        resetCarStudioForm();
      });

      document.getElementById('btn-cancel-add-car')?.addEventListener('click', () => {
        const form = document.getElementById('add-car-form');
        if (form) form.style.display = 'none';
      });

      document.getElementById('add-car-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const plateInput = document.getElementById('car-plate');
        const modelInput = document.getElementById('car-model');
        const plate = plateInput ? plateInput.value.trim() : '';
        const model = modelInput ? modelInput.value.trim() : '';

        if (!plate) return;

        try {
          const res = await fetch('api/create_car.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ license_plate: plate, model })
          });

          const json = await res.json();
          if (res.ok && json.success) {
            showReservationAlert('success', `<strong>Vehicle Registered:</strong> "${plate}" has been saved in Supabase.`);
            if (typeof showDashboardAlert === 'function') {
              showDashboardAlert('success', `<strong>Vehicle Registered:</strong> "${plate}" has been saved in Supabase.`);
            }
            if (plateInput) plateInput.value = '';
            if (modelInput) modelInput.value = '';
            document.getElementById('add-car-form').style.display = 'none';
            await loadCars();
            if (typeof loadStudioCars === 'function') await loadStudioCars();
            if (typeof loadDashboard === 'function') await loadDashboard();
          } else {
            showReservationAlert('error', `<strong>Failed to register vehicle:</strong> ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showReservationAlert('error', `<strong>Network error:</strong> ${err.message}`);
        }
      });

      async function deleteCar(id, plate) {
        if (!id) return;
        const confirmed = window.confirm(`Are you sure you want to delete vehicle "${plate}" from Supabase?`);
        if (!confirmed) return;

        try {
          const res = await fetch('api/delete_car.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ id })
          });
          const json = await res.json();
          if (res.ok && json.success) {
            showReservationAlert('success', `Vehicle "${plate}" deleted from Supabase.`);
            showVehicleAlert('success', `Vehicle "${plate}" deleted from Supabase.`);
            if (typeof showDashboardAlert === 'function') {
              showDashboardAlert('success', `Vehicle "${plate}" deleted from Supabase.`);
            }
            await loadCars();
            if (typeof loadStudioCars === 'function') await loadStudioCars();
            if (typeof loadDashboard === 'function') await loadDashboard();
          } else {
            showReservationAlert('error', `<strong>Failed to delete vehicle:</strong> ${json.error || 'Server error'}`);
            showVehicleAlert('error', `<strong>Failed to delete vehicle:</strong> ${json.error || 'Server error'}`);
            if (typeof showDashboardAlert === 'function') {
              showDashboardAlert('error', `<strong>Failed to delete vehicle:</strong> ${json.error || 'Server error'}`);
            }
          }
        } catch (err) {
          showReservationAlert('error', `<strong>Network error:</strong> ${err.message}`);
          showVehicleAlert('error', `<strong>Network error:</strong> ${err.message}`);
        }
      }

      async function cancelReservation(reservationId, spaceName) {
        if (!reservationId) return;

        const displayName = spaceName || 'this parking spot';
        const confirmed = window.confirm(
          `Cancel Parking Reservation\n\n` +
          `Are you sure you want to cancel your reservation for "${displayName}"?\n\n` +
          `The parking space will immediately be returned to available capacity for neighbors in need.`
        );
        if (!confirmed) return;

        try {
          const res = await fetch('api/cancel_reservation.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ reservation_id: reservationId })
          });

          const json = await res.json();
          if (res.ok && json.success) {
            showReservationAlert('success', `
              <strong>Reservation Cancelled:</strong><br>
              Your reservation for "${displayName}" has been cancelled. The parking spot is now available for other drivers.
            `);
            await loadReservations();
            if (typeof loadOwnerRequests === 'function') await loadOwnerRequests();
            if (typeof loadDashboard === 'function') await loadDashboard();
            if (window.parkingMap) {
              window.parkingMap.loadParkingSpaces();
            }
          } else {
            showReservationAlert('error', `<strong>Failed to cancel reservation:</strong> ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showReservationAlert('error', `<strong>Network error:</strong> ${err.message}`);
        }
      }

      // ========================================================
      // ========================================================
      // MY PARKING LOTS CONTROLLER (TWO-STEP OWNER EXPERIENCE)
      // ========================================================
      let myLotsState = {
        lots: [],
        reservations: [],
        selectedLotId: null
      };

      function showMySpacesAlert(type, message) {
        const alertBox = document.getElementById('my-spaces-action-alert');
        if (!alertBox) return;
        alertBox.className = `alert-message ${type}`;
        alertBox.innerHTML = message;
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function clearMySpacesAlert() {
        const alertBox = document.getElementById('my-spaces-action-alert');
        if (alertBox) alertBox.style.display = 'none';
      }

      async function loadMyParkingLots() {
        if (!currentUser) {
          const listContainer = document.getElementById('my-lots-list-container');
          if (listContainer) {
            listContainer.innerHTML = '<div class="empty-spaces-notice">Please sign in to view and manage your parking lots.</div>';
          }
          return;
        }

        const listContainer = document.getElementById('my-lots-list-container');
        if (listContainer && !myLotsState.selectedLotId) {
          listContainer.innerHTML = '<div class="empty-spaces-notice">Loading your parking lots...</div>';
        }

        try {
          const [spacesRes, ownerRes] = await Promise.all([
            fetch('api/get_parking_spaces.php').then(r => r.json()).catch(e => ({ success: false, error: e.message })),
            fetch('api/get_reservations.php?as_owner=1').then(r => r.json()).catch(e => ({ success: false, error: e.message }))
          ]);

          const allSpaces = (spacesRes && spacesRes.success && Array.isArray(spacesRes.data)) ? spacesRes.data : [];
          myLotsState.lots = allSpaces.filter(s => s.owner_id && s.owner_id === currentUser.id);
          myLotsState.reservations = (ownerRes && ownerRes.success && Array.isArray(ownerRes.data)) ? ownerRes.data : [];

          // If a specific lot is selected, render Step 2 detail view
          if (myLotsState.selectedLotId) {
            const currentLot = myLotsState.lots.find(l => l.id === myLotsState.selectedLotId);
            if (currentLot) {
              renderMyLotDetail(currentLot.id);
            } else {
              // Selected lot no longer exists, revert to Step 1
              myLotsState.selectedLotId = null;
              renderMyLotsList(myLotsState.lots, myLotsState.reservations);
            }
          } else {
            // Render Step 1 list view
            renderMyLotsList(myLotsState.lots, myLotsState.reservations);
          }
        } catch (err) {
          if (listContainer) {
            listContainer.innerHTML = `
              <div class="empty-spaces-notice" style="border-color: #ef4444; color: #b91c1c;">
                Network error while loading parking lots: ${err.message}
              </div>
            `;
          }
        }
      }
      window.loadMyParkingLots = loadMyParkingLots;

      // Legacy compatibility alias
      async function loadOwnerRequests() {
        return loadMyParkingLots();
      }
      window.loadOwnerRequests = loadOwnerRequests;

      function renderMyLotsList(lots, reservations) {
        const step1View = document.getElementById('my-lots-step1-view');
        const step2View = document.getElementById('my-lots-step2-view');
        const container = document.getElementById('my-lots-list-container');
        if (!container) return;

        if (step1View) step1View.style.display = 'block';
        if (step2View) step2View.style.display = 'none';

        if (lots.length === 0) {
          container.innerHTML = `
            <div class="empty-spaces-notice">
              
              <h4 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">No Parking Lots Registered</h4>
              <p style="color: #64748b; font-size: 0.88rem; max-width: 480px; margin: 0 auto 16px;">
                You haven't listed any elevated parking spaces yet. Help your community during flood emergencies by offering safe ground for neighbors' vehicles.
              </p>
              <button type="button" class="pixel-btn pixel-btn-green" id="btn-empty-offer-lot">
                Offer Parking Lot
              </button>
            </div>
          `;
          return;
        }

        let html = '<div class="my-lots-grid">';
        lots.forEach(lot => {
          const cap = parseInt(lot.capacity, 10) || 0;
          const avail = parseInt(lot.available_spaces, 10) || 0;
          const occupied = Math.max(0, cap - avail);
          const isClosed = lot.status === 'closed';
          const isFull = lot.status === 'full' || avail <= 0;

          const lat = lot.latitude ? parseFloat(lot.latitude).toFixed(5) : 'N/A';
          const lng = lot.longitude ? parseFloat(lot.longitude).toFixed(5) : 'N/A';

          const pendingCount = (reservations || []).filter(r => r.parking_space_id === lot.id && r.status === 'pending').length;
          const confirmedCount = (reservations || []).filter(r => r.parking_space_id === lot.id && (r.status === 'confirmed' || r.status === 'active')).length;

          let statusPill = '';
          if (isClosed) {
            statusPill = '<span class="status-pill status-closed">CLOSED</span>';
          } else if (isFull) {
            statusPill = '<span class="status-pill status-closed" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">FULL</span>';
          } else {
            statusPill = '<span class="status-pill status-available">AVAILABLE</span>';
          }

          html += `
            <article class="my-lot-card clickable-lot-card" id="my-lot-card-${lot.id}" data-lot-id="${lot.id}" title="Click to view full parking lot details and requests">
              <div>
                <div class="my-lot-card-header">
                  <div>
                    <h3 class="my-lot-card-title">${escapeHtml(lot.name)}</h3>
                    <div class="my-lot-card-coords">GPS: ${lat}, ${lng}</div>
                  </div>
                  <div>
                    ${statusPill}
                  </div>
                </div>

                <!-- Capacity & Availability Metrics -->
                <div class="my-lot-metrics-bar" style="margin: 12px 0;">
                  <div class="my-lot-metric-item">
                    <div class="my-lot-metric-num" style="color: #16a34a;">${avail}</div>
                    <div class="my-lot-metric-lbl">Available</div>
                  </div>
                  <div class="my-lot-metric-item">
                    <div class="my-lot-metric-num" style="color: #ea580c;">${occupied}</div>
                    <div class="my-lot-metric-lbl">Occupied</div>
                  </div>
                  <div class="my-lot-metric-item">
                    <div class="my-lot-metric-num" style="color: #b45309;">${pendingCount}</div>
                    <div class="my-lot-metric-lbl">Pending</div>
                  </div>
                  <div class="my-lot-metric-item">
                    <div class="my-lot-metric-num" style="color: #0f172a;">${cap}</div>
                    <div class="my-lot-metric-lbl">Capacity</div>
                  </div>
                </div>

                <!-- Badges Summary -->
                <div class="my-lot-badges-row">
                  ${pendingCount > 0 
                    ? `<span class="status-pill" style="font-size: 0.75rem; background: #fef3c7; color: #b45309; border-color: #fde68a;">${pendingCount} Pending Request${pendingCount > 1 ? 's' : ''}</span>`
                    : '<span style="font-size: 0.74rem; color: #94a3b8;">No pending requests</span>'
                  }
                  <span class="status-pill status-available" style="font-size: 0.75rem;">${confirmedCount} Confirmed Booking${confirmedCount > 1 ? 's' : ''}</span>
                </div>
              </div>

              <!-- Action Bar -->
              <div class="my-lot-card-actions">
                <button type="button" class="pixel-btn pixel-btn-blue btn-manage-lot" data-lot-id="${lot.id}" style="width: 100%; padding: 10px; font-weight: 800;">
                  Manage Lot & Requests &rarr;
                </button>
                <div class="my-lot-card-subactions">
                  <button type="button" class="pixel-btn pixel-btn-sm ${isClosed ? 'pixel-btn-green' : 'pixel-btn-yellow'} btn-toggle-lot-status" data-id="${lot.id}" data-name="${escapeHtml(lot.name)}" data-capacity="${lot.capacity}" data-available="${lot.available_spaces}" data-status="${lot.status}">
                    ${isClosed ? 'Reopen' : 'Close'}
                  </button>
                  <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-edit-lot-dash" data-id="${lot.id}">
                    Edit
                  </button>
                  <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-lot" data-id="${lot.id}" data-name="${escapeHtml(lot.name)}">
                    Delete
                  </button>
                </div>
              </div>
            </article>
          `;
        });
        html += '</div>';

        container.innerHTML = html;
      }

      function selectLotDetail(lotId) {
        myLotsState.selectedLotId = lotId;
        renderMyLotDetail(lotId);
        const section = document.getElementById('section-my-spaces');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      window.selectLotDetail = selectLotDetail;

      function backToLotsList() {
        myLotsState.selectedLotId = null;
        renderMyLotsList(myLotsState.lots, myLotsState.reservations);
        const section = document.getElementById('section-my-spaces');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      window.backToLotsList = backToLotsList;

      function renderMyLotDetail(lotId) {
        const step1View = document.getElementById('my-lots-step1-view');
        const step2View = document.getElementById('my-lots-step2-view');
        const detailContainer = document.getElementById('my-lot-detail-content');
        if (!detailContainer) return;

        const lot = (myLotsState.lots || []).find(l => l.id === lotId);
        if (!lot) {
          backToLotsList();
          return;
        }

        if (step1View) step1View.style.display = 'none';
        if (step2View) step2View.style.display = 'block';

        const cap = parseInt(lot.capacity, 10) || 0;
        const avail = parseInt(lot.available_spaces, 10) || 0;
        const occupied = Math.max(0, cap - avail);
        const isClosed = lot.status === 'closed';
        const isFull = lot.status === 'full' || avail <= 0;

        const lat = lot.latitude ? parseFloat(lot.latitude).toFixed(5) : 'N/A';
        const lng = lot.longitude ? parseFloat(lot.longitude).toFixed(5) : 'N/A';

        // Filter requests strictly for this lot
        const pendingReqs = (myLotsState.reservations || []).filter(r => r.parking_space_id === lot.id && r.status === 'pending');
        const confirmedBookings = (myLotsState.reservations || []).filter(r => r.parking_space_id === lot.id && (r.status === 'confirmed' || r.status === 'active'));

        let statusPill = '';
        if (isClosed) {
          statusPill = '<span class="status-pill status-closed">CLOSED</span>';
        } else if (isFull) {
          statusPill = '<span class="status-pill status-closed" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">FULL</span>';
        } else {
          statusPill = '<span class="status-pill status-available">AVAILABLE</span>';
        }

        // Render Pending Requests HTML
        let pendingHtml = '';
        if (pendingReqs.length > 0) {
          pendingHtml = `
            <div class="my-lot-req-grid">
              ${pendingReqs.map(r => {
                const driverName = r.driver_profile?.name || 'Community Driver';
                const car = r.cars || {};
                const plate = car.license_plate || 'Unlinked Vehicle';
                const model = car.model ? ` (${car.model})` : '';
                const createdStr = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';
                return `
                  <div class="dash-lot-req-card" id="owner-req-card-${r.id}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                      <div>
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">${escapeHtml(driverName)}</div>
                        <div style="font-size: 0.85rem; color: #334155; margin-top: 3px;">
                          <span class="dash-car-plate" style="font-size: 0.78rem;">${escapeHtml(plate)}</span>${escapeHtml(model)}
                        </div>
                      </div>
                      <span class="status-pill" style="font-size: 0.65rem; background: #fef3c7; color: #b45309; border-color: #fde68a;">
                        ⏳ PENDING
                      </span>
                    </div>
                    <div class="dash-req-meta" style="margin-top: 8px;">
                      <span><strong>Requested Spaces:</strong> 1 spot</span>
                      <span><strong>Submitted:</strong> ${createdStr}</span>
                    </div>
                    ${r.notes ? `<div style="font-size: 0.78rem; color: #64748b; font-style: italic; margin-top: 6px; background: #ffffff; padding: 4px 8px; border-radius: 3px;">Note: "${escapeHtml(r.notes)}"</div>` : ''}
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                      <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-owner-approve-res" data-id="${r.id}" data-action="accept" data-space="${escapeHtml(lot.name)}" data-plate="${escapeHtml(plate)}" style="flex: 1; padding: 8px;">
                        Accept
                      </button>
                      <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-owner-approve-res" data-id="${r.id}" data-action="reject" data-space="${escapeHtml(lot.name)}" data-plate="${escapeHtml(plate)}" style="flex: 1; padding: 8px;">
                        Decline
                      </button>
                    </div>
                  </div>
                `;
              }).join('')}
            </div>
          `;
        } else {
          pendingHtml = `
            <div class="dash-empty-notice">
              No pending reservation requests for this parking lot.
            </div>
          `;
        }

        // Render Confirmed Bookings HTML
        let confirmedHtml = '';
        if (confirmedBookings.length > 0) {
          confirmedHtml = `
            <div class="my-lot-req-grid">
              ${confirmedBookings.map((r, idx) => {
                const driverName = r.driver_profile?.name || 'Community Driver';
                const car = r.cars || {};
                const plate = car.license_plate || 'Unlinked Vehicle';
                const model = car.model ? ` (${car.model})` : '';
                const updatedStr = r.updated_at ? new Date(r.updated_at).toLocaleString() : (r.created_at ? new Date(r.created_at).toLocaleString() : 'Confirmed');
                return `
                  <div class="dash-lot-booking-card" id="owner-booking-card-${r.id}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                      <div>
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                          <span>Spot #${idx + 1}:</span> ${escapeHtml(driverName)}
                        </div>
                        <div style="font-size: 0.85rem; color: #334155; margin-top: 3px;">
                          <span class="dash-car-plate" style="font-size: 0.78rem;">${escapeHtml(plate)}</span>${escapeHtml(model)}
                        </div>
                      </div>
                      <span class="status-pill status-available" style="font-size: 0.65rem; padding: 3px 6px;">
                        ACCEPTED
                      </span>
                    </div>
                    <div class="dash-req-meta" style="margin-top: 8px;">
                      <span><strong>Lot:</strong> ${escapeHtml(lot.name)}</span>
                      <span><strong>Spaces:</strong> 1 space</span>
                      <span><strong>Confirmed At:</strong> ${updatedStr}</span>
                    </div>
                    ${r.notes ? `<div style="font-size: 0.78rem; color: #64748b; font-style: italic; margin-top: 6px; background: #ffffff; padding: 4px 8px; border-radius: 3px;">Note: "${escapeHtml(r.notes)}"</div>` : ''}
                  </div>
                `;
              }).join('')}
            </div>
          `;
        } else {
          confirmedHtml = `
            <div class="dash-empty-notice">
              No vehicles currently parked in this lot (0 / ${cap} spots occupied).
            </div>
          `;
        }

        detailContainer.innerHTML = `
          <!-- Lot Header Panel -->
          <div class="my-lot-detail-header-card">
            <div class="my-lot-detail-title-row">
              <div>
                <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0;">${escapeHtml(lot.name)}</h3>
                <div style="font-size: 0.82rem; color: #64748b; font-family: monospace; margin-top: 4px;">GPS: ${lat}, ${lng}</div>
              </div>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                ${statusPill}
                <button type="button" class="pixel-btn pixel-btn-sm ${isClosed ? 'pixel-btn-green' : 'pixel-btn-yellow'} btn-toggle-lot-status" data-id="${lot.id}" data-name="${escapeHtml(lot.name)}" data-capacity="${lot.capacity}" data-available="${lot.available_spaces}" data-status="${lot.status}">
                  ${isClosed ? 'Reopen Lot' : 'Close Lot'}
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-edit-lot-dash" data-id="${lot.id}">
                  Edit
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-lot" data-id="${lot.id}" data-name="${escapeHtml(lot.name)}">
                  Delete
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm btn-view-car-spot" data-lat="${lot.latitude}" data-lng="${lot.longitude}">
                  Map
                </button>
              </div>
            </div>

            <!-- Capacity Stats -->
            <div class="my-lot-metrics-bar" style="margin-top: 14px;">
              <div class="my-lot-metric-item">
                <div class="my-lot-metric-num" style="color: #16a34a;">${avail}</div>
                <div class="my-lot-metric-lbl">Available Spots</div>
              </div>
              <div class="my-lot-metric-item">
                <div class="my-lot-metric-num" style="color: #ea580c;">${occupied}</div>
                <div class="my-lot-metric-lbl">Occupied Spots</div>
              </div>
              <div class="my-lot-metric-item">
                <div class="my-lot-metric-num" style="color: #b45309;">${pendingReqs.length}</div>
                <div class="my-lot-metric-lbl">Pending Requests</div>
              </div>
              <div class="my-lot-metric-item">
                <div class="my-lot-metric-num" style="color: #0f172a;">${cap}</div>
                <div class="my-lot-metric-lbl">Total Capacity</div>
              </div>
            </div>
          </div>

          <!-- Section 1: Pending Reservation Requests -->
          <div class="my-lot-detail-section">
            <div class="my-lot-detail-section-title">
              <h3>Pending Requests (${pendingReqs.length})</h3>
              ${pendingReqs.length > 0 ? '<span class="status-badge" style="background:#fef3c7; color:#b45309; font-size:0.7rem;">Action Needed</span>' : ''}
            </div>
            <p class="my-lot-detail-section-desc">
              Review and approve reservation requests specifically for "${escapeHtml(lot.name)}". Accepting a request decreases available capacity by 1.
            </p>
            ${pendingHtml}
          </div>

          <!-- Section 2: Confirmed Bookings -->
          <div class="my-lot-detail-section">
            <div class="my-lot-detail-section-title">
              <h3>Confirmed Bookings (${confirmedBookings.length} / ${cap})</h3>
              <span class="status-badge status-available" style="font-size:0.7rem;">${occupied} Spots Filled</span>
            </div>
            <p class="my-lot-detail-section-desc">
              Vehicles currently holding an accepted spot in "${escapeHtml(lot.name)}".
            </p>
            ${confirmedHtml}
          </div>

          <!-- Section 3: Lot Details & Operating Specs -->
          <div class="my-lot-detail-section" id="my-lot-specs-section">
            <div class="my-lot-detail-section-title">
              <h3><span>ℹ️</span> Lot Details & Operating Specs</h3>
              <span class="status-badge status-available" style="font-size:0.7rem;">Verified Host</span>
            </div>
            <p class="my-lot-detail-section-desc">
              Facility specifications and verified location coordinates for this community flood parking space.
            </p>
            <div class="my-lot-specs-grid">
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Parking Space Name</span>
                <span class="my-lot-spec-val">${escapeHtml(lot.name)}</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">GPS Coordinates</span>
                <span class="my-lot-spec-val" style="font-family: monospace;">${lat}, ${lng}</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Total Vehicle Capacity</span>
                <span class="my-lot-spec-val">${cap} vehicle spaces</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Currently Available Spaces</span>
                <span class="my-lot-spec-val" style="color: #16a34a; font-weight: 800;">${avail} open spots</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Occupancy Rate</span>
                <span class="my-lot-spec-val">${cap > 0 ? Math.round((occupied / cap) * 100) : 0}% (${occupied} / ${cap} spots occupied)</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Operating Status</span>
                <span class="my-lot-spec-val">${statusPill}</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Host Account ID</span>
                <span class="my-lot-spec-val" style="font-family: monospace; font-size: 0.78rem;">${lot.owner_id || currentUser.id}</span>
              </div>
              <div class="my-lot-spec-item">
                <span class="my-lot-spec-label">Created Date</span>
                <span class="my-lot-spec-val">${lot.created_at ? new Date(lot.created_at).toLocaleDateString() : 'Active'}</span>
              </div>
            </div>
          </div>
        `;
      }

      async function handleOwnerReservationAction(reservationId, action, spaceName, plateName, buttonElement) {
        if (!reservationId || !action) return;

        const actionText = (action === 'accept') ? 'Accept' : 'Decline';
        const card = document.getElementById(`owner-req-card-${reservationId}`) || (buttonElement ? buttonElement.closest('.dash-lot-req-card, .space-card') : null);
        const actionButtons = card ? card.querySelectorAll('.btn-owner-approve-res') : [];

        // Disable repeated actions while processing
        actionButtons.forEach(b => {
          b.disabled = true;
        });
        if (buttonElement) {
          buttonElement.innerHTML = '<span>⏳</span> Processing...';
        }

        const alertBox = document.getElementById('my-spaces-action-alert') || document.getElementById('owner-requests-alert');
        if (alertBox) alertBox.style.display = 'none';

        try {
          const res = await fetch('api/approve_reservation.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({
              reservation_id: reservationId,
              action: action
            })
          });

          const json = await res.json();

          if (res.ok && json.success) {
            if (alertBox) {
              alertBox.className = 'alert-message success';
              alertBox.innerHTML = (action === 'accept') 
                ? `<strong>Reservation Accepted!</strong><br>Spot assigned for vehicle "${plateName}" at "${spaceName}". Available capacity has been updated.`
                : `ℹ️ <strong>Reservation Request Declined:</strong><br>Request for vehicle "${plateName}" was rejected and the vehicle released.`;
              alertBox.style.display = 'block';
              alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            if (typeof loadMyParkingLots === 'function') await loadMyParkingLots();
            if (typeof loadDashboard === 'function') await loadDashboard();
            if (window.parkingMap) window.parkingMap.loadParkingSpaces();
          } else {
            if (alertBox) {
              alertBox.className = 'alert-message error';
              alertBox.innerHTML = `<strong>Failed to ${actionText.toLowerCase()} reservation:</strong> ${json.error || 'Server error'}`;
              alertBox.style.display = 'block';
              alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            actionButtons.forEach(b => {
              b.disabled = false;
            });
            if (buttonElement) {
              buttonElement.innerHTML = (action === 'accept') ? 'Accept' : 'Decline';
            }
          }
        } catch (err) {
          if (alertBox) {
            alertBox.className = 'alert-message error';
            alertBox.innerHTML = `<strong>Network error:</strong> ${err.message}`;
            alertBox.style.display = 'block';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
          actionButtons.forEach(b => {
            b.disabled = false;
          });
          if (buttonElement) {
            buttonElement.innerHTML = (action === 'accept') ? 'Accept' : 'Decline';
          }
        }
      }

      // ========================================================
      // DASHBOARD CONTROLLER (MY CARS & MY PARKING LOTS)
      // ========================================================
      let dashboardState = {
        cars: [],
        spaces: [],
        reservations: [],
        ownerReservations: []
      };

      function showDashboardAlert(type, message) {
        const alertBox = document.getElementById('dash-action-alert');
        if (!alertBox) return;
        alertBox.className = `alert-message ${type}`;
        alertBox.innerHTML = message;
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function clearDashboardAlert() {
        const alertBox = document.getElementById('dash-action-alert');
        if (alertBox) alertBox.style.display = 'none';
      }

      async function loadDashboard() {
        if (!currentUser) return;

        const carsContainer = document.getElementById('dash-cars-container');
        const lotsContainer = document.getElementById('dash-lots-container');
        const assignmentsTbody = document.getElementById('dash-assignments-tbody');

        if (carsContainer) carsContainer.innerHTML = '<div class="empty-spaces-notice"><span>⏳</span> Loading your vehicles from Supabase...</div>';
        if (lotsContainer) lotsContainer.innerHTML = '<div class="empty-spaces-notice"><span>⏳</span> Loading your parking lots from Supabase...</div>';
        if (assignmentsTbody) assignmentsTbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 20px;"><span>⏳</span> Loading parking assignments from Supabase...</td></tr>';

        try {
          const [carsRes, spacesRes, reservationsRes, ownerRes] = await Promise.all([
            fetch('api/get_cars.php').then(r => r.json()).catch(e => ({ success: false, error: e.message })),
            fetch('api/get_parking_spaces.php').then(r => r.json()).catch(e => ({ success: false, error: e.message })),
            fetch('api/get_reservations.php').then(r => r.json()).catch(e => ({ success: false, error: e.message })),
            fetch('api/get_reservations.php?as_owner=1').then(r => r.json()).catch(e => ({ success: false, error: e.message }))
          ]);

          dashboardState.cars = (carsRes && carsRes.success && Array.isArray(carsRes.data)) ? carsRes.data : [];
          dashboardState.spaces = (spacesRes && spacesRes.success && Array.isArray(spacesRes.data)) ? spacesRes.data : [];
          dashboardState.reservations = (reservationsRes && reservationsRes.success && Array.isArray(reservationsRes.data)) ? reservationsRes.data : [];
          dashboardState.ownerReservations = (ownerRes && ownerRes.success && Array.isArray(ownerRes.data)) ? ownerRes.data : [];

          // Notice if tables need migration
          if ((!carsRes.success && carsRes.error && (carsRes.error.includes('relation "public.cars" does not exist') || carsRes.error.includes('PGRST205'))) ||
              (!reservationsRes.success && reservationsRes.error && (reservationsRes.error.includes('relation "public.reservations" does not exist') || reservationsRes.error.includes('PGRST205')))) {
            showDashboardAlert('error', `
              <strong>Database Tables Required:</strong> The <code>cars</code> and <code>reservations</code> tables need to be created in your Supabase project. 
              Please execute the migration script in <code>supabase/migrations/20261011_supabase_only_reservations_and_cars.sql</code> in your Supabase SQL Editor.
            `);
          }

          // Filter owned parking lots: owner_id === currentUser.id
          const myLots = dashboardState.spaces.filter(s => s.owner_id && s.owner_id === currentUser.id);

          // Map active reservations to cars (pending and confirmed count as active)
          const activeReservations = dashboardState.reservations.filter(r => r.status === 'confirmed' || r.status === 'active' || r.status === 'pending');
          const carAssignmentMap = {};
          activeReservations.forEach(r => {
            if (r.car_id) {
              carAssignmentMap[r.car_id] = r;
            }
          });

          // Section A: Metric counts
          const totalCars = dashboardState.cars.length;
          const assignedCarsCount = dashboardState.cars.filter(c => !!carAssignmentMap[c.id]).length;
          const myLotsCount = myLots.length;
          const totalAvailableSpaces = myLots.reduce((acc, lot) => acc + (parseInt(lot.available_spaces, 10) || 0), 0);
          const activeResCount = dashboardState.reservations.filter(r => r.status === 'confirmed' || r.status === 'active').length;

          // Pending counts
          const pendingOwnerCount = (dashboardState.ownerReservations || []).filter(r => (r.status || 'pending').toLowerCase() === 'pending').length;
          const pendingDriverCount = dashboardState.reservations.filter(r => (r.status || 'pending').toLowerCase() === 'pending').length;

          // Welcome greeting update with dynamic role badge
          const welcomeUserEl = document.getElementById('dash-welcome-user');
          if (welcomeUserEl && currentUser) {
            const roleName = myLotsCount > 0 ? (totalCars > 0 ? 'Driver & Parking Host' : 'Parking Host') : 'Community Driver';
            const displayName = currentUser.name || (currentUser.email ? currentUser.email.split('@')[0] : 'Community Member');
            welcomeUserEl.innerHTML = `${displayName} <span style="font-size:0.75rem; font-weight:700; color:#64748b; margin-left:4px;">(${roleName})</span>`;
          }

          const statCars = document.getElementById('dash-stat-cars');
          const statAssigned = document.getElementById('dash-stat-assigned');
          const statLots = document.getElementById('dash-stat-lots');
          const statRes = document.getElementById('dash-stat-reservations');
          const statOwnerAvailable = document.getElementById('dash-stat-owner-available');
          const statOwnerPending = document.getElementById('dash-stat-owner-pending');
          const statDriverPending = document.getElementById('dash-stat-driver-pending');

          if (statCars) statCars.textContent = totalCars;
          if (statAssigned) statAssigned.textContent = assignedCarsCount;
          if (statLots) statLots.textContent = myLotsCount;
          if (statRes) statRes.textContent = activeResCount;
          if (statOwnerAvailable) statOwnerAvailable.textContent = totalAvailableSpaces;
          if (statOwnerPending) statOwnerPending.textContent = pendingOwnerCount;
          if (statDriverPending) statDriverPending.textContent = pendingDriverCount;

          // What Needs Attention Section
          renderDashboardAttention(dashboardState.cars, myLots, dashboardState.reservations, dashboardState.ownerReservations);

          // Section B: Render My Cars
          renderDashboardCars(dashboardState.cars, carAssignmentMap);

          // Section C: Render My Parking Lots
          renderDashboardLots(myLots, dashboardState.ownerReservations);

          // Section D: Render My Parking Assignments
          renderDashboardAssignments(dashboardState.cars, carAssignmentMap, dashboardState.reservations);

          // Refresh owner incoming requests
          if (typeof loadOwnerRequests === 'function') {
            loadOwnerRequests();
          }

        } catch (err) {
          showDashboardAlert('error', `Network error while loading dashboard: ${err.message}`);
        }
      }
      window.loadDashboard = loadDashboard;

      function renderDashboardAttention(cars, myLots, driverReservations, ownerReservations) {
        const container = document.getElementById('dash-attention-container');
        const countBadge = document.getElementById('dash-attention-count-badge');
        if (!container) return;

        const pendingOwnerReqs = (ownerReservations || []).filter(r => (r.status || 'pending').toLowerCase() === 'pending');
        const pendingDriverReqs = (driverReservations || []).filter(r => (r.status || 'pending').toLowerCase() === 'pending');
        const confirmedDriverSpots = (driverReservations || []).filter(r => r.status === 'confirmed' || r.status === 'active');

        const totalUrgent = pendingOwnerReqs.length + pendingDriverReqs.length;

        if (countBadge) {
          if (totalUrgent > 0) {
            countBadge.innerHTML = `<span class="status-pill" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5; font-size:0.72rem; font-weight:800;">${totalUrgent} Action${totalUrgent > 1 ? 's' : ''} Pending</span>`;
          } else {
            countBadge.innerHTML = `<span class="status-pill status-available" style="font-size:0.72rem; font-weight:700;">All Clear</span>`;
          }
        }

        if (pendingOwnerReqs.length === 0 && pendingDriverReqs.length === 0 && confirmedDriverSpots.length === 0) {
          container.innerHTML = `
            <div class="dash-attention-empty">
              
              <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">All Clear — No Urgent Actions Required</div>
              <div style="font-size: 0.82rem; color: #64748b; margin-top: 4px;">
                You have no pending requests awaiting your decision, and no active flood reservations requiring attention.
              </div>
            </div>
          `;
          return;
        }

        let html = '<div class="dash-attention-grid">';

        // 1. Owner pending requests (highest priority - someone is requesting a spot in your lot)
        pendingOwnerReqs.forEach(r => {
          const space = r.parking_spaces || {};
          const lotName = space.name || 'Your Parking Lot';
          const driverName = r.driver_profile?.name || 'Community Driver';
          const car = r.cars || {};
          const plate = car.license_plate || 'Unlinked Vehicle';
          const model = car.model ? ` (${car.model})` : '';
          const createdStr = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';

          html += `
            <div class="dash-attention-card owner-urgent" id="dash-attn-owner-${r.id}">
              <div class="dash-attn-header">
                <div class="dash-attn-badge owner-badge">HOST DECISION NEEDED</div>
                <span class="status-pill" style="font-size: 0.65rem; background: #fef3c7; color: #b45309; border-color: #fde68a;">
                  ⏳ Awaiting Approval
                </span>
              </div>
              <div class="dash-attn-body">
                <div style="font-weight: 800; color: #0f172a; font-size: 0.92rem;">
                  ${driverName} <span style="font-weight: normal; color: #64748b; font-size: 0.82rem;">requested a spot at</span> <strong>${lotName}</strong>
                </div>
                <div style="font-size: 0.82rem; color: #334155; margin-top: 4px;">
                  <span class="dash-car-plate" style="font-size: 0.76rem;">${plate}</span>${model}
                </div>
                ${r.notes ? `<div style="font-size: 0.76rem; color: #64748b; font-style: italic; margin-top: 4px;">Note: "${r.notes}"</div>` : ''}
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">Requested: ${createdStr}</div>
              </div>
              <div class="dash-attn-actions">
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-owner-approve-res" data-id="${r.id}" data-action="accept" data-space="${lotName}" data-plate="${plate}">
                  Accept Request
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-owner-approve-res" data-id="${r.id}" data-action="reject" data-space="${lotName}" data-plate="${plate}">
                  Decline
                </button>
              </div>
            </div>
          `;
        });

        // 2. Driver pending requests (driver waiting for owner approval)
        pendingDriverReqs.forEach(r => {
          const space = r.parking_spaces || {};
          const lotName = space.name || 'Emergency Flood Lot';
          const car = r.cars || {};
          const plate = car.license_plate || 'Your Vehicle';
          const model = car.model ? ` (${car.model})` : '';
          const createdStr = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';

          html += `
            <div class="dash-attention-card driver-pending" id="dash-attn-driver-${r.id}">
              <div class="dash-attn-header">
                <div class="dash-attn-badge driver-badge">⏳ OUTGOING REQUEST</div>
                <span class="status-pill" style="font-size: 0.65rem; background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">
                  Waiting on Host
                </span>
              </div>
              <div class="dash-attn-body">
                <div style="font-weight: 800; color: #0f172a; font-size: 0.92rem;">
                  ${lotName}
                </div>
                <div style="font-size: 0.82rem; color: #334155; margin-top: 4px;">
                  <span class="dash-car-plate" style="font-size: 0.76rem;">${plate}</span>${model}
                </div>
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">Submitted: ${createdStr}</div>
              </div>
              <div class="dash-attn-actions">
                <a href="#my-reservations" class="pixel-btn pixel-btn-sm pixel-btn-blue">
                  View Reservation
                </a>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-cancel-car-res" data-id="${r.id}" data-plate="${plate}" data-lot="${lotName}">
                  Cancel Request
                </button>
              </div>
            </div>
          `;
        });

        // 3. Driver confirmed emergency spot (ready for vehicle safety)
        confirmedDriverSpots.forEach(r => {
          const space = r.parking_spaces || {};
          const lotName = space.name || 'Emergency Safe Lot';
          const car = r.cars || {};
          const plate = car.license_plate || 'Vehicle';
          const lat = space.latitude ? parseFloat(space.latitude).toFixed(4) : null;
          const lng = space.longitude ? parseFloat(space.longitude).toFixed(4) : null;

          html += `
            <div class="dash-attention-card driver-confirmed" id="dash-attn-confirmed-${r.id}">
              <div class="dash-attn-header">
                <div class="dash-attn-badge confirmed-badge">CONFIRMED FLOOD SPOT</div>
                <span class="status-pill status-available" style="font-size: 0.65rem;">
                  Ready
                </span>
              </div>
              <div class="dash-attn-body">
                <div style="font-weight: 800; color: #0f172a; font-size: 0.92rem;">
                  ${lotName}
                </div>
                <div style="font-size: 0.82rem; color: #334155; margin-top: 4px;">
                  Assigned: <span class="dash-car-plate" style="font-size: 0.76rem;">${plate}</span>
                </div>
                ${(lat && lng) ? `<div style="font-size: 0.75rem; color: #64748b; font-family: monospace; margin-top: 4px;">GPS: ${lat}, ${lng}</div>` : ''}
              </div>
              <div class="dash-attn-actions">
                ${(lat && lng) ? `
                  <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-view-car-spot" data-lat="${space.latitude}" data-lng="${space.longitude}">
                    Navigate / Map
                  </button>
                ` : ''}
                <a href="#my-reservations" class="pixel-btn pixel-btn-sm">
                  Details
                </a>
              </div>
            </div>
          `;
        });

        html += '</div>';
        container.innerHTML = html;
      }

      function renderDashboardCars(cars, carAssignmentMap) {
        const container = document.getElementById('dash-cars-container');
        if (!container) return;

        if (cars.length === 0) {
          container.innerHTML = `
            <div class="dash-empty-role-card">
              
              <div style="flex: 1; min-width: 0;">
                <h4 style="margin: 0 0 4px 0; font-size: 1rem; color: #0f172a;">No Vehicles Registered Yet</h4>
                <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.4;">
                  Register your car license plate and model to secure emergency high ground parking and track flood relocation status.
                </p>
              </div>
              <button type="button" class="pixel-btn pixel-btn-green pixel-btn-sm btn-dash-add-car-trigger" style="flex-shrink: 0;">
                Register New Car
              </button>
            </div>
          `;
          return;
        }

        let html = '';
        cars.forEach(car => {
          const res = carAssignmentMap[car.id];
          const hasAssignment = !!res;
          const space = res ? (res.parking_spaces || {}) : null;
          const lotName = space ? (space.name || 'Assigned Parking Lot') : null;
          const lat = space && space.latitude ? parseFloat(space.latitude).toFixed(4) : null;
          const lng = space && space.longitude ? parseFloat(space.longitude).toFixed(4) : null;

          let assignmentBoxHtml = '';
          if (hasAssignment) {
            const isPending = (res.status === 'pending');
            const statusBadge = isPending 
              ? '<span class="status-pill" style="font-size: 0.7rem; padding: 2px 6px; background: #fef3c7; color: #b45309; border-color: #fde68a;">⏳ PENDING OWNER APPROVAL</span>'
              : '<span class="status-pill status-available" style="font-size: 0.7rem; padding: 2px 6px;">RESERVATION CONFIRMED</span>';

            assignmentBoxHtml = `
              <div class="dash-car-assignment-box ${isPending ? 'pending' : 'assigned'}" style="${isPending ? 'border: 1px dashed #f59e0b; background: #fffbeb;' : ''}">
                <div class="dash-car-assignment-label">${isPending ? 'Requested Parking Spot' : 'Assigned Parking Spot'}</div>
                <div class="dash-car-assignment-value">${lotName}</div>
                ${(lat && lng) ? `<div style="font-size: 0.75rem; color: #64748b; font-family: monospace; margin-top: 2px;">GPS: ${lat}, ${lng}</div>` : ''}
                <div style="margin-top: 6px;">
                  ${statusBadge}
                </div>
              </div>
            `;
          } else {
            assignmentBoxHtml = `
              <div class="dash-car-assignment-box unassigned">
                <div class="dash-car-assignment-label">Assignment Status</div>
                <div class="dash-car-assignment-value" style="color: #64748b; font-weight: normal;">No parking spot assigned</div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 3px;">Ready for safe elevated parking assignment.</div>
              </div>
            `;
          }

          let actionButtons = '';
          if (hasAssignment) {
            const isPending = (res.status === 'pending');
            if (lat && lng) {
              actionButtons += `
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-view-car-spot" data-lat="${space.latitude}" data-lng="${space.longitude}" title="Locate parking lot on map">
                  View Spot
                </button>
              `;
            }
            actionButtons += `
              <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-cancel-car-res" data-id="${res.id}" data-plate="${car.license_plate}" data-lot="${lotName}" title="${isPending ? 'Cancel request' : 'Cancel reservation'}">
                ${isPending ? 'Cancel Request' : 'Cancel Spot'}
              </button>
            `;
          } else {
            actionButtons += `
              <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-open-reserve-car" data-car-id="${car.id}" data-plate="${car.license_plate}" title="Reserve available spot for this vehicle">
                Reserve Spot
              </button>
            `;
          }

          actionButtons += `
            <button type="button" class="pixel-btn pixel-btn-sm btn-edit-car" data-id="${car.id}" data-plate="${car.license_plate}" data-model="${car.model || ''}" title="Edit vehicle details">
              Edit
            </button>
            <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-car" data-id="${car.id}" data-plate="${car.license_plate}" title="Delete vehicle">
              Delete
            </button>
          `;

          html += `
            <article class="dash-car-card" id="dash-car-${car.id}">
              <div>
                <div class="dash-car-header">
                  <div class="dash-car-icon">CAR</div>
                  <div style="flex: 1; min-width: 0;">
                    <h4 class="dash-car-title">${car.model || 'Standard Vehicle'}</h4>
                    <span class="dash-car-plate">${car.license_plate}</span>
                  </div>
                  ${hasAssignment 
                    ? '<span class="status-badge status-available" style="font-size: 0.65rem;">Parked</span>'
                    : '<span class="status-badge" style="font-size: 0.65rem; background: #e2e8f0; color: #475569;">Unassigned</span>'
                  }
                </div>
                ${assignmentBoxHtml}
              </div>
              <div class="dash-card-actions">
                ${actionButtons}
              </div>
            </article>
          `;
        });

        container.innerHTML = html;
      }

      function renderDashboardLots(myLots, ownerReservations) {
        const container = document.getElementById('dash-lots-container');
        if (!container) return;

        if (myLots.length === 0) {
          container.innerHTML = `
            <div class="dash-empty-role-card">
              
              <div style="flex: 1; min-width: 0;">
                <h4 style="margin: 0 0 4px 0; font-size: 1rem; color: #0f172a;">Do you have flood-safe space to share?</h4>
                <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.4;">
                  You haven't listed any parking lots yet. If you own an elevated driveway, warehouse, or open ground above flood level, offering parking helps neighbors protect their vehicles.
                </p>
              </div>
              <a href="#my-spaces" class="pixel-btn pixel-btn-blue pixel-btn-sm" style="flex-shrink: 0;">
                Offer Parking Lot
              </a>
            </div>
          `;
          return;
        }

        let html = '';
        myLots.forEach(lot => {
          const cap = parseInt(lot.capacity, 10) || 0;
          const avail = parseInt(lot.available_spaces, 10) || 0;
          const occupied = Math.max(0, cap - avail);
          const isClosed = lot.status === 'closed';
          const isFull = lot.status === 'full' || avail <= 0;

          const lat = lot.latitude ? parseFloat(lot.latitude).toFixed(5) : 'N/A';
          const lng = lot.longitude ? parseFloat(lot.longitude).toFixed(5) : 'N/A';

          // Filter pending requests and accepted bookings for this specific lot
          const pendingReqs = (ownerReservations || []).filter(r => r.parking_space_id === lot.id && r.status === 'pending');
          const acceptedBookings = (ownerReservations || []).filter(r => r.parking_space_id === lot.id && (r.status === 'confirmed' || r.status === 'active'));

          // 1. Render Pending Requests for this parking lot
          let pendingHtml = '';
          if (pendingReqs.length > 0) {
            pendingHtml = pendingReqs.map(r => {
              const driverName = r.driver_profile?.name || 'Community Driver';
              const car = r.cars || {};
              const plate = car.license_plate || 'Unlinked Vehicle';
              const model = car.model ? ` (${car.model})` : '';
              const createdStr = r.created_at ? new Date(r.created_at).toLocaleString() : 'Recently';
              return `
                <div class="dash-lot-req-card" id="dash-owner-req-${r.id}">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                    <div>
                      <div style="font-weight: 800; color: #0f172a; font-size: 0.88rem;">${driverName}</div>
                      <div style="font-size: 0.8rem; color: #334155; margin-top: 2px;">
                        <span class="dash-car-plate" style="font-size: 0.74rem;">${plate}</span>${model}
                      </div>
                    </div>
                    <span class="status-pill" style="font-size: 0.65rem; background: #fef3c7; color: #b45309; border-color: #fde68a;">
                      PENDING
                    </span>
                  </div>
                  <div class="dash-req-meta">
                    <span><strong>Requested:</strong> 1 space</span>
                    <span><strong>Requested At:</strong> ${createdStr}</span>
                  </div>
                  ${r.notes ? `<div style="font-size: 0.75rem; color: #64748b; font-style: italic; margin-top: 4px;">Note: "${r.notes}"</div>` : ''}
                  <div style="display: flex; gap: 6px; margin-top: 8px;">
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-owner-approve-res" data-id="${r.id}" data-action="accept" data-space="${lot.name}" data-plate="${plate}" style="flex: 1; padding: 6px 8px; font-size: 0.78rem;">
                      Accept
                    </button>
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-owner-approve-res" data-id="${r.id}" data-action="reject" data-space="${lot.name}" data-plate="${plate}" style="flex: 1; padding: 6px 8px; font-size: 0.78rem;">
                      Decline
                    </button>
                  </div>
                </div>
              `;
            }).join('');
          } else {
            pendingHtml = `<div style="color: #94a3b8; font-size: 0.78rem; font-style: italic; padding: 4px 0;">No pending requests awaiting approval.</div>`;
          }

          // 2. Render Accepted Bookings (Occupied Spaces) for this parking lot
          let acceptedHtml = '';
          if (acceptedBookings.length > 0) {
            acceptedHtml = acceptedBookings.map((r, idx) => {
              const driverName = r.driver_profile?.name || 'Community Driver';
              const car = r.cars || {};
              const plate = car.license_plate || 'Unlinked Vehicle';
              const model = car.model ? ` (${car.model})` : '';
              const updatedStr = r.updated_at ? new Date(r.updated_at).toLocaleString() : (r.created_at ? new Date(r.created_at).toLocaleString() : 'Confirmed');
              return `
                <div class="dash-lot-booking-card" id="dash-owner-booking-${r.id}">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                    <div>
                      <div style="font-weight: 800; color: #0f172a; font-size: 0.88rem;">
                        <span>Spot #${idx + 1}:</span> ${driverName}
                      </div>
                      <div style="font-size: 0.8rem; color: #334155; margin-top: 2px;">
                        <span class="dash-car-plate" style="font-size: 0.74rem;">${plate}</span>${model}
                      </div>
                    </div>
                    <span class="status-pill status-available" style="font-size: 0.65rem; padding: 2px 6px;">
                      ACCEPTED
                    </span>
                  </div>
                  <div class="dash-req-meta">
                    <span><strong>Parking Lot:</strong> ${lot.name}</span>
                    <span><strong>Spaces:</strong> 1 space</span>
                    <span><strong>Confirmed:</strong> ${updatedStr}</span>
                  </div>
                  ${r.notes ? `<div style="font-size: 0.75rem; color: #64748b; font-style: italic; margin-top: 4px;">Note: "${r.notes}"</div>` : ''}
                </div>
              `;
            }).join('');
          } else {
            acceptedHtml = `<div style="color: #94a3b8; font-size: 0.78rem; font-style: italic; padding: 4px 0;">No vehicles parked yet (0 / ${cap} spots).</div>`;
          }

          let statusPill = '';
          if (isClosed) {
            statusPill = '<span class="status-pill status-closed">CLOSED</span>';
          } else if (isFull) {
            statusPill = '<span class="status-pill status-closed" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">FULL</span>';
          } else {
            statusPill = '<span class="status-pill status-available">AVAILABLE</span>';
          }

          html += `
            <article class="dash-lot-card" id="dash-lot-${lot.id}">
              <div>
                <div class="dash-lot-header">
                  <div>
                    <h4 class="dash-lot-title">${lot.name}</h4>
                    <div class="dash-lot-coords">GPS: ${lat}, ${lng}</div>
                  </div>
                  <div>
                    ${statusPill}
                  </div>
                </div>

                <!-- Capacity & Availability Metrics Bar -->
                <div class="dash-lot-spots-bar" style="margin: 12px 0;">
                  <div>
                    <div class="dash-spot-stat-num" style="color: #16a34a;">${avail}</div>
                    <div class="dash-spot-stat-lbl">Available</div>
                  </div>
                  <div>
                    <div class="dash-spot-stat-num" style="color: #ea580c;">${occupied}</div>
                    <div class="dash-spot-stat-lbl">Occupied</div>
                  </div>
                  <div>
                    <div class="dash-spot-stat-num" style="color: #b45309;">${pendingReqs.length}</div>
                    <div class="dash-spot-stat-lbl">Pending</div>
                  </div>
                  <div>
                    <div class="dash-spot-stat-num" style="color: #0f172a;">${cap}</div>
                    <div class="dash-spot-stat-lbl">Capacity</div>
                  </div>
                </div>

                <!-- Section: Pending Reservation Requests -->
                <div class="dash-lot-section" style="margin-bottom: 12px;">
                  <div class="dash-lot-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Pending Requests (${pendingReqs.length})</span>
                    ${pendingReqs.length > 0 ? '<span class="status-badge" style="background: #fef3c7; color: #b45309; font-size: 0.65rem;">Action Needed</span>' : ''}
                  </div>
                  <div class="dash-lot-requests-box">
                    ${pendingHtml}
                  </div>
                </div>

                <!-- Section: Accepted Reservations -->
                <div class="dash-lot-section">
                  <div class="dash-lot-section-title">
                    Accepted Bookings (${acceptedBookings.length}/${cap})
                  </div>
                  <div class="dash-lot-requests-box">
                    ${acceptedHtml}
                  </div>
                </div>
              </div>

              <div class="dash-card-actions" style="margin-top: 12px;">
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-manage-lot" data-lot-id="${lot.id}">
                  Manage Lot & Requests
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm ${isClosed ? 'pixel-btn-green' : 'pixel-btn-yellow'} btn-toggle-lot-status" data-id="${lot.id}" data-name="${lot.name}" data-capacity="${lot.capacity}" data-available="${lot.available_spaces}" data-status="${lot.status}">
                  ${isClosed ? 'Reopen Lot' : 'Close Lot'}
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-edit-lot-dash" data-id="${lot.id}">
                  Edit Lot
                </button>
                <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-delete-lot" data-id="${lot.id}" data-name="${lot.name}">
                  Delete
                </button>
              </div>
            </article>
          `;
        });

        container.innerHTML = html;
      }

      function renderDashboardAssignments(cars, carAssignmentMap, allReservations) {
        const tbody = document.getElementById('dash-assignments-tbody');
        if (!tbody) return;

        if (cars.length === 0) {
          tbody.innerHTML = `
            <tr>
              <td colspan="7" style="text-align: center; color: #64748b; padding: 24px;">
                No registered vehicles found. <button type="button" class="pixel-btn pixel-btn-green pixel-btn-sm" id="btn-dash-table-add-car" style="margin-left: 8px;">Add Car</button>
              </td>
            </tr>
          `;
          return;
        }

        let rowsHtml = '';
        cars.forEach(car => {
          const activeRes = carAssignmentMap[car.id];

          if (activeRes) {
            const space = activeRes.parking_spaces || {};
            const lotName = space.name || 'Flood Safe Parking Lot';
            const lat = space.latitude ? parseFloat(space.latitude).toFixed(4) : '-';
            const lng = space.longitude ? parseFloat(space.longitude).toFixed(4) : '-';
            const spotCoords = (lat !== '-' && lng !== '-') ? `${lat}, ${lng}` : 'Assigned';
            const dateStr = activeRes.created_at ? new Date(activeRes.created_at).toLocaleString() : 'Active';

            rowsHtml += `
              <tr>
                <td><strong>${car.model || 'Standard'}</strong></td>
                <td><span class="dash-car-plate" style="font-size: 0.75rem;">${car.license_plate}</span></td>
                <td><strong>${lotName}</strong></td>
                <td><span style="font-family: monospace; font-size: 0.8rem;">${spotCoords}</span></td>
                <td><span class="status-pill status-available">ACTIVE RESERVATION</span></td>
                <td style="font-size: 0.82rem; color: #64748b;">${dateStr}</td>
                <td>
                  <div style="display: flex; gap: 6px;">
                    <button type="button" class="pixel-btn pixel-btn-sm btn-view-res-dash" data-res-id="${activeRes.id}" title="View Details in My Reservations">
                      Details
                    </button>
                    ${(space.latitude && space.longitude) ? `
                      <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-blue btn-view-car-spot" data-lat="${space.latitude}" data-lng="${space.longitude}" title="View Spot on Map">
                        Map
                      </button>
                    ` : ''}
                    <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-red btn-cancel-car-res" data-id="${activeRes.id}" data-plate="${car.license_plate}" data-lot="${lotName}" title="Cancel reservation">
                      Cancel
                    </button>
                  </div>
                </td>
              </tr>
            `;
          } else {
            // Unassigned vehicle row
            rowsHtml += `
              <tr style="background: #fafafa;">
                <td><strong>${car.model || 'Standard'}</strong></td>
                <td><span class="dash-car-plate" style="font-size: 0.75rem;">${car.license_plate}</span></td>
                <td style="color: #94a3b8; font-style: italic;">None</td>
                <td style="color: #94a3b8; font-style: italic;">-</td>
                <td><span class="status-pill status-closed" style="background: #f1f5f9; color: #64748b; border-color: #cbd5e1;">NO ASSIGNMENT</span></td>
                <td style="font-size: 0.82rem; color: #94a3b8;">-</td>
                <td>
                  <button type="button" class="pixel-btn pixel-btn-sm pixel-btn-green btn-open-reserve-car" data-car-id="${car.id}" data-plate="${car.license_plate}">
                    Reserve Spot
                  </button>
                </td>
              </tr>
            `;
          }
        });

        // Also append any cancelled reservations for history awareness
        const cancelledReservations = (allReservations || []).filter(r => r.status === 'cancelled');
        cancelledReservations.forEach(r => {
          const space = r.parking_spaces || {};
          const car = r.cars || {};
          const lotName = space.name || 'Flood Safe Parking Lot';
          const plate = car.license_plate || 'Unlinked';
          const model = car.model || 'Vehicle';
          const dateStr = r.created_at ? new Date(r.created_at).toLocaleString() : '-';

          rowsHtml += `
            <tr style="opacity: 0.65; background: #fff5f5;">
              <td>${model}</td>
              <td><span class="dash-car-plate" style="font-size: 0.75rem;">${plate}</span></td>
              <td>${lotName}</td>
              <td>-</td>
              <td><span class="status-pill status-closed">CANCELLED</span></td>
              <td style="font-size: 0.82rem; color: #64748b;">${dateStr}</td>
              <td style="font-size: 0.8rem; color: #94a3b8;">Cancelled</td>
            </tr>
          `;
        });

        tbody.innerHTML = rowsHtml;
      }

      // ========================================================
      // VEHICLE MODAL CONTROLLER
      // ========================================================
      const modalCar = document.getElementById('modal-car');
      const formCarModal = document.getElementById('form-car-modal');
      const modalCarAlert = document.getElementById('modal-car-alert');
      const modalCarTitleText = document.getElementById('modal-car-title-text');
      const carModalIdInput = document.getElementById('car-modal-id');
      const carModalPlateInput = document.getElementById('car-modal-plate');
      const carModalModelInput = document.getElementById('car-modal-model');
      const btnSubmitCarModal = document.getElementById('btn-submit-car-modal');

      function openCarModal(car = null) {
        if (!modalCar) return;
        if (modalCarAlert) modalCarAlert.style.display = 'none';

        if (car && car.id) {
          if (modalCarTitleText) modalCarTitleText.textContent = 'Edit Vehicle Details';
          if (carModalIdInput) carModalIdInput.value = car.id;
          if (carModalPlateInput) carModalPlateInput.value = car.license_plate || '';
          if (carModalModelInput) carModalModelInput.value = car.model || '';
        } else {
          if (modalCarTitleText) modalCarTitleText.textContent = 'Register New Vehicle';
          if (carModalIdInput) carModalIdInput.value = '';
          if (carModalPlateInput) carModalPlateInput.value = '';
          if (carModalModelInput) carModalModelInput.value = '';
        }

        modalCar.classList.add('active');
        if (carModalPlateInput) carModalPlateInput.focus();
      }

      function closeCarModal() {
        if (modalCar) modalCar.classList.remove('active');
      }

      document.getElementById('btn-close-car-modal')?.addEventListener('click', closeCarModal);
      document.getElementById('btn-cancel-car-modal')?.addEventListener('click', closeCarModal);
      document.getElementById('btn-modal-open-3d-studio')?.addEventListener('click', () => {
        const carId = carModalIdInput ? carModalIdInput.value.trim() : '';
        closeCarModal();
        if (carId && typeof editVehicleInStudio === 'function') {
          editVehicleInStudio(carId);
        } else {
          window.location.hash = '#vehicles';
          if (typeof resetCarStudioForm === 'function') resetCarStudioForm();
        }
      });
      document.getElementById('btn-dash-add-car')?.addEventListener('click', () => {
        window.location.hash = '#vehicles';
        if (typeof resetCarStudioForm === 'function') resetCarStudioForm();
      });
      document.getElementById('btn-dash-add-car-sec')?.addEventListener('click', () => {
        window.location.hash = '#vehicles';
        if (typeof resetCarStudioForm === 'function') resetCarStudioForm();
      });
      document.addEventListener('click', (e) => {
        if (e.target.closest('.btn-dash-add-car-trigger')) {
          window.location.hash = '#vehicles';
          if (typeof resetCarStudioForm === 'function') resetCarStudioForm();
        }
      });
      document.getElementById('btn-dash-refresh')?.addEventListener('click', loadDashboard);

      formCarModal?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = carModalIdInput ? carModalIdInput.value.trim() : '';
        const license_plate = carModalPlateInput ? carModalPlateInput.value.trim() : '';
        const model = carModalModelInput ? carModalModelInput.value.trim() : '';

        if (!license_plate) {
          if (modalCarAlert) {
            modalCarAlert.className = 'alert-message error';
            modalCarAlert.innerHTML = 'Please enter a license plate.';
            modalCarAlert.style.display = 'block';
          }
          return;
        }

        try {
          if (btnSubmitCarModal) {
            btnSubmitCarModal.disabled = true;
            btnSubmitCarModal.innerHTML = '<span>⏳</span> Saving...';
          }

          const isEdit = !!id;
          const url = isEdit ? 'api/update_car.php' : 'api/create_car.php';
          const method = 'POST';
          const payload = isEdit ? { id, license_plate, model } : { license_plate, model };

          const res = await fetch(url, {
            method,
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify(payload)
          });

          const json = await res.json();
          if (res.ok && json.success) {
            closeCarModal();
            showDashboardAlert('success', `<strong>Vehicle Saved:</strong> "${license_plate}" has been saved in Supabase.`);
            await loadDashboard();
            if (typeof loadCars === 'function') await loadCars();
          } else {
            if (modalCarAlert) {
              modalCarAlert.className = 'alert-message error';
              modalCarAlert.innerHTML = `${json.error || 'Failed to save vehicle.'}`;
              modalCarAlert.style.display = 'block';
            }
          }
        } catch (err) {
          if (modalCarAlert) {
            modalCarAlert.className = 'alert-message error';
            modalCarAlert.innerHTML = `Network error: ${err.message}`;
            modalCarAlert.style.display = 'block';
          }
        } finally {
          if (btnSubmitCarModal) {
            btnSubmitCarModal.disabled = false;
            btnSubmitCarModal.innerHTML = 'Save Vehicle';
          }
        }
      });

      // ========================================================
      // SPOT RESERVATION MODAL CONTROLLER
      // ========================================================
      const modalReserve = document.getElementById('modal-reserve-car');
      const formReserveModal = document.getElementById('form-reserve-car-modal');
      const modalReserveAlert = document.getElementById('modal-reserve-alert');
      const reserveCarSelect = document.getElementById('reserve-modal-car-select');
      const reserveLotSelect = document.getElementById('reserve-modal-lot-select');
      const reserveNotesInput = document.getElementById('reserve-modal-notes');
      const btnSubmitReserveModal = document.getElementById('btn-submit-reserve-modal');

      async function openReserveModal(preselectedCarId = null, preselectedLotId = null) {
        if (!modalReserve) return;
        if (modalReserveAlert) modalReserveAlert.style.display = 'none';
        if (reserveNotesInput) reserveNotesInput.value = '';

        const noCarsNotice = document.getElementById('reserve-modal-no-cars-notice');
        if (noCarsNotice) noCarsNotice.style.display = 'none';

        // 1. Initial loading state for car dropdown
        if (reserveCarSelect) {
          reserveCarSelect.disabled = true;
          reserveCarSelect.innerHTML = '<option value="">⏳ Loading your registered cars...</option>';
        }
        if (btnSubmitReserveModal) {
          btnSubmitReserveModal.disabled = true;
        }

        // 2. Populate available parking spaces dropdown (eligible host spaces only)
        if (reserveLotSelect) {
          reserveLotSelect.innerHTML = '<option value="">-- Choose Parking Lot --</option>';
          const availableSpaces = (dashboardState.spaces || []).filter(s => s.status === 'available' && parseInt(s.available_spaces, 10) > 0 && s.owner_id !== null && s.owner_id !== undefined);

          if (availableSpaces.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'No eligible host parking spaces currently open';
            opt.disabled = true;
            reserveLotSelect.appendChild(opt);
          } else {
            availableSpaces.forEach(s => {
              const opt = document.createElement('option');
              opt.value = s.id;
              opt.textContent = `${s.name} (${s.available_spaces}/${s.capacity} spots open)`;
              if (s.id === preselectedLotId) {
                opt.selected = true;
              }
              reserveLotSelect.appendChild(opt);
            });
          }
        }

        modalReserve.classList.add('active');

        // 3. Fetch fresh cars directly from My Cars API (api/get_cars.php)
        try {
          const carsRes = await fetch('api/get_cars.php');
          const carsJson = await carsRes.json();

          if (!carsRes.ok || !carsJson.success || !Array.isArray(carsJson.data)) {
            if (reserveCarSelect) {
              reserveCarSelect.disabled = true;
              reserveCarSelect.innerHTML = '<option value="">Could not load cars</option>';
            }
            if (modalReserveAlert) {
              modalReserveAlert.className = 'alert-message error';
              modalReserveAlert.innerHTML = `Failed to load your vehicles: ${carsJson.error || 'Server error'}`;
              modalReserveAlert.style.display = 'block';
            }
            return;
          }

          const cars = carsJson.data;
          dashboardState.cars = cars; // sync state

          // Determine cars with active or pending reservations to prevent double-booking
          const activeCarIds = new Set(
            (dashboardState.reservations || [])
              .filter(r => (r.status === 'confirmed' || r.status === 'active' || r.status === 'pending') && r.car_id)
              .map(r => r.car_id)
          );

          if (cars.length === 0) {
            if (reserveCarSelect) {
              reserveCarSelect.innerHTML = '<option value="">Select your car</option>';
              reserveCarSelect.disabled = true;
            }
            if (noCarsNotice) {
              noCarsNotice.style.display = 'block';
            }
            if (btnSubmitReserveModal) {
              btnSubmitReserveModal.disabled = true;
            }
            return;
          }

          if (reserveCarSelect) {
            reserveCarSelect.innerHTML = '<option value="">Select your car</option>';
            let preselectedFound = false;

            cars.forEach(c => {
              const isAssigned = activeCarIds.has(c.id);
              const opt = document.createElement('option');
              opt.value = c.id;

              // Format: Make/Model — License Plate (e.g. "Toyota Yaris — ABC-1234")
              const modelPart = c.model && c.model.trim() ? c.model.trim() : 'Standard Vehicle';
              const platePart = c.license_plate && c.license_plate.trim() ? c.license_plate.trim() : 'No Plate';
              opt.textContent = `${modelPart} — ${platePart}${isAssigned ? ' [Active Request / Parked]' : ''}`;

              if (isAssigned) {
                opt.disabled = true;
              }

              if (c.id === preselectedCarId && !isAssigned) {
                opt.selected = true;
                preselectedFound = true;
              }
              reserveCarSelect.appendChild(opt);
            });

            reserveCarSelect.disabled = false;

            // Enable submit button only if a valid, selectable car is chosen
            if (btnSubmitReserveModal) {
              btnSubmitReserveModal.disabled = !(preselectedFound && reserveCarSelect.value);
            }
          }

        } catch (err) {
          if (reserveCarSelect) {
            reserveCarSelect.disabled = true;
            reserveCarSelect.innerHTML = '<option value="">Network error</option>';
          }
          if (modalReserveAlert) {
            modalReserveAlert.className = 'alert-message error';
            modalReserveAlert.innerHTML = `Network error while loading cars: ${err.message}`;
            modalReserveAlert.style.display = 'block';
          }
        }
      }
      window.openReserveModal = openReserveModal;

      // Handle car select change to toggle submit button disabled state
      reserveCarSelect?.addEventListener('change', () => {
        if (btnSubmitReserveModal) {
          btnSubmitReserveModal.disabled = !reserveCarSelect.value;
        }
      });

      // Handle "Add a Car in My Vehicles" link inside empty state
      document.getElementById('btn-modal-go-add-car')?.addEventListener('click', () => {
        closeReserveModal();
        window.location.hash = '#my-reservations';
        const addCarForm = document.getElementById('add-car-form');
        if (addCarForm) {
          addCarForm.style.display = 'block';
          addCarForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          const plateInput = document.getElementById('car-plate');
          if (plateInput) plateInput.focus();
        }
      });

      function closeReserveModal() {
        if (modalReserve) modalReserve.classList.remove('active');
      }

      document.getElementById('btn-close-reserve-modal')?.addEventListener('click', closeReserveModal);
      document.getElementById('btn-cancel-reserve-modal')?.addEventListener('click', closeReserveModal);

      formReserveModal?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const car_id = reserveCarSelect ? reserveCarSelect.value : '';
        const parking_space_id = reserveLotSelect ? reserveLotSelect.value : '';
        const notes = reserveNotesInput ? reserveNotesInput.value.trim() : '';

        if (!car_id) {
          if (modalReserveAlert) {
            modalReserveAlert.className = 'alert-message error';
            modalReserveAlert.innerHTML = 'Please select your registered car.';
            modalReserveAlert.style.display = 'block';
          }
          return;
        }

        if (!parking_space_id) {
          if (modalReserveAlert) {
            modalReserveAlert.className = 'alert-message error';
            modalReserveAlert.innerHTML = 'Please select an eligible host parking lot.';
            modalReserveAlert.style.display = 'block';
          }
          return;
        }

        try {
          if (btnSubmitReserveModal) {
            btnSubmitReserveModal.disabled = true;
            btnSubmitReserveModal.innerHTML = '<span>⏳</span> Submitting Request...';
          }

          const res = await fetch('api/create_reservation.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ car_id, parking_space_id, notes })
          });

          const json = await res.json();
          if (res.ok && json.success) {
            closeReserveModal();
            showDashboardAlert('success', `
              <strong>Reservation Request Submitted!</strong><br>
              Your request is pending parking space owner approval. Available spots remain open until the owner accepts.
            `);
            if (typeof showReservationAlert === 'function') {
              showReservationAlert('success', `
                <strong>Reservation Request Submitted!</strong><br>
                Your request is pending parking space owner approval.
              `);
            }
            await loadDashboard();
            if (typeof loadReservations === 'function') await loadReservations();
            if (window.parkingMap) window.parkingMap.loadParkingSpaces();
          } else {
            if (modalReserveAlert) {
              modalReserveAlert.className = 'alert-message error';
              modalReserveAlert.innerHTML = `${json.error || 'Failed to submit reservation request.'}`;
              modalReserveAlert.style.display = 'block';
            }
          }
        } catch (err) {
          if (modalReserveAlert) {
            modalReserveAlert.className = 'alert-message error';
            modalReserveAlert.innerHTML = `Network error: ${err.message}`;
            modalReserveAlert.style.display = 'block';
          }
        } finally {
          if (btnSubmitReserveModal) {
            btnSubmitReserveModal.disabled = !reserveCarSelect?.value;
            btnSubmitReserveModal.innerHTML = 'Request Reservation';
          }
        }
      });

      // Quick Toggle Lot Status (Available <-> Closed)
      async function toggleLotStatus(spaceId, currentStatus, currentCapacity) {
        if (!spaceId) return;
        const newStatus = (currentStatus === 'available') ? 'closed' : 'available';
        const space = (dashboardState.spaces || []).find(s => s.id === spaceId);
        const name = space ? space.name : 'Parking Space';
        const cap = space ? parseInt(space.capacity, 10) : parseInt(currentCapacity, 10) || 5;
        const avail = (newStatus === 'closed') ? 0 : cap;

        try {
          const res = await fetch('api/update_parking_space.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({
              id: spaceId,
              name,
              capacity: cap,
              available_spaces: avail,
              status: newStatus
            })
          });

          const json = await res.json();
          if (res.ok && json.success) {
            showDashboardAlert('success', `<strong>Lot Updated:</strong> "${name}" is now marked as <strong>${newStatus.toUpperCase()}</strong>.`);
            if (typeof loadMyParkingLots === 'function') await loadMyParkingLots();
            if (typeof loadDashboard === 'function') await loadDashboard();
            if (window.parkingMap) window.parkingMap.loadParkingSpaces();
          } else {
            showDashboardAlert('error', `Failed to update status: ${json.error || 'Server error'}`);
          }
        } catch (err) {
          showDashboardAlert('error', `Network error: ${err.message}`);
        }
      }

      // Delete Lot with Active Reservation Protection
      async function deleteParkingLot(spaceId, spaceName) {
        if (!spaceId) return;
        const confirmed = window.confirm(
          `Delete Parking Lot\n\n` +
          `Are you sure you want to delete "${spaceName}"?\n` +
          `This will permanently remove the lot from the map.`
        );
        if (!confirmed) return;

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
            showDashboardAlert('success', `<strong>Parking Lot Deleted:</strong> "${spaceName}" has been removed.`);
            await loadDashboard();
            if (window.parkingMap) window.parkingMap.loadParkingSpaces();
          } else {
            showDashboardAlert('error', `<strong>Cannot Delete Lot:</strong> ${json.error || 'Database error'}`);
          }
        } catch (err) {
          showDashboardAlert('error', `Network error: ${err.message}`);
        }
      }

      // Delegated events for cars, reservations, and profile actions
      document.body.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit-profile');
        if (editBtn) {
          openEditProfileModal(editBtn.dataset.id);
        }

        const deleteBtn = e.target.closest('.btn-delete-profile');
        if (deleteBtn) {
          deleteProfile(deleteBtn.dataset.id, deleteBtn.dataset.name);
        }

        const deleteCarBtn = e.target.closest('.btn-delete-car');
        if (deleteCarBtn) {
          deleteCar(deleteCarBtn.dataset.id, deleteCarBtn.dataset.plate);
        }

        const editCarBtn = e.target.closest('.btn-edit-car');
        if (editCarBtn) {
          if (typeof editVehicleInStudio === 'function') {
            editVehicleInStudio(editCarBtn.dataset.id);
          } else {
            openCarModal({
              id: editCarBtn.dataset.id,
              license_plate: editCarBtn.dataset.plate,
              model: editCarBtn.dataset.model
            });
          }
        }

        const openReserveCarBtn = e.target.closest('.btn-open-reserve-car');
        if (openReserveCarBtn) {
          openReserveModal(openReserveCarBtn.dataset.carId);
        }

        const cancelResBtn = e.target.closest('.btn-cancel-reservation, .btn-cancel-car-res');
        if (cancelResBtn) {
          cancelReservation(cancelResBtn.dataset.id, cancelResBtn.dataset.name || cancelResBtn.dataset.lot);
        }

        const ownerApproveBtn = e.target.closest('.btn-owner-approve-res');
        if (ownerApproveBtn) {
          handleOwnerReservationAction(
            ownerApproveBtn.dataset.id,
            ownerApproveBtn.dataset.action,
            ownerApproveBtn.dataset.space,
            ownerApproveBtn.dataset.plate,
            ownerApproveBtn
          );
        }

        const manageLotBtn = e.target.closest('.btn-manage-lot, .btn-select-lot, .clickable-lot-card');
        if (manageLotBtn) {
          // If clicked inside another button or link inside the card, let that specific handler run
          if (e.target.closest('button, a') && !e.target.closest('.btn-manage-lot')) {
            // let default or specific sub-button handler execute
          } else {
            const lotId = manageLotBtn.dataset.lotId || manageLotBtn.dataset.id;
            if (lotId) {
              window.location.hash = '#my-spaces';
              if (typeof selectLotDetail === 'function') {
                selectLotDetail(lotId);
              }
            }
          }
        }

        const backToLotsBtn = e.target.closest('#btn-back-to-my-lots, .btn-back-to-lots');
        if (backToLotsBtn) {
          if (typeof backToLotsList === 'function') {
            backToLotsList();
          }
        }

        const openOfferBtn = e.target.closest('#btn-open-offer-lot, #btn-empty-offer-lot, #btn-toggle-offer-form');
        if (openOfferBtn) {
          const offerContainer = document.getElementById('offer-parking-form-container');
          if (offerContainer) {
            const isHidden = (offerContainer.style.display === 'none' || !offerContainer.style.display);
            offerContainer.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
              offerContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
              const nameInput = document.getElementById('offer-name');
              if (nameInput) nameInput.focus();
            }
          }
        }

        const closeOfferBtn = e.target.closest('#btn-close-offer-form');
        if (closeOfferBtn) {
          const offerContainer = document.getElementById('offer-parking-form-container');
          if (offerContainer) {
            offerContainer.style.display = 'none';
          }
        }

        const refreshMyLotsBtn = e.target.closest('#btn-refresh-my-lots, #btn-refresh-owner-requests');
        if (refreshMyLotsBtn) {
          if (typeof loadMyParkingLots === 'function') {
            loadMyParkingLots();
          }
        }

        const toggleLotBtn = e.target.closest('.btn-toggle-lot-status');
        if (toggleLotBtn) {
          toggleLotStatus(toggleLotBtn.dataset.id, toggleLotBtn.dataset.status, toggleLotBtn.dataset.capacity);
        }

        const editLotDashBtn = e.target.closest('.btn-edit-lot-dash');
        if (editLotDashBtn) {
          if (window.parkingMap && typeof window.parkingMap.openEditModalById === 'function') {
            window.parkingMap.openEditModalById(editLotDashBtn.dataset.id);
          } else {
            window.location.hash = '#map';
          }
        }

        const deleteLotBtn = e.target.closest('.btn-delete-lot');
        if (deleteLotBtn) {
          deleteParkingLot(deleteLotBtn.dataset.id, deleteLotBtn.dataset.name);
        }

        const viewCarSpotBtn = e.target.closest('.btn-view-car-spot, .btn-view-reservation-map');
        if (viewCarSpotBtn) {
          const lat = parseFloat(viewCarSpotBtn.dataset.lat);
          const lng = parseFloat(viewCarSpotBtn.dataset.lng);
          window.location.hash = '#map';
          if (window.parkingMap && window.parkingMap.map) {
            window.parkingMap.map.flyTo([lat, lng], 15, { duration: 1.0 });
          }
        }

        const tableAddCarBtn = e.target.closest('#btn-dash-table-add-car');
        if (tableAddCarBtn) {
          openCarModal(null);
        }

        const selectResBtn = e.target.closest('.btn-select-res, .clickable-res-card, .my-res-card');
        if (selectResBtn) {
          // If clicked inside another button or link inside the card, let that specific handler run
          if (e.target.closest('button, a') && !e.target.closest('.btn-select-res')) {
            // let default or specific handler execute
          } else {
            const resId = selectResBtn.dataset.resId || selectResBtn.dataset.id;
            if (resId && typeof selectReservationDetail === 'function') {
              selectReservationDetail(resId);
            }
          }
        }

        // Reservation filter buttons
        const resFilterBtn = e.target.closest('#my-res-filter-controls .filter-btn');
        if (resFilterBtn) {
          const f = resFilterBtn.dataset.filter;
          if (f && typeof setReservationFilter === 'function') {
            setReservationFilter(f);
          }
        }

        // Reservation summary metric pills as quick filter triggers
        const resMetricPill = e.target.closest('#my-res-summary-metrics .my-res-metric-item');
        if (resMetricPill && typeof setReservationFilter === 'function') {
          const numId = resMetricPill.querySelector('.my-res-metric-num')?.id;
          if (numId === 'res-metric-total') setReservationFilter('all');
          else if (numId === 'res-metric-pending') setReservationFilter('pending');
          else if (numId === 'res-metric-confirmed') setReservationFilter('confirmed');
          else if (numId === 'res-metric-history') setReservationFilter('past');
        }

        const backToResBtn = e.target.closest('#btn-back-to-my-reservations, .btn-back-to-reservations');
        if (backToResBtn) {
          if (typeof backToReservationsList === 'function') {
            backToReservationsList();
          }
        }

        const refreshMyResBtn = e.target.closest('#btn-refresh-my-reservations');
        if (refreshMyResBtn) {
          if (typeof loadReservations === 'function') {
            loadReservations();
          }
        }

        const viewResDashBtn = e.target.closest('.btn-view-res-dash');
        if (viewResDashBtn) {
          const resId = viewResDashBtn.dataset.resId || viewResDashBtn.dataset.id;
          if (resId) {
            window.location.hash = '#my-reservations';
            if (typeof selectReservationDetail === 'function') {
              selectReservationDetail(resId);
            }
          }
        }
      });
    });
  </script>

</body>
</html>
