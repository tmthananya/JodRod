<?php
/**
 * API: Cancel Parking Space Reservation (CANCEL)
 * Supabase-Only Source of Truth.
 * 
 * Verifies reservation ownership, marks the record as 'cancelled'
 * in public.reservations, and restores available_spaces on public.parking_spaces.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Enforce backend authentication guard and CSRF verification
$currentUser = require_auth();
require_csrf();
$uid = $currentUser['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$reservationId = trim($input['reservation_id'] ?? '');
if (empty($reservationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reservation ID is required for cancellation.']);
    exit;
}

// 2. Enforce Supabase-Only Storage
if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Attempt 1: Call atomic database RPC function if installed
$rpcRes = supabase_request('/rest/v1/rpc/cancel_reservation', 'POST', [
    'p_reservation_id' => $reservationId,
    'p_user_id'        => $uid
]);

if ($rpcRes['success'] && isset($rpcRes['data']['success'])) {
    if ($rpcRes['data']['success']) {
        echo json_encode($rpcRes['data'], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(400);
        echo json_encode($rpcRes['data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Attempt 2: Direct PostgREST query flow in Supabase
$checkRes = supabase_request("/rest/v1/reservations?id=eq.{$reservationId}&select=*", 'GET');
if (!$checkRes['success'] || empty($checkRes['data'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Reservation record not found.']);
    exit;
}

$res = $checkRes['data'][0];

// Ownership check: User can only cancel their own reservation
if (($res['user_id'] ?? '') !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: You do not have permission to cancel this reservation.']);
    exit;
}

if (($res['status'] ?? '') !== 'confirmed') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'This reservation is already ' . ($res['status'] ?? 'inactive') . '.']);
    exit;
}

// Cancel reservation in Supabase
$patchRes = supabase_request("/rest/v1/reservations?id=eq.{$reservationId}&user_id=eq.{$uid}", 'PATCH', [
    'status' => 'cancelled'
]);

if (!$patchRes['success']) {
    http_response_code($patchRes['http_code'] ?: 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to update reservation in Supabase: ' . ($patchRes['error'] ?? 'Database error.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Increment availability on space
$spaceId = $res['parking_space_id'];
$spaceRes = supabase_request("/rest/v1/parking_spaces?id=eq.{$spaceId}&select=*", 'GET');
if ($spaceRes['success'] && !empty($spaceRes['data'])) {
    $space = $spaceRes['data'][0];
    $newAvail = min((int)$space['capacity'], (int)$space['available_spaces'] + 1);
    $newStatus = ($space['status'] === 'full' && $newAvail > 0) ? 'available' : $space['status'];

    supabase_request("/rest/v1/parking_spaces?id=eq.{$spaceId}", 'PATCH', [
        'available_spaces' => $newAvail,
        'status'           => $newStatus
    ]);
}

echo json_encode([
    'success'        => true,
    'source'         => 'supabase',
    'message'        => 'Reservation cancelled successfully. Spot restored to available parking.',
    'reservation_id' => $reservationId
], JSON_UNESCAPED_UNICODE);
