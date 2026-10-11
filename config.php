<?php
/**
 * JodRod (จอดรถ) - Database & Application Configuration
 * Connects securely to Supabase via environment variables.
 * Private credentials are kept strictly on the backend and never exposed to client-side code.
 */

define('APP_NAME', 'JodRod - Flood Parking Community');
define('APP_VERSION', '1.0.0');

// Base Paths
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');

// Ensure local data directory exists
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

/**
 * Load environment variables from .env file if present
 */
function load_env_file($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes
            $value = trim($value, "'\"");
            if (!empty($key) && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Automatically load .env if present
load_env_file(BASE_DIR . '/.env');

// Read Supabase Credentials from Environment Variables
$supabase_url = getenv('SUPABASE_URL') ?: '';
$supabase_anon_key = getenv('SUPABASE_ANON_KEY') ?: '';

define('SUPABASE_URL', $supabase_url);
define('SUPABASE_ANON_KEY', $supabase_anon_key);

// Determine if live Supabase mode is configured
$is_placeholder = (
    empty($supabase_url) || 
    empty($supabase_anon_key) || 
    str_contains($supabase_url, 'YOUR_PROJECT_ID') ||
    $supabase_anon_key === 'YOUR_SUPABASE_ANON_KEY'
);

define('SUPABASE_LIVE_MODE', !$is_placeholder);

/**
 * Secure Server-Side Supabase Client (PostgREST)
 * 
 * @param string $endpoint e.g. '/rest/v1/parking_spaces'
 * @param string $method GET, POST, PATCH, DELETE
 * @param array|null $payload Data for POST/PATCH
 * @param array $headers Additional headers
 * @return array ['success' => bool, 'http_code' => int, 'data' => mixed, 'error' => string|null]
 */
function supabase_request($endpoint, $method = 'GET', $payload = null, $headers = []) {
    if (!SUPABASE_LIVE_MODE) {
        return [
            'success'   => false,
            'http_code' => 503,
            'error'     => 'Supabase is in fallback mode. Credentials not configured in .env',
            'mode'      => 'fallback'
        ];
    }

    $url = rtrim(SUPABASE_URL, '/') . $endpoint;
    $ch = curl_init($url);

    if (session_status() === PHP_SESSION_NONE && function_exists('jodrod_session_start')) {
        jodrod_session_start();
    }

    // Forward active user access_token if present in session, otherwise fallback to SUPABASE_ANON_KEY
    $bearer_token = (!empty($_SESSION['access_token'])) ? $_SESSION['access_token'] : SUPABASE_ANON_KEY;

    $header_map = [
        'apikey'        => 'apikey: ' . SUPABASE_ANON_KEY,
        'authorization' => 'Authorization: Bearer ' . $bearer_token,
        'content-type'  => 'Content-Type: application/json',
        'prefer'        => 'Prefer: return=representation'
    ];

    foreach ($headers as $h) {
        $parts = explode(':', $h, 2);
        if (count($parts) === 2) {
            $header_map[strtolower(trim($parts[0]))] = $h;
        } else {
            $header_map[] = $h;
        }
    }

    $all_headers = array_values($header_map);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $all_headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    @curl_close($ch);

    if ($curl_error) {
        return [
            'success'   => false,
            'http_code' => $http_code,
            'error'     => $curl_error
        ];
    }

    $decoded = json_decode($response, true);
    $success = ($http_code >= 200 && $http_code < 300);

    return [
        'success'   => $success,
        'http_code' => $http_code,
        'data'      => $decoded,
        'error'     => $success ? null : ($decoded['message'] ?? 'HTTP ' . $http_code)
    ];
}

/**
 * Local Data Helpers (Disabled: Supabase PostgreSQL is the only source of truth)
 */
function get_local_data($filename, $default = []) {
    return $default;
}

function save_local_data($filename, $data) {
    return false;
}

/**
 * Safe Session Initializer with strict cookie attributes.
 */
function jodrod_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'],
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'],
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        if (!headers_sent()) {
            @session_start();
        }
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Retrieve current session CSRF token.
 */
function get_csrf_token() {
    jodrod_session_start();
    return $_SESSION['csrf_token'];
}

/**
 * Stage 1 Backend Authentication & CSRF Guard
 * Reusable helper that verifies the current PHP session contains an authenticated Supabase user.
 * On write requests (POST, PATCH, PUT, DELETE), verifies valid X-CSRF-Token header.
 * Exits immediately with HTTP 401 JSON if unauthenticated or token invalid.
 *
 * @return array The authenticated user session array
 */
function require_auth() {
    jodrod_session_start();

    // 1. Verify authenticated user in PHP session
    if (empty($_SESSION['auth_user']) || empty($_SESSION['access_token'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized. Please log in to perform this action.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. CSRF Token Verification for state-modifying requests
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        $client_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($client_token) && function_exists('getallheaders')) {
            $hdrs = getallheaders();
            foreach ($hdrs as $k => $v) {
                if (strcasecmp($k, 'X-CSRF-Token') === 0) {
                    $client_token = $v;
                    break;
                }
            }
        }

        $server_token = $_SESSION['csrf_token'] ?? '';
        if (empty($client_token) || empty($server_token) || !hash_equals($server_token, $client_token)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'Unauthorized. Missing or invalid CSRF token.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Stage 4 Defense: Validate live access token with Supabase Auth
        if (SUPABASE_LIVE_MODE) {
            $token = $_SESSION['access_token'];
            $verifyRes = supabase_request('/auth/v1/user', 'GET', null, [
                'Authorization: Bearer ' . $token
            ]);
            if (!$verifyRes['success'] || empty($verifyRes['data']['id'])) {
                unset($_SESSION['auth_user'], $_SESSION['access_token']);
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Unauthorized. Session expired or revoked in Supabase Auth. Please log in again.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['auth_user'] = $verifyRes['data'];
        }
    }

    return $_SESSION['auth_user'];
}

/**
 * Generate a cryptographically secure UUID v4 string.
 */
function generate_uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

