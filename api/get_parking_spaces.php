<?php
/**
 * API: Get Parking Spaces (READ)
 * Supabase-Only Source of Truth.
 * Returns all active parking spaces from public.parking_spaces table.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

http_response_code($res['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to retrieve parking spaces from Supabase: ' . ($res['error'] ?? 'Table unavailable.')
], JSON_UNESCAPED_UNICODE);
