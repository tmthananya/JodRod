<?php
/**
 * API: Get Parking Spaces (READ)
 * Returns all active parking spaces from Supabase or store.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (SUPABASE_LIVE_MODE) {
    $res = supabase_request('/rest/v1/parking_spaces?select=*', 'GET');
    if ($res['success'] && is_array($res['data'])) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'count'   => count($res['data']),
            'data'    => $res['data']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Fallback store
$spaces = get_local_data('parking_spaces.json', []);

echo json_encode([
    'success' => true,
    'source'  => 'local_fallback',
    'count'   => count($spaces),
    'data'    => $spaces
], JSON_UNESCAPED_UNICODE);
