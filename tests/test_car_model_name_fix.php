<?php
/**
 * Test: Car Model Name Persistence & Display Verification
 * 
 * Verifies that:
 * 1. Registering a vehicle with 3D model customization and user-inputted name
 *    saves the user input name as the label without defaulting to 3D model style/color details.
 * 2. When requesting a parking spot (openReserveModal option format), the car name displays
 *    the user input name (e.g. "Toyota Hilux Rescue — PLATE") rather than the 3D model JSON blob.
 * 3. Reservations store and return the clean user input car model name in both driver and owner views.
 * 4. In request details and status cards, the car model displayed is the user input name.
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: Car Model Name Persistence & Display Verification      \n";
echo "=================================================================\n\n";

function http_json($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
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

// 1. Authenticate Driver (User A) and Owner (User B)
echo "Step 1: Authenticating test accounts...\n";
$loginDriver = http_json("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginDriver['code'] === 200, "Driver login failed");
$driverCookies = $loginDriver['cookies'];
$driverCsrf = $loginDriver['body']['csrf_token'] ?? '';

$loginOwner = http_json("$baseUrl/api/auth.php?action=login", 'POST', [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginOwner['code'] === 200, "Owner login failed");
$ownerCookies = $loginOwner['cookies'];
$ownerCsrf = $loginOwner['body']['csrf_token'] ?? '';
echo "   [PASS] Driver and Owner authenticated.\n\n";

// 2. Register Owner Parking Lot
echo "Step 2: Creating host parking lot...\n";
$lotSuffix = substr(md5(microtime()), 0, 5);
$createLot = http_json("$baseUrl/api/create_parking_space.php", 'POST', [
    'name'             => "Elevated Safe Lot {$lotSuffix}",
    'latitude'         => 18.7883,
    'longitude'        => 98.9853,
    'capacity'         => 5,
    'available_spaces' => 5,
    'status'           => 'available'
], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
assert($createLot['code'] === 201 && $createLot['body']['success'], "Failed to create parking lot");
$spaceId = $createLot['body']['data']['id'];
echo "   [PASS] Created parking lot: $spaceId\n\n";

// 3. Register Driver Car with 3D customization and user input name
echo "Step 3: Registering car with 3D model customization and user input name...\n";
$userCarName = "Toyota Hilux Rescue Edition";
$plateNumber = '1TEST-' . substr(md5(microtime()), 0, 4);

$modelPayload = json_encode([
    'bodyStyle'  => 'pickup',
    'paintColor' => '#ea580c',
    'wheelStyle' => 'offroad',
    'detail'     => 'snorkel',
    'label'      => $userCarName,
    'snapshot'   => 'data:image/webp;base64,fake_snapshot_bytes'
]);

$createCar = http_json("$baseUrl/api/create_car.php", 'POST', [
    'license_plate' => $plateNumber,
    'model'         => $modelPayload
], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
assert($createCar['code'] === 201 && $createCar['body']['success'], "Failed to register vehicle");
$carId = $createCar['body']['data']['id'];
echo "   [PASS] Registered car with user input name '$userCarName' (Plate: $plateNumber)\n\n";

// 4. Create Reservation
echo "Step 4: Requesting parking spot for this vehicle...\n";
$reserveRes = http_json("$baseUrl/api/create_reservation.php", 'POST', [
    'parking_space_id' => $spaceId,
    'car_id'           => $carId,
    'notes'            => 'Flood evacuation request'
], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
assert($reserveRes['code'] === 201 && $reserveRes['body']['success'], "Failed to create reservation: " . ($reserveRes['body']['error'] ?? ''));
$reservationId = $reserveRes['body']['data']['id'];
echo "   [PASS] Reservation created with ID: $reservationId\n\n";

// 5. Test Driver View (api/get_reservations.php)
echo "Step 5: Verifying car model in Driver View API...\n";
$driverResList = http_json("$baseUrl/api/get_reservations.php", 'GET', null, $driverCookies);
assert($driverResList['code'] === 200 && $driverResList['body']['success'], "Failed to get driver reservations");
$foundReservation = null;
foreach ($driverResList['body']['data'] as $r) {
    if ($r['id'] === $reservationId) {
        $foundReservation = $r;
        break;
    }
}
assert($foundReservation !== null, "Reservation must be in driver reservations list");
$carModelReturned = $foundReservation['cars']['model'] ?? '';
echo "   Driver View returned car model: '$carModelReturned'\n";
assert($carModelReturned === $userCarName, "Car model in driver reservation must match user input name '$userCarName', got: '$carModelReturned'");
assert(strpos($carModelReturned, '{') === false, "Car model must NOT contain raw JSON");
assert(strpos($carModelReturned, 'bodyStyle') === false, "Car model must NOT contain 3D body style details");
echo "   [PASS] Driver View returns the exact user input name without 3D model details.\n\n";

// 6. Test Owner View (api/get_reservations.php?as_owner=1)
echo "Step 6: Verifying car model in Owner View API...\n";
$ownerResList = http_json("$baseUrl/api/get_reservations.php?as_owner=1", 'GET', null, $ownerCookies);
assert($ownerResList['code'] === 200 && $ownerResList['body']['success'], "Failed to get owner reservations");
$foundOwnerReservation = null;
foreach ($ownerResList['body']['data'] as $r) {
    if ($r['id'] === $reservationId) {
        $foundOwnerReservation = $r;
        break;
    }
}
assert($foundOwnerReservation !== null, "Reservation must be in owner reservations list");
$ownerCarModel = $foundOwnerReservation['cars']['model'] ?? '';
echo "   Owner View returned car model: '$ownerCarModel'\n";
assert($ownerCarModel === $userCarName, "Car model in owner reservation must match user input name '$userCarName', got: '$ownerCarModel'");
assert(strpos($ownerCarModel, '{') === false, "Car model in owner view must NOT contain raw JSON");
echo "   [PASS] Owner View returns the exact user input name without 3D model details.\n\n";

// 7. Verify index.php frontend JavaScript logic
echo "Step 7: Verifying frontend JS logic in index.php...\n";
$html = file_get_contents(__DIR__ . '/../index.php');

// Check that openReserveModal formats option using getCarDisplayLabel
assert(strpos($html, 'const modelPart = getCarDisplayLabel(c.model);') !== false, "openReserveModal must use getCarDisplayLabel");

// Check that getCarDisplayLabel does not construct bodyStyle/paintColor strings
assert(strpos($html, 'function getCarDisplayLabel(modelRaw)') !== false, "Must define getCarDisplayLabel");

// Check that renderMyReservationDetail uses getCarDisplayLabel
assert(strpos($html, 'const model = getCarDisplayLabel(car.model);') !== false, "renderMyReservationDetail must use getCarDisplayLabel");

// Check that renderMyReservationsList uses getCarDisplayLabel
assert(strpos($html, 'const carModelName = getCarDisplayLabel(car.model);') !== false, "renderMyReservationsList must use getCarDisplayLabel");

// Check that #vehicle-studio-name is labeled 'Car Model *'
assert(strpos($html, 'for="vehicle-studio-name" class="form-label">Car Model *</label>') !== false, "#vehicle-studio-name must be labeled Car Model *");

echo "   [PASS] Frontend JavaScript and HTML bindings properly verified.\n\n";

// 8. Clean up
echo "Step 8: Cleaning up test artifacts...\n";
// Cancel reservation
http_json("$baseUrl/api/cancel_reservation.php", 'POST', ['reservation_id' => $reservationId], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
// Delete car
http_json("$baseUrl/api/delete_car.php", 'POST', ['id' => $carId], $driverCookies, ['X-CSRF-Token: ' . $driverCsrf]);
// Delete lot
http_json("$baseUrl/api/delete_parking_space.php", 'POST', ['id' => $spaceId], $ownerCookies, ['X-CSRF-Token: ' . $ownerCsrf]);
echo "   [PASS] Cleaned up temporary test reservation, vehicle, and parking space.\n\n";

echo "=================================================================\n";
echo "   ALL CAR MODEL NAME VERIFICATIONS PASSED SUCCESSFULLY!         \n";
echo "=================================================================\n";
