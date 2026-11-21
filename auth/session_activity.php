<?php

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$settings = security_settings();

$timeout = $settings['idle_minutes'] * 60;

$now = time();

$lastActivity = $_SESSION['last_activity'] ?? $now;

// Enforce the server-side idle timeout.
if (($now - $lastActivity) > $timeout) {

    $_SESSION = [];

    session_destroy();

    http_response_code(401);

    echo json_encode(['success' => false]);

    exit;
}

// Record legitimate user activity.
$_SESSION['last_activity'] = $now;

echo json_encode([
    'success' => true
]);

exit;
