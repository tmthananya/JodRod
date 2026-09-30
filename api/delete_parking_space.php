<?php
/**
 * API: Delete Parking Space (DELETE)
 * Permanently removes a parking space record from Supabase / database.
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
    echo json_encode(['success' => false, 'error' => 'Parking space ID is required for deletion.']);
    exit;
}

// Live Supabase Mode
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request("/rest/v1/parking_spaces?id=eq.{$id}", 'DELETE');
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Parking space deleted successfully from Supabase.',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to delete record from Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Local Fallback Storage
$spaces = get_local_data('parking_spaces.json', []);
$initial_count = count($spaces);
$filtered = array_values(array_filter($spaces, function ($s) use ($id) {
    return ($s['id'] ?? '') !== $id;
}));

if (count($filtered) < $initial_count) {
    save_local_data('parking_spaces.json', $filtered);
    echo json_encode([
        'success' => true,
        'source'  => 'local_fallback',
        'message' => 'Parking space deleted successfully.',
        'id'      => $id
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Parking space not found with the specified ID.'
    ]);
}
