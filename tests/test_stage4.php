<?php
/**
 * Test Suite: JodRod Stage 4 Authentication, Ownership & Security Hardening
 * Verifies backend auth enforcement, live token validation, profile ownership,
 * parking space ownership, ownership parameter tampering rejection,
 * cross-user write denial, community space protection, and post-logout isolation.
 */

$baseUrl = 'http://127.0.0.1:8088';
$cookieUserA = tempnam(sys_get_temp_dir(), 'jodrod_s4_ua_');
$cookieUserB = tempnam(sys_get_temp_dir(), 'jodrod_s4_ub_');

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

echo "=== Starting JodRod Stage 4 Hardened Security Verification ===\n\n";

// -------------------------------------------------------------
// 1. Unauthenticated writes are rejected (401)
// -------------------------------------------------------------
echo "[Requirement 1: Unauthenticated Writes Rejected (401)]\n";

$r = http_call('POST', "{$baseUrl}/api/create_profile.php", ['name' => 'Intruder']);
assert_test("POST create_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_profile.php", ['id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e', 'name' => 'Intruder']);
assert_test("POST update_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e']);
assert_test("POST delete_profile without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/create_parking_space.php", ['name' => 'Intruder Lot']);
assert_test("POST create_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/update_parking_space.php", ['id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e', 'name' => 'Intruder']);
assert_test("POST update_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

$r = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => '09413e15-4fa9-477b-aa26-6a10e6d9744e']);
assert_test("POST delete_parking_space without auth returns 401", $r['code'] === 401, "Got: {$r['code']}");

// -------------------------------------------------------------
// 2. User A & User B Authentication
// -------------------------------------------------------------
echo "\n[Requirement 2: Authenticate User A and User B]\n";

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

assert_test("User A and User B have separate UUIDs", !empty($ua_id) && !empty($ub_id) && $ua_id !== $ub_id, "UA: {$ua_id}, UB: {$ub_id}");

// -------------------------------------------------------------
// 3. User A Profile Creation, Duplication, and Anti-Tampering
// -------------------------------------------------------------
echo "\n[Requirement 3: User A Profile Ownership & Tampering Defense]\n";

// Attempt to forge user_id to User B's UUID on creation
$ua_forge_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'    => 'Forged Profile',
    'avatar'  => '👤',
    'user_id' => $ub_id
], $cookieUserA, $ua_csrf);
assert_test("User A cannot forge user_id on creation (403 Forbidden)", $ua_forge_create['code'] === 403, "Code: {$ua_forge_create['code']}");

// Legitimate creation for User A
$ua_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Commander Maya Stage 4',
    'avatar' => '🚗'
], $cookieUserA, $ua_csrf);
assert_test("User A can create own profile (201)", $ua_create['code'] === 201, "Code: {$ua_create['code']}");
$pa_id = $ua_create['body']['data']['id'] ?? '';
assert_test("User A profile stamped with User A UUID", ($ua_create['body']['data']['user_id'] ?? '') === $ua_id, "Owner: " . ($ua_create['body']['data']['user_id'] ?? 'none'));

// Requirement 3: User A cannot create a second profile (1 profile per account)
$ua_dup = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Maya Second Profile Attempt',
    'avatar' => '🚙'
], $cookieUserA, $ua_csrf);
assert_test("User A cannot create a second profile (409 Conflict)", $ua_dup['code'] === 409, "Code: {$ua_dup['code']}");

// User A edits their own profile
$ua_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $pa_id,
    'name'   => 'Commander Maya Stage 4 - Updated',
    'avatar' => '🚙'
], $cookieUserA, $ua_csrf);
assert_test("User A can edit own profile (200)", $ua_edit['code'] === 200, "Code: {$ua_edit['code']}");

// User A cannot change ownership (tampering defense on update)
$ua_tamper_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'      => $pa_id,
    'name'    => 'Maya Trying To Reassign',
    'user_id' => $ub_id
], $cookieUserA, $ua_csrf);
assert_test("User A cannot reassign profile ownership via update (403 Forbidden)", $ua_tamper_edit['code'] === 403, "Code: {$ua_tamper_edit['code']}");

// -------------------------------------------------------------
// 4. User B Cannot Edit or Delete User A's Profile
// -------------------------------------------------------------
echo "\n[Requirement 4: Cross-User Profile Defense]\n";

// User B creates own profile
$ub_create = http_call('POST', "{$baseUrl}/api/create_profile.php", [
    'name'   => 'Captain Alex Stage 4',
    'avatar' => '🏍️'
], $cookieUserB, $ub_csrf);
assert_test("User B can create own profile (201)", $ub_create['code'] === 201, "Code: {$ub_create['code']}");
$pb_id = $ub_create['body']['data']['id'] ?? '';

