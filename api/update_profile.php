<?php
/**
 * API: Update Profile (UPDATE)
 * Updates only name and avatar for an existing profile.
 * Prevents editing the record ID.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Stage 1: Enforce backend authentication guard
$currentUser = require_auth();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// 1. Validate: Profile ID (required, cannot be modified)
$id = trim($input['id'] ?? '');
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profile ID is required for update.']);
    exit;
}

// 2. Validate: Name (required, non-empty)
$name = trim($input['name'] ?? '');
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profile name cannot be empty.']);
    exit;
}

// Check for attempted ownership tampering
if (isset($input['user_id']) && $input['user_id'] !== $currentUser['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: user_id cannot be changed. Profile ownership is strictly immutable.']);
    exit;
}

// 3. Avatar
$avatar = trim($input['avatar'] ?? '👤');

$update_fields = [
    'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'avatar' => $avatar
];

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    // 1. Fetch target profile to verify existence and ownership
    $check = supabase_request("/rest/v1/profiles?id=eq.{$id}&select=id,name,avatar,user_id", 'GET');
    if (!$check['success'] || empty($check['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found with the specified ID.']);
        exit;
    }

    $existingProfile = $check['data'][0];

    // 2. Strict ownership check: Must belong to current authenticated user
    if (empty($existingProfile['user_id']) || $existingProfile['user_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to update this profile.']);
        exit;
    }

    // 3. Update profile while scoping by user_id
    $res = supabase_request("/rest/v1/profiles?id=eq.{$id}&user_id=eq.{$currentUser['id']}", 'PATCH', $update_fields);
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Profile updated successfully in Supabase.',
            'data'    => $res['data'][0] ?? array_merge(['id' => $id, 'user_id' => $currentUser['id']], $update_fields)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code($res['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to update profile in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Database unavailable: Supabase credentials are not configured.']);
exit;
