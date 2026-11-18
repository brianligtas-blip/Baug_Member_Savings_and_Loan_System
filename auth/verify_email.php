<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// The current BAUG registration system uses a six-digit OTP.
if (!empty($_SESSION['pending_registration_user_id'])) {
    header('Location: verify_otp.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Email Verification';
$message = 'No pending email verification session was found. Please return to registration and try again.';

require __DIR__ . '/../includes/header.php';

?>

<div class="registration-card">
    <h1>Email Verification</h1>

    <p><?= e($message) ?></p>

    <a href="register.php" class="btn btn-primary">
        Back to Registration
    </a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>