// ATTACK: User B tries to update User A's profile
$cross_p_edit = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'     => $pa_id,
    'name'   => 'Hacked By User B',
    'avatar' => '💀'
], $cookieUserB, $ub_csrf);
assert_test("User B CANNOT update User A profile (403 Forbidden)", $cross_p_edit['code'] === 403, "Code: {$cross_p_edit['code']}");

// ATTACK: User B tries to delete User A's profile
$cross_p_del = http_call('POST', "{$baseUrl}/api/delete_profile.php", [
    'id' => $pa_id
], $cookieUserB, $ub_csrf);
assert_test("User B CANNOT delete User A profile (403 Forbidden)", $cross_p_del['code'] === 403, "Code: {$cross_p_del['code']}");

// -------------------------------------------------------------
// 5. User A Creates Parking Space & Anti-Tampering
// -------------------------------------------------------------
echo "\n[Requirement 5: Parking Space Ownership & Tampering Defense]\n";

// Attempt to forge owner_id to User B's UUID on creation
$ua_forge_space = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Forged Parking Lot',
    'latitude'         => 18.790,
    'longitude'        => 98.980,
    'capacity'         => 10,
    'available_spaces' => 10,
    'status'           => 'available',
    'owner_id'         => $ub_id
], $cookieUserA, $ua_csrf);
assert_test("User A cannot forge owner_id on creation (403 Forbidden)", $ua_forge_space['code'] === 403, "Code: {$ua_forge_space['code']}");

// Legitimate parking space creation for User A
$sa_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Maya Hillside Refuge Stage 4',
    'latitude'         => 18.7940,
    'longitude'        => 98.9810,
    'capacity'         => 15,
    'available_spaces' => 12,
    'status'           => 'available'
], $cookieUserA, $ua_csrf);
assert_test("User A can create own parking space (201)", $sa_create['code'] === 201, "Code: {$sa_create['code']}");
$sa_id = $sa_create['body']['data']['id'] ?? '';
assert_test("Parking space stamped with User A UUID", ($sa_create['body']['data']['owner_id'] ?? '') === $ua_id, "Owner: " . ($sa_create['body']['data']['owner_id'] ?? 'none'));

// User A edits their own parking space
$sa_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $sa_id,
    'name'             => 'Maya Hillside Refuge - Renovated',
    'capacity'         => 15,
    'available_spaces' => 9,
    'status'           => 'available'
], $cookieUserA, $ua_csrf);
assert_test("User A can edit own parking space (200)", $sa_edit['code'] === 200, "Code: {$sa_edit['code']}");

// User A cannot reassign parking space ownership via update
$sa_tamper = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $sa_id,
    'name'             => 'Attempted Ownership Reassignment',
    'capacity'         => 15,
    'available_spaces' => 9,
    'status'           => 'available',
    'owner_id'         => $ub_id
], $cookieUserA, $ua_csrf);
assert_test("User A cannot reassign space ownership via update (403 Forbidden)", $sa_tamper['code'] === 403, "Code: {$sa_tamper['code']}");

// -------------------------------------------------------------
// 6. User B Cannot Edit or Delete User A's Parking Space
// -------------------------------------------------------------
echo "\n[Requirement 6: Cross-User Parking Space Defense]\n";

// User B creates own space
$sb_create = http_call('POST', "{$baseUrl}/api/create_parking_space.php", [
    'name'             => 'Alex Secure Yard Stage 4',
    'latitude'         => 18.7910,
    'longitude'        => 98.9880,
    'capacity'         => 8,
    'available_spaces' => 8,
    'status'           => 'available'
], $cookieUserB, $ub_csrf);
assert_test("User B can create own parking space (201)", $sb_create['code'] === 201, "Code: {$sb_create['code']}");
$sb_id = $sb_create['body']['data']['id'] ?? '';

// ATTACK: User B tries to update User A's parking space
$cross_s_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $sa_id,
    'name'             => 'Maya Space Altered by Alex',
    'capacity'         => 20,
    'available_spaces' => 0,
    'status'           => 'closed'
], $cookieUserB, $ub_csrf);
assert_test("User B CANNOT update User A parking space (403 Forbidden)", $cross_s_edit['code'] === 403, "Code: {$cross_s_edit['code']}");

// ATTACK: User B tries to delete User A's parking space
$cross_s_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $sa_id
], $cookieUserB, $ub_csrf);
assert_test("User B CANNOT delete User A parking space (403 Forbidden)", $cross_s_del['code'] === 403, "Code: {$cross_s_del['code']}");

