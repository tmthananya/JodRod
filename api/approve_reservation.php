<?php
/**
 * API: Approve or Reject Reservation (Owner Action)
 * 
 * Securely handles parking space owner decisions for pending reservation requests.
 * Uses atomic Supabase RPC functions:
 * - 'accept_reservation': Verifies lot ownership, locks capacity, decrements available_spaces by 1.
 * - 'reject_reservation': Verifies lot ownership, marks as 'rejected', releases car.
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
$action = strtolower(trim($input['action'] ?? ''));

// Validate reservation ID
if (empty($reservationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reservation ID is required.']);
    exit;
}

// Strictly accept only 'accept' or 'reject' actions
if (!in_array($action, ['accept', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid action. Must be strictly 'accept' or 'reject'."]);
    exit;
}

// Security: Check for client-supplied owner spoofing
if (isset($input['owner_id']) && $input['owner_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: owner_id cannot be spoofed. Derived strictly from your session.']);
    exit;
}

// 2. Dispatch to the corresponding atomic database RPC
$rpcFunction = ($action === 'accept') ? 'accept_reservation' : 'reject_reservation';
$rpcPayload = [
    'p_reservation_id' => $reservationId,
    'p_owner_id'       => $uid
];

$res = supabase_request("/rest/v1/rpc/{$rpcFunction}", 'POST', $rpcPayload);

if ($res['success']) {
    $data = $res['data'];
    if (is_array($data) && isset($data['success'])) {
        if ($data['success']) {
            echo json_encode([
                'success' => true,
                'source'  => 'supabase',
                'action'  => $action,
                'message' => $data['message'] ?? 'Action completed successfully.',
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            // Map business error returned from PostgreSQL function
            $errorMsg = $data['error'] ?? 'Approval action failed.';
            $httpCode = 400;
            if (stripos($errorMsg, 'Forbidden') !== false) {
                $httpCode = 403;
            } elseif (stripos($errorMsg, 'not found') !== false) {
                $httpCode = 404;
            } elseif (stripos($errorMsg, 'full') !== false || stripos($errorMsg, 'closed') !== false) {
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

    echo json_encode([
        'success' => true,
        'source'  => 'supabase',
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Map HTTP level errors from Supabase
$httpCode = $res['http_code'] ?: 500;
$errMsg = $res['error'] ?? 'Failed to execute approval RPC in Supabase.';
if ($httpCode === 404) {
    $errMsg = 'The reservation system RPC functions are not yet installed in Supabase. Please apply migration 20261011_phase2_create_reservations.sql.';
}

http_response_code($httpCode);
echo json_encode([
    'success' => false,
    'error'   => $errMsg
], JSON_UNESCAPED_UNICODE);
exit;
