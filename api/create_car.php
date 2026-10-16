<?php
/**
 * API: Create Car (CREATE)
 * Adds a new vehicle belonging to the currently authenticated user.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Check for attempted ownership tampering
if (isset($input['user_id']) && $input['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden: user_id cannot be forged. Vehicle ownership is derived strictly from your authenticated session.'
    ]);
    exit;
}

$licensePlate = trim($input['license_plate'] ?? '');
$model = trim($input['model'] ?? '');

if (empty($licensePlate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'License plate is required.']);
    exit;
}

// Check for duplicate license plate for this user
$safeUid = urlencode($uid);
$safePlate = urlencode($licensePlate);
$checkRes = supabase_request("/rest/v1/cars?user_id=eq.{$safeUid}&license_plate=eq.{$safePlate}&select=id", 'GET');
if ($checkRes['success'] && !empty($checkRes['data'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'A vehicle with this license plate is already registered under your account.'
    ]);
    exit;
}

$modelToSave = null;
if (!empty($model)) {
    json_decode($model);
    if (json_last_error() === JSON_ERROR_NONE) {
        $modelToSave = $model;
    } else {
        $modelToSave = htmlspecialchars($model, ENT_QUOTES, 'UTF-8');
    }
}

$payload = [
    'user_id'       => $uid,
    'license_plate' => htmlspecialchars($licensePlate, ENT_QUOTES, 'UTF-8'),
    'model'         => $modelToSave,
    'created_at'    => date('c'),
    'updated_at'    => date('c')
];

$insertRes = supabase_request('/rest/v1/cars', 'POST', $payload);
if ($insertRes['success']) {
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'source'  => 'supabase',
        'message' => 'Vehicle registered successfully in Supabase.',
        'data'    => $insertRes['data'][0] ?? $payload
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$httpCode = $insertRes['http_code'] ?: 500;
if ($httpCode === 409) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'A vehicle with this license plate is already registered.'
    ]);
    exit;
}

http_response_code($httpCode);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to register vehicle in Supabase: ' . ($insertRes['error'] ?? 'Database error.')
], JSON_UNESCAPED_UNICODE);
exit;