// -------------------------------------------------------------
// 7. Community Spaces Protection (owner_id IS NULL)
// -------------------------------------------------------------
echo "\n[Requirement 7: Unassigned Community Space Protection]\n";

$cmu_space_id = '09413e15-4fa9-477b-aa26-6a10e6d9744e';
$cmu_edit = http_call('POST', "{$baseUrl}/api/update_parking_space.php", [
    'id'               => $cmu_space_id,
    'name'             => 'CMU Community Lot Claim Attempt',
    'capacity'         => 50,
    'available_spaces' => 10,
    'status'           => 'available',
    'owner_id'         => $ua_id
], $cookieUserA, $ua_csrf);
assert_test("Community space cannot be modified or claimed (403 Forbidden)", $cmu_edit['code'] === 403, "Code: {$cmu_edit['code']}");

$cmu_del = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", [
    'id' => $cmu_space_id
], $cookieUserA, $ua_csrf);
assert_test("Community space cannot be deleted (403 Forbidden)", $cmu_del['code'] === 403, "Code: {$cmu_del['code']}");

// -------------------------------------------------------------
// 8. Session Isolation & Re-Login
// -------------------------------------------------------------
echo "\n[Requirement 8: Logout and Session Isolation]\n";

// User A logs out
$ua_logout = http_call('POST', "{$baseUrl}/api/auth.php?action=logout", null, $cookieUserA, $ua_csrf);
assert_test("User A logged out successfully", $ua_logout['code'] === 200, "Code: {$ua_logout['code']}");

// Attempting write with logged-out session -> 401
$ua_post_logout = http_call('POST', "{$baseUrl}/api/update_profile.php", [
    'id'   => $pa_id,
    'name' => 'Ghost Write After Logout'
], $cookieUserA, $ua_csrf);
assert_test("Write request with terminated session rejected (401)", $ua_post_logout['code'] === 401, "Code: {$ua_post_logout['code']}");

// User A logs back in
$ua_relogin = http_call('POST', "{$baseUrl}/api/auth.php?action=login", [
    'email'    => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
], $cookieUserA);
assert_test("User A re-login succeeds", $ua_relogin['code'] === 200, "Code: {$ua_relogin['code']}");
$ua_csrf = $ua_relogin['body']['csrf_token'] ?? '';

// Check profile persistence
$ua_me = http_call('GET', "{$baseUrl}/api/get_profiles.php?action=me", null, $cookieUserA);
assert_test("User A profile correctly loaded on fresh session", $ua_me['code'] === 200 && ($ua_me['body']['data']['name'] ?? '') === 'Commander Maya Stage 4 - Updated', "Name: " . ($ua_me['body']['data']['name'] ?? 'none'));

// -------------------------------------------------------------
// 9. Public Reads & Directory Functionality
// -------------------------------------------------------------
echo "\n[Requirement 9: Public Map and Community Reads]\n";

$public_spaces = http_call('GET', "{$baseUrl}/api/get_parking_spaces.php");
assert_test("Public parking spaces query succeeds (200)", $public_spaces['code'] === 200, "Code: {$public_spaces['code']}");

$public_profiles = http_call('GET', "{$baseUrl}/api/get_profiles.php");
assert_test("Public profiles directory query succeeds (200)", $public_profiles['code'] === 200, "Code: {$public_profiles['code']}");

// -------------------------------------------------------------
// 10. Clean-up of Test Records
// -------------------------------------------------------------
echo "\n[Requirement 10: Clean-up Test Records]\n";

// User A deletes own test parking space
$sa_clean = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $sa_id], $cookieUserA, $ua_csrf);
assert_test("User A clean up test parking space (200)", $sa_clean['code'] === 200, "Code: {$sa_clean['code']}");

// User A deletes own test profile
$pa_clean = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => $pa_id], $cookieUserA, $ua_csrf);
assert_test("User A clean up test profile (200)", $pa_clean['code'] === 200, "Code: {$pa_clean['code']}");

// User B deletes own test parking space
$sb_clean = http_call('POST', "{$baseUrl}/api/delete_parking_space.php", ['id' => $sb_id], $cookieUserB, $ub_csrf);
assert_test("User B clean up test parking space (200)", $sb_clean['code'] === 200, "Code: {$sb_clean['code']}");

// User B deletes own test profile
$pb_clean = http_call('POST', "{$baseUrl}/api/delete_profile.php", ['id' => $pb_id], $cookieUserB, $ub_csrf);
assert_test("User B clean up test profile (200)", $pb_clean['code'] === 200, "Code: {$pb_clean['code']}");

@unlink($cookieUserA);
@unlink($cookieUserB);

echo "\n==============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "==============================================\n";

exit($failed > 0 ? 1 : 0);
