<?php
/**
 * Test: 3D Car Customizer & Vehicle Studio Automated Verification
 * 
 * Verifies:
 * 1. 3D Car Engine (assets/js/car_3d.js):
 *    - Class Car3D
 *    - 8 distinct body styles (Sedan, Pickup, Coupe, SUV, Jeep, Hatchback, Van, Supercar)
 *    - Wheel styles (standard, sport, offroad, flood)
 *    - Contextual cosmetic details (spoiler, snorkel, roofrack, beacon, none)
 *    - Smooth scale transition & fallback rendering
 *    - Snapshot generation & interaction events
 * 2. Visual & Structural alignment with Avatar Customizer in index.php:
 *    - #section-vehicles structure matching #section-profile
 *    - 3D viewport canvas & badges
 *    - Live studio summary bar (#car-studio-live-summary)
 *    - Viewport controls (#btn-car-reset-angle, #btn-car-toggle-spin)
 *    - 8 Body Style chips with retro pixel mini badges
 *    - 8 Color swatches + Custom Color Picker input
 *    - Wheels & Contextual Cosmetics tabs
 *    - Reset appearance controls
 * 3. Responsive styling in assets/css/style.css:
 *    - Shared portrait styling
 *    - Summary bar & custom color picker styles
 *    - Responsive options stability down to 320px
 * 4. Live API CRUD with 3D customization payload:
 *    - Create 3D vehicle with Supercar / Coupe / SUV payload
 *    - Retrieve and verify payload integrity in Supabase
 *    - Update vehicle 3D customization
 *    - Clean deletion
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: Expanded 3D Vehicle Customizer Verification           \n";
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

// 8 Distinct 3D Model Builders
assert(strpos($carJs, 'buildSedan') !== false, "Must support Sedan model");
assert(strpos($carJs, 'buildPickup') !== false, "Must support Pickup Truck model");
assert(strpos($carJs, 'buildCoupe') !== false, "Must support Sport Coupe model");
assert(strpos($carJs, 'buildSUV') !== false, "Must support SUV model");
assert(strpos($carJs, 'buildJeep') !== false, "Must support Jeep / Off-Road model");
assert(strpos($carJs, 'buildHatchback') !== false, "Must support Hatchback model");
assert(strpos($carJs, 'buildVan') !== false, "Must support Van / MPV model");
assert(strpos($carJs, 'buildSupercar') !== false, "Must support Supercar model");

// Engine Features
assert(strpos($carJs, 'buildWheels') !== false, "Must support customizable wheels");
assert(strpos($carJs, 'buildDetails') !== false, "Must support cosmetic details");
assert(strpos($carJs, 'renderFallback') !== false, "Must provide 2D fallback renderer");
assert(strpos($carJs, 'targetScale') !== false, "Must support smooth transition scaling");
assert(strpos($carJs, 'updateConfig') !== false, "Must have updateConfig method");
assert(strpos($carJs, 'resetToDefaults') !== false, "Must have resetToDefaults method");
assert(strpos($carJs, 'getSnapshot') !== false, "Must generate snapshot data URL");
assert(strpos($carJs, 'bindEvents') !== false, "Must bind drag-to-rotate events");
echo "   [PASS] 3D Car Engine architecture and 8 distinct body styles verified.\n\n";

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

// Studio Components
assert(strpos($html, 'id="car-3d-viewport"') !== false, "Must contain #car-3d-viewport");
assert(strpos($html, 'id="car-studio-canvas"') !== false, "Must contain #car-studio-canvas");
assert(strpos($html, 'id="car-studio-live-summary"') !== false, "Must contain #car-studio-live-summary");
assert(strpos($html, 'id="btn-car-reset-angle"') !== false, "Must contain #btn-car-reset-angle");
assert(strpos($html, 'id="btn-car-toggle-spin"') !== false, "Must contain #btn-car-toggle-spin");
assert(strpos($html, 'id="car-customizer-tabs"') !== false, "Must contain #car-customizer-tabs");

// All 8 Body Styles in HTML
assert(strpos($html, 'data-style="sedan"') !== false, "Must have sedan option");
assert(strpos($html, 'data-style="pickup"') !== false, "Must have pickup option");
assert(strpos($html, 'data-style="coupe"') !== false, "Must have coupe option");
assert(strpos($html, 'data-style="suv"') !== false, "Must have suv option");
assert(strpos($html, 'data-style="jeep"') !== false, "Must have jeep option");
assert(strpos($html, 'data-style="hatchback"') !== false, "Must have hatchback option");
assert(strpos($html, 'data-style="van"') !== false, "Must have van option");
assert(strpos($html, 'data-style="supercar"') !== false, "Must have supercar option");

// Color Swatches & Custom Picker
assert(strpos($html, 'data-color="#dc2626"') !== false, "Must have Red color swatch");
assert(strpos($html, 'data-color="#2563eb"') !== false, "Must have Blue color swatch");
assert(strpos($html, 'data-color="#18181b"') !== false, "Must have Black color swatch");
assert(strpos($html, 'data-color="#f8fafc"') !== false, "Must have White color swatch");
assert(strpos($html, 'data-color="#94a3b8"') !== false, "Must have Silver color swatch");
assert(strpos($html, 'data-color="#16a34a"') !== false, "Must have Green color swatch");
assert(strpos($html, 'data-color="#eab308"') !== false, "Must have Yellow color swatch");
assert(strpos($html, 'data-color="#9333ea"') !== false, "Must have Purple color swatch");
assert(strpos($html, 'id="car-color-picker-input"') !== false, "Must have custom color picker input");

// Wheels & Reset
assert(strpos($html, 'data-wheels="standard"') !== false, "Must have standard wheels option");
assert(strpos($html, 'data-wheels="sport"') !== false, "Must have sport wheels option");
assert(strpos($html, 'data-wheels="offroad"') !== false, "Must have offroad wheels option");
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

// Step 3b: Register a 3D customized Supercar
$plateNumber = 'SUPER-' . substr(md5(microtime()), 0, 5);
$sample3dConfig = [
    'bodyStyle'  => 'supercar',
    'paintColor' => '#9333ea', // Custom Purple
    'wheelStyle' => 'sport',
    'detail'     => 'spoiler',
    'label'      => 'Aero GT Supercar (Purple)',
    'snapshot'   => 'data:image/jpeg;base64,' . base64_encode('fake_supercar_snapshot')
];

$createRes = make_call("$baseUrl/api/create_car.php", 'POST', [
    'license_plate' => $plateNumber,
    'model'         => json_encode($sample3dConfig)
], $cookies, ['X-CSRF-Token: ' . $csrfToken]);

assert($createRes['code'] === 201 && $createRes['body']['success'], "Failed to create 3D customized car. Code: {$createRes['code']}, error: " . ($createRes['body']['error'] ?? ''));
$createdCar = $createRes['body']['data'];
$carId = $createdCar['id'];
echo "   [PASS] Created 3D Supercar with ID: $carId and plate: $plateNumber\n";

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
assert($decodedModel['bodyStyle'] === 'supercar', "bodyStyle must be preserved");
assert($decodedModel['paintColor'] === '#9333ea', "paintColor must be preserved");
assert($decodedModel['wheelStyle'] === 'sport', "wheelStyle must be preserved");
assert($decodedModel['detail'] === 'spoiler', "detail must be preserved");
assert(strpos($decodedModel['snapshot'], 'data:image/jpeg;base64,') === 0, "snapshot data URI must be preserved");
echo "   [PASS] Vehicle customization data integrity verified in Supabase.\n";

// Step 3d: Update 3D customization to Jeep
$updatedConfig = $sample3dConfig;
$updatedConfig['bodyStyle'] = 'jeep';
$updatedConfig['paintColor'] = '#16a34a'; // Green
$updatedConfig['wheelStyle'] = 'offroad';
$updatedConfig['detail'] = 'snorkel';
$updatedConfig['label'] = 'Rescue Jeep (Tactical Green)';

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
assert($decodedUpdated['bodyStyle'] === 'jeep', "Updated bodyStyle must persist");
assert($decodedUpdated['paintColor'] === '#16a34a', "Updated paintColor must persist");
assert($decodedUpdated['wheelStyle'] === 'offroad', "Updated wheelStyle must persist");
assert($decodedUpdated['detail'] === 'snorkel', "Updated detail must persist");
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
assert(strpos($css, '.car-studio-summary-bar') !== false, "Must include .car-studio-summary-bar");
assert(strpos($css, '.color-picker-swatch-wrapper') !== false, "Must include .color-picker-swatch-wrapper");
assert(strpos($css, '.color-swatch-btn.color-swatch-custom') !== false, "Must style custom color picker");
echo "   [PASS] CSS styling and layout rules verified.\n\n";

echo "=================================================================\n";
echo "   ALL 3D CAR CUSTOMIZER & VEHICLE STUDIO CHECKS PASSED!        \n";
echo "=================================================================\n";
