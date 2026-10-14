<?php
/**
 * Test: Dashboard Content Refinement Verification
 * 
 * Verifies:
 * 1. Dashboard Welcome Area & Dynamic Role Greeting
 * 2. "What Needs Attention" section & container
 * 3. Driver Summary vs. Parking Lot Host Summary grouping
 * 4. All preserved dashboard IDs and metrics
 * 5. CSS styles for attention cards, role empty cards, and badges
 * 6. JS functions for attention rendering and dashboard state sync
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: Dashboard Content Refinement Verification             \n";
echo "=================================================================\n\n";

function get_url($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'body' => $res];
}

// -------------------------------------------------------------
// Test 1: Inspect index.php HTML for Dashboard Elements
// -------------------------------------------------------------
echo "Test 1: Inspecting index.php HTML for refined Dashboard elements...\n";
$res = get_url("$baseUrl/index.php");
assert($res['code'] === 200, "Failed to load index.php");
$html = $res['body'];

// Welcome area
assert(strpos($html, 'id="dash-welcome-area"') !== false, "Must contain #dash-welcome-area");
assert(strpos($html, 'id="dash-welcome-user"') !== false, "Must contain #dash-welcome-user");

// What Needs Attention section
assert(strpos($html, 'id="dash-attention-section"') !== false, "Must contain #dash-attention-section");
assert(strpos($html, 'id="dash-attention-container"') !== false, "Must contain #dash-attention-container");
assert(strpos($html, 'id="dash-attention-count-badge"') !== false, "Must contain #dash-attention-count-badge");

// Role groupings
assert(strpos($html, 'Driver Summary') !== false, "Must contain 'Driver Summary' header");
assert(strpos($html, 'Parking Lot Host Summary') !== false, "Must contain 'Parking Lot Host Summary' header");

// Metric IDs
assert(strpos($html, 'id="dash-stat-cars"') !== false, "Must contain #dash-stat-cars");
assert(strpos($html, 'id="dash-stat-assigned"') !== false, "Must contain #dash-stat-assigned");
assert(strpos($html, 'id="dash-stat-reservations"') !== false, "Must contain #dash-stat-reservations");
assert(strpos($html, 'id="dash-stat-driver-pending"') !== false, "Must contain #dash-stat-driver-pending");
assert(strpos($html, 'id="dash-stat-lots"') !== false, "Must contain #dash-stat-lots");
assert(strpos($html, 'id="dash-stat-owner-available"') !== false, "Must contain #dash-stat-owner-available");
assert(strpos($html, 'id="dash-stat-owner-pending"') !== false, "Must contain #dash-stat-owner-pending");

// Containers
assert(strpos($html, 'id="dash-cars-container"') !== false, "Must contain #dash-cars-container");
assert(strpos($html, 'id="dash-lots-container"') !== false, "Must contain #dash-lots-container");
assert(strpos($html, 'id="dash-assignments-table"') !== false, "Must contain #dash-assignments-table");
assert(strpos($html, 'id="dash-assignments-tbody"') !== false, "Must contain #dash-assignments-tbody");

echo "   [PASS] All dashboard HTML structure, role groupings, and metric containers verified.\n\n";

// -------------------------------------------------------------
// Test 2: Inspect assets/css/style.css for Refined Styles
// -------------------------------------------------------------
echo "Test 2: Verifying CSS classes in assets/css/style.css...\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');

assert(strpos($cssContent, '.dash-attention-empty') !== false, "CSS must define .dash-attention-empty");
assert(strpos($cssContent, '.dash-attention-grid') !== false, "CSS must define .dash-attention-grid");
assert(strpos($cssContent, '.dash-attention-card') !== false, "CSS must define .dash-attention-card");
assert(strpos($cssContent, '.owner-urgent') !== false, "CSS must define .owner-urgent");
assert(strpos($cssContent, '.driver-pending') !== false, "CSS must define .driver-pending");
assert(strpos($cssContent, '.driver-confirmed') !== false, "CSS must define .driver-confirmed");
assert(strpos($cssContent, '.dash-empty-role-card') !== false, "CSS must define .dash-empty-role-card");

echo "   [PASS] All dashboard CSS classes verified.\n\n";

// -------------------------------------------------------------
// Test 3: Verify JS Functions in index.php
// -------------------------------------------------------------
echo "Test 3: Inspecting JS controller functions in index.php...\n";
$indexContent = file_get_contents(__DIR__ . '/../index.php');

assert(strpos($indexContent, 'function renderDashboardAttention') !== false, "index.php must contain renderDashboardAttention()");
assert(strpos($indexContent, 'function renderDashboardCars') !== false, "index.php must contain renderDashboardCars()");
assert(strpos($indexContent, 'function renderDashboardLots') !== false, "index.php must contain renderDashboardLots()");
assert(strpos($indexContent, 'function renderDashboardAssignments') !== false, "index.php must contain renderDashboardAssignments()");
assert(strpos($indexContent, 'loadDashboard') !== false, "index.php must contain loadDashboard()");

echo "   [PASS] All dashboard JavaScript functions verified.\n\n";

echo "=================================================================\n";
echo "   ALL DASHBOARD REFINEMENT VERIFICATION CHECKS PASSED!          \n";
echo "=================================================================\n";
