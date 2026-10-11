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

// Check for forged owner_id parameter
if (isset($input['owner_id']) && $input['owner_id'] !== $currentUser['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: owner_id cannot be forged. Parking spaces are linked strictly to your authenticated session.']);
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

// Build exact record payload stamped with authenticated owner_id
$record = [
    'owner_id'         => $currentUser['id'],
    'name'             => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'latitude'         => $latitude,
    'longitude'        => $longitude,
    'capacity'         => $capacity,
    'available_spaces' => $available_spaces,
    'status'           => $status
];

// Send POST to Supabase PostgREST endpoint
if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    http_response_code($res['http_code'] ?: 500);
    echo json_encode([
        'success' => false,
        'source'  => 'supabase',
        'error'   => 'Failed to create record in Supabase: ' . ($res['error'] ?? 'Unknown error')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
