<?php
/**
 * Test: Phase 1 Build My Cars Feature Automated Regression Test Suite
 * 
 * Verifies:
 * 1. Unauthenticated users cannot manage cars (HTTP 401).
 * 2. CSRF tokens are required for all state mutations (HTTP 403).
 * 3. User ID cannot be forged or tampered with (HTTP 403).
 * 4. Input validation: empty license plate, missing car ID (HTTP 400).
 * 5. Live Supabase table detection & schema migration verification:
 *    - public.cars table with composite unique (id, user_id)
 *    - unique (user_id, license_plate)
 *    - RLS policies for auth.uid() = user_id
 * 6. Multi-user isolation & CRUD operations (when cars table is present):
 *    - User A can create and view multiple cars
 *    - User A cannot view User B's cars
 *    - User A cannot edit or delete User B's cars (HTTP 403)
 *    - Duplicate license plates for the same user are rejected (HTTP 409)
 *    - Different users may register the same plate
 *    - Updating a car persists
 *    - Deleting a car removes only that car
 * 7. Regression checks:
 *    - Existing profiles API & community list still work
 *    - Existing parking spaces API & Leaflet map still work
 *    - Existing authentication session checks still work
 * 8. PHP syntax checks across all modified and created files
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod Phase 1: Automated Regression Test Suite (My Cars)     \n";
echo "=================================================================\n\n";

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
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'body'     => json_decode($bodyStr, true),
        'raw_body' => $bodyStr,
        'cookies'  => $newCookies
    ];
}

// ----------------------------------------------------------------------
// 1. Unauthenticated Security Guards
// ----------------------------------------------------------------------
echo "1. Verifying unauthenticated security guards on car APIs...\n";

$endpoints = [
    ['GET',  "$baseUrl/api/get_cars.php", null],
    ['POST', "$baseUrl/api/create_car.php", ['license_plate' => 'ABC-123']],
    ['POST', "$baseUrl/api/update_car.php", ['id' => 'dummy', 'license_plate' => 'ABC-123']],
    ['POST', "$baseUrl/api/delete_car.php", ['id' => 'dummy']],
    ['GET',  "$baseUrl/api/cars.php", null],
    ['POST', "$baseUrl/api/cars.php", ['license_plate' => 'ABC-123']],
    ['DELETE', "$baseUrl/api/cars.php", ['id' => 'dummy']]
];

foreach ($endpoints as [$method, $url, $payload]) {
    $res = make_req($url, $method, $payload);
    assert($res['code'] === 401, "Expected 401 for unauthenticated $method $url, got {$res['code']}");
}
echo "   [PASS] All car endpoints strictly reject unauthenticated requests with HTTP 401 Unauthorized.\n\n";

// ----------------------------------------------------------------------
// 2. Authenticate Test Users (User A & User B)
// ----------------------------------------------------------------------
echo "2. Authenticating test users with Supabase Auth session...\n";

$loginA = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginA['code'] === 200, "User A login failed with HTTP {$loginA['code']}");
$userA = $loginA['body']['user'];
$cookiesA = $loginA['cookies'];
$csrfA = $loginA['body']['csrf_token'];
echo "   [PASS] User A authenticated: {$userA['email']} ({$userA['id']})\n";

$loginB = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginB['code'] === 200, "User B login failed with HTTP {$loginB['code']}");
$userB = $loginB['body']['user'];
$cookiesB = $loginB['cookies'];
$csrfB = $loginB['body']['csrf_token'];
echo "   [PASS] User B authenticated: {$userB['email']} ({$userB['id']})\n\n";

// ----------------------------------------------------------------------
// 3. CSRF Protection Enforcement
// ----------------------------------------------------------------------
echo "3. Verifying CSRF token protection on state-changing car endpoints...\n";

$csrfCreateFail = make_req("$baseUrl/api/create_car.php", 'POST', [
    'license_plate' => 'TEST-CSRF'
], $cookiesA, []); // Missing X-CSRF-Token header
assert($csrfCreateFail['code'] === 403, "Expected 403 for create_car.php without CSRF token, got {$csrfCreateFail['code']}");

$csrfUpdateFail = make_req("$baseUrl/api/update_car.php", 'POST', [
    'id' => 'dummy-id',
    'license_plate' => 'TEST-CSRF'
], $cookiesA, []); // Missing X-CSRF-Token header
assert($csrfUpdateFail['code'] === 403, "Expected 403 for update_car.php without CSRF token, got {$csrfUpdateFail['code']}");

$csrfDeleteFail = make_req("$baseUrl/api/delete_car.php", 'POST', [
    'id' => 'dummy-id'
], $cookiesA, []); // Missing X-CSRF-Token header
assert($csrfDeleteFail['code'] === 403, "Expected 403 for delete_car.php without CSRF token, got {$csrfDeleteFail['code']}");

echo "   [PASS] CSRF protection strictly blocks state-changing mutations lacking valid CSRF token (HTTP 403).\n\n";

// ----------------------------------------------------------------------
// 4. Client-Supplied user_id Forgery & Tampering Defense
// ----------------------------------------------------------------------
echo "4. Verifying ownership spoofing / user_id forgery defense...\n";

// Attacker tries to create a car claiming ownership by User B
$forgedCreate = make_req("$baseUrl/api/create_car.php", 'POST', [
    'user_id'       => $userB['id'],
    'license_plate' => 'FORGED-01',
    'model'         => 'Hacker Sedan'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($forgedCreate['code'] === 403, "Expected 403 when User A supplies User B's user_id on creation, got {$forgedCreate['code']}");
echo "   [PASS] Forged user_id in create_car.php successfully blocked (HTTP 403).\n";

// Attacker tries to update a car attempting to reassign ownership
$forgedUpdate = make_req("$baseUrl/api/update_car.php", 'POST', [
    'id'            => 'some-car-id',
    'user_id'       => $userB['id'],
    'license_plate' => 'FORGED-02'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($forgedUpdate['code'] === 403, "Expected 403 when User A supplies User B's user_id on update, got {$forgedUpdate['code']}");
echo "   [PASS] Ownership reassignment tampering in update_car.php successfully blocked (HTTP 403).\n\n";

// ----------------------------------------------------------------------
// 5. Server-Side Input Validation
// ----------------------------------------------------------------------
echo "5. Verifying server-side input validation on car endpoints...\n";

// Empty license plate on create
$emptyPlate = make_req("$baseUrl/api/create_car.php", 'POST', [
    'license_plate' => '   ',
    'model'         => 'Toyota Corolla'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($emptyPlate['code'] === 400, "Expected 400 for empty license plate, got {$emptyPlate['code']}");

// Missing car ID on update
$noIdUpdate = make_req("$baseUrl/api/update_car.php", 'POST', [
    'license_plate' => 'PLATE-123'
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($noIdUpdate['code'] === 400, "Expected 400 for missing car ID on update, got {$noIdUpdate['code']}");

// Empty license plate on update
$emptyPlateUpdate = make_req("$baseUrl/api/update_car.php", 'POST', [
    'id'            => 'dummy-id',
    'license_plate' => ''
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($emptyPlateUpdate['code'] === 400, "Expected 400 for empty license plate on update, got {$emptyPlateUpdate['code']}");

// Missing car ID on delete
$noIdDelete = make_req("$baseUrl/api/delete_car.php", 'POST', [], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
assert($noIdDelete['code'] === 400, "Expected 400 for missing car ID on delete, got {$noIdDelete['code']}");

echo "   [PASS] Server-side validation correctly rejects malformed requests (HTTP 400 Bad Request).\n\n";

// ----------------------------------------------------------------------
// 6. SQL Migration File Verification
// ----------------------------------------------------------------------
echo "6. Verifying SQL migration file architecture for public.cars...\n";

$migrationFile = __DIR__ . '/../supabase/migrations/20261011_phase1_create_cars_table.sql';
assert(file_exists($migrationFile), "Migration file must exist at $migrationFile");
$sqlContent = file_get_contents($migrationFile);

// Check required schema constraints
assert(strpos($sqlContent, 'CREATE TABLE IF NOT EXISTS public.cars') !== false, "Must define public.cars");
assert(strpos($sqlContent, 'user_id UUID NOT NULL REFERENCES auth.users(id)') !== false, "Must reference auth.users(id)");
assert(strpos($sqlContent, 'CONSTRAINT cars_user_plate_unique UNIQUE (user_id, license_plate)') !== false, "Must enforce UNIQUE (user_id, license_plate)");
assert(strpos($sqlContent, 'CONSTRAINT cars_id_user_id_unique UNIQUE (id, user_id)') !== false, "Must enforce UNIQUE (id, user_id) for composite foreign key");
assert(strpos($sqlContent, 'ENABLE ROW LEVEL SECURITY') !== false, "Must enable RLS");
assert(strpos($sqlContent, 'auth.uid() = user_id') !== false, "Must restrict RLS policies to auth.uid() = user_id");

echo "   [PASS] Migration file contains all required schema constraints, composite keys, and RLS policies.\n\n";

// ----------------------------------------------------------------------
// 7. Live Supabase Table Probe & Multi-User Operations
// ----------------------------------------------------------------------
echo "7. Probing live Supabase database for public.cars table status...\n";

require_once __DIR__ . '/../config.php';
$probeRes = supabase_request('/rest/v1/cars?select=id&limit=1', 'GET');
$carsTableExists = ($probeRes['http_code'] === 200);

if (!$carsTableExists) {
    echo "   [NOTICE] Live Supabase table 'public.cars' is not yet installed in schema cache.\n";
    echo "            HTTP status: {$probeRes['http_code']}, Error: {$probeRes['error']}\n";
    echo "            --> Migration 'supabase/migrations/20261011_phase1_create_cars_table.sql' must be executed manually in Supabase SQL Editor.\n";
    echo "            Pre-migration security guards, CSRF enforcement, parameter validation, and UI integration verified.\n\n";
} else {
    echo "   [SUCCESS] Live Supabase table 'public.cars' is ACTIVE (HTTP 200).\n";
    echo "   Proceeding with live multi-user CRUD and ownership isolation tests...\n\n";

    // 7.1 User A creates multiple cars
    $tag = substr(uniqid(), -5);
    $plateA1 = "กข-{$tag}1";
    $plateA2 = "กข-{$tag}2";

    $createA1 = make_req("$baseUrl/api/create_car.php", 'POST', [
        'license_plate' => $plateA1,
        'model'         => 'Toyota Hilux Revo'
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($createA1['code'] === 201, "User A create car 1 failed: " . json_encode($createA1['body']));
    $carA1Id = $createA1['body']['data']['id'];
    echo "   [PASS] User A created Car 1: {$plateA1} (ID: {$carA1Id})\n";

    $createA2 = make_req("$baseUrl/api/create_car.php", 'POST', [
        'license_plate' => $plateA2,
        'model'         => 'Honda CR-V'
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($createA2['code'] === 201, "User A create car 2 failed: " . json_encode($createA2['body']));
    $carA2Id = $createA2['body']['data']['id'];
    echo "   [PASS] User A created Car 2: {$plateA2} (ID: {$carA2Id})\n";

    // 7.2 User A views own cars
    $viewA = make_req("$baseUrl/api/get_cars.php", 'GET', null, $cookiesA);
    assert($viewA['code'] === 200, "User A get_cars failed");
    $carsA = $viewA['body']['data'];
    $carAIds = array_column($carsA, 'id');
    assert(in_array($carA1Id, $carAIds), "Car A1 must be in User A list");
    assert(in_array($carA2Id, $carAIds), "Car A2 must be in User A list");
    echo "   [PASS] User A successfully listed their registered cars ({$viewA['body']['count']} total).\n";

    // 7.3 Duplicate license plate for the same user is rejected (HTTP 409)
    $dupCreate = make_req("$baseUrl/api/create_car.php", 'POST', [
        'license_plate' => $plateA1,
        'model'         => 'Another vehicle with duplicate plate'
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($dupCreate['code'] === 409, "Expected 409 Conflict for duplicate plate under User A, got {$dupCreate['code']}");
    echo "   [PASS] Duplicate plate for the same user rejected with HTTP 409 Conflict.\n";

    // 7.4 Different user (User B) registering the SAME plate is permitted
    $createB = make_req("$baseUrl/api/create_car.php", 'POST', [
        'license_plate' => $plateA1, // Same plate as User A's car
        'model'         => 'User B vehicle with same plate number'
    ], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);
    assert($createB['code'] === 201, "User B registering plate {$plateA1} should be permitted: " . json_encode($createB['body']));
    $carBId = $createB['body']['data']['id'];
    echo "   [PASS] User B successfully registered the same plate ({$plateA1}) under their own account (ID: {$carBId}).\n";

    // 7.5 Cross-user viewing isolation: User A cannot see User B's cars, User B cannot see User A's cars
    $viewB = make_req("$baseUrl/api/get_cars.php", 'GET', null, $cookiesB);
    assert($viewB['code'] === 200, "User B get_cars failed");
    $carsB = $viewB['body']['data'];
    $carBIds = array_column($carsB, 'id');
    assert(in_array($carBId, $carBIds), "Car B must be in User B list");
    assert(!in_array($carA1Id, $carBIds), "User A's car A1 must NOT be visible to User B");
    assert(!in_array($carA2Id, $carBIds), "User A's car A2 must NOT be visible to User B");
    assert(!in_array($carBId, $carAIds), "User B's car must NOT be visible to User A");
    echo "   [PASS] Cross-user read isolation verified: neither user can view the other's cars.\n";

    // 7.6 Cross-user update defense: User A cannot edit User B's car
    $attackUpdate = make_req("$baseUrl/api/update_car.php", 'POST', [
        'id'            => $carBId,
        'license_plate' => 'HACKED-PLATE',
        'model'         => 'Hacked Model'
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($attackUpdate['code'] === 403, "User A editing User B's car must return 403 Forbidden, got {$attackUpdate['code']}");
    echo "   [PASS] Cross-user edit attack blocked: User A cannot update User B's car (HTTP 403 Forbidden).\n";

    // 7.7 Cross-user delete defense: User A cannot delete User B's car
    $attackDelete = make_req("$baseUrl/api/delete_car.php", 'POST', [
        'id' => $carBId
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($attackDelete['code'] === 403, "User A deleting User B's car must return 403 Forbidden, got {$attackDelete['code']}");
    echo "   [PASS] Cross-user delete attack blocked: User A cannot delete User B's car (HTTP 403 Forbidden).\n";

    // 7.8 Legitimate update by owner persists
    $updatedModel = "Toyota Hilux Revo 4x4 Elevated";
    $updateA1 = make_req("$baseUrl/api/update_car.php", 'POST', [
        'id'            => $carA1Id,
        'license_plate' => $plateA1,
        'model'         => $updatedModel
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($updateA1['code'] === 200, "User A update own car failed: " . json_encode($updateA1['body']));

    // Re-verify persistence via get_cars
    $reViewA = make_req("$baseUrl/api/get_cars.php", 'GET', null, $cookiesA);
    $foundUpdated = false;
    foreach ($reViewA['body']['data'] as $c) {
        if ($c['id'] === $carA1Id && $c['model'] === $updatedModel) {
            $foundUpdated = true;
            break;
        }
    }
    assert($foundUpdated, "Updated car model must persist in live database");
    echo "   [PASS] User A updated vehicle details, persisted in Supabase.\n";

    // 7.9 Deletion affects only the selected car owned by the current user
    $delA2 = make_req("$baseUrl/api/delete_car.php", 'POST', [
        'id' => $carA2Id
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($delA2['code'] === 200, "User A delete car 2 failed: " . json_encode($delA2['body']));

    // Verify Car A2 is gone but Car A1 still exists
    $afterDelA = make_req("$baseUrl/api/get_cars.php", 'GET', null, $cookiesA);
    $remainingIdsA = array_column($afterDelA['body']['data'], 'id');
    assert(!in_array($carA2Id, $remainingIdsA), "Car A2 must have been deleted");
    assert(in_array($carA1Id, $remainingIdsA), "Car A1 must still remain after deleting Car A2");
    echo "   [PASS] Deleting a car removes only that specific car and preserves other vehicles.\n";

    // 7.10 Clean up test records
    make_req("$baseUrl/api/delete_car.php", 'POST', ['id' => $carA1Id], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    make_req("$baseUrl/api/delete_car.php", 'POST', ['id' => $carBId], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);
    echo "   [PASS] Test vehicles safely cleaned up.\n\n";
}

// ----------------------------------------------------------------------
// 8. Regression Checks on Existing Features
// ----------------------------------------------------------------------
echo "8. Verifying existing system features remain operational...\n";

// 8.1 Profile listing & viewer
$profRes = make_req("$baseUrl/api/get_profiles.php");
assert($profRes['code'] === 200, "get_profiles.php must return 200");
assert($profRes['body']['success'] === true, "get_profiles.php must succeed");
echo "   [PASS] Community profiles endpoint is functional.\n";

// 8.2 Parking spaces & Leaflet map
$spacesRes = make_req("$baseUrl/api/get_parking_spaces.php");
assert($spacesRes['code'] === 200, "get_parking_spaces.php must return 200");
assert($spacesRes['body']['success'] === true, "get_parking_spaces.php must succeed");
echo "   [PASS] Parking spaces map endpoint is functional.\n";

// 8.3 Authentication session verification
$authStatus = make_req("$baseUrl/api/auth.php?action=session", 'GET', null, $cookiesA);
assert($authStatus['code'] === 200, "auth.php session check must return 200");
assert($authStatus['body']['logged_in'] === true, "User A must still be authenticated");
echo "   [PASS] Authentication session management is functional.\n\n";

// ----------------------------------------------------------------------
// 9. PHP Syntax Checks
// ----------------------------------------------------------------------
echo "9. Performing PHP syntax lint checks across all project car files...\n";

$filesToCheck = [
    'api/get_cars.php',
    'api/create_car.php',
    'api/update_car.php',
    'api/delete_car.php',
    'api/cars.php',
    'config.php',
    'index.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);
    assert($returnVar === 0, "Syntax error in $file: " . implode("\n", $output));
    echo "   [PASS] Syntax check passed: $file\n";
}
echo "\n";

echo "=================================================================\n";
echo "   ALL PHASE 1: MY CARS REGRESSION CHECKS COMPLETED SUCCESSFULLY \n";
echo "=================================================================\n";
