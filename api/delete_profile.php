<?php
/**
 * API: Delete Profile (DELETE)
 * Permanently deletes a profile record by ID.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? ($_GET['id'] ?? ''));

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profile ID is required for deletion.']);
    exit;
}

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request("/rest/v1/profiles?id=eq.{$id}", 'DELETE');
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Profile deleted successfully from Supabase.',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to delete profile from Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Local Fallback Storage
$profiles = get_local_data('profiles.json', []);
$initial_count = count($profiles);
$filtered = array_values(array_filter($profiles, function ($p) use ($id) {
    return ($p['id'] ?? '') !== $id;
}));

if (count($filtered) < $initial_count) {
    save_local_data('profiles.json', $filtered);
    echo json_encode([
        'success' => true,
        'source'  => 'local_fallback',
        'message' => 'Profile deleted successfully.',
        'id'      => $id
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Profile not found with the specified ID.'
    ]);
}
