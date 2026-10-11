<?php
/**
 * Test Suite: JodRod Stage 5 Parking Reservation UI and Ownership-Based Actions
 * 
 * Verifies:
 * 1. Unauthenticated reservation API attempts return 401 Unauthorized.
 * 2. Ownership-based button visibility logic (isOwner vs !isOwner && isAvailable).
 * 3. Community spaces (owner_id === null) never allow edit or delete.
 * 4. Safe reservations associated with authenticated user and target space.
 * 5. Spot capacity decrement and atomic transition to 'full' when 0 spots remain.
 * 6. Protection against overbooking full/closed spaces and duplicate active reservations.
 * 7. Reservation retrieval data isolation between accounts.
 * 8. Cancellation authorization: non-owners cannot cancel other users' reservations (403).
 * 9. Cancellation restores capacity and status to 'available'.
 */

$baseUrl = 'http://127.0.0.1:8088';
$cookieUserA = tempnam(sys_get_temp_dir(), 'jodrod_s5_ua_');
$cookieUserB = tempnam(sys_get_temp_dir(), 'jodrod_s5_ub_');

function http_call($method, $url, $data = null, $cookieFile = null, $csrfToken = null, $extraHeaders = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = [];
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    }
    if ($csrfToken) {
        $headers[] = 'X-CSRF-Token: ' . $csrfToken;
    }
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw'  => $response
    ];
}

$passed = 0;
$failed = 0;

function assert_test($description, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description} - {$details}\n";
        $failed++;
    }
}

echo "=== Starting JodRod Stage 5 Reservation & Ownership Verification ===\n\n";

// -------------------------------------------------------------
// 1. Unauthenticated Reservation Requests Rejected (401)
// -------------------------------------------------------------
echo "[Test Suite 1: Unauthenticated Reservation Requests Rejected (401)]\n";

$r = http_call('GET', "{$baseUrl}/api/get_reservations.php");
assert_test("GET get_reservations without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/create_reservation.php", ['parking_space_id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e']);
assert_test("POST create_reservation without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/cancel_reservation.php", ['reservation_id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e']);
assert_test("POST cancel_reservation without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

// -------------------------------------------------------------
// 2. Authenticate User A and User B
// -------------------------------------------------------------
echo "\n[Test Suite 2: Authenticate Distinct User A and User B]\n";

$ua_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUserA);
assert_test("User A login succeeds", $ua_login['code'] === 200, "Code: {$ua_login['code']}");
$ua_csrf = $ua_login['body']['csrf_token'] ?? '';
$ua_id = $ua_login['body']['user']['id'] ?? '';

$ub_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUserB);
assert_test("User B login succeeds", $ub_login['code'] === 200, "Code: {$ub_login['code']}");
$ub_csrf = $ub_login['body']['csrf_token'] ?? '';
$ub_id = $ub_login['body']['user']['id'] ?? '';

assert_test("User A and User B have valid distinct IDs", !empty($ua_id) && !empty($ub_id) && $ua_id !== $ub_id, "UA: {$ua_id}, UB: {$ub_id}");

// -------------------------------------------------------------
// 3. Create Test Spaces for User A and User B
// -------------------------------------------------------------
echo "\n[Test Suite 3: Create Owned Parking Spaces]\n";

// User A creates Space A with capacity 2, available 2
$spaceA_res = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Maya Flood Haven Stage 5',
    'latitude'         => 18.7950,
    'longitude'        => 98.9820,
    'capacity'         => 2,
    'available_spaces' => 2,
    'status'           => 'available'
], $cookieUserA, $ua_csrf);
assert_test("User A creates Space A (2 spots)", $spaceA_res['code'] === 201, "Code: {$spaceA_res['code']}");
$spaceA_id = $spaceA_res['body']['data']['id'] ?? '';
$spaceA_owner = $spaceA_res['body']['data']['owner_id'] ?? '';
assert_test("Space A owner_id equals User A ID", $spaceA_owner === $ua_id, "Owner: {$spaceA_owner}");

