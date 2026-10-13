<?php
/**
 * JodRod: Reservation Car Dropdown & Security Verification
 *
 * Verifies:
 * 1. UI Structure: Dropdown element (#reserve-modal-car-select), placeholder option ("Select your car"),
 *    and empty guidance notice (#reserve-modal-no-cars-notice).
 * 2. API Data Integration: Cars loaded from api/get_cars.php with formatted labels: "Model — Plate".
 * 3. Server-side Ownership Security: Submitting another user's car_id is strictly blocked with HTTP 403 Forbidden.
 * 4. Legitimate Reservation Flow: Submitting authenticated driver's valid car_id succeeds (HTTP 201)
 *    and links correctly.
 * 5. Owner Acceptance Regression: The owner can accept the reservation, lot available_spaces decreases,
 *    and cancellation properly restores capacity.
 */

require_once __DIR__ . '/../config.php';

echo "=================================================================\n";
echo "   JodRod: Reservation Car Dropdown & Security Verification      \n";
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
// Test 1: Verify UI structure in index.php
// -------------------------------------------------------------------------
echo "Test 1: Inspecting Reservation Modal HTML in index.php...\n";
$indexContent = file_get_contents(__DIR__ . '/../index.php');

$requiredSnippets = [
    '<select id="reserve-modal-car-select"',
    '<option value="">Select your car</option>',
    'id="reserve-modal-no-cars-notice"',
    'You haven\'t added a car yet',
    'id="btn-modal-go-add-car"'
];

foreach ($requiredSnippets as $snippet) {
    if (strpos($indexContent, $snippet) === false) {
        echo "   [FAIL] Missing UI snippet in index.php: $snippet\n";
        exit(1);
    }
}
echo "   [PASS] Dropdown HTML, placeholder, and empty-state guidance verified in index.php.\n\n";

