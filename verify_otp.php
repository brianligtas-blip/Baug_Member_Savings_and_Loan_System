<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Verify Email';

$errors = [];
$success = '';

if (empty($_SESSION['pending_registration_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId = (int) $_SESSION['pending_registration_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) {

        $errors['otp'] = 'Enter a valid six-digit verification code.';

    } else {

        $stmt = $pdo->prepare("
            SELECT id, code_hash, expires_at, attempts, used_at
            FROM otps
            WHERE user_id = ?
            AND purpose = 'register'
            AND used_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$userId]);
        $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRecord) {

            $errors['otp'] = 'No valid verification code was found.';

        } elseif ((int)$otpRecord['attempts'] >= 5) {

            $errors['otp'] = 'Too many incorrect attempts. Please request a new code.';

        } elseif (strtotime($otpRecord['expires_at']) < time()) {

            $errors['otp'] = 'Your verification code has expired.';

        } elseif (!password_verify($otp, $otpRecord['code_hash'])) {

            $updateAttempts = $pdo->prepare("
                UPDATE otps
                SET attempts = attempts + 1
                WHERE id = ?
                AND used_at IS NULL
            ");

            $updateAttempts->execute([$otpRecord['id']]);

            $errors['otp'] = 'Incorrect verification code. Please try again.';

        } else {

            try {

                $pdo->beginTransaction();

                $useOtp = $pdo->prepare("
                    UPDATE otps
                    SET used_at = NOW()
                    WHERE id = ?
                    AND used_at IS NULL
                ");

                $useOtp->execute([$otpRecord['id']]);

                if ($useOtp->rowCount() !== 1) {
                    throw new RuntimeException('OTP was already used.');
                }

                $verifyUser = $pdo->prepare("
                    UPDATE users
                    SET email_verified = 1
                    WHERE id = ?
                ");

                $verifyUser->execute([$userId]);

                $pdo->commit();

                unset($_SESSION['pending_registration_user_id']);

                $success = 'Your email has been verified successfully. Your account is now awaiting administrator approval.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('BAUG OTP Verification Error: ' . $e->getMessage());

                $errors['general'] = 'Verification failed. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';

?>

<div class="registration-card">

  <div class="otp-page">

    <div class="otp-card">

        <div class="otp-icon">
            <i class="fas fa-envelope"></i>
        </div>

        <div class="section-title">
            <h1>Verify Your Email</h1>
        </div>

        <?php if ($success !== ''): ?>

            <p class="success-message">
                <?= e($success) ?>
            </p>

            <a href="login.php" class="btn btn-primary otp-login-btn">
                Go to Login
            </a>

        <?php else: ?>

            <p class="otp-description">
                Enter the six-digit verification code sent to your email address.
            </p>

            <?php if (!empty($errors['general'])): ?>
                <p class="error-message">
                    <?= e($errors['general']) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="verify_otp.php">

                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="otp">Verification Code</label>

                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        maxlength="6"
                        minlength="6"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="Enter 6-digit OTP"
                        required
                    >

                    <?php if (!empty($errors['otp'])): ?>
                        <small class="error-message">
                            <?= e($errors['otp']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary otp-verify-btn">
                    Verify Email
                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>