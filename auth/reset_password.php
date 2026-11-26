<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Reset Password';

$error = '';
$success = '';

// REQUIRE SUCCESSFUL PASSWORD RESET OTP VERIFICATION
if (
    empty($_SESSION['recovery_otp_verified']) ||
    $_SESSION['recovery_otp_verified'] !== true ||
    empty($_SESSION['recovery_user_id']) ||
    empty($_SESSION['recovery_otp_verified_at'])
) {
    header('Location: forgot_password.php');
    exit;
}

// VERIFICATION AUTHORIZATION EXPIRES AFTER 10 MINUTES
if (time() - $_SESSION['recovery_otp_verified_at'] > 600) {

    unset(
        $_SESSION['recovery_user_id'],
        $_SESSION['recovery_questions_verified'],
        $_SESSION['recovery_otp_verified'],
        $_SESSION['recovery_otp_verified_at']
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

    } elseif (
        strlen($newPassword) < 9 ||
        strlen($newPassword) > 64
    ) {

        $error = 'Password must be between 9 and 64 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } else {

        // CHECK PASSWORD STRENGTH
        $passwordTypes = 0;

        if (preg_match('/[a-z]/', $newPassword)) {
            $passwordTypes++;
        }

        if (preg_match('/[A-Z]/', $newPassword)) {
            $passwordTypes++;
        }

        if (preg_match('/[0-9]/', $newPassword)) {
            $passwordTypes++;
        }

        if (preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
            $passwordTypes++;
        }

        if ($passwordTypes < 2) {

            $error = 'Your password must contain at least two types of characters: uppercase letters, lowercase letters, numbers, or special characters.';

        } else {

            try {

                $userId = (int) $_SESSION['recovery_user_id'];

                // GET ACCOUNT INFORMATION
                $userStmt = $pdo->prepare("
                    SELECT id, full_name, email, username, password
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $userStmt->execute([$userId]);

                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {

                    $error = 'Unable to reset your password. Please restart the recovery process.';

                } else {

                    // GET PREVIOUS PASSWORD HASHES
                    $historyStmt = $pdo->prepare("
                        SELECT password_hash
                        FROM password_history
                        WHERE user_id = ?
                    ");

                    $historyStmt->execute([$userId]);

                    $passwordHistory = $historyStmt->fetchAll(PDO::FETCH_COLUMN);

                    // CHECK AGAINST PREVIOUSLY USED PASSWORDS
                    $passwordReused = false;

                    foreach ($passwordHistory as $oldHash) {

                        if (password_verify($newPassword, $oldHash)) {
                            $passwordReused = true;
                            break;
                        }
                    }

                    // ALSO CHECK THE CURRENT PASSWORD
                    if (
                        !$passwordReused &&
                        password_verify($newPassword, $user['password'])
                    ) {
                        $passwordReused = true;
                    }

                    if ($passwordReused) {

                        $error = 'You cannot reuse a previously used password. Please choose a different password.';

                    } else {

                        $hashedPassword = password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );

                        // UPDATE PASSWORD AND HISTORY TOGETHER
                        $pdo->beginTransaction();

                        try {

                            // PRESERVE CURRENT PASSWORD IN HISTORY
                            // IF IT HAS NOT ALREADY BEEN RECORDED.
                            $currentHashRecorded = false;

                            foreach ($passwordHistory as $oldHash) {

                                if (hash_equals($oldHash, $user['password'])) {
                                    $currentHashRecorded = true;
                                    break;
                                }
                            }

                            if (!$currentHashRecorded) {

                                $saveOldPassword = $pdo->prepare("
                                    INSERT INTO password_history
                                        (user_id, password_hash)
                                    VALUES (?, ?)
                                ");

                                $saveOldPassword->execute([
                                    $userId,
                                    $user['password']
                                ]);
                            }

                            // UPDATE ACCOUNT PASSWORD
                            $updateStmt = $pdo->prepare("
                                UPDATE users
                                SET
                                    password = ?,
                                    failed_attempts = 0,
                                    locked_until = NULL,
                                    lock_tier = 0
                                WHERE id = ?
                            ");

                            $updateStmt->execute([
                                $hashedPassword,
                                $userId
                            ]);

                            // SAVE NEW PASSWORD HASH
                            $saveNewPassword = $pdo->prepare("
                                INSERT INTO password_history
                                    (user_id, password_hash)
                                VALUES (?, ?)
                            ");

                            $saveNewPassword->execute([
                                $userId,
                                $hashedPassword
                            ]);

                            $pdo->commit();

                        } catch (Throwable $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            throw $e;
                        }

                        // RECORD SUCCESSFUL PASSWORD CHANGE
                        log_activity(
                            $pdo,
                            'PASSWORD_CHANGED',
                            $user['username'],
                            $userId,
                            'Member successfully changed their password through password recovery.'
                        );

                        // REMOVE RECOVERY AUTHORIZATION
                        unset(
                            $_SESSION['recovery_user_id'],
                            $_SESSION['recovery_verified'],
                            $_SESSION['recovery_verified_at'],
                            $_SESSION['recovery_email'],
                            $_SESSION['recovery_questions_verified'],
                            $_SESSION['recovery_otp_verified'],
                            $_SESSION['recovery_otp_verified_at']
                        );

                        // REGENERATE CSRF TOKEN
                        $_SESSION['csrf_token'] = bin2hex(
                            random_bytes(32)
                        );

                        // SEND PASSWORD CHANGE NOTIFICATION
                        $subject = 'Your password was changed';

                        $message = "
                            <h2>Password Change Notification</h2>

                            <p>Hello " . e($user['full_name']) . ",</p>

                            <p>Your password was changed successfully.</p>

                            <p><strong>Account:</strong> "
                                . e($user['username']) . "</p>

                            <p><strong>Date and time:</strong> "
                                . date('Y-m-d H:i:s') . "</p>

                            <p>If you did not perform this action,
                            please reset your password immediately
                            or contact the administrator.</p>

                            <p>BAUG Member Savings and Loan System</p>
                        ";

                        $emailSent = false;

                        if (!empty($user['email'])) {

                            $emailSent = send_email(
                                $user['email'],
                                $user['full_name'],
                                $subject,
                                $message
                            );
                        }

                        // RECORD SUCCESSFUL EMAIL NOTIFICATION
                        if ($emailSent) {

                            log_activity(
                                $pdo,
                                'ALERT_EMAIL_SENT',
                                $user['username'],
                                $userId,
                                'Password change notification email successfully sent.'
                            );

                            $success = 'Your password has been successfully reset. A notification email has been sent to your registered email address. You may now log in.';

                        } else {

                            $success = 'Your password has been successfully reset, but the notification email could not be sent. You may now log in.';
                        }
                    }
                }

            } catch (Throwable $e) {

                error_log(
                    'BAUG Password Reset Error: ' . $e->getMessage()
                );

                $error = 'Something went wrong. Please try again later.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 440px; text-align: center;">

        <div class="login-title"
             style="flex-direction: column; gap: 8px; margin-bottom: 12px;">

            <h1 style="font-size: 22px; color: #064b2e;">
                RESET PASSWORD
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

                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="lucide lucide-key-round">
                                <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/>
                                <circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
                            </svg>
                        </span>

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

                       <span>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="lucide lucide-key-round">
                                <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/>
                                <circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
                            </svg>
                        </span>

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