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

    $default_headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $all_headers = array_merge($default_headers, $headers);

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
 * Local Data Helpers for zero-risk fallback
 */
function get_local_data($filename, $default = []) {
    $path = DATA_DIR . '/' . $filename;
    if (!file_exists($path)) {
        file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }
    $raw = file_get_contents($path);
    return json_decode($raw, true) ?: $default;
}

function save_local_data($filename, $data) {
    $path = DATA_DIR . '/' . $filename;
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
