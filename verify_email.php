<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$status = 'error';

$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {

    $message = 'Invalid or missing verification link.';

} else {

    $tokenHash = hash('sha256', $token);

    try {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE verification_token_hash = ?
              AND verification_expires_at > NOW()
              AND email_verified = 0
            LIMIT 1
        ");

        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $update = $pdo->prepare("
                UPDATE users
                SET email_verified = 1,
                    email_verified_at = NOW(),
                    verification_token_hash = NULL,
                    verification_expires_at = NULL
                WHERE id = ?
            ");

            $update->execute([$user['id']]);

            $message = 'Your email address has been successfully verified. Your account is still pending administrator approval.';
            $status = 'success';

        } else {

            $message = 'This verification link is invalid, expired, or has already been used.';

        }

    } catch (PDOException $e) {

        error_log('BAUG Email Verification Error: ' . $e->getMessage());

        $message = 'Unable to verify your email right now. Please try again later.';

    }
}

$pageTitle = 'Email Verification';

require __DIR__ . '/includes/header.php';

?>

<div class="registration-card">
    <h1>Email Verification</h1>

    <p><?= e($message) ?></p>

    <a href="login.php" class="btn btn-primary">Go to Login</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
