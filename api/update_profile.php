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

// 3. Avatar
$avatar = trim($input['avatar'] ?? '👤');

$update_fields = [
    'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'avatar' => $avatar
];

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request("/rest/v1/profiles?id=eq.{$id}", 'PATCH', $update_fields);
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Profile updated successfully in Supabase.',
            'data'    => $res['data'][0] ?? array_merge(['id' => $id], $update_fields)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to update profile in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Local Fallback Storage
$profiles = get_local_data('profiles.json', []);
$updated_record = null;

foreach ($profiles as &$p) {
    if (isset($p['id']) && $p['id'] === $id) {
        $p['name'] = $update_fields['name'];
        $p['avatar'] = $update_fields['avatar'];
        $updated_record = $p;
        break;
    }
}

if ($updated_record) {
    save_local_data('profiles.json', $profiles);
    echo json_encode([
        'success' => true,
        'source'  => 'local_fallback',
        'message' => 'Profile updated successfully.',
        'data'    => $updated_record
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Profile not found with the specified ID.'
    ]);
}
