<?php
/**
 * API: Get Reservations (READ)
 * Supabase-Only Source of Truth.
 * Returns all reservations for the currently authenticated user from public.reservations.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Enforce backend authentication guard
$currentUser = require_auth();
$uid = $currentUser['id'];

// 2. Enforce Supabase-Only Storage
if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$asOwner = isset($_GET['as_owner']) && ($_GET['as_owner'] === '1' || $_GET['as_owner'] === 'true');

if ($asOwner) {
    // Fetch parking spaces owned by current user
    $spacesRes = supabase_request("/rest/v1/parking_spaces?owner_id=eq.{$uid}&select=id", 'GET');
    if ($spacesRes['success'] && is_array($spacesRes['data'])) {
        $spaceIds = array_column($spacesRes['data'], 'id');
        if (empty($spaceIds)) {
            echo json_encode([
                'success' => true,
                'source'  => 'supabase',
                'count'   => 0,
                'data'    => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $inList = '(' . implode(',', array_map('urlencode', $spaceIds)) . ')';
        $res = supabase_request("/rest/v1/reservations?parking_space_id=in.{$inList}&select=*,cars(id,license_plate,model),parking_spaces(id,name,capacity,available_spaces,status)&order=created_at.desc", 'GET');
    } else {
        $res = $spacesRes;
    }
} else {
    $res = supabase_request("/rest/v1/reservations?user_id=eq.{$uid}&select=*,cars(id,license_plate,model),parking_spaces(id,name,latitude,longitude,capacity,available_spaces,status)&order=created_at.desc", 'GET');
}

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
    'error'   => 'Failed to retrieve reservations from Supabase: ' . ($res['error'] ?? 'Table unavailable.')
], JSON_UNESCAPED_UNICODE);
