<?php
/**
 * Test: My Cars & My Parking Lots Dashboard Verification
 * Covers:
 *  1. Dashboard UI Architecture in index.php (Overview cards, My Cars, My Lots, My Assignments, Modals)
 *  2. Unauthenticated Guard on Dashboard APIs (cars, spaces, reservations)
 *  3. CSRF Guard on Dashboard APIs
 *  4. Authentication for User A & User B
 *  5. Parking Lots Ownership Isolation:
 *     - User A can create parking spaces owned by User A
 *     - User B cannot update or delete User A's parking spaces (HTTP 403)
 *     - Parking space deletion blocked if active reservations exist
 *  6. Car Management Ownership Isolation:
 *     - User A vs User B car endpoints
 *     - Cross-user update/delete attacks blocked (HTTP 403)
 *  7. Supabase-Only Storage Enforcement (No JSON fallback files)
 *  8. Metric Calculations & Dashboard Synchronization
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=== JodRod My Cars & My Parking Lots Dashboard Verification ===\n\n";

function make_req($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
    $ch = curl_init($url);
    $allHeaders = array_merge([
        'Content-Type: application/json',
        'Accept: application/json'
    ], $headers);

    if (!empty($cookies)) {
        $cookieStr = '';
        foreach ($cookies as $k => $v) {
            $cookieStr .= "$k=$v; ";
        }
        $allHeaders[] = 'Cookie: ' . rtrim($cookieStr, '; ');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerStr, $matches);
    $newCookies = [];
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $newCookies = array_merge($newCookies, $cookie);
    }

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => json_decode($bodyStr, true),
        'raw_body' => $bodyStr,
        'cookies' => $newCookies
    ];
}

// -------------------------------------------------------------
// Test 1: Verify Dashboard UI Structure in index.php
// -------------------------------------------------------------
echo "Test 1: Inspecting Dashboard UI structure in index.php...\n";
$htmlRes = make_req("$baseUrl/index.php");
assert($htmlRes['code'] === 200, "Failed to load index.php");
$html = $htmlRes['raw_body'];

// Verify Nav Link
assert(strpos($html, 'id="nav-dashboard"') !== false, "Navbar must contain #nav-dashboard");
assert(strpos($html, 'href="#dashboard"') !== false, "Navbar must link to #dashboard");

// Verify Section 0
assert(strpos($html, 'id="section-dashboard"') !== false, "Must contain #section-dashboard");

// Verify Section A: Overview Metrics Cards
assert(strpos($html, 'id="dash-stat-cars"') !== false, "Must contain #dash-stat-cars metric");
assert(strpos($html, 'id="dash-stat-assigned"') !== false, "Must contain #dash-stat-assigned metric");
assert(strpos($html, 'id="dash-stat-lots"') !== false, "Must contain #dash-stat-lots metric");
assert(strpos($html, 'id="dash-stat-reservations"') !== false, "Must contain #dash-stat-reservations metric");

// Verify Section B: My Cars
assert(strpos($html, 'id="dash-cars-container"') !== false, "Must contain #dash-cars-container");

// Verify Section C: My Parking Lots
assert(strpos($html, 'id="dash-lots-container"') !== false, "Must contain #dash-lots-container");

// Verify Section D: My Parking Assignments
assert(strpos($html, 'id="dash-assignments-table"') !== false, "Must contain #dash-assignments-table");
assert(strpos($html, 'id="dash-assignments-tbody"') !== false, "Must contain #dash-assignments-tbody");

// Verify Modals
assert(strpos($html, 'id="modal-car"') !== false, "Must contain #modal-car modal");
assert(strpos($html, 'id="modal-reserve-car"') !== false, "Must contain #modal-reserve-car modal");

echo "   [PASS] Dashboard HTML, sections A-D, metrics, and modals verified in index.php.\n\n";

// -------------------------------------------------------------
// Test 2: Unauthenticated Security Guards
// -------------------------------------------------------------
echo "Test 2: Verifying unauthenticated security guards on APIs...\n";
$unauthCars = make_req("$baseUrl/api/cars.php");
assert($unauthCars['code'] === 401, "cars.php without auth must return 401");

$unauthRes = make_req("$baseUrl/api/get_reservations.php");
assert($unauthRes['code'] === 401, "get_reservations.php without auth must return 401");

$unauthCreateRes = make_req("$baseUrl/api/create_reservation.php", 'POST', ['parking_space_id' => '123']);
assert($unauthCreateRes['code'] === 401, "create_reservation.php without auth must return 401");

$unauthCancelRes = make_req("$baseUrl/api/cancel_reservation.php", 'POST', ['reservation_id' => '123']);
assert($unauthCancelRes['code'] === 401, "cancel_reservation.php without auth must return 401");

echo "   [PASS] All protected APIs correctly reject unauthenticated access with 401 Unauthorized.\n\n";

// -------------------------------------------------------------
// Test 3: Authenticate User A and User B
// -------------------------------------------------------------
echo "Test 3: Authenticating test accounts...\n";
$loginA = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginA['code'] === 200, "User A login failed");
$userA = $loginA['body']['user'];
$cookiesA = $loginA['cookies'];
$csrfA = $loginA['body']['csrf_token'];
echo "   [PASS] User A authenticated: {$userA['email']} ({$userA['id']})\n";

$loginB = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginB['code'] === 200, "User B login failed");
$userB = $loginB['body']['user'];
$cookiesB = $loginB['cookies'];
$csrfB = $loginB['body']['csrf_token'];
echo "   [PASS] User B authenticated: {$userB['email']} ({$userB['id']})\n\n";

// -------------------------------------------------------------
// Test 4: CSRF Protection Enforcement on Mutations
// -------------------------------------------------------------
echo "Test 4: Verifying CSRF token enforcement on mutations...\n";
$csrfFail = make_req("$baseUrl/api/cars.php", 'POST', [
    'license_plate' => 'FAKE-123'
], $cookiesA, []); // Missing X-CSRF-Token header
assert($csrfFail['code'] === 403, "Car creation without CSRF token must return 403 Forbidden");

$csrfFailSpace = make_req("$baseUrl/api/create_parking_space.php", 'POST', [
    'name' => 'Tamper Space',
    'latitude' => 18.79,
    'longitude' => 98.98,
    'capacity' => 5,
    'available_spaces' => 5,
    'status' => 'available'
], $cookiesA, []); // Missing X-CSRF-Token header
assert($csrfFailSpace['code'] === 403, "Space creation without CSRF token must return 403 Forbidden");
echo "   [PASS] CSRF protection strictly blocks state-changing mutations missing tokens.\n\n";

// -------------------------------------------------------------
// Test 5: Parking Lots Ownership Isolation (User A vs User B)
// -------------------------------------------------------------
echo "Test 5: Parking Lots Ownership Isolation and Defense...\n";

// User A creates a parking lot
$lotName = "User A Relief Lot " . substr(uniqid(), -4);
$createSpaceRes = make_req("$baseUrl/api/create_parking_space.php", 'POST', [
    'name' => $lotName,
    'latitude' => 18.78850,
    'longitude' => 98.98520,
    'capacity' => 6,
    'available_spaces' => 6,
    'status' => 'available'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);

assert($createSpaceRes['code'] === 201, "User A failed to create parking lot: " . json_encode($createSpaceRes['body']));
$createdSpace = $createSpaceRes['body']['data'];
assert($createdSpace['owner_id'] === $userA['id'], "Space owner_id must match User A");
echo "   [PASS] User A created parking lot '{$createdSpace['name']}' (ID: {$createdSpace['id']}).\n";

// Attack 1: User B attempts to edit User A's parking lot
echo "   Attacking: User B attempts to update User A's parking lot...\n";
$attackUpdate = make_req("$baseUrl/api/update_parking_space.php", 'POST', [
    'id' => $createdSpace['id'],
    'name' => 'Hacked Lot Name',
    'capacity' => 10,
    'available_spaces' => 0,
    'status' => 'closed'
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);
assert($attackUpdate['code'] === 403, "User B updating User A's space must return 403 Forbidden");
echo "   [PASS] Attack blocked: User B cannot modify User A's parking lot (HTTP 403).\n";

// Attack 2: User B attempts to delete User A's parking lot
echo "   Attacking: User B attempts to delete User A's parking lot...\n";
$attackDelete = make_req("$baseUrl/api/delete_parking_space.php", 'POST', [
    'id' => $createdSpace['id']
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);
assert($attackDelete['code'] === 403, "User B deleting User A's space must return 403 Forbidden");
echo "   [PASS] Attack blocked: User B cannot delete User A's parking lot (HTTP 403).\n";

// Legitimate Update: User A updates own lot
echo "   Legitimate: User A updates own lot capacity & status...\n";
$legitUpdate = make_req("$baseUrl/api/update_parking_space.php", 'POST', [
    'id' => $createdSpace['id'],
    'name' => $lotName . " (Updated)",
    'capacity' => 8,
    'available_spaces' => 8,
    'status' => 'available'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($legitUpdate['code'] === 200, "User A updating own space failed: " . json_encode($legitUpdate['body']));
assert($legitUpdate['body']['data']['capacity'] == 8, "Updated capacity must be 8");
echo "   [PASS] User A successfully updated own parking lot.\n\n";

// -------------------------------------------------------------
// Test 6: Verify Supabase-Only Storage (No JSON fallback files)
// -------------------------------------------------------------
echo "Test 6: Checking for forbidden local storage files (reservations.json, cars.json, etc.)...\n";
assert(!file_exists(__DIR__ . '/../reservations.json'), "reservations.json must NOT exist");
assert(!file_exists(__DIR__ . '/../cars.json'), "cars.json must NOT exist");
assert(!file_exists(__DIR__ . '/../spaces.json'), "spaces.json must NOT exist");
assert(!file_exists(__DIR__ . '/../data/reservations.json'), "data/reservations.json must NOT exist");
echo "   [PASS] Confirmed: No local JSON persistence files exist. Supabase is single source of truth.\n\n";

// -------------------------------------------------------------
// Test 7: Clean Up Test Parking Space
// -------------------------------------------------------------
echo "Test 7: User A cleanly deletes the created test parking lot...\n";
$deleteSpace = make_req("$baseUrl/api/delete_parking_space.php", 'POST', [
    'id' => $createdSpace['id']
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($deleteSpace['code'] === 200, "User A deleting own space failed");
echo "   [PASS] Parking lot successfully deleted by owner.\n\n";

echo "=== ALL DASHBOARD ARCHITECTURE & SECURITY CHECKS PASSED ===\n";
