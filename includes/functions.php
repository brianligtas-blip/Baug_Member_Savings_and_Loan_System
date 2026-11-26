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

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ../home.php');
        exit;
    }

    $settings = security_settings();

    $timeout = $settings['idle_minutes'] * 60;

    $now = time();

    $lastActivity = $_SESSION['last_activity'] ?? $now;

    // Check session expiration.
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

        header('Location: ../auth/login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = $now;
}
/*
|--------------------------------------------------------------------------
| ADMINISTRATOR ACCESS
|--------------------------------------------------------------------------
*/

function require_admin(): void
{
    // Ensure the user is logged in and the session is valid.
    require_login();

    // Get the authenticated user's role.
    $role = current_user()['role'] ?? '';

    // Allow administrators only.
    if (strtolower(trim($role)) !== 'admin') {

        log_activity(
            $GLOBALS['pdo'],
            'ADMIN_ACCESS_DENIED',
            current_user()['username'] ?? null,
            isset(current_user()['id'])
                ? (int) current_user()['id']
                : null,
            'Non-administrator attempted to access an admin-only page.'
        );

        http_response_code(403);

        exit('Access denied. You are not authorized to access this page.');
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

        // Record rejected CSRF request.
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {

            log_activity(
                $GLOBALS['pdo'],
                'CSRF_REJECTED',
                null,
                isset($_SESSION['user']['id'])
                    ? (int) $_SESSION['user']['id']
                    : null,
                'Request rejected because the CSRF token was invalid or missing.'
            );
        }

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

/*
|--------------------------------------------------------------------------
| CLIENT IP ADDRESS
|--------------------------------------------------------------------------
*/

function get_client_ip(): string
{
    // Use the direct connection address.
    // Do not trust forwarded headers from arbitrary clients.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/*
|--------------------------------------------------------------------------
| ACTIVITY LOGGING
|--------------------------------------------------------------------------
*/

function log_activity(
    PDO $pdo,
    string $eventType,
    ?string $usernameTyped = null,
    ?int $userId = null,
    ?string $details = null
): bool {

    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            event_type,
            username_typed,
            user_id,
            ip_address,
            user_agent,
            details
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $eventType,
        $usernameTyped,
        $userId,
        get_client_ip(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 1000),
        $details
    ]);
}

/*
|--------------------------------------------------------------------------
| SECURITY FLAG CREATION
|--------------------------------------------------------------------------
*/

function create_security_flag(
    PDO $pdo,
    string $flagType,
    string $description,
    string $severity = 'medium',
    ?int $userId = null
): bool {

    $allowedSeverities = [
        'low',
        'medium',
        'high',
        'critical'
    ];

    if (!in_array($severity, $allowedSeverities, true)) {
        $severity = 'medium';
    }

    $stmt = $pdo->prepare("
        INSERT INTO security_flags (
            user_id,
            ip_address,
            flag_type,
            description,
            severity,
            is_reviewed
        ) VALUES (?, ?, ?, ?, ?, 0)
    ");

    return $stmt->execute([
        $userId,
        get_client_ip(),
        $flagType,
        $description,
        $severity
    ]);
}

/*
|--------------------------------------------------------------------------
| IP BLOCK CHECK
|--------------------------------------------------------------------------
*/

function check_ip_block(PDO $pdo): array
{
    $ipAddress = get_client_ip();

    $stmt = $pdo->prepare("
        SELECT
            failed_attempts,
            window_started_at,
            blocked_until
        FROM ip_blocks
        WHERE ip_address = ?
        LIMIT 1
    ");

    $stmt->execute([$ipAddress]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        return [
            'blocked' => false,
            'remaining' => 0
        ];
    }

    if (empty($record['blocked_until'])) {
        return [
            'blocked' => false,
            'remaining' => 0
        ];
    }

    $remaining = strtotime($record['blocked_until']) - time();

    if ($remaining <= 0) {
        return [
            'blocked' => false,
            'remaining' => 0
        ];
    }

    return [
        'blocked' => true,
        'remaining' => $remaining
    ];
}

/*
|--------------------------------------------------------------------------
| RECORD FAILED IP ATTEMPT
|--------------------------------------------------------------------------
| Tracks failed attempts within the configured time window.
| The block duration must be supplied according to the handout.
|--------------------------------------------------------------------------
*/

function record_ip_failure(
    PDO $pdo,
    int $blockSeconds
): array {

    $settings = security_settings();

    $ipAddress = get_client_ip();
    $threshold = $settings['ip_threshold'];
    $windowMinutes = $settings['ip_window_minutes'];

    $stmt = $pdo->prepare("
        INSERT INTO ip_blocks (
            ip_address,
            failed_attempts,
            window_started_at,
            blocked_until
        ) VALUES (
            ?,
            1,
            NOW(),
            NULL
        )
        ON DUPLICATE KEY UPDATE

            failed_attempts = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(),
                    INTERVAL {$windowMinutes} MINUTE
                )
                THEN 1
                ELSE failed_attempts + 1
            END,

            window_started_at = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(),
                    INTERVAL {$windowMinutes} MINUTE
                )
                THEN NOW()
                ELSE window_started_at
            END,

            blocked_until = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(),
                    INTERVAL {$windowMinutes} MINUTE
                )
                THEN NULL
                ELSE blocked_until
            END
    ");

    $stmt->execute([$ipAddress]);

    $select = $pdo->prepare("
        SELECT failed_attempts, blocked_until
        FROM ip_blocks
        WHERE ip_address = ?
        LIMIT 1
    ");

    $select->execute([$ipAddress]);

    $record = $select->fetch(PDO::FETCH_ASSOC);

    $failedAttempts = (int) $record['failed_attempts'];

    if ($failedAttempts >= $threshold) {

        $block = $pdo->prepare("
            UPDATE ip_blocks
            SET blocked_until = DATE_ADD(
                NOW(),
                INTERVAL ? SECOND
            )
            WHERE ip_address = ?
        ");

        $block->execute([
            $blockSeconds,
            $ipAddress
        ]);

        return [
            'blocked' => true,
            'failed_attempts' => $failedAttempts,
            'block_seconds' => $blockSeconds
        ];
    }

    return [
        'blocked' => false,
        'failed_attempts' => $failedAttempts,
        'block_seconds' => 0
    ];
}

function baug_generate_login_captcha(): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha = '';

    for ($i = 0; $i < 5; $i++) {
        $captcha .= $characters[
            random_int(0, strlen($characters) - 1)
        ];
    }

    return $captcha;
}