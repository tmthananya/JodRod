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

// 5. Validate: Status (required, must be one of: available, full, closed)
$allowed_statuses = ['available', 'full', 'closed'];
$status = trim($input['status'] ?? '');
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status must be one of: available, full, closed.']);
    exit;
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
    $res = supabase_request("/rest/v1/parking_spaces?id=eq.{$id}", 'PATCH', $update_fields);
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Parking space updated successfully in Supabase.',
            'data'    => $res['data'][0] ?? array_merge(['id' => $id], $update_fields)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to update record in Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Local Fallback Storage
$spaces = get_local_data('parking_spaces.json', []);
$updated_record = null;

foreach ($spaces as &$space) {
    if (isset($space['id']) && $space['id'] === $id) {
        $space['name'] = $update_fields['name'];
        $space['capacity'] = $update_fields['capacity'];
        $space['available_spaces'] = $update_fields['available_spaces'];
        $space['status'] = $update_fields['status'];
        $updated_record = $space;
        break;
    }
}

if ($updated_record) {
    save_local_data('parking_spaces.json', $spaces);
    echo json_encode([
        'success' => true,
        'source'  => 'local_fallback',
        'message' => 'Parking space updated successfully.',
        'data'    => $updated_record
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Parking space not found with the specified ID.'
    ]);
}
