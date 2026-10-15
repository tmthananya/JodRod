<?php
/**
 * API: Update Parking Space (UPDATE)
 * Allows updating ONLY: name, capacity, available_spaces, status.
 * Prevents editing the record ID.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
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

// Check for attempted ownership tampering
if (isset($input['owner_id']) && $input['owner_id'] !== $currentUser['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: owner_id cannot be changed. Parking space ownership is strictly immutable.']);
    exit;
}

// 1. Validate: Record ID (required, cannot be modified)
$id = trim($input['id'] ?? '');
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space ID is required for update.']);
    exit;
}

// 2. Validate: Parking Name (required, non-empty)
$name = trim($input['name'] ?? '');
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space name cannot be empty.']);
    exit;
}

// 3. Validate: Capacity (required, integer > 0)
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

// 5. Automatic Lot Status Calculation
// Capacity values are the source of truth:
// - If owner explicitly marks it 'closed', status is 'closed'.
// - If available_spaces reaches 0, status is automatically 'full'.
// - If available_spaces > 0, status is 'available'.
$statusInput = trim($input['status'] ?? 'available');
if ($statusInput === 'closed') {
    $status = 'closed';
} else {
    $status = ($available_spaces === 0) ? 'full' : 'available';
}

// Prepare update payload strictly with the 4 editable fields
$update_fields = [
    'name'             => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'capacity'         => $capacity,
    'available_spaces' => $available_spaces,
    'status'           => $status
];

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    // 1. Fetch target space to verify existence and ownership
    $check = supabase_request("/rest/v1/parking_spaces?id=eq.{$id}&select=id,name,owner_id", 'GET');
    if (!$check['success'] || empty($check['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Parking space not found with the specified ID.']);
        exit;
    }

    $targetSpace = $check['data'][0];

    // 2. Protect existing unassigned community lots (owner_id IS NULL)
    if (empty($targetSpace['owner_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Community parking spaces cannot be modified.']);
        exit;
    }

    // 3. Enforce ownership: only the owner can modify their space
    if ($targetSpace['owner_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to update this parking space.']);
        exit;
    }

    $res = supabase_request("/rest/v1/parking_spaces?id=eq.{$id}&owner_id=eq.{$currentUser['id']}", 'PATCH', $update_fields);
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Parking space updated successfully in Supabase.',
            'data'    => $res['data'][0] ?? array_merge(['id' => $id, 'owner_id' => $currentUser['id']], $update_fields)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code($res['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to update record in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Database unavailable: Supabase credentials are not configured.']);
exit;
