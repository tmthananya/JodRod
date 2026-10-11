<?php
/**
 * Test Suite: JodRod Stage 3 Full Security & Cross-User Defense
 * Verifies authentication, profile & avatar ownership, parking space ownership,
 * community space protection, logout-login persistence, and cross-user denial.
 */

$baseUrl = 'http://127.0.0.1:8088';
$cookieUser1 = tempnam(sys_get_temp_dir(), 'jodrod_s3_u1_');
$cookieUser2 = tempnam(sys_get_temp_dir(), 'jodrod_s3_u2_');

function http_call($method, $url, $data = null, $cookieFile = null, $csrfToken = null) {
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

echo "=== Starting JodRod Stage 3 Multi-User Verification ===\n\n";

// -------------------------------------------------------------
// Test Group 1: Unauthenticated Requests Rejected (401)
// -------------------------------------------------------------
echo "[Group 1: Unauthenticated Requests]\n";

$r = http_call('POST', "{$baseUrl}/api/create_profile.php", ['name' => 'Ghost']);
assert_test("POST create_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_profile.php", ['id' => 'any-id', 'name' => 'Ghost']);
assert_test("POST update_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => 'any-id']);
assert_test("POST delete_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/create_parking_space.php", ['name' => 'Ghost Lot']);
assert_test("POST create_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_parking_space.php", ['id' => 'any-id', 'name' => 'Ghost']);
assert_test("POST update_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => 'any-id']);
assert_test("POST delete_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

// -------------------------------------------------------------
// Test Group 2: User 1 Login & Avatar 1 Creation
// -------------------------------------------------------------
echo "\n[Group 2: User 1 Lifecycle & Persistence]\n";

$u1_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage2_test_user1@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUser1);
assert_test("User 1 login successful", $u1_login['code'] === 200, "Code: {$u1_login['code']}");
$u1_csrf = $u1_login['body']['csrf_token'] ?? '';
$u1_id = $u1_login['body']['user']['id'] ?? '';

// Create 3D Avatar for User 1
$avatar1_data = json_encode([
    'gender'       => 'female',
    'feature'      => 'ribbon',
    'hair'         => 'ponytail',
    'hairColor'    => '#261e1b',
    'clothes'      => 'vest',
    'clothesColor' => '#ea580c',
    'skinColor'    => '#fed7aa',
    'snapshot'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
]);

$p1_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Commander Maya',
    'avatar' => $avatar1_data
], $cookieUser1, $u1_csrf);
assert_test("User 1 creates Avatar 1 (201)", $p1_create['code'] === 201, "Code: {$p1_create['code']}");
$p1_id = $p1_create['body']['data']['id'] ?? '';
assert_test("Avatar 1 stamped with user_id = User 1 UUID", ($p1_create['body']['data']['user_id'] ?? '') === $u1_id, "User ID: " . ($p1_create['body']['data']['user_id'] ?? 'none'));

// Verify Profile & Avatar Persistence across Logout & Login
echo "  Testing persistence across session logout/re-login...\n";
$u1_logout = http_call('POST', "{$baseUrl}/api/auth.php?action=logout", null, $cookieUser1, $u1_csrf);
assert_test("User 1 logged out", $u1_logout['code'] === 200, "Code: {$u1_logout['code']}");

$u1_relogin = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage2_test_user1@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUser1);
assert_test("User 1 re-login successful", $u1_relogin['code'] === 200, "Code: {$u1_relogin['code']}");
$u1_csrf = $u1_relogin['body']['csrf_token'] ?? '';

$p1_check = http_call('GET', "{$baseUrl}/api/get_profiles.php?action=me", null, $cookieUser1);
assert_test("Avatar 1 persists and loads correctly after re-login", $p1_check['code'] === 200 && ($p1_check['body']['data']['name'] ?? '') === 'Commander Maya', "Name: " . ($p1_check['body']['data']['name'] ?? 'none'));

// User 1 Updates Avatar 1
$avatar1_updated = json_encode([
    'gender'       => 'female',
    'feature'      => 'flower',
    'hair'         => 'ponytail',
    'hairColor'    => '#451a03',
    'clothes'      => 'raincoat',
    'clothesColor' => '#facc15',
    'skinColor'    => '#fed7aa',
    'snapshot'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
]);

$p1_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $p1_id,
    'name'   => 'Commander Maya - Rain Rescue',
    'avatar' => $avatar1_updated
], $cookieUser1, $u1_csrf);
assert_test("User 1 updates Avatar 1 (200)", $p1_edit['code'] === 200 && ($p1_edit['body']['data']['name'] ?? '') === 'Commander Maya - Rain Rescue', "Code: {$p1_edit['code']}");

