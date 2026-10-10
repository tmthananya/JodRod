<?php
/**
 * API: Create Parking Space (INSERT)
 * Validates strictly the required columns and inserts into Supabase parking_spaces.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Stage 1: Enforce backend authentication guard
$currentUser = require_auth();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// 1. Validate: Parking Name (required, non-empty)
$name = trim($input['name'] ?? '');
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space name is required.']);
    exit;
}

// 2. Validate: Latitude and Longitude (required, numeric, valid geographical bounds)
if (!isset($input['latitude']) || !is_numeric($input['latitude'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid latitude from the map is required.']);
    exit;
}

if (!isset($input['longitude']) || !is_numeric($input['longitude'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid longitude from the map is required.']);
    exit;
}

$latitude = (float)$input['latitude'];
$longitude = (float)$input['longitude'];

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Latitude or longitude out of geographic bounds.']);
    exit;
}

// 3. Validate: Capacity (required, integer >= 0)
if (!isset($input['capacity']) || !is_numeric($input['capacity'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Total capacity is required and must be a number.']);
    exit;
}

$capacity = (int)$input['capacity'];
if ($capacity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Total capacity must be greater than 0.']);
    exit;
}

// 4. Validate: Available spaces (required, integer >= 0 and <= capacity)
if (!isset($input['available_spaces']) || !is_numeric($input['available_spaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Available spaces is required and must be a number.']);
    exit;
}

$available_spaces = (int)$input['available_spaces'];
if ($available_spaces < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Available spaces cannot be negative.']);
    exit;
}

if ($available_spaces > $capacity) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Available spaces cannot exceed total capacity.']);
    exit;
}

// 5. Validate: Status (required, must be one of: available, full, closed)
$allowed_statuses = ['available', 'full', 'closed'];
$status = trim($input['status'] ?? 'available');
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status must be one of: available, full, closed.']);
    exit;
}

// Build exact record payload with ONLY the required columns
$record = [
    'name'             => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'latitude'         => $latitude,
    'longitude'        => $longitude,
    'capacity'         => $capacity,
    'available_spaces' => $available_spaces,
    'status'           => $status
];

// If in Supabase Live Mode, send POST to Supabase PostgREST endpoint
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request('/rest/v1/parking_spaces', 'POST', $record);
    if ($res['success']) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Parking space created successfully in Supabase.',
            'data'    => $res['data'][0] ?? $record
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to create record in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Fallback persistence (when .env credentials are not yet configured)
// Uses a real generated UUID
$record['id'] = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$existing = get_local_data('parking_spaces.json', []);
array_unshift($existing, $record);
save_local_data('parking_spaces.json', $existing);

http_response_code(201);
echo json_encode([
    'success' => true,
    'source'  => 'local_fallback',
    'message' => 'Parking space created successfully.',
    'data'    => $record
], JSON_UNESCAPED_UNICODE);