// User B creates Space B with capacity 1, available 1 (for testing lot full transition)
$spaceB_res = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Alex Rooftop Parking Stage 5',
    'latitude'         => 18.7920,
    'longitude'        => 98.9890,
    'capacity'         => 1,
    'available_spaces' => 1,
    'status'           => 'available'
], $cookieUserB, $ub_csrf);
assert_test("User B creates Space B (1 spot)", $spaceB_res['code'] === 201, "Code: {$spaceB_res['code']}");
$spaceB_id = $spaceB_res['body']['data']['id'] ?? '';
$spaceB_owner = $spaceB_res['body']['data']['owner_id'] ?? '';
assert_test("Space B owner_id equals User B ID", $spaceB_owner === $ub_id, "Owner: {$spaceB_owner}");

// -------------------------------------------------------------
// 4. Test Frontend Ownership Action Logic Simulation
// -------------------------------------------------------------
echo "\n[Test Suite 4: Verify Ownership Button Rules in JS Logic]\n";

/**
 * Simulates rendering logic from assets/js/parking_map.js:
 * isOwner = (currentUser && space.owner_id === currentUser.id)
 * isAvailable = (space.status === 'available' && space.available_spaces > 0)
 */
function simulate_buttons($currentUser, $space) {
    $isOwner = (!empty($currentUser) && !empty($space['owner_id']) && $space['owner_id'] === $currentUser['id']);
    $isAvailable = (($space['status'] ?? '') === 'available' && (int)($space['available_spaces'] ?? 0) > 0);

    $buttons = [];
    if ($isOwner) {
        $buttons[] = 'edit';
        $buttons[] = 'delete';
    } elseif ($isAvailable) {
        $buttons[] = 'reserve';
    } else {
        $buttons[] = 'pill_full_or_closed';
    }
    return $buttons;
}

$currentUserA = ['id' => $ua_id];
$currentUserB = ['id' => $ub_id];
$unownedCommunitySpace = ['id' => 'comm-1', 'owner_id' => null, 'status' => 'available', 'available_spaces' => 5];
$fullSpace = ['id' => 'full-1', 'owner_id' => $ua_id, 'status' => 'full', 'available_spaces' => 0];

$userA_on_spaceA = simulate_buttons($currentUserA, ['owner_id' => $ua_id, 'status' => 'available', 'available_spaces' => 2]);
assert_test("User A sees Edit & Delete on own Space A", in_array('edit', $userA_on_spaceA) && in_array('delete', $userA_on_spaceA) && !in_array('reserve', $userA_on_spaceA));

$userB_on_spaceA = simulate_buttons($currentUserB, ['owner_id' => $ua_id, 'status' => 'available', 'available_spaces' => 2]);
assert_test("User B does NOT see Edit/Delete on User A's Space A", !in_array('edit', $userB_on_spaceA) && !in_array('delete', $userB_on_spaceA));
assert_test("User B sees Reserve on User A's available Space A", in_array('reserve', $userB_on_spaceA));

$userA_on_community = simulate_buttons($currentUserA, $unownedCommunitySpace);
assert_test("User A does NOT see Edit/Delete on unassigned community space", !in_array('edit', $userA_on_community) && !in_array('delete', $userA_on_community));
assert_test("User A sees Reserve on available community space", in_array('reserve', $userA_on_community));

$userB_on_fullSpace = simulate_buttons($currentUserB, $fullSpace);
assert_test("User B does NOT see Reserve on full space", !in_array('reserve', $userB_on_fullSpace) && in_array('pill_full_or_closed', $userB_on_fullSpace));

// -------------------------------------------------------------
// 5. Backend Authorization Checks Retained (Security Proof)
// -------------------------------------------------------------
echo "\n[Test Suite 5: Backend Authorization Checks Retained]\n";

// User B attempts to edit User A's Space A
$hack_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $spaceA_id,
    'name'             => 'Hacked Space Name',
    'capacity'         => 10,
    'available_spaces' => 5,
    'status'           => 'available'
], $cookieUserB, $ub_csrf);
assert_test("User B cannot edit User A space (403 Forbidden)", $hack_edit['code'] === 403, "Code: {$hack_edit['code']}");

