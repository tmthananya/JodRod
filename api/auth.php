<?php
/**
 * JodRod - Supabase Authentication API
 * Implements Sign Up, Login, Logout, and User Verification using GoTrue endpoints.
 * Strictly uses existing Supabase connection and environment variables.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = strtolower(trim($_GET['action'] ?? ''));
$method = $_SERVER['REQUEST_METHOD'];

function auth_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// 1. GET CURRENT USER / SESSION (action: user or session)
// -------------------------------------------------------------
if ($action === 'user' || $action === 'session') {
    if (empty($_SESSION['access_token']) || empty($_SESSION['auth_user'])) {
        auth_response([
            'success'   => true,
            'logged_in' => false,
            'user'      => null
        ]);
    }

    $token = $_SESSION['access_token'];
    $res = supabase_request('/auth/v1/user', 'GET', null, [
        'Authorization: Bearer ' . $token
    ]);

    if ($res['success'] && !empty($res['data']['id'])) {
        // Token is valid and verified by Supabase
        $_SESSION['auth_user'] = $res['data'];
        auth_response([
            'success'   => true,
            'logged_in' => true,
            'user'      => [
                'id'    => $res['data']['id'],
                'email' => $res['data']['email'] ?? ''
            ]
        ]);
    } else {
        // Expired or invalid token
        unset($_SESSION['auth_user'], $_SESSION['access_token']);
        auth_response([
            'success'   => true,
            'logged_in' => false,
            'user'      => null
        ]);
    }
}

// -------------------------------------------------------------
// 2. SIGN UP (action: signup)
// -------------------------------------------------------------
if ($action === 'signup') {
    if ($method !== 'POST') {
        auth_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        auth_response(['success' => false, 'error' => 'Please enter a valid email address.'], 400);
    }

    if (strlen($password) < 6) {
        auth_response(['success' => false, 'error' => 'Password must be at least 6 characters long.'], 400);
    }

    $signupRes = supabase_request('/auth/v1/signup', 'POST', [
        'email'    => $email,
        'password' => $password
    ]);

    if (!$signupRes['success']) {
        $errData = $signupRes['data'] ?? [];
        $errMsg = $errData['msg'] ?? $errData['error_description'] ?? $signupRes['error'] ?? 'Sign up failed.';
        auth_response(['success' => false, 'error' => $errMsg], $signupRes['http_code'] ?: 400);
    }

    $data = $signupRes['data'] ?? [];
    $user = $data['user'] ?? $data;

    // Check if auto-confirmed (access_token returned immediately)
    if (!empty($data['access_token'])) {
        $_SESSION['access_token'] = $data['access_token'];
        $_SESSION['auth_user'] = $user;
        auth_response([
            'success'        => true,
            'auto_logged_in' => true,
            'message'        => 'Account created and logged in successfully!',
            'user'           => [
                'id'    => $user['id'] ?? '',
                'email' => $user['email'] ?? $email
            ]
        ], 201);
    }

    // Account created, but email confirmation is active in Supabase project
    auth_response([
        'success'               => true,
        'auto_logged_in'        => false,
        'requires_confirmation' => true,
        'message'               => 'Account created successfully! Please log in with your email and password.',
        'user'                  => [
            'id'    => $user['id'] ?? '',
            'email' => $user['email'] ?? $email
        ]
    ], 201);
}

// -------------------------------------------------------------
// 3. LOGIN (action: login)
// -------------------------------------------------------------
if ($action === 'login') {
    if ($method !== 'POST') {
        auth_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($email === '' || $password === '') {
        auth_response(['success' => false, 'error' => 'Email and password are required.'], 400);
    }

    $loginRes = supabase_request('/auth/v1/token?grant_type=password', 'POST', [
        'email'    => $email,
        'password' => $password
    ]);

    if (!$loginRes['success']) {
        $errData = $loginRes['data'] ?? [];
        $errorCode = $errData['error_code'] ?? '';
        $errMsg = $errData['msg'] ?? $errData['error_description'] ?? 'Invalid email or password.';

        if ($errorCode === 'email_not_confirmed') {
            $errMsg = 'Email is not confirmed yet. Please verify your email or disable "Confirm email" in your Supabase project dashboard.';
        } elseif ($errorCode === 'invalid_credentials') {
            $errMsg = 'Invalid email or password. Please try again.';
        }

        auth_response([
            'success'    => false,
            'error'      => $errMsg,
            'error_code' => $errorCode
        ], $loginRes['http_code'] ?: 401);
    }

    $data = $loginRes['data'] ?? [];
    if (empty($data['access_token']) || empty($data['user'])) {
        auth_response(['success' => false, 'error' => 'Malformed response from Supabase auth.'], 500);
    }

    // Save authenticated session
    $_SESSION['access_token'] = $data['access_token'];
    $_SESSION['auth_user'] = $data['user'];

    auth_response([
        'success' => true,
        'message' => 'Logged in successfully.',
        'user'    => [
            'id'    => $data['user']['id'] ?? '',
            'email' => $data['user']['email'] ?? $email
        ]
    ]);
}

// -------------------------------------------------------------
// 4. LOGOUT (action: logout)
// -------------------------------------------------------------
if ($action === 'logout') {
    if (!empty($_SESSION['access_token'])) {
        supabase_request('/auth/v1/logout', 'POST', null, [
            'Authorization: Bearer ' . $_SESSION['access_token']
        ]);
    }

    unset($_SESSION['auth_user'], $_SESSION['access_token']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    auth_response([
        'success' => true,
        'message' => 'Logged out successfully.'
    ]);
}

// Invalid action
auth_response(['success' => false, 'error' => 'Invalid auth action specified.'], 400);
