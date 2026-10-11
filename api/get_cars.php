<?php
/**
 * API: Get Cars (READ)
 * Returns all vehicles owned by the currently authenticated user.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Enforce authentication guard
$currentUser = require_auth();
$uid = $currentUser['id'];

if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Fetch cars scoped strictly to the authenticated user ID
$safeUid = urlencode($uid);
$res = supabase_request("/rest/v1/cars?user_id=eq.{$safeUid}&select=*&order=created_at.desc", 'GET');

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
    'error'   => 'Failed to retrieve cars from Supabase: ' . ($res['error'] ?? 'Table unavailable.')
], JSON_UNESCAPED_UNICODE);
exit;
