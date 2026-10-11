<?php
/**
 * API: Delete Profile (DELETE)
 * Permanently deletes a profile record by ID.
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
    echo json_encode(['success' => false, 'error' => 'Profile ID is required for deletion.']);
    exit;
}

// Supabase Live Mode
if (SUPABASE_LIVE_MODE) {
    // 1. Fetch target profile to verify existence and ownership
    $safeId = urlencode($id);
    $check = supabase_request("/rest/v1/profiles?id=eq.{$safeId}&select=id,user_id", 'GET');
    if (!$check['success'] || empty($check['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found with the specified ID.']);
        exit;
    }

    $existingProfile = $check['data'][0];

    // 2. Strict ownership check: Must belong to current authenticated user
    if (empty($existingProfile['user_id']) || $existingProfile['user_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to delete this profile.']);
        exit;
    }

    $res = supabase_request("/rest/v1/profiles?id=eq.{$safeId}&user_id=eq.{$currentUser['id']}", 'DELETE');
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'message' => 'Profile deleted successfully from Supabase.',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        http_response_code($res['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'source'  => 'supabase',
            'error'   => 'Failed to delete profile from Supabase: ' . ($res['error'] ?? 'Unknown error')
        ]);
        exit;
    }
}

http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Database unavailable: Supabase credentials are not configured.']);
exit;
