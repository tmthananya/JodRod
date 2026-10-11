<?php
/**
 * API: Cancel Parking Space Reservation (Driver or Owner Action)
 * 
 * Securely cancels a pending or confirmed flood parking reservation.
 * Calls atomic Supabase RPC 'cancel_reservation':
 * - Verifies caller is either the driver who reserved the spot OR the lot owner.
 * - Restores capacity (+1 available spot) ONLY if the reservation was in 'confirmed' status.
 * - Leaves capacity unchanged if cancelling a 'pending' request.
 * - Respects the lot's 'closed' status when recalculating status.
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

if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

// Security: Check for client-supplied user_id spoofing
if (isset($input['user_id']) && $input['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: user_id cannot be spoofed. Derived strictly from your session.']);
    exit;
}

// 2. Call atomic database RPC function: cancel_reservation
$rpcRes = supabase_request('/rest/v1/rpc/cancel_reservation', 'POST', [
    'p_reservation_id' => $reservationId,
    'p_user_id'        => $uid
]);

if ($rpcRes['success']) {
    $data = $rpcRes['data'];
    if (is_array($data) && isset($data['success'])) {
        if ($data['success']) {
            echo json_encode([
                'success'        => true,
                'source'         => 'supabase',
                'message'        => $data['message'] ?? 'Reservation cancelled successfully.',
                'reservation_id' => $reservationId
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $errorMsg = $data['error'] ?? 'Cancellation failed.';
            $httpCode = 400;
            if (stripos($errorMsg, 'Forbidden') !== false) {
                $httpCode = 403;
            } elseif (stripos($errorMsg, 'not found') !== false) {
                $httpCode = 404;
            }
            http_response_code($httpCode);
            echo json_encode([
                'success' => false,
                'error'   => $errorMsg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    echo json_encode([
        'success'        => true,
        'source'         => 'supabase',
        'message'        => 'Reservation cancelled successfully.',
        'reservation_id' => $reservationId
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$httpCode = $rpcRes['http_code'] ?: 500;
$errMsg = $rpcRes['error'] ?? 'Database error while cancelling reservation.';
if ($httpCode === 404) {
    $errMsg = 'The reservation system is not yet installed in Supabase. Please execute migration 20261011_phase2_create_reservations.sql in the Supabase SQL Editor.';
}

http_response_code($httpCode);
echo json_encode([
    'success' => false,
    'error'   => $errMsg
], JSON_UNESCAPED_UNICODE);
exit;