// -------------------------------------------------------------
// Test Group 3: User 2 Login & Avatar 2 Creation
// -------------------------------------------------------------
echo "\n[Group 3: User 2 Lifecycle & Cross-User Attacks]\n";

$u2_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage2_test_user2@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUser2);
assert_test("User 2 login successful", $u2_login['code'] === 200, "Code: {$u2_login['code']}");
$u2_csrf = $u2_login['body']['csrf_token'] ?? '';
$u2_id = $u2_login['body']['user']['id'] ?? '';

assert_test("User 1 and User 2 accounts are distinct", $u1_id !== $u2_id, "U1: {$u1_id}, U2: {$u2_id}");

// Create Avatar 2 for User 2
$avatar2_data = json_encode([
    'gender'       => 'male',
    'feature'      => 'stubble',
    'hair'         => 'spiky',
    'hairColor'    => '#1e293b',
    'clothes'      => 'jacket',
    'clothesColor' => '#0284c7',
    'skinColor'    => '#fed7aa'
]);

$p2_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Captain Alex',
    'avatar' => $avatar2_data
], $cookieUser2, $u2_csrf);
assert_test("User 2 creates Avatar 2 (201)", $p2_create['code'] === 201, "Code: {$p2_create['code']}");
$p2_id = $p2_create['body']['data']['id'] ?? '';
assert_test("Avatar 2 stamped with user_id = User 2 UUID", ($p2_create['body']['data']['user_id'] ?? '') === $u2_id, "User ID: " . ($p2_create['body']['data']['user_id'] ?? 'none'));

// ATTACK: User 2 attempts to update User 1's avatar
$cross_avatar_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $p1_id,
    'name'   => 'Hacked Commander Maya',
    'avatar' => $avatar2_data
], $cookieUser2, $u2_csrf);
assert_test("User 2 CANNOT update User 1 Avatar (403 Forbidden)", $cross_avatar_edit['code'] === 403, "Code: {$cross_avatar_edit['code']}");

// ATTACK: User 2 attempts to delete User 1's avatar
$cross_avatar_del = http_call('POST', "{$baseUrl}/api/delete_profile.php", [
    'id' => $p1_id
], $cookieUser2, $u2_csrf);
assert_test("User 2 CANNOT delete User 1 Avatar (403 Forbidden)", $cross_avatar_del['code'] === 403, "Code: {$cross_avatar_del['code']}");

// -------------------------------------------------------------
// Test Group 4: Parking Spaces Ownership & Cross-User Attacks
// -------------------------------------------------------------
echo "\n[Group 4: Parking Spaces Ownership & Community Lots]\n";

// User 1 creates parking space
$s1_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Maya Flood Safe Garage',
    'latitude'         => 18.7980,
    'longitude'        => 98.9820,
    'capacity'         => 10,
    'available_spaces' => 7,
    'status'           => 'available'
], $cookieUser1, $u1_csrf);
assert_test("User 1 creates Parking Space 1 (201)", $s1_create['code'] === 201, "Code: {$s1_create['code']}");
$s1_id = $s1_create['body']['data']['id'] ?? '';
assert_test("Parking Space 1 stamped with owner_id = User 1 UUID", ($s1_create['body']['data']['owner_id'] ?? '') === $u1_id, "Owner: " . ($s1_create['body']['data']['owner_id'] ?? 'none'));

// User 2 creates parking space
$s2_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Alex Hilltop Haven',
    'latitude'         => 18.7915,
    'longitude'        => 98.9890,
    'capacity'         => 8,
    'available_spaces' => 8,
    'status'           => 'available'
], $cookieUser2, $u2_csrf);
assert_test("User 2 creates Parking Space 2 (201)", $s2_create['code'] === 201, "Code: {$s2_create['code']}");
$s2_id = $s2_create['body']['data']['id'] ?? '';
assert_test("Parking Space 2 stamped with owner_id = User 2 UUID", ($s2_create['body']['data']['owner_id'] ?? '') === $u2_id, "Owner: " . ($s2_create['body']['data']['owner_id'] ?? 'none'));

// ATTACK: User 2 attempts to update User 1's parking space
$cross_space_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $s1_id,
    'name'             => 'Maya Garage Hijacked by Alex',
    'capacity'         => 15,
    'available_spaces' => 0,
    'status'           => 'closed'
], $cookieUser2, $u2_csrf);
assert_test("User 2 CANNOT update User 1 Parking Space (403 Forbidden)", $cross_space_edit['code'] === 403, "Code: {$cross_space_edit['code']}");

