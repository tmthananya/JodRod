<?php
/**
 * API: Create Reservation (Driver Action)
 * 
 * Securely submits a pending flood parking reservation request for the authenticated driver's vehicle.
 * Calls atomic Supabase RPC 'request_reservation':
 * - Validates car belongs to the authenticated user.
 * - Enforces composite ownership and single active reservation constraint.
 * - Validates parking space is available and owned by a community host (not a legacy orphan).
 * - Creates record in 'pending' status WITHOUT decrementing lot capacity.
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

// Security: Disallow forging user_id
if (isset($input['user_id']) && $input['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: user_id cannot be forged. Derived strictly from your session.']);
    exit;
}

$spaceId = trim($input['parking_space_id'] ?? '');
$carId   = trim($input['car_id'] ?? '');
$notes   = trim($input['notes'] ?? '');

// Input validation
if (empty($carId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vehicle ID (car_id) is required to request a reservation.']);
    exit;
}

if (empty($spaceId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space ID (parking_space_id) is required to request a reservation.']);
    exit;
}

// 2. Execute atomic database RPC: request_reservation
$rpcRes = supabase_request('/rest/v1/rpc/request_reservation', 'POST', [
    'p_parking_space_id' => $spaceId,
    'p_user_id'          => $uid,
    'p_car_id'           => $carId,
    'p_notes'            => !empty($notes) ? htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') : null
]);

if ($rpcRes['success']) {
    $data = $rpcRes['data'];
    if (is_array($data) && isset($data['success'])) {
        if ($data['success']) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'source'  => 'supabase',
                'message' => $data['message'] ?? 'Reservation request submitted. Awaiting parking lot owner approval.',
                'data'    => $data['reservation'] ?? $data
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $errorMsg = $data['error'] ?? 'Failed to submit reservation request.';
            $httpCode = 400;
            if (stripos($errorMsg, 'Forbidden') !== false || stripos($errorMsg, 'does not belong to you') !== false) {
                $httpCode = 403;
            } elseif (stripos($errorMsg, 'already has an active reservation') !== false) {
                $httpCode = 409;
            } elseif (stripos($errorMsg, 'not found') !== false) {
                $httpCode = 404;
            } elseif (stripos($errorMsg, 'full or closed') !== false) {
                $httpCode = 409;
            }
            http_response_code($httpCode);
            echo json_encode([
                'success' => false,
                'error'   => $errorMsg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(201);
    echo json_encode([
        'success'     => true,
        'source'      => 'supabase',
        'data'        => $data,
        'reservation' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$httpCode = $rpcRes['http_code'] ?: 500;
$errMsg = $rpcRes['error'] ?? 'Database error while requesting reservation.';
if ($httpCode === 404) {
    $errMsg = 'The reservation system is not yet installed in Supabase. Please execute migration 20261011_phase2_create_reservations.sql in the Supabase SQL Editor.';
}

http_response_code($httpCode);
echo json_encode([
    'success' => false,
    'error'   => $errMsg
], JSON_UNESCAPED_UNICODE);
exit;
