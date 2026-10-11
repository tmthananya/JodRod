<?php
/**
 * API: Delete Car (DELETE)
 * Deletes a vehicle owned by the currently authenticated user.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
$carId = trim($input['id'] ?? ($_GET['id'] ?? ''));

if (empty($carId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Car ID is required for deletion.']);
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
        'error'   => 'Forbidden: Vehicle not found or you do not have permission to delete it.'
    ]);
    exit;
}

$targetCar = $checkCar['data'][0];

// 3. Check for active reservation dependency (prevent deleting car with active booking)
$resCheck = supabase_request("/rest/v1/reservations?car_id=eq.{$safeCarId}&status=in.(pending,confirmed)&select=id&limit=1", 'GET');
if ($resCheck['success'] && !empty($resCheck['data'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'Cannot delete vehicle while it has an active parking reservation or pending request. Please cancel the reservation first.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Delete scoped strictly to id and user_id
$delRes = supabase_request("/rest/v1/cars?id=eq.{$safeCarId}&user_id=eq.{$safeUid}", 'DELETE');
if ($delRes['success']) {
    echo json_encode([
        'success' => true,
        'source'  => 'supabase',
        'message' => "Vehicle \"{$targetCar['license_plate']}\" deleted successfully from Supabase.",
        'id'      => $carId
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($delRes['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to delete vehicle from Supabase: ' . ($delRes['error'] ?? 'Database error.')
], JSON_UNESCAPED_UNICODE);
exit;
