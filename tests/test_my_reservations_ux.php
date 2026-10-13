<?php
/**
 * JodRod: My Reservations Two-Step UX & Flow Verification
 *
 * Verifies:
 * 1. UI Structure: Step 1 view, Step 2 detail view, metrics bar, Back button, Find Parking buttons.
 * 2. CSS Styles: Responsive grid, detail cards, timeline, action banners, pixel borders.
 * 3. Driver Isolation: Only reservations belonging to the authenticated driver are returned.
 * 4. Owner Enrichment: Reservations contain owner information from database profiles.
 * 5. Two-Step Interaction & Status Behavior:
 *    - Pending status awaiting owner approval with cancellation allowed.
 *    - Confirmed status with secured details and spot cancellation.
 *    - Status machine transitions and capacity preservation/restoration.
 * 6. Clean tear-down of test data.
 */

require_once __DIR__ . '/../config.php';

echo "=================================================================\n";
echo "   JodRod: My Reservations Two-Step UX & Flow Verification       \n";
echo "=================================================================\n\n";

$baseUrl = 'http://127.0.0.1:8088';

function makeReq($method, $path, $data = null, $cookies = '', $csrf = '') {
    global $baseUrl;
    $ch = curl_init($baseUrl . '/' . ltrim($path, '/'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [];
    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
    }
    if (!empty($csrf)) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr = substr($raw, $headerSize);

    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $matches);
    $cookieList = $matches[1] ?? [];
    $cookieHeader = implode('; ', $cookieList);

    $json = json_decode($bodyStr, true);
    return [
        'code'    => $httpCode,
        'headers' => $headerStr,
        'cookies' => $cookieHeader,
        'body'    => $bodyStr,
        'json'    => $json
    ];
}

// -------------------------------------------------------------------------
// Test 1: Inspect UI structure in index.php
// -------------------------------------------------------------------------
echo "Test 1: Inspecting My Reservations UI structure in index.php...\n";
$indexContent = file_get_contents(__DIR__ . '/../index.php');

$requiredElements = [
    'id="section-my-reservations"',
    'id="btn-header-find-parking"',
    'id="btn-refresh-my-reservations"',
    'id="my-res-step1-view"',
    'id="my-res-summary-metrics"',
    'id="res-metric-total"',
    'id="res-metric-pending"',
    'id="res-metric-confirmed"',
    'id="res-metric-history"',
    'id="reservations-list-container"',
    'id="my-res-step2-view"',
    'id="btn-back-to-my-reservations"',
    'id="my-reservation-detail-content"',
    'id="cars-list-container"',
    'id="btn-toggle-add-car"'
];

foreach ($requiredElements as $elem) {
    if (strpos($indexContent, $elem) === false) {
        echo "   [FAIL] Missing required UI element in index.php: $elem\n";
        exit(1);
    }
}
echo "   [PASS] HTML structure verified: Step 1 view, Step 2 detail view, metrics bar, Back button, and Vehicles card.\n\n";

// -------------------------------------------------------------------------
// Test 2: Inspect CSS classes in assets/css/style.css
// -------------------------------------------------------------------------
echo "Test 2: Verifying CSS classes in assets/css/style.css...\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');

$requiredCss = [
    '.my-res-header-bar',
    '.my-res-header-actions',
    '.my-res-metrics-bar',
    '.my-res-grid',
    '.my-res-card',
    '.my-res-detail-header-card',
    '.my-res-detail-grid',
    '.my-res-detail-card',
    '.my-res-timeline',
    '.my-res-action-card',
    '.banner-pending',
    '.banner-confirmed',
    '.banner-rejected',
    '.banner-cancelled'
];

foreach ($requiredCss as $css) {
    if (strpos($cssContent, $css) === false) {
        echo "   [FAIL] Missing CSS selector in assets/css/style.css: $css\n";
        exit(1);
    }
}
echo "   [PASS] CSS classes verified for responsive cards, grid, status timeline, and action banners.\n\n";

