<?php
/**
 * API: Create Parking Space Reservation (CREATE)
 * Supabase-Only Source of Truth.
 * 
 * Atomically creates a reservation record in public.reservations
 * and decrements available_spaces on public.parking_spaces.
 * Enforces one active reservation per car and prevents overbooking.
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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$spaceId = trim($input['parking_space_id'] ?? '');
$carId   = !empty($input['car_id']) ? trim($input['car_id']) : null;
$notes   = trim($input['notes'] ?? '');

if (empty($spaceId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space ID is required for reservation.']);
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
$rpcRes = supabase_request('/rest/v1/rpc/create_reservation', 'POST', [
    'p_parking_space_id' => $spaceId,
    'p_user_id'          => $currentUser['id'],
    'p_car_id'           => $carId,
    'p_notes'            => $notes ?: null
]);

if ($rpcRes['success'] && isset($rpcRes['data']['success'])) {
    if ($rpcRes['data']['success']) {
        http_response_code(201);
        echo json_encode($rpcRes['data'], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(400);
        echo json_encode($rpcRes['data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Attempt 2: Direct PostgREST atomic flow in Supabase
// Step A: If car_id is provided, verify ownership and active reservation status
if ($carId !== null) {
    $carRes = supabase_request("/rest/v1/cars?id=eq.{$carId}&user_id=eq.{$currentUser['id']}&select=id", 'GET');
    if (!$carRes['success'] || empty($carRes['data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Specified vehicle not found or does not belong to you.']);
        exit;
    }

    $activeCarRes = supabase_request("/rest/v1/reservations?car_id=eq.{$carId}&status=eq.confirmed&select=id", 'GET');
    if ($activeCarRes['success'] && !empty($activeCarRes['data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'This vehicle already has an active reservation.']);
        exit;
    }
}

// Step B: Retrieve live parking space from Supabase
$spaceRes = supabase_request("/rest/v1/parking_spaces?id=eq.{$spaceId}&select=*", 'GET');
if (!$spaceRes['success'] || empty($spaceRes['data'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Parking space not found with the specified ID.']);
    exit;
}

$space = $spaceRes['data'][0];

// Step C: Capacity & Status Verification
if ($space['status'] !== 'available' || (int)$space['available_spaces'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'This parking space is currently full or closed.']);
    exit;
}

// Step D: Prevent duplicate active reservation for this space if car_id not supplied
if ($carId === null) {
    $dupCheck = supabase_request("/rest/v1/reservations?user_id=eq.{$currentUser['id']}&parking_space_id=eq.{$spaceId}&status=eq.confirmed&select=id", 'GET');
    if ($dupCheck['success'] && !empty($dupCheck['data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You already have an active reservation for this parking lot.']);
        exit;
    }
}

// Step E: Insert reservation into Supabase public.reservations
$resRecord = [
    'user_id'          => $currentUser['id'],
    'car_id'           => $carId,
    'parking_space_id' => $spaceId,
    'status'           => 'confirmed',
    'notes'            => $notes ?: null
];
$insertRes = supabase_request('/rest/v1/reservations', 'POST', $resRecord);

if (!$insertRes['success']) {
    http_response_code($insertRes['http_code'] ?: 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to create reservation in Supabase: ' . ($insertRes['error'] ?? 'Database error.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$createdRes = $insertRes['data'][0] ?? $resRecord;

// Step F: Decrement space availability
$newAvail = max(0, (int)$space['available_spaces'] - 1);
$newStatus = ($newAvail === 0) ? 'full' : 'available';

supabase_request("/rest/v1/parking_spaces?id=eq.{$spaceId}", 'PATCH', [
    'available_spaces' => $newAvail,
    'status'           => $newStatus
]);

http_response_code(201);
echo json_encode([
    'success'       => true,
    'source'        => 'supabase',
    'message'       => 'Parking spot reserved successfully in Supabase!',
    'data'          => $createdRes,
    'reservation'   => $createdRes,
    'parking_space' => [
        'id'               => $space['id'],
        'name'             => $space['name'],
        'available_spaces' => $newAvail,
        'status'           => $newStatus
    ]
], JSON_UNESCAPED_UNICODE);
