<?php
/**
 * API: Create Profile (CREATE)
 * Creates a profile with strictly required fields: name, avatar.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// 1. Validate: Name (required, non-empty)
$name = trim($input['name'] ?? '');
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profile name is required.']);
    exit;
}

// Check for forged user_id parameter
if (isset($input['user_id']) && $input['user_id'] !== $currentUser['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: user_id cannot be forged. Profiles are linked strictly to your authenticated session.']);
    exit;
}

// 2. Avatar (optional TEXT string, defaults to standard avatar)
$avatar = trim($input['avatar'] ?? '👤');

$record = [
    'user_id' => $currentUser['id'],
    'name'    => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'avatar'  => $avatar
];

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    // Check if the authenticated account already has a profile (1 profile per account)
    $existingCheck = supabase_request("/rest/v1/profiles?user_id=eq.{$currentUser['id']}&select=id,name,avatar,user_id", 'GET');
    if ($existingCheck['success'] && !empty($existingCheck['data'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'A profile already exists for this account. Please update your existing profile.',
            'data'    => $existingCheck['data'][0]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = supabase_request('/rest/v1/profiles', 'POST', $record);
    if ($res['success']) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Profile created successfully in Supabase.',
            'data'    => $res['data'][0] ?? $record
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        $code = ($res['http_code'] === 409) ? 409 : 500;
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => ($code === 409) ? 'A profile already exists for this account.' : ('Failed to create profile in Supabase: ' . ($res['error'] ?? 'Unknown error'))
        ]);
        exit;
    }
}

http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Database unavailable: Supabase credentials are not configured.']);
exit;
