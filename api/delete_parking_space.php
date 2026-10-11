<?php
/**
 * API: Delete Parking Space (DELETE)
 * Permanently removes a parking space record from Supabase / database.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Stage 1: Enforce backend authentication guard
$currentUser = require_auth();

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? ($_GET['id'] ?? ''));

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parking space ID is required for deletion.']);
    exit;
}

// Live Supabase Mode
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
        echo json_encode(['success' => false, 'error' => 'Forbidden. Community parking spaces cannot be deleted.']);
        exit;
    }

    // 3. Enforce ownership: only the owner can delete their space
    if ($targetSpace['owner_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to delete this parking space.']);
        exit;
    }

    // 4. Check for active confirmed reservations
    $resCheck = supabase_request("/rest/v1/reservations?parking_space_id=eq.{$id}&status=eq.confirmed&select=id&limit=1", 'GET');
    if ($resCheck['success'] && !empty($resCheck['data'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'Cannot delete parking lot while it has active vehicle reservations. Please cancel reservations first.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $res = supabase_request("/rest/v1/parking_spaces?id=eq.{$id}&owner_id=eq.{$currentUser['id']}", 'DELETE');
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Parking space deleted successfully from Supabase.',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code($res['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to delete record from Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Database unavailable: Supabase credentials are not configured.']);
exit;
