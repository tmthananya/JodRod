<?php
/**
 * API: Cars Management (Supabase-Only Source of Truth)
 * Handles vehicle registration and listing for authenticated drivers.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

$currentUser = require_auth();
$uid = $currentUser['id'];
$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// 1. GET: List Cars for Authenticated User
// -------------------------------------------------------------
if ($method === 'GET') {
    if (!SUPABASE_LIVE_MODE) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error'   => 'Database unavailable: Supabase credentials are not configured.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = supabase_request("/rest/v1/cars?user_id=eq.{$uid}&select=*&order=created_at.desc", 'GET');
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
}

// -------------------------------------------------------------
// 2. POST: Register a Car
// -------------------------------------------------------------
if ($method === 'POST') {
    require_csrf();

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

    $licensePlate = trim($input['license_plate'] ?? '');
    $model = trim($input['model'] ?? '');

    if (empty($licensePlate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'License plate is required.']);
        exit;
    }

    // Check for existing car with same license plate for this user
    $checkRes = supabase_request("/rest/v1/cars?user_id=eq.{$uid}&license_plate=eq." . urlencode($licensePlate) . "&select=id", 'GET');
    if ($checkRes['success'] && !empty($checkRes['data'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'You have already registered a car with this license plate.']);
        exit;
    }

    $carRecord = [
        'user_id'       => $uid,
        'license_plate' => $licensePlate,
        'model'         => $model ?: null
    ];

    $insertRes = supabase_request('/rest/v1/cars', 'POST', $carRecord);
    if ($insertRes['success']) {
        $created = $insertRes['data'][0] ?? $carRecord;
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Vehicle registered successfully in Supabase!',
            'data'    => $created
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($insertRes['http_code'] ?: 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to register car in Supabase: ' . ($insertRes['error'] ?? 'Database error.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// 3. DELETE: Delete a Car
// -------------------------------------------------------------
if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
    require_csrf();

    if (!SUPABASE_LIVE_MODE) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $carId = trim($input['id'] ?? $_GET['id'] ?? '');

    if (empty($carId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Car ID is required.']);
        exit;
    }

    // Delete directly using owner filter in query
    $delRes = supabase_request("/rest/v1/cars?id=eq.{$carId}&user_id=eq.{$uid}", 'DELETE');
    if ($delRes['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Vehicle deleted successfully from Supabase.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($delRes['http_code'] ?: 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to delete vehicle: ' . ($delRes['error'] ?? 'Database error.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
