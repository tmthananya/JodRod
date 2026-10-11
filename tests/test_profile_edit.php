<?php
/**
 * Test: Profile Editing, Ownership, and Cross-User Defense
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=== JodRod Profile Editing & Ownership Regression Test ===\n\n";

function http_request($url, $method = 'GET', $data = null, $cookies = [], $headers = []) {
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

// -----------------------------------------------------------------
// Step 1: Log in User A
// -----------------------------------------------------------------
echo "Step 1: Logging in User A...\n";
$loginA = http_request("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);

assert($loginA['code'] === 200, "User A login failed: " . $loginA['raw_body']);
$userA = $loginA['body']['user'];
$cookiesA = $loginA['cookies'];
$csrfA = $loginA['body']['csrf_token'];
echo "   [PASS] User A logged in. UUID: {$userA['id']}\n\n";

// -----------------------------------------------------------------
// Step 2: Ensure User A has an active profile
// -----------------------------------------------------------------
echo "Step 2: Checking User A's profile in Supabase...\n";
$getMeA = http_request("$baseUrl/api/get_profiles.php?mine=me", 'GET', null, $cookiesA);
$profileA = null;

if (!empty($getMeA['body']['has_profile']) && !empty($getMeA['body']['data'])) {
    $profileA = $getMeA['body']['data'];
    echo "   [INFO] Existing profile found for User A: '{$profileA['name']}' (ID: {$profileA['id']})\n";
} else {
    echo "   [INFO] Creating initial profile for User A...\n";
    $createA = http_request("$baseUrl/api/create_profile.php", 'POST', [
        'name' => 'Commander Maya Initial',
        'avatar' => json_encode(['gender' => 'female', 'clothes' => 'vest', 'clothesColor' => '#ea580c'])
    ], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);
    assert($createA['code'] === 201, "Failed to create initial profile: " . $createA['raw_body']);
    $profileA = $createA['body']['data'];
    echo "   [PASS] Created initial profile: '{$profileA['name']}' (ID: {$profileA['id']})\n";
}

assert(!empty($profileA['id']), "User A profile ID must not be empty");
assert($profileA['user_id'] === $userA['id'], "Profile user_id must match User A UUID");
assert($profileA['id'] !== $profileA['user_id'], "Profile ID (PK) should be distinct from user_id (FK)");
echo "   [PASS] Verified profiles.id ({$profileA['id']}) vs profiles.user_id ({$profileA['user_id']})\n\n";

// -----------------------------------------------------------------
// Step 3: User A Edits Their Own Profile Name and Avatar
// -----------------------------------------------------------------
echo "Step 3: User A editing their own profile...\n";
$updatedName = 'Commander Maya Updated ' . date('His');
$updatedAvatar = json_encode([
    'gender' => 'female',
    'feature' => 'ribbon',
    'hair' => 'twintails',
    'hairColor' => '#4a2c2a',
    'clothes' => 'raincoat',
    'clothesColor' => '#0284c7',
    'skinColor' => '#fed7aa',
    'snapshot' => 'data:image/png;base64,mockSnapshotData'
]);

$updateA = http_request("$baseUrl/api/update_profile.php", 'POST', [
    'id' => $profileA['id'],
    'name' => $updatedName,
    'avatar' => $updatedAvatar
], $cookiesA, ['X-CSRF-Token: ' . $csrfA]);

echo "   Response Code: {$updateA['code']}\n";
assert($updateA['code'] === 200, "User A update failed: " . $updateA['raw_body']);
assert($updateA['body']['success'] === true, "User A update success must be true");
assert($updateA['body']['data']['name'] === $updatedName, "Updated name must match: {$updateA['body']['data']['name']}");
echo "   [PASS] User A successfully edited name to '{$updatedName}' and customized 3D avatar.\n\n";

// -----------------------------------------------------------------
// Step 4: Log in User B
// -----------------------------------------------------------------
echo "Step 4: Logging in User B...\n";
$loginB = http_request("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_b@jodrod-test.org',
    'password' => 'TestPassword123!'
]);

assert($loginB['code'] === 200, "User B login failed: " . $loginB['raw_body']);
$userB = $loginB['body']['user'];
$cookiesB = $loginB['cookies'];
$csrfB = $loginB['body']['csrf_token'];
echo "   [PASS] User B logged in. UUID: {$userB['id']}\n\n";

// -----------------------------------------------------------------
// Step 5: User B CANNOT Edit User A's Profile (403 Forbidden)
// -----------------------------------------------------------------
echo "Step 5: Testing cross-user defense - User B attempts to edit User A's profile...\n";
$attackEdit = http_request("$baseUrl/api/update_profile.php", 'POST', [
    'id' => $profileA['id'],
    'name' => 'Defaced By User B',
    'avatar' => '💀'
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);

echo "   Attack Update Code: {$attackEdit['code']}\n";
assert($attackEdit['code'] === 403, "Expected 403 Forbidden, got: {$attackEdit['code']}");
assert($attackEdit['body']['success'] === false, "Attack update must fail");
echo "   [PASS] User B was blocked from updating User A's profile (403 Forbidden).\n\n";

// -----------------------------------------------------------------
// Step 6: User B CANNOT Delete User A's Profile (403 Forbidden)
// -----------------------------------------------------------------
echo "Step 6: Testing cross-user defense - User B attempts to delete User A's profile...\n";
$attackDelete = http_request("$baseUrl/api/delete_profile.php", 'POST', [
    'id' => $profileA['id']
], $cookiesB, ['X-CSRF-Token: ' . $csrfB]);

echo "   Attack Delete Code: {$attackDelete['code']}\n";
assert($attackDelete['code'] === 403, "Expected 403 Forbidden, got: {$attackDelete['code']}");
assert($attackDelete['body']['success'] === false, "Attack delete must fail");
echo "   [PASS] User B was blocked from deleting User A's profile (403 Forbidden).\n\n";

// -----------------------------------------------------------------
// Step 7: Persistence Verification After Re-login
// -----------------------------------------------------------------
echo "Step 7: Verifying persistence of User A's updated profile after re-login...\n";
// Re-login User A
$reloginA = http_request("$baseUrl/api/auth.php?action=login", 'POST', [
    'email' => 'stage5_user_a@jodrod-test.org',
    'password' => 'TestPassword123!'
]);
$reCookiesA = $reloginA['cookies'];

$verifyMe = http_request("$baseUrl/api/get_profiles.php?mine=me", 'GET', null, $reCookiesA);
assert($verifyMe['code'] === 200, "Failed to get profile: " . $verifyMe['raw_body']);
assert($verifyMe['body']['data']['name'] === $updatedName, "Persisted name does not match updated name");
assert($verifyMe['body']['data']['avatar'] === $updatedAvatar, "Persisted avatar does not match updated avatar");
echo "   [PASS] User A's profile persistently updated in Supabase ('{$verifyMe['body']['data']['name']}').\n\n";

// -----------------------------------------------------------------
// Step 8: Community Profiles Public Readability
// -----------------------------------------------------------------
echo "Step 8: Checking public community profile listing...\n";
$publicProfiles = http_request("$baseUrl/api/get_profiles.php");
assert($publicProfiles['code'] === 200, "Public profiles query failed");
assert(is_array($publicProfiles['body']['data']), "Data must be an array");
assert(count($publicProfiles['body']['data']) >= 1, "Must have community profiles");
echo "   [PASS] Community profiles publicly accessible. Total profiles: " . count($publicProfiles['body']['data']) . "\n\n";

// -----------------------------------------------------------------
// Step 9: Frontend Initial Bootstrap Check (index.php)
// -----------------------------------------------------------------
echo "Step 9: Testing index.php initial session bootstrapping in HTML...\n";
$indexWithAuth = http_request("$baseUrl/index.php", 'GET', null, $reCookiesA);
assert(strpos($indexWithAuth['raw_body'], 'window.initialUser = {"id":') !== false, "index.php must bootstrap window.initialUser with session");
assert(strpos($indexWithAuth['raw_body'], 'window.initialCsrf =') !== false, "index.php must bootstrap window.initialCsrf");

$indexAnon = http_request("$baseUrl/index.php", 'GET');
assert(strpos($indexAnon['raw_body'], 'window.initialUser = null') !== false, "Anonymous index.php must have window.initialUser = null");
echo "   [PASS] index.php bootstrap script injects session user and CSRF without race condition.\n\n";

// -----------------------------------------------------------------
// Step 10: Verify User Does Not Appear in Community List & Left Editor
// -----------------------------------------------------------------
echo "Step 10: Verifying user is not in community list and left editor controls are configured...\n";
$indexContent = $indexWithAuth['raw_body'];
assert(strpos($indexContent, 'id="my-profile-id"') !== false, "Form must have #my-profile-id hidden field");
assert(strpos($indexContent, 'id="btn-delete-my-profile"') !== false, "Form must have left delete button");
assert(strpos($indexContent, 'const communityProfiles = json.data.filter(p => !currentUser || p.user_id !== currentUser.id);') !== false, "Must filter out current user from community list");
echo "   [PASS] Verified: user is excluded from community list and managed directly via left 3D editor.\n\n";

echo "=== ALL PROFILE EDITING & OWNERSHIP TESTS PASSED SUCCESSFULLY ===\n";
