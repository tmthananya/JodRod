<?php
/**
 * Test Suite: JodRod Stage 2B Backend Ownership Enforcement
 * Verifies two separate authenticated accounts, profile ownership,
 * parking space ownership, community space protection, and unauthenticated rejections.
 */

$baseUrl = 'http://127.0.0.1:8088';
$cookieFileUser1 = tempnam(sys_get_temp_dir(), 'jodrod_u1_');
$cookieFileUser2 = tempnam(sys_get_temp_dir(), 'jodrod_u2_');

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
    curl_close($ch);

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

echo "=== Starting JodRod Stage 2B Ownership Tests ===\n\n";

// -------------------------------------------------------------
// Test Group 1: Unauthenticated write requests rejected (401)
// -------------------------------------------------------------
echo "[Group 1: Unauthenticated write requests]\n";

$r = http_call('POST', "{$baseUrl}/api/create_profile.php", ['name' => 'Hacker']);
assert_test("POST create_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_profile.php", ['id' => 'fake-id', 'name' => 'Hacker']);
assert_test("POST update_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => 'fake-id']);
assert_test("POST delete_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/create_parking_space.php", ['name' => 'Hacker Lot']);
assert_test("POST create_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_parking_space.php", ['id' => 'fake-id', 'name' => 'Hacker']);
assert_test("POST update_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => 'fake-id']);
assert_test("POST delete_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

// -------------------------------------------------------------
// Test Group 2: Authenticate User 1 and User 2
// -------------------------------------------------------------
echo "\n[Group 2: Authenticate two separate users]\n";

$u1_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage2_test_user1@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieFileUser1);
assert_test("User 1 login successful", $u1_login['code'] === 200 && !empty($u1_login['body']['csrf_token']), "Code: {$u1_login['code']}");
$u1_csrf = $u1_login['body']['csrf_token'] ?? '';
$u1_id = $u1_login['body']['user']['id'] ?? '';

$u2_login = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage2_test_user2@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieFileUser2);
assert_test("User 2 login successful", $u2_login['code'] === 200 && !empty($u2_login['body']['csrf_token']), "Code: {$u2_login['code']}");
$u2_csrf = $u2_login['body']['csrf_token'] ?? '';
$u2_id = $u2_login['body']['user']['id'] ?? '';

assert_test("User 1 and User 2 have distinct Supabase UUIDs", !empty($u1_id) && !empty($u2_id) && $u1_id !== $u2_id, "U1: {$u1_id}, U2: {$u2_id}");

// -------------------------------------------------------------
// Test Group 3: User 1 Profile Lifecycle
// -------------------------------------------------------------
echo "\n[Group 3: User 1 Profile Lifecycle]\n";

// Create Profile User 1
$p1_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Driver One Official',
    'avatar' => '🚗'
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 can create own profile (201)", $p1_create['code'] === 201, "Code: {$p1_create['code']} Error: " . ($p1_create['body']['error'] ?? ''));
$p1_id = $p1_create['body']['data']['id'] ?? '';

// Check User 1 profile ownership
assert_test("User 1 profile stamped with user_id = u1_id", ($p1_create['body']['data']['user_id'] ?? '') === $u1_id, "User ID: " . ($p1_create['body']['data']['user_id'] ?? 'none'));

// Try duplicate profile creation -> 409
$p1_dup = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Driver One Duplicate',
    'avatar' => '🏎️'
], $cookieFileUser1, $u1_csrf);
assert_test("Duplicate profile creation rejected with 409 Conflict", $p1_dup['code'] === 409, "Code: {$p1_dup['code']}");

// Secure get own profile
$p1_me = http_call('GET', "{$baseUrl}/api/get_profiles.php?action=me", null, $cookieFileUser1);
assert_test("User 1 can retrieve own profile via ?action=me", $p1_me['code'] === 200 && ($p1_me['body']['data']['name'] ?? '') === 'Driver One Official', "Body: " . json_encode($p1_me['body']));

// Edit own profile
$p1_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $p1_id,
    'name'   => 'Driver One Updated Name',
    'avatar' => '🚙'
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 can update own profile (200)", $p1_edit['code'] === 200 && ($p1_edit['body']['data']['name'] ?? '') === 'Driver One Updated Name', "Code: {$p1_edit['code']}");

// -------------------------------------------------------------
// Test Group 4: User 2 Profile Lifecycle & Cross-User Attacks
// -------------------------------------------------------------
echo "\n[Group 4: User 2 Profile Lifecycle & Cross-User Security]\n";

// Create Profile User 2
$p2_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Driver Two Official',
    'avatar' => '🏍️'
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 can create own profile (201)", $p2_create['code'] === 201, "Code: {$p2_create['code']}");
$p2_id = $p2_create['body']['data']['id'] ?? '';

// User 2 attempts to UPDATE User 1's profile -> 403 Forbidden
$cross_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $p1_id,
    'name'   => 'Hacked by User 2',
    'avatar' => '💀'
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CANNOT update User 1 profile (403 Forbidden)", $cross_edit['code'] === 403, "Code: {$cross_edit['code']}");

