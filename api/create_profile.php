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

// 2. Avatar (optional TEXT string, defaults to standard avatar)
$avatar = trim($input['avatar'] ?? '👤');

$record = [
    'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'avatar' => htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8')
];

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
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
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to create profile in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Local Fallback Storage
$record['id'] = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$existing = get_local_data('profiles.json', []);
array_unshift($existing, $record);
save_local_data('profiles.json', $existing);

http_response_code(201);
echo json_encode([
    'success' => true,
    'source'  => 'local_fallback',
    'message' => 'Profile created successfully.',
    'data'    => $record
], JSON_UNESCAPED_UNICODE);