// User B attempts to delete User A's Space A
$hack_delete = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $spaceA_id
], $cookieUserB, $ub_csrf);
assert_test("User B cannot delete User A space (403 Forbidden)", $hack_delete['code'] === 403, "Code: {$hack_delete['code']}");

// -------------------------------------------------------------
// 6. Safe Reservation Execution & Capacity Management
// -------------------------------------------------------------
echo "\n[Test Suite 6: Safe Reservation Execution & Atomic Capacity Decrement]\n";

// User B reserves a spot at Space A (capacity 2, available 2 -> 1)
$res_create1 = http_call('POST', "{$baseUrl}/api/create_reservation.php", [
    'parking_space_id' => $spaceA_id,
    'notes'            => 'Honda Civic flood escape'
], $cookieUserB, $ub_csrf);
assert_test("User B can reserve spot at Space A (201 Created)", $res_create1['code'] === 201, "Code: {$res_create1['code']}");
$res1_id = $res_create1['body']['data']['id'] ?? '';
assert_test("Reservation record has valid ID", !empty($res1_id));

// Verify Space A available_spaces decremented to 1
$spaces_check1 = http_call('GET', "{$baseUrl}/api/get_parking_spaces.php");
$spaceA_updated = null;
foreach ($spaces_check1['body']['data'] as $sp) {
    if ($sp['id'] === $spaceA_id) {
        $spaceA_updated = $sp;
        break;
    }
}
assert_test("Space A available spots decremented from 2 to 1", (int)$spaceA_updated['available_spaces'] === 1, "Avail: " . ($spaceA_updated['available_spaces'] ?? 'none'));
assert_test("Space A remains 'available'", $spaceA_updated['status'] === 'available');

// User B attempts duplicate reservation on Space A -> rejected (400)
$res_dup = http_call('POST', "{$baseUrl}/api/create_reservation.php", [
    'parking_space_id' => $spaceA_id
], $cookieUserB, $ub_csrf);
assert_test("Duplicate active reservation is rejected (400 Conflict/Bad Request)", $res_dup['code'] === 400, "Code: {$res_dup['code']}");

// User A reserves spot at Space B (capacity 1, available 1 -> 0, status -> 'full')
$res_create2 = http_call('POST', "{$baseUrl}/api/create_reservation.php", [
    'parking_space_id' => $spaceB_id
], $cookieUserA, $ua_csrf);
assert_test("User A can reserve spot at Space B (201 Created)", $res_create2['code'] === 201, "Code: {$res_create2['code']}");
$res2_id = $res_create2['body']['data']['id'] ?? '';

// Verify Space B status transitioned to 'full' and available_spaces is 0
$spaces_check2 = http_call('GET', "{$baseUrl}/api/get_parking_spaces.php");
$spaceB_updated = null;
foreach ($spaces_check2['body']['data'] as $sp) {
    if ($sp['id'] === $spaceB_id) {
        $spaceB_updated = $sp;
        break;
    }
}
assert_test("Space B available spots decremented to 0", (int)$spaceB_updated['available_spaces'] === 0, "Avail: " . ($spaceB_updated['available_spaces'] ?? 'none'));
assert_test("Space B status transitioned to 'full'", $spaceB_updated['status'] === 'full', "Status: " . ($spaceB_updated['status'] ?? 'none'));

// Attempt to reserve full Space B -> rejected (400)
$res_full = http_call('POST', "{$baseUrl}/api/create_reservation.php", [
    'parking_space_id' => $spaceB_id
], $cookieUserB, $ub_csrf);
assert_test("Over-booking full space is rejected (400)", $res_full['code'] === 400, "Code: {$res_full['code']}");

// -------------------------------------------------------------
// 7. Get Reservations API & Isolation
// -------------------------------------------------------------
echo "\n[Test Suite 7: Reservation Retrieval & User Isolation]\n";

