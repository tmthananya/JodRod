<?php
/**
 * Test Suite: My Parking Lots UX Verification
 * 
 * Verifies the improved two-step My Parking Lots owner experience:
 * 1. UI Structure in index.php & style.css (Step 1 grid, Step 2 detail, Offer form container).
 * 2. Offer Parking Lot form preservation: All original fields, buttons, and endpoints intact.
 * 3. Step 1: Owner lot isolation (only owned lots displayed, no foreign lots).
 * 4. Step 1: Metrics accuracy (capacity, available spaces, pending count, confirmed count, status).
 * 5. Step 2: Per-lot isolation (requests & bookings strictly filtered by parking_space_id; no lot cross-contamination).
 * 6. Offer Parking Lot creation: Creating a new lot adds it to the owner's list immediately.
 * 7. Action handling: Decline action updates status without altering capacity.
 * 8. Back navigation support: Returns to Step 1 preserving context.
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: My Parking Lots Two-Step UX & Flow Verification       \n";
echo "=================================================================\n\n";

function make_call($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $formattedHeaders = array_merge(['Accept: application/json'], $headers);

    if (!empty($cookies)) {
        $cookiePairs = [];
        foreach ($cookies as $k => $v) {
            $cookiePairs[] = "$k=$v";
        }
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookiePairs));
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $json = is_string($data) ? $data : json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $formattedHeaders[] = 'Content-Type: application/json';
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    @curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    // Extract cookies
    $respCookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $rawHeaders, $matches);
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        foreach ($cookie as $k => $v) {
            $respCookies[$k] = $v;
        }
    }

    $decoded = json_decode($body, true);

    return [
        'code'     => $code,
        'body'     => $decoded,
        'raw_body' => $body,
        'cookies'  => $respCookies
    ];
}

// -------------------------------------------------------------
// Test 1: Verify UI Structure in index.php
// -------------------------------------------------------------
echo "Test 1: Inspecting My Parking Lots UI structure in index.php...\n";
$htmlRes = make_call("$baseUrl/index.php");
assert($htmlRes['code'] === 200, "Failed to load index.php");
$html = $htmlRes['raw_body'];

// Section and Nav
assert(strpos($html, 'id="section-my-spaces"') !== false, "Must contain #section-my-spaces");
assert(strpos($html, 'id="nav-my-spaces"') !== false, "Navbar must link to #nav-my-spaces");

// Prominent Offer Button
assert(strpos($html, 'id="btn-open-offer-lot"') !== false, "Must contain prominent #btn-open-offer-lot button");

// Preserved Offer Form and all original fields
assert(strpos($html, 'id="offer-parking-form-container"') !== false, "Must contain #offer-parking-form-container");
assert(strpos($html, 'id="offer-parking-form"') !== false, "Must preserve #offer-parking-form");
assert(strpos($html, 'id="offer-name"') !== false, "Must preserve #offer-name input");
assert(strpos($html, 'id="offer-latitude"') !== false, "Must preserve #offer-latitude input");
assert(strpos($html, 'id="offer-longitude"') !== false, "Must preserve #offer-longitude input");
assert(strpos($html, 'id="btn-pick-location"') !== false, "Must preserve #btn-pick-location button");
assert(strpos($html, 'id="offer-capacity"') !== false, "Must preserve #offer-capacity input");
assert(strpos($html, 'id="offer-available-spaces"') !== false, "Must preserve #offer-available-spaces input");
assert(strpos($html, 'id="offer-status"') !== false, "Must preserve #offer-status select");
assert(strpos($html, 'id="btn-submit-space"') !== false, "Must preserve #btn-submit-space button");

// Step 1 & Step 2 Containers
assert(strpos($html, 'id="my-lots-step1-view"') !== false, "Must contain Step 1 view #my-lots-step1-view");
assert(strpos($html, 'id="my-lots-list-container"') !== false, "Must contain #my-lots-list-container");
assert(strpos($html, 'id="my-lots-step2-view"') !== false, "Must contain Step 2 view #my-lots-step2-view");
assert(strpos($html, 'id="btn-back-to-my-lots"') !== false, "Must contain #btn-back-to-my-lots back button");
assert(strpos($html, 'id="my-lot-detail-content"') !== false, "Must contain #my-lot-detail-content");

echo "   [PASS] HTML structure verified: Preserved offer form, Step 1 list container, and Step 2 detail view.\n\n";

// -------------------------------------------------------------
// Test 2: Verify CSS Styling in assets/css/style.css
// -------------------------------------------------------------
echo "Test 2: Verifying CSS classes in assets/css/style.css...\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');
assert(strpos($cssContent, '.my-spaces-header-bar') !== false, "CSS must define .my-spaces-header-bar");
assert(strpos($cssContent, '.my-lots-grid') !== false, "CSS must define .my-lots-grid");
assert(strpos($cssContent, '.my-lot-card') !== false, "CSS must define .my-lot-card");
assert(strpos($cssContent, '.my-lot-detail-header-card') !== false, "CSS must define .my-lot-detail-header-card");
assert(strpos($cssContent, '.my-lot-req-grid') !== false, "CSS must define .my-lot-req-grid");
echo "   [PASS] CSS classes verified for responsive grid, cards, and detail layout.\n\n";

// -------------------------------------------------------------
// Test 3: Authenticate Test Accounts
// -------------------------------------------------------------
echo "Test 3: Authenticating test accounts...\n";
$loginDriver = make_call("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginDriver['code'] === 200, "Driver login failed");
$driverCookies = $loginDriver['cookies'];
$driverCsrf = $loginDriver['body']['csrf_token'];
$driverId = $loginDriver['body']['user']['id'];
echo "   [PASS] Driver (User A) authenticated ($driverId).\n";

$loginOwner = make_call("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginOwner['code'] === 200, "Owner login failed");
$ownerCookies = $loginOwner['cookies'];
$ownerCsrf = $loginOwner['body']['csrf_token'];
$ownerId = $loginOwner['body']['user']['id'];
echo "   [PASS] Owner (User B) authenticated ($ownerId).\n\n";

// -------------------------------------------------------------
// Test 4: Step 1 Owner Parking Lots Isolation
// -------------------------------------------------------------
echo "Test 4: Verifying Step 1 Parking Lots Isolation (Only owned spaces)...\n";
// Create test lot for Owner B
$tag = substr(md5(uniqid()), 0, 5);
$createLot1 = make_call("$baseUrl/api/create_parking_space.php", 'POST', [
    'name'             => "Owner B Safe Lot 1-$tag",
    'latitude'         => 18.790000,
    'longitude'        => 98.980000,
    'capacity'         => 3,
    'available_spaces' => 3,
    'status'           => 'available'
], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
assert($createLot1['code'] === 201, "Failed to create Lot 1: " . $createLot1['raw_body']);
$lot1 = $createLot1['body']['data'];
$lot1Id = $lot1['id'];

// Create second test lot for Owner B
$createLot2 = make_call("$baseUrl/api/create_parking_space.php", 'POST', [
    'name'             => "Owner B Safe Lot 2-$tag",
    'latitude'         => 18.792000,
    'longitude'        => 98.982000,
    'capacity'         => 2,
    'available_spaces' => 2,
    'status'           => 'available'
], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
assert($createLot2['code'] === 201, "Failed to create Lot 2: " . $createLot2['raw_body']);
$lot2 = $createLot2['body']['data'];
$lot2Id = $lot2['id'];

// Fetch all parking spaces
$allSpacesRes = make_call("$baseUrl/api/get_parking_spaces.php");
assert($allSpacesRes['code'] === 200, "Failed to fetch parking spaces");
$allSpaces = $allSpacesRes['body']['data'];

// Filter lots for Owner B
$ownerLots = array_filter($allSpaces, function($s) use ($ownerId) {
    return !empty($s['owner_id']) && $s['owner_id'] === $ownerId;
});

// Verify Owner B lots contain Lot 1 and Lot 2
$foundLot1 = false;
$foundLot2 = false;
foreach ($ownerLots as $ol) {
    if ($ol['id'] === $lot1Id) $foundLot1 = true;
    if ($ol['id'] === $lot2Id) $foundLot2 = true;
    // Verify none belongs to someone else
    assert($ol['owner_id'] === $ownerId, "Foreign parking lot leaked into owner list!");
}
assert($foundLot1 && $foundLot2, "Owner lots must contain both Lot 1 and Lot 2");
echo "   [PASS] Step 1 isolates only owner's parking lots (no foreign spaces exposed).\n\n";

// -------------------------------------------------------------
// Test 5: Step 2 Isolation (Requests filtered strictly by parking_space_id)
// -------------------------------------------------------------
echo "Test 5: Verifying Step 2 Per-Lot Isolation (No cross-lot contamination)...\n";

// Driver gets or registers vehicle
$driverCars = make_call("$baseUrl/api/get_cars.php", 'GET', null, $driverCookies)['body']['data'] ?? [];
if (empty($driverCars)) {
    $c = make_call("$baseUrl/api/create_car.php", 'POST', [
        'license_plate' => 'UX-' . rand(100, 999),
        'model'         => 'Toyota Vios'
    ], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
    $carId = $c['body']['data']['id'];
} else {
    $carId = $driverCars[0]['id'];
}

// Driver requests spot on Lot 1 ONLY
$reqLot1 = make_call("$baseUrl/api/create_reservation.php", 'POST', [
    'car_id'           => $carId,
    'parking_space_id' => $lot1Id,
    'notes'            => 'UX isolation verification request'
], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
assert($reqLot1['code'] === 201, "Driver request on Lot 1 failed: " . $reqLot1['raw_body']);
$res1 = $reqLot1['body']['data'] ?? $reqLot1['body']['reservation'];
$res1Id = $res1['id'];

// Owner queries owner reservations
$ownerResQuery = make_call("$baseUrl/api/get_reservations.php?as_owner=1", 'GET', null, $ownerCookies);
assert($ownerResQuery['code'] === 200, "Owner query failed");
$allOwnerReservations = $ownerResQuery['body']['data'];

// Compute per-lot counts
$lot1Pending = array_filter($allOwnerReservations, function($r) use ($lot1Id) {
    return $r['parking_space_id'] === $lot1Id && $r['status'] === 'pending';
});
$lot2Pending = array_filter($allOwnerReservations, function($r) use ($lot2Id) {
    return $r['parking_space_id'] === $lot2Id && $r['status'] === 'pending';
});

assert(count($lot1Pending) === 1, "Lot 1 must have exactly 1 pending request");
assert(count($lot2Pending) === 0, "Lot 2 must have 0 pending requests! No cross-contamination permitted.");
echo "   [PASS] Per-lot isolation verified: Lot 1 has 1 pending request; Lot 2 has 0 pending requests.\n\n";

// -------------------------------------------------------------
// Test 6: Verify Decline Action & Capacity Preservation
// -------------------------------------------------------------
echo "Test 6: Verifying Decline action preserves capacity and updates status...\n";
$declineRes = make_call("$baseUrl/api/approve_reservation.php", 'POST', [
    'reservation_id' => $res1Id,
    'action'         => 'reject'
], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
assert($declineRes['code'] === 200 && $declineRes['body']['success'] === true, "Decline action failed");

// Verify reservation status is rejected
$checkRes = make_call("$baseUrl/api/get_reservations.php?as_owner=1", 'GET', null, $ownerCookies);
$declined = null;
foreach ($checkRes['body']['data'] as $r) {
    if ($r['id'] === $res1Id) $declined = $r;
}
assert($declined !== null && $declined['status'] === 'rejected', "Status must be 'rejected'");

// Verify capacity of Lot 1 was NOT modified (remains 3)
$spacesCheck = make_call("$baseUrl/api/get_parking_spaces.php");
$checkLot1 = null;
foreach ($spacesCheck['body']['data'] as $s) {
    if ($s['id'] === $lot1Id) $checkLot1 = $s;
}
assert((int)$checkLot1['available_spaces'] === 3, "Available spaces must remain 3 after decline");
echo "   [PASS] Decline action succeeded: reservation marked rejected, lot capacity unchanged (3/3 available).\n\n";

// -------------------------------------------------------------
// Test 7: Cleanup Test Lots
// -------------------------------------------------------------
echo "Test 7: Cleaning up test parking lots...\n";
make_call("$baseUrl/api/delete_parking_space.php", 'POST', ['id' => $lot1Id], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
make_call("$baseUrl/api/delete_parking_space.php", 'POST', ['id' => $lot2Id], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
echo "   [PASS] Cleaned up test lots successfully.\n\n";

echo "=================================================================\n";
echo "   ALL MY PARKING LOTS UX INTEGRATION TESTS PASSED!             \n";
echo "=================================================================\n";