// ATTACK: User 2 attempts to delete User 1's parking space
$cross_space_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $s1_id
], $cookieUser2, $u2_csrf);
assert_test("User 2 CANNOT delete User 1 Parking Space (403 Forbidden)", $cross_space_del['code'] === 403, "Code: {$cross_space_del['code']}");

// ATTACK: User 2 attempts to update Chiang Mai University Community Lot
$comm_space_1 = '09413e15-4fa9-477b-aa26-6a10e6d9744e';
$comm_edit_1 = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $comm_space_1,
    'name'             => 'CMU Community Lot Hijacked',
    'capacity'         => 100,
    'available_spaces' => 0,
    'status'           => 'closed'
], $cookieUser2, $u2_csrf);
assert_test("Community space (CMU) CANNOT be updated (403 Forbidden)", $comm_edit_1['code'] === 403, "Code: {$comm_edit_1['code']}");

$comm_del_1 = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $comm_space_1
], $cookieUser2, $u2_csrf);
assert_test("Community space (CMU) CANNOT be deleted (403 Forbidden)", $comm_del_1['code'] === 403, "Code: {$comm_del_1['code']}");

// ATTACK: User 1 attempts to update Migrated Community Space (Safe Haven Elevated Deck)
$comm_space_2 = 'f575cedc-7c21-45db-b191-ce7e02246b45';
$comm_edit_2 = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $comm_space_2,
    'name'             => 'Safe Haven Deck Tampered',
    'capacity'         => 50,
    'available_spaces' => 50,
    'status'           => 'available'
], $cookieUser1, $u1_csrf);
assert_test("Migrated community space CANNOT be updated (403 Forbidden)", $comm_edit_2['code'] === 403, "Code: {$comm_edit_2['code']}");

$comm_del_2 = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $comm_space_2
], $cookieUser1, $u1_csrf);
assert_test("Migrated community space CANNOT be deleted (403 Forbidden)", $comm_del_2['code'] === 403, "Code: {$comm_del_2['code']}");

// -------------------------------------------------------------
// Test Group 5: Legitimate Operations & Clean-up
// -------------------------------------------------------------
echo "\n[Group 5: Legitimate Operations & Clean-up]\n";

// User 1 updates own space
$s1_update = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $s1_id,
    'name'             => 'Maya Flood Safe Garage - Expanded',
    'capacity'         => 14,
    'available_spaces' => 11,
    'status'           => 'available'
], $cookieUser1, $u1_csrf);
assert_test("User 1 can update their own parking space (200)", $s1_update['code'] === 200, "Code: {$s1_update['code']}");

// Cleanup User 1 test records
$s1_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $s1_id], $cookieUser1, $u1_csrf);
assert_test("User 1 can delete their own parking space (200)", $s1_del['code'] === 200, "Code: {$s1_del['code']}");

$p1_del = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => $p1_id], $cookieUser1, $u1_csrf);
assert_test("User 1 can delete their own profile (200)", $p1_del['code'] === 200, "Code: {$p1_del['code']}");

// Cleanup User 2 test records
$s2_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $s2_id], $cookieUser2, $u2_csrf);
assert_test("User 2 can delete their own parking space (200)", $s2_del['code'] === 200, "Code: {$s2_del['code']}");

$p2_del = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => $p2_id], $cookieUser2, $u2_csrf);
assert_test("User 2 can delete their own profile (200)", $p2_del['code'] === 200, "Code: {$p2_del['code']}");

// -------------------------------------------------------------
// Test Group 6: Verification that Community Spaces Remain Intact
// -------------------------------------------------------------
echo "\n[Group 6: Community Spaces Integrity]\n";

$public_spaces = http_call('GET', "{$baseUrl}/api/get_parking_spaces.php");
assert_test("Public parking spaces query succeeds (200)", $public_spaces['code'] === 200, "Code: {$public_spaces['code']}");
$public_ids = array_column($public_spaces['body']['data'] ?? [], 'id');

assert_test("Chiang Mai University lot is preserved", in_array('09413e15-4fa9-477b-aa26-6a10e6d9744e', $public_ids, true));
assert_test("User1 lot is preserved", in_array('70bf73be-b16e-45dd-89aa-0102b62ac23e', $public_ids, true));
assert_test("Safe Haven Elevated Deck lot is preserved", in_array('f575cedc-7c21-45db-b191-ce7e02246b45', $public_ids, true));
assert_test("Highland Sanctuary Parking lot is preserved", in_array('bdfca2df-7a66-4bbb-9450-19fd57086ed3', $public_ids, true));

@unlink($cookieUser1);
@unlink($cookieUser2);

echo "\n==============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "==============================================\n";

exit($failed > 0 ? 1 : 0);
