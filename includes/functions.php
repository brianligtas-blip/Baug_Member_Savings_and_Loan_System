<?php

/*
|--------------------------------------------------------------------------
| BAUG SYSTEM - SHARED SECURITY FUNCTIONS
|--------------------------------------------------------------------------
| Seed values: D1 = 6, D2 = 5
*/

// Configure session security before starting the session.
if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| SEEDED SECURITY SETTINGS
|--------------------------------------------------------------------------
*/

function security_settings() {

    return [
        'seed_d1' => 6,
        'seed_d2' => 5,

        'min_username' => 6,
        'max_username' => 16,

        'min_password' => 9,
        'max_password' => 64,

        'max_name' => 25,

        'forgot_after' => 2,

        'lock_tier_1' => 21,
        'lock_tier_2' => 42,
        'lock_tier_3' => 84,

        'otp_minutes' => 5,

        'idle_minutes' => 6,

        'ip_threshold' => 13,
        'ip_window_minutes' => 10
    ];
}

/*
|--------------------------------------------------------------------------
| HTML OUTPUT ESCAPING
|--------------------------------------------------------------------------
*/

function e($value) {

    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Compatibility with existing pages that use esc().
function esc($value) {
    return e($value);
}

/*
|--------------------------------------------------------------------------
| LOGIN STATUS
|--------------------------------------------------------------------------
*/

function is_logged_in() {

    return isset($_SESSION['user'])
        && isset($_SESSION['user']['id']);
}

function current_user() {

    return $_SESSION['user'] ?? null;
}

/*
|--------------------------------------------------------------------------
| SESSION IDLE TIMEOUT
|--------------------------------------------------------------------------
*/

function require_login() {

    if (!is_logged_in()) {

        header('Location: home.php');
        exit;
    }

    $settings = security_settings();

    $timeout = $settings['idle_minutes'] * 60;

    $now = time();

    $lastActivity = $_SESSION['last_activity'] ?? $now;

    if (($now - $lastActivity) > $timeout) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax'
                ]
            );
        }

        session_destroy();

        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = $now;
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR ACCESS
|--------------------------------------------------------------------------
*/

function require_admin() {

    require_login();

    if (
        !isset($_SESSION['user']['role']) ||
        $_SESSION['user']['role'] !== 'admin'
    ) {

        http_response_code(403);

        exit('Access Denied.');
    }
}

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

function csrf_token() {

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {

    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}

function verify_csrf() {

    $submittedToken = $_POST['csrf_token'] ?? '';

    $storedToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($storedToken) ||
        $storedToken === '' ||
        !hash_equals($storedToken, $submittedToken)
    ) {

        http_response_code(403);

        exit('Invalid security token. Please refresh the page and try again.');
    }
}

/*
|--------------------------------------------------------------------------
| PASSWORD VALIDATION
|--------------------------------------------------------------------------
*/

function validate_password_length($password) {

    $settings = security_settings();

    $length = strlen($password);

    return $length >= $settings['min_password']
        && $length <= $settings['max_password'];
}

/*
|--------------------------------------------------------------------------
| USERNAME VALIDATION
|--------------------------------------------------------------------------
*/

function validate_username_length($username) {

    $settings = security_settings();

    $length = strlen($username);

    return $length >= $settings['min_username']
        && $length <= $settings['max_username'];
}

/*
|--------------------------------------------------------------------------
| PHILIPPINE PESO FORMAT
|--------------------------------------------------------------------------
*/

function money($amount) {

    return '₱' . number_format((float) $amount, 2);
}

/*
|--------------------------------------------------------------------------
| FLASH NOTIFICATIONS
|--------------------------------------------------------------------------
*/

function set_flash($message) {

    $_SESSION['flash'] = $message;
}

function get_flash() {

    $message = $_SESSION['flash'] ?? null;

    unset($_SESSION['flash']);

    return $message;
}