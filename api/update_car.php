<?php
/**
 * API: Update Car (UPDATE)
 * Updates vehicle details (license plate, model) owned by the currently authenticated user.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Enforce authentication & CSRF verification
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

$carId = trim($input['id'] ?? ($_GET['id'] ?? ''));
$licensePlate = trim($input['license_plate'] ?? '');
$model = trim($input['model'] ?? '');

if (empty($carId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Car ID is required for update.']);
    exit;
}

if (empty($licensePlate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'License plate cannot be empty.']);
    exit;
}

// Check for attempted ownership tampering
if (isset($input['user_id']) && $input['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden: user_id cannot be changed. Vehicle ownership is strictly immutable.'
    ]);
    exit;
}

$safeCarId = urlencode($carId);
$safeUid = urlencode($uid);

// 2. Verify existence and ownership: Must belong to current user
$checkCar = supabase_request("/rest/v1/cars?id=eq.{$safeCarId}&user_id=eq.{$safeUid}&select=id,user_id,license_plate", 'GET');
if (!$checkCar['success'] || empty($checkCar['data'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden: Vehicle not found or you do not have permission to edit it.'
    ]);
    exit;
}

// 3. Check for duplicate license plate under this user (excluding current car ID)
$safePlate = urlencode($licensePlate);
$dupCheck = supabase_request("/rest/v1/cars?user_id=eq.{$safeUid}&license_plate=eq.{$safePlate}&id=neq.{$safeCarId}&select=id", 'GET');
if ($dupCheck['success'] && !empty($dupCheck['data'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'Another vehicle with this license plate is already registered under your account.'
    ]);
    exit;
}

$updatePayload = [
    'license_plate' => htmlspecialchars($licensePlate, ENT_QUOTES, 'UTF-8'),
    'model'         => !empty($model) ? htmlspecialchars($model, ENT_QUOTES, 'UTF-8') : null,
    'updated_at'    => date('c')
];

$updateRes = supabase_request("/rest/v1/cars?id=eq.{$safeCarId}&user_id=eq.{$safeUid}", 'PATCH', $updatePayload);
if ($updateRes['success']) {
    echo json_encode([
        'success' => true,
        'source'  => 'supabase',
        'message' => 'Vehicle updated successfully in Supabase.',
        'data'    => $updateRes['data'][0] ?? array_merge(['id' => $carId, 'user_id' => $uid], $updatePayload)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($updateRes['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to update vehicle in Supabase: ' . ($updateRes['error'] ?? 'Database error.')
], JSON_UNESCAPED_UNICODE);
exit;