// User 2 attempts to DELETE User 1's profile -> 403 Forbidden
$cross_del = http_call('POST', "{$baseUrl}/api/delete_profile.php", [
    'id' => $p1_id
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CANNOT delete User 1 profile (403 Forbidden)", $cross_del['code'] === 403, "Code: {$cross_del['code']}");

// -------------------------------------------------------------
// Test Group 5: Parking Spaces Ownership & Community Protection
// -------------------------------------------------------------
echo "\n[Group 5: Parking Spaces Ownership & Cross-User Security]\n";

// User 1 creates parking space
$s1_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'User 1 Highland Parking',
    'latitude'         => 18.7950,
    'longitude'        => 98.9810,
    'capacity'         => 12,
    'available_spaces' => 10,
    'status'           => 'available'
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 creates parking space (201)", $s1_create['code'] === 201, "Code: {$s1_create['code']}");
$s1_id = $s1_create['body']['data']['id'] ?? '';
assert_test("User 1 parking space stamped with owner_id = User 1", ($s1_create['body']['data']['owner_id'] ?? '') === $u1_id, "Owner: " . ($s1_create['body']['data']['owner_id'] ?? 'none'));

// User 2 creates parking space
$s2_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'User 2 Safe Lot',
    'latitude'         => 18.7900,
    'longitude'        => 98.9850,
    'capacity'         => 6,
    'available_spaces' => 6,
    'status'           => 'available'
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 creates parking space (201)", $s2_create['code'] === 201, "Code: {$s2_create['code']}");
$s2_id = $s2_create['body']['data']['id'] ?? '';
assert_test("User 2 parking space stamped with owner_id = User 2", ($s2_create['body']['data']['owner_id'] ?? '') === $u2_id, "Owner: " . ($s2_create['body']['data']['owner_id'] ?? 'none'));

// User 2 attempts to UPDATE User 1's parking space -> 403 Forbidden
$cross_s_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $s1_id,
    'name'             => 'Hijacked by User 2',
    'capacity'         => 20,
    'available_spaces' => 0,
    'status'           => 'closed'
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CANNOT update User 1 parking space (403 Forbidden)", $cross_s_edit['code'] === 403, "Code: {$cross_s_edit['code']}");

// User 2 attempts to DELETE User 1's parking space -> 403 Forbidden
$cross_s_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $s1_id
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CANNOT delete User 1 parking space (403 Forbidden)", $cross_s_del['code'] === 403, "Code: {$cross_s_del['code']}");

// Protect Unassigned Community Spaces: Try updating Chiang Mai University High Ground Lot (09413e15-4fa9-477b-aa26-6a10e6d9744e)
$comm_id = '09413e15-4fa9-477b-aa26-6a10e6d9744e';
$comm_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $comm_id,
    'name'             => 'Community Space Alteration Attempt',
    'capacity'         => 50,
    'available_spaces' => 25,
    'status'           => 'available'
], $cookieFileUser1, $u1_csrf);
assert_test("Community space (owner_id NULL) CANNOT be updated (403 Forbidden)", $comm_edit['code'] === 403, "Code: {$comm_edit['code']}");

$comm_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $comm_id
], $cookieFileUser1, $u1_csrf);
assert_test("Community space (owner_id NULL) CANNOT be deleted (403 Forbidden)", $comm_del['code'] === 403, "Code: {$comm_del['code']}");

// -------------------------------------------------------------
// Test Group 6: Legitimate Owner Updates & Clean-up
// -------------------------------------------------------------
echo "\n[Group 6: Legitimate Owner Operations & Cleanup]\n";

// User 1 updates their own space
$s1_update = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $s1_id,
    'name'             => 'User 1 Highland Parking - Renovated',
    'capacity'         => 12,
    'available_spaces' => 8,
    'status'           => 'available'
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 CAN update their own parking space (200)", $s1_update['code'] === 200, "Code: {$s1_update['code']}");

// User 1 deletes their own space
$s1_clean = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $s1_id
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 CAN delete their own parking space (200)", $s1_clean['code'] === 200, "Code: {$s1_clean['code']}");

// User 2 deletes their own space
$s2_clean = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $s2_id
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CAN delete their own parking space (200)", $s2_clean['code'] === 200, "Code: {$s2_clean['code']}");

// User 1 deletes their own profile
$p1_clean = http_call('POST', "{$baseUrl}/api/delete_profile.php", [
    'id' => $p1_id
], $cookieFileUser1, $u1_csrf);
assert_test("User 1 CAN delete their own profile (200)", $p1_clean['code'] === 200, "Code: {$p1_clean['code']}");

// User 2 deletes their own profile
$p2_clean = http_call('POST', "{$baseUrl}/api/delete_profile.php", [
    'id' => $p2_id
], $cookieFileUser2, $u2_csrf);
assert_test("User 2 CAN delete their own profile (200)", $p2_clean['code'] === 200, "Code: {$p2_clean['code']}");

// Cleanup temp cookies
@unlink($cookieFileUser1);
@unlink($cookieFileUser2);

echo "\n==============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "==============================================\n";

exit($failed > 0 ? 1 : 0);
