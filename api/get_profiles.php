<?php
/**
 * API: Get Profiles (READ)
 * Supabase-Only Source of Truth.
 * Retrieves user profiles from public.profiles table.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = strtolower(trim($_GET['action'] ?? ($_GET['mine'] ?? '')));

// 1. Secure retrieval of the current authenticated user's own profile
if ($action === 'me' || $action === '1') {
    if (session_status() === PHP_SESSION_NONE) {
        jodrod_session_start();
    }
    if (empty($_SESSION['auth_user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in to view your profile.']);
        exit;
    }

    $uid = $_SESSION['auth_user']['id'];
    $res = supabase_request("/rest/v1/profiles?user_id=eq.{$uid}&select=id,name,avatar,user_id&limit=1", 'GET');
    if ($res['success'] && !empty($res['data'])) {
        echo json_encode([
            'success'     => true,
            'has_profile' => true,
            'data'        => $res['data'][0]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'     => true,
        'has_profile' => false,
        'data'        => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Fetch public community profiles from Supabase
$res = supabase_request('/rest/v1/profiles?select=id,name,avatar,user_id&order=name.asc', 'GET');
if ($res['success']) {
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
    'error'   => 'Failed to retrieve profiles from Supabase: ' . ($res['error'] ?? 'Database error.')
], JSON_UNESCAPED_UNICODE);