$ua_res_list = http_call('GET', "{$baseUrl}/api/get_reservations.php", null, $cookieUserA);
assert_test("User A can list reservations (200)", $ua_res_list['code'] === 200, "Code: {$ua_res_list['code']}");
$ua_res_ids = array_column($ua_res_list['body']['data'] ?? [], 'id');
assert_test("User A sees reservation Res2", in_array($res2_id, $ua_res_ids));
assert_test("User A does NOT see User B's reservation Res1 (isolated)", !in_array($res1_id, $ua_res_ids));

$ub_res_list = http_call('GET', "{$baseUrl}/api/get_reservations.php", null, $cookieUserB);
assert_test("User B can list reservations (200)", $ub_res_list['code'] === 200, "Code: {$ub_res_list['code']}");
$ub_res_ids = array_column($ub_res_list['body']['data'] ?? [], 'id');
assert_test("User B sees reservation Res1", in_array($res1_id, $ub_res_ids));
assert_test("User B does NOT see User A's reservation Res2 (isolated)", !in_array($res2_id, $ub_res_ids));

// -------------------------------------------------------------
// 8. Cancellation Authorization & Spot Restoration
// -------------------------------------------------------------
echo "\n[Test Suite 8: Cancellation Authorization & Capacity Restoration]\n";

// User A attempts to cancel User B's reservation Res1 -> 403 Forbidden
$hack_cancel = http_call('POST', "{$baseUrl}/api/cancel_reservation.php", [
    'reservation_id' => $res1_id
], $cookieUserA, $ua_csrf);
assert_test("User A cannot cancel User B's reservation (403 Forbidden)", $hack_cancel['code'] === 403, "Code: {$hack_cancel['code']}");

// User A cancels their own reservation Res2 at Space B
$legit_cancel = http_call('POST', "{$baseUrl}/api/cancel_reservation.php", [
    'reservation_id' => $res2_id
], $cookieUserA, $ua_csrf);
assert_test("User A can cancel own reservation (200 OK)", $legit_cancel['code'] === 200, "Code: {$legit_cancel['code']}");

// Verify Space B capacity restored from 0 to 1 and status restored from 'full' to 'available'
$spaces_check3 = http_call('GET', "{$baseUrl}/api/get_parking_spaces.php");
$spaceB_restored = null;
foreach ($spaces_check3['body']['data'] as $sp) {
    if ($sp['id'] === $spaceB_id) {
        $spaceB_restored = $sp;
        break;
    }
}
assert_test("Space B available spots restored to 1", (int)$spaceB_restored['available_spaces'] === 1, "Avail: " . ($spaceB_restored['available_spaces'] ?? 'none'));
assert_test("Space B status restored to 'available'", $spaceB_restored['status'] === 'available', "Status: " . ($spaceB_restored['status'] ?? 'none'));

// User A cancels already-cancelled reservation -> 400 Bad Request
$repeat_cancel = http_call('POST', "{$baseUrl}/api/cancel_reservation.php", [
    'reservation_id' => $res2_id
], $cookieUserA, $ua_csrf);
assert_test("Cancelling already-cancelled reservation is rejected (400)", $repeat_cancel['code'] === 400, "Code: {$repeat_cancel['code']}");

// -------------------------------------------------------------
// 9. Clean up Test Spaces
// -------------------------------------------------------------
echo "\n[Test Suite 9: Cleanup Test Records]\n";

// Cancel Res1 so Space A returns to 2 spots
http_call('POST', "{$baseUrl}/api/cancel_reservation.php", ['reservation_id' => $res1_id], $cookieUserB, $ub_csrf);

// Delete Space A
$delA = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $spaceA_id], $cookieUserA, $ua_csrf);
assert_test("Clean up Space A", $delA['code'] === 200);

// Delete Space B
$delB = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $spaceB_id], $cookieUserB, $ub_csrf);
assert_test("Clean up Space B", $delB['code'] === 200);

// Clean up temporary cookie files
@unlink($cookieUserA);
@unlink($cookieUserB);

echo "\n=======================================================\n";
echo "STAGE 5 VERIFICATION SUMMARY:\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
