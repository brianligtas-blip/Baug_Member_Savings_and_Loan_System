<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Forgot Password';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {

        $error = 'Please enter your registered email address.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        // Clear any previous password recovery session.
        unset(
            $_SESSION['recovery_email'],
            $_SESSION['recovery_user_id'],
            $_SESSION['recovery_verified'],
            $_SESSION['recovery_verified_at']
        );

        // Temporarily save the email for the next verification step.
        $_SESSION['recovery_email'] = $email;

        header('Location: verify_security_questions.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 440px; text-align: center;">

        <div class="login-title"
             style="flex-direction: column; gap: 8px; margin-bottom: 12px;">

            <span style="font-size: 40px; color: #075b37;">🔒</span>

            <h1 style="font-size: 22px; color: #064b2e;">
                Forgot Password?
            </h1>

        </div>

        <p style="font-size: 13px; color: #557264; margin-bottom: 25px; line-height: 1.4;">
            Enter your registered email address to begin the account
            recovery process. You will need to answer your assigned
            security questions to verify your identity.
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

                    Email Address

                </label>

                <div class="login-field">

                    <span>✉</span>

                    <input
                        type="email"
                        name="email"
                        placeholder="Enter your email address"
                        value="<?= e($email) ?>"
                        maxlength="100"
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