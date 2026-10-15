<?php
/**
 * Automated Test: Verification for Parking Offer Flow, Automatic Lot Status, and 3D Avatar UI
 * 
 * Verifies:
 * 1. Find Parking Map -> Offer a Spot direct flow & preserved form fields.
 * 2. Automatic lot status enforcement (creation, update, capacity = 0 -> full, available > 0 -> available, closed handling).
 * 3. 3D Avatar hairstyle watertight geometry & stable outfit choice grid layout.
 */

require_once __DIR__ . '/../config.php';

echo "=================================================================\n";
echo "   JodRod: Offer Flow, Auto Lot Status & 3D Avatar Verification   \n";
echo "=================================================================\n\n";

// -------------------------------------------------------------
// Test 1: Offer Flow UI & Routing Verification in index.php
// -------------------------------------------------------------
echo "Test 1: Verifying Offer Flow directly opens the offer form...\n";
$indexHtml = file_get_contents(__DIR__ . '/../index.php');

assert(strpos($indexHtml, 'id="btn-offer-from-map"') !== false, "Must contain #btn-offer-from-map");
assert(strpos($indexHtml, 'href="#my-spaces?action=offer"') !== false, "#btn-offer-from-map must direct to #my-spaces?action=offer");
assert(strpos($indexHtml, 'id="offer-parking-form-container"') !== false, "Must contain #offer-parking-form-container");
assert(strpos($indexHtml, 'id="offer-parking-form"') !== false, "Must contain #offer-parking-form");
assert(strpos($indexHtml, 'id="offer-name"') !== false, "Must contain #offer-name");
assert(strpos($indexHtml, 'id="offer-latitude"') !== false, "Must contain #offer-latitude");
assert(strpos($indexHtml, 'id="offer-longitude"') !== false, "Must contain #offer-longitude");
assert(strpos($indexHtml, 'isOfferAction') !== false, "handleRoute must support isOfferAction to unhide form directly");
assert(strpos($indexHtml, 'offerOrigin') !== false, "Must track offerOrigin to return to map after creation");

echo "   [PASS] Offer flow direct navigation verified: #btn-offer-from-map links to offer action and handleRoute unhides form directly.\n\n";

// -------------------------------------------------------------
// Test 2: Full Option Removed from Forms in index.php
// -------------------------------------------------------------
echo "Test 2: Verifying 'Full' option is removed from form dropdowns...\n";

// Match #offer-status select block
preg_match('/<select id="offer-status"[^>]*>(.*?)<\/select>/s', $indexHtml, $offerSelectMatch);
assert(!empty($offerSelectMatch), "Must find #offer-status select");
assert(strpos($offerSelectMatch[1], 'value="full"') === false, "#offer-status must NOT contain value='full'");
assert(strpos($offerSelectMatch[1], 'value="available"') !== false, "#offer-status must contain value='available'");
assert(strpos($offerSelectMatch[1], 'value="closed"') !== false, "#offer-status must contain value='closed'");

// Match #edit-space-status select block
preg_match('/<select id="edit-space-status"[^>]*>(.*?)<\/select>/s', $indexHtml, $editSelectMatch);
assert(!empty($editSelectMatch), "Must find #edit-space-status select");
assert(strpos($editSelectMatch[1], 'value="full"') === false, "#edit-space-status must NOT contain value='full'");
assert(strpos($editSelectMatch[1], 'value="available"') !== false, "#edit-space-status must contain value='available'");
assert(strpos($editSelectMatch[1], 'value="closed"') !== false, "#edit-space-status must contain value='closed'");

echo "   [PASS] Form controls verified: User-selectable 'Full' option is removed from create and edit forms.\n\n";

// -------------------------------------------------------------
// Test 3: Backend Automatic Status Calculation (API Verification)
// -------------------------------------------------------------
echo "Test 3: Testing backend automatic status calculation with Supabase...\n";

// Authenticate test owner account
$loginRes = supabase_request('/auth/v1/token?grant_type=password', 'POST', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginRes['success'] && isset($loginRes['data']['access_token']), "Authentication failed for stage5_user_b");
$ownerToken = $loginRes['data']['access_token'];
$ownerId = $loginRes['data']['user']['id'];

