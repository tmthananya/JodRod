<?php
/**
 * API: Get Reservations (READ)
 * 
 * Returns reservations scoped strictly to the authenticated user.
 * Modes:
 * - Driver View (default): Returns reservations created by the current user.
 * - Owner View (?as_owner=1): Returns reservations for parking lots owned by the current user.
 * Supports optional status filtering (?status=pending).
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Enforce backend authentication guard
$currentUser = require_auth();
$uid = $currentUser['id'];

if (!SUPABASE_LIVE_MODE) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error'   => 'Database unavailable: Supabase credentials are not configured.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$asOwner = isset($_GET['as_owner']) && ($_GET['as_owner'] === '1' || $_GET['as_owner'] === 'true');
$statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
$validStatuses = ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'];

$statusClause = '';
if (!empty($statusFilter) && in_array($statusFilter, $validStatuses, true)) {
    $statusClause = "&status=eq." . urlencode($statusFilter);
}

if ($asOwner) {
    // -------------------------------------------------------------------------
    // OWNER VIEW: Reservations for parking spaces owned by current user
    // -------------------------------------------------------------------------
    $safeUid = urlencode($uid);
    $spacesRes = supabase_request("/rest/v1/parking_spaces?owner_id=eq.{$safeUid}&select=id", 'GET');
    
    if (!$spacesRes['success'] || !is_array($spacesRes['data'])) {
        http_response_code($spacesRes['http_code'] ?: 500);
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to retrieve owned parking spaces from Supabase: ' . ($spacesRes['error'] ?? 'Database error.')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $spaceIds = array_column($spacesRes['data'], 'id');
    if (empty($spaceIds)) {
        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'view'    => 'owner',
            'count'   => 0,
            'data'    => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $inList = '(' . implode(',', array_map('urlencode', $spaceIds)) . ')';
    $resUrl = "/rest/v1/reservations?parking_space_id=in.{$inList}{$statusClause}&select=*,cars(id,license_plate,model),parking_spaces(id,name,capacity,available_spaces,status,owner_id)&order=created_at.desc";
    $res = supabase_request($resUrl, 'GET');

    if ($res['success'] && is_array($res['data'])) {
        $reservations = $res['data'];

        // Enrich reservations with driver display names and avatars from profiles
        $driverUserIds = array_values(array_unique(array_filter(array_column($reservations, 'user_id'))));
        $driverProfiles = [];

        if (!empty($driverUserIds)) {
            $userInList = '(' . implode(',', array_map('urlencode', $driverUserIds)) . ')';
            $profRes = supabase_request("/rest/v1/profiles?user_id=in.{$userInList}&select=user_id,name,avatar", 'GET');
            if ($profRes['success'] && is_array($profRes['data'])) {
                foreach ($profRes['data'] as $p) {
                    $driverProfiles[$p['user_id']] = [
                        'name'   => $p['name'] ?? 'Driver',
                        'avatar' => $p['avatar'] ?? null
                    ];
                }
            }
        }

        foreach ($reservations as &$item) {
            $dUid = $item['user_id'] ?? null;
            $item['driver_profile'] = $driverProfiles[$dUid] ?? [
                'name'   => 'Community Driver',
                'avatar' => null
            ];

            // If cars relation was filtered by Supabase RLS, extract from resilient metadata
            if (empty($item['cars']) || empty($item['cars']['license_plate'])) {
                if (!empty($item['notes']) && preg_match('/\[Vehicle:\s*([^\]|]+)(?:\s*\|\s*([^\]]+))?\]/i', $item['notes'], $m)) {
                    $item['cars'] = [
                        'id'            => $item['car_id'] ?? null,
                        'license_plate' => trim($m[1]),
                        'model'         => isset($m[2]) ? trim($m[2]) : 'Standard'
                    ];
                }
            }

            // Clean visible notes for display
            if (!empty($item['notes'])) {
                $item['notes'] = trim(preg_replace('/\[Vehicle:[^\]]+\]\s*/i', '', $item['notes']));
            }
        }
        unset($item);

        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'view'    => 'owner',
            'count'   => count($reservations),
            'data'    => $reservations
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

} else {
    // -------------------------------------------------------------------------
    // DRIVER VIEW: Current user's own reservations
    // -------------------------------------------------------------------------
    $safeUid = urlencode($uid);
    $resUrl = "/rest/v1/reservations?user_id=eq.{$safeUid}{$statusClause}&select=*,cars(id,license_plate,model),parking_spaces(id,name,latitude,longitude,capacity,available_spaces,status,owner_id)&order=created_at.desc";
    $res = supabase_request($resUrl, 'GET');

    if ($res['success'] && is_array($res['data'])) {
        $driverRes = $res['data'];

        // Enrich reservations with owner profiles
        $ownerUserIds = array_values(array_unique(array_filter(array_map(function($r) {
            return $r['parking_spaces']['owner_id'] ?? null;
        }, $driverRes))));
        $ownerProfiles = [];

        if (!empty($ownerUserIds)) {
            $ownerInList = '(' . implode(',', array_map('urlencode', $ownerUserIds)) . ')';
            $profRes = supabase_request("/rest/v1/profiles?user_id=in.{$ownerInList}&select=user_id,name,avatar", 'GET');
            if ($profRes['success'] && is_array($profRes['data'])) {
                foreach ($profRes['data'] as $p) {
                    $ownerProfiles[$p['user_id']] = [
                        'name'   => $p['name'] ?? 'Parking Lot Owner',
                        'avatar' => $p['avatar'] ?? null
                    ];
                }
            }
        }

        foreach ($driverRes as &$item) {
            $oUid = $item['parking_spaces']['owner_id'] ?? null;
            if ($oUid && isset($ownerProfiles[$oUid])) {
                $item['owner_profile'] = $ownerProfiles[$oUid];
            } elseif ($oUid) {
                $item['owner_profile'] = [
                    'name'   => 'Community Lot Owner',
                    'avatar' => null
                ];
            } else {
                $item['owner_profile'] = [
                    'name'   => 'Community Emergency Space',
                    'avatar' => null
                ];
            }

            if (empty($item['cars']) || empty($item['cars']['license_plate'])) {
                if (!empty($item['notes']) && preg_match('/\[Vehicle:\s*([^\]|]+)(?:\s*\|\s*([^\]]+))?\]/i', $item['notes'], $m)) {
                    $item['cars'] = [
                        'id'            => $item['car_id'] ?? null,
                        'license_plate' => trim($m[1]),
                        'model'         => isset($m[2]) ? trim($m[2]) : 'Standard'
                    ];
                }
            }
            if (!empty($item['notes'])) {
                $item['notes'] = trim(preg_replace('/\[Vehicle:[^\]]+\]\s*/i', '', $item['notes']));
            }
        }
        unset($item);

        echo json_encode([
            'success' => true,
            'source'  => 'supabase',
            'view'    => 'driver',
            'count'   => count($driverRes),
            'data'    => $driverRes
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code($res['http_code'] ?: 500);
echo json_encode([
    'success' => false,
    'error'   => 'Failed to retrieve reservations from Supabase: ' . ($res['error'] ?? 'Table unavailable.')
], JSON_UNESCAPED_UNICODE);
exit;
