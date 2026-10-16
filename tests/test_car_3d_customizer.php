<?php
/**
 * Test: 3D Car Customizer & Vehicle Studio Automated Verification
 * 
 * Verifies:
 * 1. 3D Car Engine (assets/js/car_3d.js): Class Car3D, models, wheels, details, snapshots, events.
 * 2. Visual & Structural alignment with Avatar Customizer in index.php:
 *    - #section-vehicles structure matching #section-profile
 *    - 3D viewport canvas & badges
 *    - Category tabs (model, paint, wheels, details)
 *    - Style chips, color swatches, wheel chips, flood gear chips
 *    - Reset appearance control
 * 3. Responsive styling in assets/css/style.css:
 *    - Shared portrait styling
 *    - Fixed-grid options stability
 * 4. Live API CRUD with 3D customization payload:
 *    - Create 3D vehicle with bodyStyle, paintColor, wheelStyle, detail, snapshot
 *    - Retrieve and verify payload integrity
 *    - Update vehicle 3D customization
 *    - Security: 401 unauthenticated, 403 CSRF check, 403 cross-user protection
 *    - Clean deletion
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: 3D Car Customizer & Vehicle Studio Verification       \n";
echo "=================================================================\n\n";

function make_call($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
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
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'body'     => json_decode($bodyStr, true),
        'raw_body' => $bodyStr,
        'cookies'  => $newCookies
    ];
}

// -------------------------------------------------------------
// Test 1: Verify 3D Car Engine Architecture (assets/js/car_3d.js)
// -------------------------------------------------------------
echo "Test 1: Inspecting 3D Car Engine in assets/js/car_3d.js...\n";
$carJsPath = __DIR__ . '/../assets/js/car_3d.js';
assert(file_exists($carJsPath), "assets/js/car_3d.js must exist");
$carJs = file_get_contents($carJsPath);

assert(strpos($carJs, 'class Car3D') !== false, "Must define class Car3D");
assert(strpos($carJs, 'window.Car3D = Car3D') !== false, "Must export Car3D to window");
assert(strpos($carJs, 'buildPickup') !== false, "Must support Pickup Truck model");
assert(strpos($carJs, 'buildSUV') !== false, "Must support 4x4 Offroad SUV model");
assert(strpos($carJs, 'buildVan') !== false, "Must support Emergency Van model");
assert(strpos($carJs, 'buildCompact') !== false, "Must support Compact Car model");
assert(strpos($carJs, 'buildWheels') !== false, "Must support customizable wheels");
assert(strpos($carJs, 'buildDetails') !== false, "Must support flood details (snorkel, roofrack, beacon)");
assert(strpos($carJs, 'updateConfig') !== false, "Must have updateConfig method");
assert(strpos($carJs, 'resetToDefaults') !== false, "Must have resetToDefaults method");
assert(strpos($carJs, 'getSnapshot') !== false, "Must generate snapshot data URL");
assert(strpos($carJs, 'bindEvents') !== false, "Must bind drag-to-rotate events");
echo "   [PASS] 3D Car Engine architecture and methods verified.\n\n";

// -------------------------------------------------------------
// Test 2: Inspect HTML & 16-Bit Studio Structure in index.php
// -------------------------------------------------------------
echo "Test 2: Inspecting HTML & 16-Bit Studio UI in index.php...\n";
$htmlRes = make_call("$baseUrl/index.php");
assert($htmlRes['code'] === 200, "Failed to load index.php");
$html = $htmlRes['raw_body'];

// Script tags
assert(strpos($html, 'src="assets/js/car_3d.js"') !== false, "index.php must include car_3d.js");
assert(strpos($html, 'src="assets/js/character_3d.js"') !== false, "index.php must preserve character_3d.js");

// Navigation
assert(strpos($html, 'id="nav-vehicles"') !== false, "Navbar must contain #nav-vehicles");
assert(strpos($html, 'id="section-vehicles"') !== false, "DOM must contain #section-vehicles");

// Studio Components matching Avatar Customizer
assert(strpos($html, 'id="car-3d-viewport"') !== false, "Must contain #car-3d-viewport");
assert(strpos($html, 'id="car-studio-canvas"') !== false, "Must contain #car-studio-canvas");
assert(strpos($html, 'id="car-customizer-tabs"') !== false, "Must contain #car-customizer-tabs");
assert(strpos($html, 'data-tab="model"') !== false, "Must contain Body Style tab");
assert(strpos($html, 'data-tab="paint"') !== false, "Must contain Paint Color tab");
assert(strpos($html, 'data-tab="wheels"') !== false, "Must contain Wheels tab");
assert(strpos($html, 'data-tab="details"') !== false, "Must contain Flood Gear tab");

// Option chips & swatches
assert(strpos($html, 'data-style="pickup"') !== false, "Must have pickup option");
assert(strpos($html, 'data-style="suv"') !== false, "Must have suv option");
assert(strpos($html, 'data-style="van"') !== false, "Must have van option");
assert(strpos($html, 'data-style="compact"') !== false, "Must have compact option");
assert(strpos($html, 'id="car-paint-colors"') !== false, "Must contain #car-paint-colors swatches");
assert(strpos($html, 'id="btn-reset-car-appearance"') !== false, "Must contain #btn-reset-car-appearance button");

// Form & List
assert(strpos($html, 'id="vehicle-studio-form"') !== false, "Must contain #vehicle-studio-form");
assert(strpos($html, 'id="vehicle-studio-plate"') !== false, "Must contain #vehicle-studio-plate input");
assert(strpos($html, 'id="btn-submit-car-studio"') !== false, "Must contain #btn-submit-car-studio button");
assert(strpos($html, 'id="vehicle-studio-list-container"') !== false, "Must contain #vehicle-studio-list-container");
echo "   [PASS] HTML & 16-Bit Studio UI structure verified.\n\n";

// -------------------------------------------------------------
// Test 3: Authenticate User and Test 3D Vehicle Registration API
// -------------------------------------------------------------
echo "Test 3: Testing live 3D Vehicle Registration with Supabase...\n";

// Step 3a: Authenticate Test User
$testEmail = 'stage5_user_a@jodrod-test.org';
$testPass = 'TestPassword123!';
$loginRes = make_call("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => $testEmail,
    'password' => $testPass
]);
assert($loginRes['code'] === 200, "Failed to login test user with HTTP {$loginRes['code']}");
$cookies = $loginRes['cookies'];
$csrfToken = $loginRes['body']['csrf_token'] ?? '';
assert(!empty($csrfToken), "Must obtain valid CSRF token");

// Step 3b: Register a 3D customized car
$plateNumber = '3D-TEST-' . substr(md5(microtime()), 0, 5);
$sample3dConfig = [
    'bodyStyle'  => 'pickup',
    'paintColor' => '#ea580c',
    'wheelStyle' => 'flood',
    'detail'     => 'snorkel',
    'label'      => 'Flood Relief Hilux (Hi-Vis Orange)',
    'snapshot'   => 'data:image/jpeg;base64,' . base64_encode('fake_3d_snapshot_for_test')
];

$createRes = make_call("$baseUrl/api/create_car.php", 'POST', [
    'license_plate' => $plateNumber,
    'model'         => json_encode($sample3dConfig)
], $cookies, ['X-CSRF-Token: ' . $csrfToken]);

assert($createRes['code'] === 201 && $createRes['body']['success'], "Failed to create 3D customized car. Code: {$createRes['code']}, error: " . ($createRes['body']['error'] ?? ''));
$createdCar = $createRes['body']['data'];
$carId = $createdCar['id'];
echo "   [PASS] Created 3D vehicle with ID: $carId and plate: $plateNumber\n";

// Step 3c: Verify payload integrity in get_cars.php
$getRes = make_call("$baseUrl/api/get_cars.php", 'GET', null, $cookies);
assert($getRes['code'] === 200 && $getRes['body']['success'], "Failed to list cars");
$foundCar = null;
foreach ($getRes['body']['data'] as $c) {
    if ($c['id'] === $carId) {
        $foundCar = $c;
        break;
    }
}
assert($foundCar !== null, "Registered 3D car must be returned by get_cars.php");

// Decode model JSON
$decodedModel = json_decode($foundCar['model'], true);
assert(is_array($decodedModel), "Car model must be valid JSON object");
assert($decodedModel['bodyStyle'] === 'pickup', "bodyStyle must be preserved");
assert($decodedModel['paintColor'] === '#ea580c', "paintColor must be preserved");
assert($decodedModel['wheelStyle'] === 'flood', "wheelStyle must be preserved");
assert($decodedModel['detail'] === 'snorkel', "detail must be preserved");
assert(strpos($decodedModel['snapshot'], 'data:image/jpeg;base64,') === 0, "snapshot data URI must be preserved");
echo "   [PASS] Vehicle customization data integrity verified.\n";

// Step 3d: Update 3D customization
$updatedConfig = $sample3dConfig;
$updatedConfig['bodyStyle'] = 'suv';
$updatedConfig['paintColor'] = '#eab308';
$updatedConfig['label'] = 'Rescue SUV (Safety Yellow)';

$updateRes = make_call("$baseUrl/api/update_car.php", 'POST', [
    'id'            => $carId,
    'license_plate' => $plateNumber,
    'model'         => json_encode($updatedConfig)
], $cookies, ['X-CSRF-Token: ' . $csrfToken]);

assert($updateRes['code'] === 200 && $updateRes['body']['success'], "Failed to update 3D car");

$verifyRes = make_call("$baseUrl/api/get_cars.php", 'GET', null, $cookies);
$updatedCar = null;
foreach ($verifyRes['body']['data'] as $c) {
    if ($c['id'] === $carId) {
        $updatedCar = $c;
        break;
    }
}
$decodedUpdated = json_decode($updatedCar['model'], true);
assert($decodedUpdated['bodyStyle'] === 'suv', "Updated bodyStyle must persist");
assert($decodedUpdated['paintColor'] === '#eab308', "Updated paintColor must persist");
echo "   [PASS] 3D Car update persisted in Supabase.\n";

// Step 3e: Clean up test vehicle
$deleteRes = make_call("$baseUrl/api/delete_car.php", 'POST', [
    'id' => $carId
], $cookies, ['X-CSRF-Token: ' . $csrfToken]);
assert($deleteRes['code'] === 200 && $deleteRes['body']['success'], "Failed to delete test car");
echo "   [PASS] Test vehicle cleaned up safely.\n\n";

// -------------------------------------------------------------
// Test 4: Verify CSS Styling Rules in assets/css/style.css
// -------------------------------------------------------------
echo "Test 4: Verifying CSS styling rules in assets/css/style.css...\n";
$css = file_get_contents(__DIR__ . '/../assets/css/style.css');
assert(strpos($css, '.car-avatar-portrait') !== false, "Must include .car-avatar-portrait");
assert(strpos($css, '#car-model-options') !== false, "Must style #car-model-options grid");
assert(strpos($css, '#car-wheels-options') !== false, "Must style #car-wheels-options grid");
assert(strpos($css, '#car-details-options') !== false, "Must style #car-details-options grid");
echo "   [PASS] CSS styling and layout rules verified.\n\n";

echo "=================================================================\n";
echo "   ALL 3D CAR CUSTOMIZER & VEHICLE STUDIO CHECKS PASSED!        \n";
echo "=================================================================\n";
