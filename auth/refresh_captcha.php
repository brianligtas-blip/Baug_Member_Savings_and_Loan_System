<?php

require_once __DIR__ . '/../includes/functions.php';

// Generate a random CAPTCHA code.
$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$captcha = '';

for ($i = 0; $i < 5; $i++) {
    $captcha .= $characters[random_int(0, strlen($characters) - 1)];
}

// Store CAPTCHA in the session.
$_SESSION['captcha'] = $captcha;

// Return the code as JSON.
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'captcha' => $captcha
]);

exit;