<?php
/**
 * API: Get Profiles (READ)
 * Retrieves user profiles from Supabase.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Fetch from Supabase if Live Mode is active
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request('/rest/v1/profiles?select=*&order=name.asc', 'GET');
    if ($res['success']) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'count'   => count($res['data']),
            'data'    => $res['data']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 2. Fallback to local storage if Supabase is offline or table does not exist
$profiles = get_local_data('profiles.json', []);
echo json_encode([
    'success' => true,
    'source'  => 'local_fallback',
    'count'   => count($profiles),
    'data'    => $profiles
], JSON_UNESCAPED_UNICODE);
