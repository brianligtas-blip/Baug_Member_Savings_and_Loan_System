<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Reset Password';

$error = '';
$success = '';

// Require successful security-question verification.
if (
    empty($_SESSION['recovery_verified']) ||
    $_SESSION['recovery_verified'] !== true ||
    empty($_SESSION['recovery_user_id']) ||
    empty($_SESSION['recovery_verified_at'])
) {
    header('Location: forgot_password.php');
    exit;
}

// Verification authorization expires after 10 minutes.
if (time() - $_SESSION['recovery_verified_at'] > 600) {

    unset(
        $_SESSION['recovery_user_id'],
        $_SESSION['recovery_verified'],
        $_SESSION['recovery_verified_at']
    );

    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {

        $error = 'Please complete both password fields.';

    } elseif (strlen($newPassword) < 9 || strlen($newPassword) > 64) {

        $error = 'Password must be between 9 and 64 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } else {

        try {

            $userId = (int) $_SESSION['recovery_user_id'];

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $hashedPassword,
                $userId
            ]);

            if ($stmt->rowCount() > 0) {

                // Remove recovery authorization after successful reset.
                unset(
                    $_SESSION['recovery_user_id'],
                    $_SESSION['recovery_verified'],
                    $_SESSION['recovery_verified_at'],
                    $_SESSION['recovery_email']
                );

                // Generate a fresh CSRF token.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $success = 'Your password has been successfully reset. You may now log in.';

            } else {

                $error = 'Unable to reset your password. Please try again.';
            }

        } catch (PDOException $e) {

            $error = 'Something went wrong. Please try again later.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 440px; text-align: center;">

        <div class="login-title"
             style="flex-direction: column; gap: 8px; margin-bottom: 12px;">

            <span style="font-size: 40px; color: #075b37;">🔐</span>

            <h1 style="font-size: 22px; color: #064b2e;">
                Reset Password
            </h1>

        </div>

        <p style="font-size: 13px;
                  color: #557264;
                  margin-bottom: 25px;
                  line-height: 1.5;">

            Create a new password for your account.

        </p>

        <?php if ($error): ?>

            <div class="error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success): ?>

            <div class="success" style="margin-bottom: 18px;">
                <?= e($success) ?>
            </div>

            <a href="login.php"
               class="login-button"
               style="display: block;
                      text-decoration: none;
                      margin-top: 18px;">

                Go to Login

            </a>

        <?php else: ?>

            <form method="POST" class="login-form">

                <?= csrf_field() ?>

                <div style="text-align: left;">

                    <label style="display: block;
                                  font-weight: bold;
                                  margin-bottom: 6px;
                                  font-size: 13px;
                                  color: #064b2e;">

                        New Password

                    </label>

                    <div class="login-field">

                        <span>🔑</span>

                        <input
                            type="password"
                            name="new_password"
                            placeholder="Enter new password"
                            minlength="9"
                            maxlength="64"
                            required
                        >

                    </div>

                </div>

                <div style="text-align: left; margin-top: 12px;">

                    <label style="display: block;
                                  font-weight: bold;
                                  margin-bottom: 6px;
                                  font-size: 13px;
                                  color: #064b2e;">

                        Confirm New Password

                    </label>

                    <div class="login-field">

                        <span>🔑</span>

                        <input
                            type="password"
                            name="confirm_password"
                            placeholder="Confirm your new password"
                            minlength="9"
                            maxlength="64"
                            required
                        >

                    </div>

                </div>

                <button type="submit"
                        class="login-button"
                        style="margin-top: 18px;">

                    Reset Password

                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>