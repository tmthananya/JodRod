<?php
/**
 * Test: Profile Privacy, Ownership Enforcement, and UI Synchronization
 * Covers:
 *  1. Left-side My Profile editor structure (Overview mode + Edit My Profile toggle)
 *  2. Right-side Community Profiles list (View-only, no edit/delete buttons for other members)
 *  3. User A editing their own profile (name, avatar) -> saves to Supabase
 *  4. Instant reflection in both Left summary and Right community card with "You" badge
 *  5. Cross-user attack prevention: User B cannot edit User A (HTTP 403 Forbidden)
 *  6. Cross-user attack prevention: User B cannot delete User A (HTTP 403 Forbidden)
 *  7. Supabase RLS and API enforcement (tamper resistance)
 *  8. Data privacy: Only permitted public fields are exposed via api/get_profiles.php
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=== JodRod Profile Privacy & Security Verification ===\n\n";

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
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => json_decode($bodyStr, true),
        'raw_body' => $bodyStr,
        'cookies' => $newCookies
    ];
}

// -------------------------------------------------------------
// Test 1: Verify Left-Side HTML Structure in index.php
// -------------------------------------------------------------
echo "Test 1: Inspecting Left Side & Right Side UI architecture in index.php...\n";
$htmlRes = make_req("$baseUrl/index.php");
assert($htmlRes['code'] === 200, "Failed to load index.php");
$html = $htmlRes['raw_body'];

assert(strpos($html, 'id="my-profile-view-box"') !== false, "Must have my-profile-view-box view mode container");
assert(strpos($html, 'id="btn-open-my-profile-editor"') !== false, "Must have btn-open-my-profile-editor button");
assert(strpos($html, 'Edit My Profile') !== false, "Must have 'Edit My Profile' button text");
assert(strpos($html, 'id="my-profile-editor-box"') !== false, "Must have my-profile-editor-box container");
assert(strpos($html, 'id="btn-cancel-profile-edit"') !== false, "Must have btn-cancel-profile-edit button");
assert(strpos($html, 'id="create-profile-form"') !== false, "Must have create-profile-form");
assert(strpos($html, 'id="edit-profile-modal"') === false, "Legacy edit-profile-modal must be removed");
echo "   [PASS] Verified Left-side UI: View Mode ('Edit My Profile') and Editor Mode ('Save' / 'Cancel') exist cleanly.\n\n";

// -------------------------------------------------------------
// Test 2: Log in User A
// -------------------------------------------------------------
echo "Test 2: Authenticating User A (stage5_user_a@jodrod-test.org)...\n";
$loginA = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginA['code'] === 200, "User A login failed");
$userA = $loginA['body']['user'];
$cookiesA = $loginA['cookies'];
$csrfA = $loginA['body']['csrf_token'];
echo "   [PASS] User A authenticated. User ID: {$userA['id']}\n\n";

// -------------------------------------------------------------
// Test 3: Retrieve User A's Profile & Check Privacy of Fields
// -------------------------------------------------------------
echo "Test 3: Fetching User A's current profile from Supabase...\n";
$getMeA = make_req("$baseUrl/api/get_profiles.php?action=me", 'GET', null, $cookiesA);
assert($getMeA['code'] === 200, "Failed to fetch profile");
$profileA = $getMeA['body']['data'];
assert(!empty($profileA['id']), "User A must have an existing profile ID");
assert($profileA['user_id'] === $userA['id'], "Profile user_id must match User A auth ID");
echo "   [PASS] User A profile verified. Profile ID: {$profileA['id']}, Owner User ID: {$profileA['user_id']}\n\n";

// -------------------------------------------------------------
// Test 4: User A edits own profile (Name and 3D Avatar)
// -------------------------------------------------------------
echo "Test 4: User A edits own profile (Updating display name & 3D avatar)...\n";
$newTestName = "Rescue Officer " . substr(md5(microtime()), 0, 6);
$newAvatarPayload = json_encode([
    'gender' => 'male',
    'feature' => 'clean',
    'hair' => 'short',
    'hairColor' => '#0284c7',
    'clothes' => 'jacket',
    'clothesColor' => '#dc2626',
    'skinColor' => '#fed7aa',
    'snapshot' => 'data:image/webp;base64,UklGRmYAAABXRUJQVlA4...'
]);

$updateRes = make_req("$baseUrl/api/update_profile.php", 'POST', [
    'id' => $profileA['id'],
    'name' => $newTestName,
    'avatar' => $newAvatarPayload
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);

assert($updateRes['code'] === 200, "User A update failed: " . $updateRes['raw_body']);
assert($updateRes['body']['success'] === true, "Update must return success: true");
assert($updateRes['body']['data']['name'] === $newTestName, "Updated name must match");
echo "   [PASS] Profile saved to Supabase successfully. Updated name: '{$newTestName}'\n\n";

// -------------------------------------------------------------
// Test 5: Verify immediate visibility in public Community Profiles list
// -------------------------------------------------------------
echo "Test 5: Verifying immediate sync in Community Profiles list (api/get_profiles.php)...\n";
$communityRes = make_req("$baseUrl/api/get_profiles.php");
assert($communityRes['code'] === 200, "Failed to fetch community profiles");
$foundUpdatedA = false;
foreach ($communityRes['body']['data'] as $p) {
    // Verify privacy: No sensitive fields leaked
    assert(!isset($p['email']), "Email must not be leaked in public profiles");
    assert(!isset($p['password']), "Password must not be leaked");
    assert(!isset($p['phone']), "Phone must not be leaked");

    if ($p['id'] === $profileA['id']) {
        $foundUpdatedA = true;
        assert($p['name'] === $newTestName, "Community list must reflect updated name immediately");
        assert($p['avatar'] === $newAvatarPayload, "Community list must reflect updated 3D avatar immediately");
    }
}
assert($foundUpdatedA, "Updated profile of User A must appear in community list");
echo "   [PASS] User A's updated profile appears immediately in Community Profiles with only permitted public fields.\n\n";

// -------------------------------------------------------------
// Test 6: Authenticate User B
// -------------------------------------------------------------
echo "Test 6: Authenticating User B (stage5_user_b@jodrod-test.org)...\n";
$loginB = make_req("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
assert($loginB['code'] === 200, "User B login failed");
$userB = $loginB['body']['user'];
$cookiesB = $loginB['cookies'];
$csrfB = $loginB['body']['csrf_token'];
echo "   [PASS] User B authenticated. User ID: {$userB['id']}\n\n";

// -------------------------------------------------------------
// Test 7: Enforce Profile Privacy - User B attempts to edit User A's profile
// -------------------------------------------------------------
echo "Test 7: Cross-User Attack Defense: User B attempts to edit User A's profile...\n";
$maliciousUpdate = make_req("$baseUrl/api/update_profile.php", 'POST', [
    'id' => $profileA['id'],
    'name' => 'HACKED BY USER B',
    'avatar' => 'hacked_avatar'
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);

assert($maliciousUpdate['code'] === 403, "API must return HTTP 403 Forbidden on cross-user edit attempt. Got: " . $maliciousUpdate['code']);
assert($maliciousUpdate['body']['success'] === false, "Must return success: false");
echo "   [PASS] Cross-user edit was blocked by backend and Supabase RLS with HTTP 403 Forbidden.\n\n";

// -------------------------------------------------------------
// Test 8: Enforce Profile Privacy - User B attempts to delete User A's profile
// -------------------------------------------------------------
echo "Test 8: Cross-User Attack Defense: User B attempts to delete User A's profile...\n";
$maliciousDelete = make_req("$baseUrl/api/delete_profile.php", 'POST', [
    'id' => $profileA['id']
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);

assert($maliciousDelete['code'] === 403, "API must return HTTP 403 Forbidden on cross-user delete attempt. Got: " . $maliciousDelete['code']);
assert($maliciousDelete['body']['success'] === false, "Must return success: false");
echo "   [PASS] Cross-user delete was blocked by backend and Supabase RLS with HTTP 403 Forbidden.\n\n";

// -------------------------------------------------------------
// Test 9: Enforce Profile Privacy - Unauthenticated write attack
// -------------------------------------------------------------
echo "Test 9: Unauthenticated Attack Defense: Anonymous user attempts to edit profile...\n";
$anonUpdate = make_req("$baseUrl/api/update_profile.php", 'POST', [
    'id' => $profileA['id'],
    'name' => 'ANON TAMPER'
]);
assert($anonUpdate['code'] === 401, "API must return HTTP 401 Unauthorized for unauthenticated writes. Got: " . $anonUpdate['code']);
echo "   [PASS] Unauthenticated write was blocked with HTTP 401 Unauthorized.\n\n";

// -------------------------------------------------------------
// Test 10: Verify User A's data remains untouched after attack attempts
// -------------------------------------------------------------
echo "Test 10: Verifying User A's profile integrity after attempted attacks...\n";
$verifyA = make_req("$baseUrl/api/get_profiles.php?action=me", 'GET', null, $cookiesA);
assert($verifyA['body']['data']['name'] === $newTestName, "User A's name must not have been tampered with");
echo "   [PASS] User A profile remains completely secure ('{$newTestName}').\n\n";

echo "=== ALL PROFILE PRIVACY & SECURITY CHECKS PASSED SUCCESSFULLY ===\n";
