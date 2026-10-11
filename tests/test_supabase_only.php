<?php
/**
 * Test: Supabase Only Storage & No Fallbacks Verification
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=== JodRod Supabase Only Storage Verification ===\n\n";

// Helper for HTTP requests
function send_request($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
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

    // Extract cookies
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

// 1. Check Unauthenticated calls to reservation endpoints
echo "1. Checking unauthenticated protection on reservation endpoints...\n";
$unauthGetRes = send_request("$baseUrl/api/get_reservations.php");
echo "   GET api/get_reservations.php => HTTP {$unauthGetRes['code']}: " . ($unauthGetRes['body']['error'] ?? '') . "\n";
assert($unauthGetRes['code'] === 401, "Expected 401 for unauthenticated reservations request");

$unauthCreateRes = send_request("$baseUrl/api/create_reservation.php", 'POST', ['parking_space_id' => 'abc']);
echo "   POST api/create_reservation.php => HTTP {$unauthCreateRes['code']}: " . ($unauthCreateRes['body']['error'] ?? '') . "\n";
assert($unauthCreateRes['code'] === 401, "Expected 401 for unauthenticated create reservation");

$unauthCars = send_request("$baseUrl/api/cars.php");
echo "   GET api/cars.php => HTTP {$unauthCars['code']}: " . ($unauthCars['body']['error'] ?? '') . "\n";
assert($unauthCars['code'] === 401, "Expected 401 for unauthenticated cars request");

echo "   [PASS] All endpoints strictly enforce authentication.\n\n";

// 2. Login User A
echo "2. Logging in test user A...\n";
$loginRes = send_request("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);

if ($loginRes['code'] !== 200 || empty($loginRes['body']['user'])) {
    echo "   [FAIL] Could not log in test user: " . $loginRes['raw_body'] . "\n";
    exit(1);
}

$userACookies = $loginRes['cookies'];
$csrfToken = $loginRes['body']['csrf_token'] ?? '';
echo "   [PASS] User A logged in successfully. User ID: {$loginRes['body']['user']['id']}\n\n";

// 3. Test CSRF Protection
echo "3. Testing CSRF enforcement on Cars and Reservations endpoints...\n";
$noCsrfCar = send_request("$baseUrl/api/cars.php", 'POST', ['license_plate' => 'TEST-123'], $userACookies);
echo "   POST api/cars.php (no CSRF) => HTTP {$noCsrfCar['code']}: " . ($noCsrfCar['body']['error'] ?? '') . "\n";
assert(in_array($noCsrfCar['code'], [401, 403]), "Expected 401/403 when CSRF is missing");

$noCsrfRes = send_request("$baseUrl/api/create_reservation.php", 'POST', ['parking_space_id' => 'abc'], $userACookies);
echo "   POST api/create_reservation.php (no CSRF) => HTTP {$noCsrfRes['code']}: " . ($noCsrfRes['body']['error'] ?? '') . "\n";
assert(in_array($noCsrfRes['code'], [401, 403]), "Expected 401/403 when CSRF is missing");
echo "   [PASS] CSRF strictly enforced.\n\n";

// 4. Test Cars and Reservations behavior with Supabase (without fallback)
echo "4. Testing Supabase-only behavior (confirming no silent local saving)...\n";
$headersWithCsrf = ['X-CSRF-Token: ' . $csrfToken];

// GET Cars
$getCarsRes = send_request("$baseUrl/api/cars.php", 'GET', null, $userACookies, $headersWithCsrf);
echo "   GET api/cars.php => HTTP {$getCarsRes['code']}: " . json_encode($getCarsRes['body']) . "\n";

// GET Reservations
$getRes = send_request("$baseUrl/api/get_reservations.php", 'GET', null, $userACookies, $headersWithCsrf);
echo "   GET api/get_reservations.php => HTTP {$getRes['code']}: " . json_encode($getRes['body']) . "\n";

// POST Car attempt
$postCarRes = send_request("$baseUrl/api/cars.php", 'POST', [
    'license_plate' => 'TEST-999',
    'model' => 'Honda Civic',
    'color' => 'White'
], $userACookies, $headersWithCsrf);
echo "   POST api/cars.php => HTTP {$postCarRes['code']}: " . json_encode($postCarRes['body']) . "\n";

// POST Reservation attempt
$postRes = send_request("$baseUrl/api/create_reservation.php", 'POST', [
    'parking_space_id' => '00000000-0000-0000-0000-000000000000'
], $userACookies, $headersWithCsrf);
echo "   POST api/create_reservation.php => HTTP {$postRes['code']}: " . json_encode($postRes['body']) . "\n";

// 5. Verify NO files were created in data/
echo "\n5. Verifying no local files were created in data/...\n";
$dataFiles = array_diff(scandir(__DIR__ . '/../data'), ['.', '..', '.gitkeep']);
if (empty($dataFiles)) {
    echo "   [PASS] data/ directory contains 0 files. Zero fallback persistence.\n";
} else {
    echo "   [FAIL] Local files were found in data/: " . implode(', ', $dataFiles) . "\n";
    exit(1);
}

echo "\n=== ALL VERIFICATION CHECKS PASSED ===\n";