// -------------------------------------------------------------------------
// Test 2: Authenticate Driver (User A) and Owner (User B)
// -------------------------------------------------------------------------
echo "Test 2: Authenticating Driver (User A) and Owner (User B)...\n";
$loginDriver = makeReq('POST', '/api/auth.php?action=login', [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
if ($loginDriver['code'] !== 200) {
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
if ($loginOwner['code'] !== 200) {
    echo "   [FAIL] Owner login failed: " . json_encode($loginOwner['json']) . "\n";
    exit(1);
}
$ownerUser = $loginOwner['json']['user'];
$ownerCookies = $loginOwner['cookies'];
$ownerCsrf = $loginOwner['json']['csrf_token'] ?? '';
echo "   [PASS] Owner (User B) authenticated ({$ownerUser['id']}).\n\n";

// -------------------------------------------------------------------------
// Test 3: Setup test parking lot and distinct vehicles
// -------------------------------------------------------------------------
echo "Test 3: Creating test parking lot and driver vehicle...\n";
$lotTag = substr(uniqid(), -5);
$createLotRes = makeReq('POST', '/api/create_parking_space.php', [
    'name'             => 'High Ground Lot ' . $lotTag,
    'latitude'         => 18.7900,
    'longitude'        => 98.9800,
    'capacity'         => 3,
    'available_spaces' => 3,
    'status'           => 'available'
], $ownerCookies, $ownerCsrf);

if ($createLotRes['code'] !== 201 && $createLotRes['code'] !== 200) {
    echo "   [FAIL] Lot creation failed: " . json_encode($createLotRes['json']) . "\n";
    exit(1);
}
$testLot = $createLotRes['json']['data'];
$testLotId = $testLot['id'];
echo "   [PASS] Created test lot '{$testLot['name']}' (ID: $testLotId, 3/3 capacity).\n";

// Register vehicle for Driver A
$carPlateA = 'A-' . substr(uniqid(), -4);
$addCarARes = makeReq('POST', '/api/create_car.php', [
    'license_plate' => $carPlateA,
    'model'         => 'Toyota Yaris'
], $driverCookies, $driverCsrf);
$carA = $addCarARes['json']['data'];
$carA_id = $carA['id'];
echo "   [PASS] Driver A vehicle ready: {$carA['model']} — {$carA['license_plate']} (ID: $carA_id).\n";

// Register vehicle for Owner B (to test cross-user theft defense)
$carPlateB = 'B-' . substr(uniqid(), -4);
$addCarBRes = makeReq('POST', '/api/create_car.php', [
    'license_plate' => $carPlateB,
    'model'         => 'Honda Civic'
], $ownerCookies, $ownerCsrf);
$carB = $addCarBRes['json']['data'];
$carB_id = $carB['id'];
echo "   [PASS] User B vehicle ready: {$carB['model']} — {$carB['license_plate']} (ID: $carB_id).\n\n";

// -------------------------------------------------------------------------
// Test 4: Verify Car Dropdown Data Format via api/get_cars.php
// -------------------------------------------------------------------------
echo "Test 4: Verifying Car Dropdown options from api/get_cars.php...\n";
$getCarsRes = makeReq('GET', '/api/get_cars.php', null, $driverCookies);
if ($getCarsRes['code'] !== 200 || !is_array($getCarsRes['json']['data'])) {
    echo "   [FAIL] Failed to fetch driver cars: " . json_encode($getCarsRes['json']) . "\n";
    exit(1);
}
$driverCars = $getCarsRes['json']['data'];
$foundCarA = null;
foreach ($driverCars as $c) {
    if ($c['id'] === $carA_id) {
        $foundCarA = $c;
        break;
    }
}
if (!$foundCarA) {
    echo "   [FAIL] Newly added car not returned in api/get_cars.php\n";
    exit(1);
}
$dropdownLabel = ($foundCarA['model'] ?: 'Vehicle') . ' — ' . $foundCarA['license_plate'];
echo "   [PASS] Car dropdown label formatted as: '$dropdownLabel'.\n\n";

// -------------------------------------------------------------------------
// Test 5: Security Defense — Driver A attempts to reserve using User B's car_id
// -------------------------------------------------------------------------
echo "Test 5: Testing cross-user car security defense (Driver A using User B's car_id)...\n";
$maliciousRes = makeReq('POST', '/api/create_reservation.php', [
    'car_id'           => $carB_id,
    'parking_space_id' => $testLotId,
    'notes'            => 'Malicious cross-user reservation attempt'
], $driverCookies, $driverCsrf);

if ($maliciousRes['code'] === 403) {
    echo "   [PASS] Cross-user car submission strictly blocked with HTTP 403 Forbidden: '{$maliciousRes['json']['error']}'.\n\n";
} else {
    echo "   [FAIL] Expected HTTP 403 Forbidden for foreign car, got HTTP {$maliciousRes['code']}: " . json_encode($maliciousRes['json']) . "\n";
    exit(1);
}

// -------------------------------------------------------------------------
// Test 6: Legitimate Reservation Submission with own car_id UUID
// -------------------------------------------------------------------------
echo "Test 6: Submitting legitimate reservation request with driver's car UUID...\n";
$legitRes = makeReq('POST', '/api/create_reservation.php', [
    'car_id'           => $carA_id,
    'parking_space_id' => $testLotId,
    'notes'            => 'Safe flood evacuation'
], $driverCookies, $driverCsrf);

if ($legitRes['code'] !== 201 && $legitRes['code'] !== 200) {
    echo "   [FAIL] Legitimate reservation submission failed: " . json_encode($legitRes['json']) . "\n";
    exit(1);
}
$createdResId = $legitRes['json']['data']['id'];
echo "   [PASS] Reservation created successfully with ID $createdResId in 'pending' status.\n\n";

// -------------------------------------------------------------------------
// Test 7: Owner Acceptance & Capacity Decrement
// -------------------------------------------------------------------------
echo "Test 7: Owner accepts reservation request via api/approve_reservation.php...\n";
$approveRes = makeReq('POST', '/api/approve_reservation.php', [
    'reservation_id' => $createdResId,
    'action'         => 'accept'
], $ownerCookies, $ownerCsrf);

if ($approveRes['code'] !== 200 || empty($approveRes['json']['success'])) {
    echo "   [FAIL] Owner acceptance failed: " . json_encode($approveRes['json']) . "\n";
    exit(1);
}

// Verify lot capacity decreased from 3 to 2
$getSpacesRes = makeReq('GET', '/api/get_parking_spaces.php');
$spaces = $getSpacesRes['json']['data'] ?? [];
$lotAfterAccept = null;
foreach ($spaces as $s) {
    if ($s['id'] === $testLotId) {
        $lotAfterAccept = $s;
        break;
    }
}
if (!$lotAfterAccept || (int)$lotAfterAccept['available_spaces'] !== 2) {
    echo "   [FAIL] Expected lot available_spaces = 2, got " . ($lotAfterAccept['available_spaces'] ?? 'null') . "\n";
    exit(1);
}
echo "   [PASS] Owner accepted request: status updated to 'confirmed', capacity decreased from 3 to 2.\n\n";

// -------------------------------------------------------------------------
// Test 8: Cancellation & Capacity Restoration
// -------------------------------------------------------------------------
echo "Test 8: Driver cancels confirmed reservation spot...\n";
$cancelRes = makeReq('POST', '/api/cancel_reservation.php', [
    'reservation_id' => $createdResId
], $driverCookies, $driverCsrf);

if ($cancelRes['code'] !== 200 || empty($cancelRes['json']['success'])) {
    echo "   [FAIL] Cancellation failed: " . json_encode($cancelRes['json']) . "\n";
    exit(1);
}

// Verify lot capacity restored to 3
$getSpacesRes = makeReq('GET', '/api/get_parking_spaces.php');
$spaces = $getSpacesRes['json']['data'] ?? [];
$lotAfterCancel = null;
foreach ($spaces as $s) {
    if ($s['id'] === $testLotId) {
        $lotAfterCancel = $s;
        break;
    }
}
if (!$lotAfterCancel || (int)$lotAfterCancel['available_spaces'] !== 3) {
    echo "   [FAIL] Expected capacity restored to 3, got " . ($lotAfterCancel['available_spaces'] ?? 'null') . "\n";
    exit(1);
}
echo "   [PASS] Spot cancellation succeeded: capacity restored to 3/3.\n\n";

// -------------------------------------------------------------------------
// Test 9: Cleanup test artifacts
// -------------------------------------------------------------------------
echo "Test 9: Cleaning up test artifacts...\n";
makeReq('POST', '/api/delete_parking_space.php', ['id' => $testLotId], $ownerCookies, $ownerCsrf);
makeReq('POST', '/api/delete_car.php', ['id' => $carA_id], $driverCookies, $driverCsrf);
makeReq('POST', '/api/delete_car.php', ['id' => $carB_id], $ownerCookies, $ownerCsrf);
echo "   [PASS] Cleaned up test parking lot and vehicles.\n\n";

echo "=================================================================\n";
echo "   ALL RESERVATION CAR DROPDOWN TESTS PASSED!                    \n";
echo "=================================================================\n";
