<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error']);
    exit;
}

$username = trim($_GET['username'] ?? '');

if ($username === '') {
    echo json_encode(['status' => 'empty']);
    exit;
}

if (
    !validate_username_length($username) ||
    !preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $username)
) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);

    if ($stmt->fetchColumn()) {
        echo json_encode(['status' => 'taken']);
    } else {
        echo json_encode(['status' => 'available']);
    }

} catch (PDOException $e) {

    error_log('Username availability check failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['status' => 'error']);
}