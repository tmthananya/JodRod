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
    $spaces = $res['data'];

    // Collect owner IDs to enrich with owner names
    $ownerIds = array_values(array_unique(array_filter(array_column($spaces, 'owner_id'))));
    $ownerMap = [];

    if (!empty($ownerIds)) {
        $inList = '(' . implode(',', array_map('urlencode', $ownerIds)) . ')';
        $profRes = supabase_request("/rest/v1/profiles?user_id=in.{$inList}&select=user_id,name", 'GET');
        if ($profRes['success'] && is_array($profRes['data'])) {
            foreach ($profRes['data'] as $p) {
                if (!empty($p['user_id'])) {
                    $ownerMap[$p['user_id']] = $p['name'] ?? 'Community Host';
                }
            }
        }
    }

    foreach ($spaces as &$s) {
        $oUid = $s['owner_id'] ?? null;
        if ($oUid && isset($ownerMap[$oUid])) {
            $s['owner_name'] = $ownerMap[$oUid];
        } elseif ($oUid) {
            $s['owner_name'] = 'Community Host';
        } else {
            $s['owner_name'] = 'Public Relief Area';
        }
    }
    unset($s);

    echo json_encode([
        'success' => true,
        'source'  => 'supabase',
        'count'   => count($spaces),
        'data'    => $spaces
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($res['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to retrieve parking spaces from Supabase: ' . ($res['error'] ?? 'Table unavailable.')
], JSON_UNESCAPED_UNICODE);