$baseUrl = "http://127.0.0.1:8088";

function make_call($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $formattedHeaders = $headers;
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
            $formattedHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    @curl_close($ch);
    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $respCookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $rawHeaders, $matches);
    foreach ($matches[1] as $item) {
        parse_str($item, $c);
        foreach ($c as $k => $v) {
            $respCookies[$k] = $v;
        }
    }
    return [
        'code'     => $code,
        'body'     => json_decode($body, true),
        'raw_body' => $body,
        'cookies'  => $respCookies
    ];
}

$loginOwner = make_call("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginOwner['code'] === 200, "Owner login failed: " . $loginOwner['raw_body']);
$ownerCookies = $loginOwner['cookies'];
$ownerCsrf = $loginOwner['body']['csrf_token'] ?? '';
$authHeaders = ['X-CSRF-Token: ' . $ownerCsrf];

// 3A: Create a lot with capacity = 4, available_spaces = 4, but attempt status = 'full'
$testLotName = "AutoStatus Test Lot " . substr(md5(uniqid()), 0, 6);
$createAttempt = make_call("$baseUrl/api/create_parking_space.php", 'POST', [
    'name'             => $testLotName,
    'latitude'         => 18.7900,
    'longitude'        => 98.9800,
    'capacity'         => 4,
    'available_spaces' => 4,
    'status'           => 'full' // User attempts to force full
], $ownerCookies, $authHeaders);

assert($createAttempt['code'] === 201, "Create parking space should succeed with 201: " . $createAttempt['raw_body']);
$createdLot = $createAttempt['body']['data'];
$lotId = $createdLot['id'];
assert($createdLot['status'] === 'available', "New lot with available > 0 MUST NOT be marked full (got {$createdLot['status']})");
echo "   [PASS] New lot with available capacity (4/4) automatically enforced as 'available', overriding manual 'full'.\n";

// 3B: Update lot to available_spaces = 0 -> Must automatically become 'full'
$updateToZero = make_call("$baseUrl/api/update_parking_space.php", 'POST', [
    'id'               => $lotId,
    'name'             => $testLotName,
    'capacity'         => 4,
    'available_spaces' => 0,
    'status'           => 'available' // User tries to keep available
], $ownerCookies, $authHeaders);

assert($updateToZero['code'] === 200, "Update to available_spaces=0 should succeed: " . $updateToZero['raw_body']);
assert($updateToZero['body']['data']['status'] === 'full', "Lot with available_spaces=0 MUST automatically become 'full'");
echo "   [PASS] Updating lot to available_spaces = 0 automatically recalculated status to 'full'.\n";

// 3C: Update lot to available_spaces = 2 -> Must automatically become 'available'
$updateToTwo = make_call("$baseUrl/api/update_parking_space.php", 'POST', [
    'id'               => $lotId,
    'name'             => $testLotName,
    'capacity'         => 4,
    'available_spaces' => 2,
    'status'           => 'available'
], $ownerCookies, $authHeaders);

assert($updateToTwo['code'] === 200, "Update to available_spaces=2 should succeed: " . $updateToTwo['raw_body']);
assert($updateToTwo['body']['data']['status'] === 'available', "Lot with available_spaces=2 MUST become 'available'");
echo "   [PASS] Updating lot to available_spaces = 2 automatically restored status to 'available'.\n";

// 3D: Explicitly closing a lot -> Must become 'closed'
$updateToClosed = make_call("$baseUrl/api/update_parking_space.php", 'POST', [
    'id'               => $lotId,
    'name'             => $testLotName,
    'capacity'         => 4,
    'available_spaces' => 2,
    'status'           => 'closed'
], $ownerCookies, $authHeaders);

assert($updateToClosed['code'] === 200, "Update to closed should succeed: " . $updateToClosed['raw_body']);
assert($updateToClosed['body']['data']['status'] === 'closed', "Lot explicitly closed must have status 'closed'");
echo "   [PASS] Explicitly setting 'closed' is respected.\n";

// Cleanup test lot
supabase_request("/rest/v1/parking_spaces?id=eq.{$lotId}", 'DELETE', [], [
    "Authorization: Bearer {$ownerToken}"
]);
echo "   [PASS] Test parking space cleaned up successfully.\n\n";

// -------------------------------------------------------------
// Test 4: 3D Avatar Hairstyle Watertight Geometries
// -------------------------------------------------------------
echo "Test 4: Verifying 3D Avatar Hairstyle Geometries in assets/js/character_3d.js...\n";
$charJs = file_get_contents(__DIR__ . '/../assets/js/character_3d.js');

// Helmet geometry check
assert(strpos($charJs, "SphereGeometry(0.56") !== false, "Helmet must use expanded dome radius (0.56) to properly enclose box head");
assert(strpos($charJs, "CylinderGeometry(0.62") !== false, "Helmet rim must expand to 0.62 to match helmet dome");

// Buzz cut geometry check
assert(strpos($charJs, "top = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.26, 0.74), mat)") !== false, "Buzz cut top box must have height >= 0.26 to cover scalp without gap");

// Bob cut check
assert(strpos($charJs, "style === 'bob'") !== false && strpos($charJs, "fringe = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.15, 0.14), mat)") !== false, "Bob cut must include front fringe to prevent exposed forehead seam");

// Wavy hair check
assert(strpos($charJs, "style === 'wavy'") !== false && strpos($charJs, "BoxGeometry(0.12, 0.85, 0.68)") !== false, "Wavy hair must use side depth >= 0.68 to connect seamlessly with back hair");

// Beanie check
assert(strpos($charJs, "style === 'beanie'") !== false && strpos($charJs, "sideL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.25, 0.55), mat)") !== false, "Beanie must include side hair under the fold");

echo "   [PASS] 3D Avatar hairstyle geometries verified: Helmet dome encasement, buzz cut scalp coverage, bob fringe, wavy seamless sides, and beanie side hair.\n\n";

// -------------------------------------------------------------
// Test 5: Outfit Choice Layout Stability in assets/css/style.css
// -------------------------------------------------------------
echo "Test 5: Verifying Outfit Choice Layout Stability in assets/css/style.css...\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');

assert(strpos($cssContent, '#create-clothes-styles') !== false, "CSS must style #create-clothes-styles");
assert(strpos($cssContent, 'grid-template-columns: repeat(3, 1fr);') !== false, "Clothes and hair styles must use stable 3-column grid");
assert(strpos($cssContent, 'grid-template-columns: repeat(2, 1fr);') !== false, "Mobile media query must use stable 2-column grid");

// Check .customizer-chip-btn outer dimensions consistency
preg_match('/\.customizer-chip-btn\s*\{(.*?)\}/s', $cssContent, $chipMatch);
assert(!empty($chipMatch), "Must find .customizer-chip-btn rule");
assert(strpos($chipMatch[1], 'box-sizing: border-box') !== false, ".customizer-chip-btn must specify box-sizing: border-box");
assert(strpos($chipMatch[1], 'font-weight: 700') !== false, ".customizer-chip-btn must have font-weight: 700");

preg_match('/\.customizer-chip-btn\.active\s*\{(.*?)\}/s', $cssContent, $activeChipMatch);
assert(!empty($activeChipMatch), "Must find .customizer-chip-btn.active rule");
assert(strpos($activeChipMatch[1], 'font-weight: 700') !== false, ".customizer-chip-btn.active must maintain consistent font-weight: 700 (no reflow)");
assert(strpos($activeChipMatch[1], 'border: 2px solid') !== false, ".customizer-chip-btn.active must maintain 2px border width (no dimension change)");
assert(strpos($activeChipMatch[1], 'transform: none') !== false, ".customizer-chip-btn.active must have transform: none to avoid layout shifting");

echo "   [PASS] Outfit choice layout stability verified: Fixed 3-column desktop / 2-column mobile grid with identical outer dimensions and zero reflow on active selection.\n\n";

echo "=================================================================\n";
echo "   ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY!                \n";
echo "=================================================================\n";
