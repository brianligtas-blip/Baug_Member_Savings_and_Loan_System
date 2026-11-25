<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Forgot Password';

$error = '';
$username = '';

// Generate CAPTCHA if one does not already exist.
if (empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = baug_generate_login_captcha();
}

// Refresh CAPTCHA when requested.
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha'] = baug_generate_login_captcha();

    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    // Check IP block.
    $ipStatus = check_ip_block($pdo);

    if ($ipStatus['blocked']) {

        $remaining = (int) $ipStatus['remaining'];

        $error = "Too many attempts from your IP address. Please try again in {$remaining} seconds.";

    } else {

        // Validate CAPTCHA.
        $expectedCaptcha = $_SESSION['captcha'] ?? '';

        if (
            $expectedCaptcha === '' ||
            !hash_equals((string) $expectedCaptcha, $captcha)
        ) {

            $error = 'Invalid CAPTCHA. Please try again.';

            log_activity(
                $pdo,
                'CAPTCHA_FAILED',
                $username,
                null,
                'Incorrect or missing password recovery CAPTCHA.'
            );

        } elseif ($username === '') {

            $error = 'Please enter your username.';

        } else {

            // Clear the CAPTCHA after submission.
            unset($_SESSION['captcha']);

            // Find the account using username.
            $stmt = $pdo->prepare("
                SELECT id, username, email, account_status
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                // Use a generic message to avoid revealing accounts.
                $error = 'If the account information is valid, you may continue with account recovery.';

            } else {

                // Clear previous recovery session information.
                unset(
                    $_SESSION['recovery_email'],
                    $_SESSION['recovery_user_id'],
                    $_SESSION['recovery_verified'],
                    $_SESSION['recovery_verified_at'],
                    $_SESSION['recovery_questions_verified'],
                    $_SESSION['recovery_otp_verified'],
                    $_SESSION['recovery_otp_verified_at']
                );

                // Save the identified account for security-question verification.
                $_SESSION['recovery_user_id'] = (int) $user['id'];

                header('Location: verify_security_questions.php');
                exit;
            }
        }
    }

    // Generate a fresh CAPTCHA after a submission.
    if (empty($_SESSION['captcha'])) {
        $_SESSION['captcha'] = baug_generate_login_captcha();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 440px; text-align: center;">

        <div class="login-title"
             style="flex-direction: column; gap: 8px; margin-bottom: 12px;">

            <h1 style="font-size: 22px; color: #064b2e;">
                Forgot Password?
            </h1>

        </div>

        <p style="font-size: 13px; color: #557264; margin-bottom: 25px; line-height: 1.4;">
            Enter your username and complete the CAPTCHA
            to begin account recovery.
        </p>

        <?php if ($error): ?>

            <div class="error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" class="login-form">

            <?= csrf_field() ?>

            <div style="text-align: left;">

                <label style="display: block;
                              font-weight: bold;
                              margin-bottom: 6px;
                              font-size: 13px;
                              color: #064b2e;">
                    Username
                </label>

                <div class="login-field">

                    <span>👤</span>

                    <input
                        type="text"
                        name="username"
                        placeholder="Enter your username"
                        value="<?= e($username) ?>"
                        maxlength="16"
                        required
                    >

                </div>

            </div>

            <!-- CAPTCHA -->
            <div style="text-align: left; margin-top: 18px;">

                <label style="display: block;
                              font-weight: bold;
                              margin-bottom: 7px;
                              font-size: 13px;
                              color: #064b2e;">
                    CAPTCHA
                </label>

                <div class="captcha-row">

                    <div class="captcha-code">
                        <?= e($_SESSION['captcha'] ?? '') ?>
                    </div>

                    <a
                        href="forgot_password.php?refresh_captcha=1"
                        class="captcha-refresh"
                        aria-label="Refresh CAPTCHA"
                        title="Refresh CAPTCHA"
                    >
                        ⟳
                    </a>

                    <input
                        type="text"
                        name="captcha"
                        placeholder="Enter CAPTCHA"
                        autocomplete="off"
                        maxlength="5"
                        required
                    >

                </div>

            </div>

            <button type="submit"
                    class="login-button"
                    style="margin-top: 18px;">
                Continue
            </button>

        </form>

        <div class="login-links" style="margin-top: 22px;">

            <a href="login.php"
               style="color: #087c43;
                      font-weight: bold;
                      text-decoration: none;">
                Back to Login
            </a>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>