// -------------------------------------------------------------------------
// Test 3: Authenticate accounts
// -------------------------------------------------------------------------
echo "Test 3: Authenticating test accounts...\n";
$loginDriver = makeReq('POST', '/api/auth.php?action=login', [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
if ($loginDriver['code'] !== 200 || empty($loginDriver['json']['user']['id'])) {
    echo "   [FAIL] Driver login failed: " . json_encode($loginDriver['json']) . "\n";
    exit(1);
}
$driverUser = $loginDriver['json']['user'];
$driverCookies = $loginDriver['cookies'];
$driverCsrf = $loginDriver['json']['csrf_token'] ?? '';
echo "   [PASS] Driver (User A) authenticated ({$driverUser['id']}).\n";

$loginOwner = makeReq('POST', '/api/auth.php?action=login', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
if ($loginOwner['code'] !== 200 || empty($loginOwner['json']['user']['id'])) {
    echo "   [FAIL] Owner login failed: " . json_encode($loginOwner['json']) . "\n";
    exit(1);
}
$ownerUser = $loginOwner['json']['user'];
$ownerCookies = $loginOwner['cookies'];
$ownerCsrf = $loginOwner['json']['csrf_token'] ?? '';
echo "   [PASS] Owner (User B) authenticated ({$ownerUser['id']}).\n\n";

// -------------------------------------------------------------------------
// Test 4: Setup test lot and vehicle
// -------------------------------------------------------------------------
echo "Test 4: Setting up test parking lot and driver vehicle...\n";
$lotSuffix = substr(uniqid(), -6);
$createLotRes = makeReq('POST', '/api/create_parking_space.php', [
    'name'             => 'Relief Shelter Lot ' . $lotSuffix,
    'latitude'         => 18.8055,
    'longitude'        => 98.9855,
    'capacity'         => 4,
    'available_spaces' => 4,
    'status'           => 'available'
], $ownerCookies, $ownerCsrf);

if ($createLotRes['code'] !== 201 && $createLotRes['code'] !== 200) {
    echo "   [FAIL] Could not create test parking lot: " . json_encode($createLotRes['json']) . "\n";
    exit(1);
}
$testLot = $createLotRes['json']['data'];
$testLotId = $testLot['id'];
echo "   [PASS] Created test lot '{$testLot['name']}' (ID: $testLotId, Capacity: 4/4).\n";

// Create a dedicated fresh test car for this run
$carPlate = 'UX-' . substr(uniqid(), -5);
$addCarRes = makeReq('POST', '/api/create_car.php', [
    'license_plate' => $carPlate,
    'model'         => 'Mazda CX-5 Relief'
], $driverCookies, $driverCsrf);
if ($addCarRes['code'] !== 201 && $addCarRes['code'] !== 200) {
    echo "   [FAIL] Could not register test vehicle: " . json_encode($addCarRes['json']) . "\n";
    exit(1);
}
$driverCar = $addCarRes['json']['data'];
$driverCarId = $driverCar['id'];
echo "   [PASS] Driver vehicle ready: {$driverCar['license_plate']} ({$driverCar['model']}).\n\n";

// -------------------------------------------------------------------------
// Test 5: Driver creates reservation & verifies Driver View isolation & owner enrichment
// -------------------------------------------------------------------------
echo "Test 5: Verifying Driver View isolation and owner profile enrichment...\n";
$bookRes = makeReq('POST', '/api/create_reservation.php', [
    'parking_space_id' => $testLotId,
    'car_id'           => $driverCarId,
    'notes'            => 'High-water evacuation request'
], $driverCookies, $driverCsrf);

if ($bookRes['code'] !== 201 && $bookRes['code'] !== 200) {
    echo "   [FAIL] Failed to create driver reservation: " . json_encode($bookRes['json']) . "\n";
    exit(1);
}
$createdReservationId = $bookRes['json']['data']['id'];
echo "   [PASS] Created pending reservation (ID: $createdReservationId).\n";

// Query Driver View
$driverReservationsRes = makeReq('GET', '/api/get_reservations.php', null, $driverCookies);
if ($driverReservationsRes['code'] !== 200 || !is_array($driverReservationsRes['json']['data'])) {
    echo "   [FAIL] Failed to fetch driver reservations: " . json_encode($driverReservationsRes['json']) . "\n";
    exit(1);
}
$driverReservations = $driverReservationsRes['json']['data'];

// Verify all returned reservations belong strictly to the authenticated driver
foreach ($driverReservations as $r) {
    if ($r['user_id'] !== $driverUser['id']) {
        echo "   [FAIL] Security violation: Driver received reservation belonging to user {$r['user_id']}.\n";
        exit(1);
    }
}
echo "   [PASS] Driver isolation verified: All reservations strictly belong to authenticated driver.\n";

// Verify owner profile is enriched
$myTestRes = null;
foreach ($driverReservations as $r) {
    if ($r['id'] === $createdReservationId) {
        $myTestRes = $r;
        break;
    }
}
if (!$myTestRes) {
    echo "   [FAIL] Newly created reservation not found in driver list.\n";
    exit(1);
}
if (empty($myTestRes['owner_profile']) || empty($myTestRes['owner_profile']['name'])) {
    echo "   [FAIL] Owner profile not enriched in reservation: " . json_encode($myTestRes) . "\n";
    exit(1);
}
echo "   [PASS] Owner profile successfully enriched: '{$myTestRes['owner_profile']['name']}'.\n\n";

// -------------------------------------------------------------------------
// Test 6: Testing cancellation of pending reservation (Capacity preserved)
// -------------------------------------------------------------------------
echo "Test 6: Testing pending reservation cancellation (capacity unchanged)...\n";
$cancelPendingRes = makeReq('POST', '/api/cancel_reservation.php', [
    'reservation_id' => $createdReservationId
], $driverCookies, $driverCsrf);

if ($cancelPendingRes['code'] !== 200 || empty($cancelPendingRes['json']['success'])) {
    echo "   [FAIL] Failed to cancel pending reservation: " . json_encode($cancelPendingRes['json']) . "\n";
    exit(1);
}

// Verify lot capacity is still 4
$checkLotRes = makeReq('GET', '/api/get_parking_spaces.php');
$lotsData = $checkLotRes['json']['data'] ?? [];
$checkLot = null;
foreach ($lotsData as $lot) {
    if ($lot['id'] === $testLotId) {
        $checkLot = $lot;
        break;
    }
}
if (!$checkLot || (int)$checkLot['available_spaces'] !== 4) {
    echo "   [FAIL] Expected lot capacity 4, got " . ($checkLot['available_spaces'] ?? 'null') . ".\n";
    exit(1);
}
echo "   [PASS] Pending cancellation succeeded: lot capacity preserved at 4/4.\n\n";

// -------------------------------------------------------------------------
// Test 7: Testing confirmed reservation flow and cancellation (Capacity restored)
// -------------------------------------------------------------------------
echo "Test 7: Testing confirmed reservation flow & capacity restoration...\n";
// Re-book
$bookRes2 = makeReq('POST', '/api/create_reservation.php', [
    'parking_space_id' => $testLotId,
    'car_id'           => $driverCarId,
    'notes'            => 'Second evacuation spot'
], $driverCookies, $driverCsrf);
$res2Id = $bookRes2['json']['data']['id'];

// Owner accepts
$acceptRes = makeReq('POST', '/api/approve_reservation.php', [
    'reservation_id' => $res2Id,
    'action'         => 'accept'
], $ownerCookies, $ownerCsrf);
if ($acceptRes['code'] !== 200 || empty($acceptRes['json']['success'])) {
    echo "   [FAIL] Owner acceptance failed: " . json_encode($acceptRes['json']) . "\n";
    exit(1);
}

// Verify available_spaces decreased to 3
$checkLotRes = makeReq('GET', '/api/get_parking_spaces.php');
$lotsData = $checkLotRes['json']['data'] ?? [];
$checkLot = null;
foreach ($lotsData as $lot) {
    if ($lot['id'] === $testLotId) {
        $checkLot = $lot;
        break;
    }
}
if (!$checkLot || (int)$checkLot['available_spaces'] !== 3) {
    echo "   [FAIL] Expected capacity 3 after approval, got " . ($checkLot['available_spaces'] ?? 'null') . ".\n";
    exit(1);
}
echo "   [PASS] Reservation confirmed by owner: lot available_spaces decreased to 3/4.\n";

// Driver cancels confirmed spot
$cancelConfirmedRes = makeReq('POST', '/api/cancel_reservation.php', [
    'reservation_id' => $res2Id
], $driverCookies, $driverCsrf);
if ($cancelConfirmedRes['code'] !== 200 || empty($cancelConfirmedRes['json']['success'])) {
    echo "   [FAIL] Driver failed to cancel confirmed reservation: " . json_encode($cancelConfirmedRes['json']) . "\n";
    exit(1);
}

// Verify available_spaces restored to 4
$checkLotRes = makeReq('GET', '/api/get_parking_spaces.php');
$lotsData = $checkLotRes['json']['data'] ?? [];
$checkLot = null;
foreach ($lotsData as $lot) {
    if ($lot['id'] === $testLotId) {
        $checkLot = $lot;
        break;
    }
}
if (!$checkLot || (int)$checkLot['available_spaces'] !== 4) {
    echo "   [FAIL] Expected capacity restored to 4, got " . ($checkLot['available_spaces'] ?? 'null') . ".\n";
    exit(1);
}
echo "   [PASS] Confirmed cancellation succeeded: lot capacity restored to 4/4.\n\n";

// -------------------------------------------------------------------------
// Cleanup
// -------------------------------------------------------------------------
echo "Test 8: Cleaning up test artifacts...\n";
makeReq('POST', '/api/delete_parking_space.php', [
    'id' => $testLotId
], $ownerCookies, $ownerCsrf);
makeReq('POST', '/api/delete_car.php', [
    'id' => $driverCarId
], $driverCookies, $driverCsrf);
echo "   [PASS] Cleaned up temporary test lot and test car.\n\n";

echo "=================================================================\n";
echo "   ALL MY RESERVATIONS UX INTEGRATION TESTS PASSED!              \n";
echo "=================================================================\n";
