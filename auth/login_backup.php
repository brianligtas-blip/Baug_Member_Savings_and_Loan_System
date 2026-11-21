<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (is_logged_in()) {
    if (current_user()['role'] === 'admin') {
        header('Location: ../admin/admin.php');
    } else {
        header('Location: ../member/dashboard.php');
    }
    exit;
}

$error = '';
$showForgotPassword = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF VALIDATION
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // CAPTCHA VALIDATION
    $captcha = trim($_POST['captcha'] ?? '');
    $expectedCaptcha = $_SESSION['captcha'] ?? '';

    if (
        $expectedCaptcha === '' ||
        !hash_equals((string) $expectedCaptcha, $captcha)
    ) {

        $error = 'Invalid CAPTCHA. Please try again.';

    } else {

        unset($_SESSION['captcha']);

        // FIND USER
        $stmt = $pdo->prepare("
            SELECT
                id,
                full_name,
                email,
                username,
                password,
                role,
                email_verified,
                account_status,
                failed_attempts,
                locked_until,
                lock_tier
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // CHECK FORGOT PASSWORD VISIBILITY
        if (
            $user &&
            (
                (int) $user['failed_attempts'] >= 2 ||
                (int) $user['lock_tier'] > 0
            )
        ) {
            $showForgotPassword = true;
        }

        // CHECK ACCOUNT LOCKOUT
        if ($user && !empty($user['locked_until'])) {

            $lockExpiry = strtotime($user['locked_until']);

            if ($lockExpiry !== false && $lockExpiry > time()) {

                $remaining = $lockExpiry - time();

                $error = "Your account is temporarily locked. Please try again in {$remaining} seconds.";

            } else {

                // EXPIRED LOCKOUT: RESET FAILED ATTEMPTS
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET failed_attempts = 0,
                        locked_until = NULL
                    WHERE id = ?
                ");

                $stmt->execute([$user['id']]);

                $user['failed_attempts'] = 0;
                $user['locked_until'] = null;
            }
        }

        // VERIFY PASSWORD
        if ($error === '') {

            if ($user && password_verify($password, $user['password'])) {

                // CHECK EMAIL VERIFICATION
                if ((int) $user['email_verified'] !== 1) {

                    $error = 'Please verify your email before logging in.';

                // CHECK ACCOUNT STATUS
                } elseif ($user['account_status'] === 'pending') {

                    $error = 'Your account is awaiting administrator approval.';

                } elseif ($user['account_status'] === 'locked') {

                    $error = 'Your account is locked. Please contact the administrator.';

                } elseif ($user['account_status'] !== 'active') {

                    $error = 'Your account is not active. Please contact the administrator.';

                } else {

                    // RESET FAILED ATTEMPTS AFTER SUCCESSFUL LOGIN
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET failed_attempts = 0,
                            locked_until = NULL,
                            lock_tier = 0,
                            last_login_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([$user['id']]);

                    // CREATE LOGIN SESSION
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ];

                    $_SESSION['last_activity'] = time();

                    if ($user['role'] === 'admin') {
                        header('Location: ../admin/admin.php');
                    } else {
                        header('Location: ../member/dashboard.php');
                    }

                    exit;
                }

            } else {

                $error = 'Invalid username or password. Please try again.';

                // COUNT FAILED PASSWORD ATTEMPTS
                if ($user) {

                    $failedAttempts = (int) $user['failed_attempts'] + 1;
                    $lockTier = (int) $user['lock_tier'];

                    // APPLY PROGRESSIVE LOCKOUT
                    if ($failedAttempts >= 3) {

                        $lockTier = min($lockTier + 1, 3);

                        $lockDurations = [
                            1 => 21,
                            2 => 42,
                            3 => 84
                        ];

                        $lockSeconds = $lockDurations[$lockTier];

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = 0,
                                lock_tier = ?,
                                locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $lockTier,
                            $lockSeconds,
                            $user['id']
                        ]);

                        $error = "Too many failed login attempts. Your account is temporarily locked for {$lockSeconds} seconds.";

                        // SEND SECURITY ALERT EMAIL
                        if (!empty($user['email'])) {

                            $subject = 'BAUG Security Alert: Account Temporarily Locked';

                            $message = "
                                <h2>Security Alert</h2>

                                <p>Hello " . e($user['full_name']) . ",</p>

                                <p>
                                    We detected multiple unsuccessful login attempts
                                    on your BAUG Member Savings and Loan account.
                                </p>

                                <p>
                                    <strong>Account:</strong> " . e($user['username']) . "<br>
                                    <strong>Lockout duration:</strong> {$lockSeconds} seconds<br>
                                    <strong>Date and time:</strong> " . date('Y-m-d H:i:s') . "
                                </p>

                                <p>
                                    If this activity was not performed by you,
                                    please secure your account and contact the administrator.
                                </p>

                                <p>BAUG Member Savings and Loan System</p>
                            ";

                            send_email(
                                $user['email'],
                                $user['full_name'],
                                $subject,
                                $message
                            );
                        }

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $failedAttempts,
                            $user['id']
                        ]);
                    }
                    // SHOW FORGOT PASSWORD AFTER TWO FAILED ATTEMPTS
                    if ($failedAttempts >= 2 || $lockTier > 0) {
                        $showForgotPassword = true;
                    }
                }
            }
        }
    }
}


// GENERATE CAPTCHA IF NONE EXISTS
if (empty($_SESSION['captcha'])) {

    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha = '';

    for ($i = 0; $i < 5; $i++) {
        $captcha .= $characters[
            random_int(0, strlen($characters) - 1)
        ];
    }

    $_SESSION['captcha'] = $captcha;
}

$pageTitle = 'Member Login';

require __DIR__ . '/../includes/header.php';

?>

<main class="login-screen">

    <section class="login-card">

        <div class="login-title">
            <h1>Login</h1>
        </div>

        <?php if (!empty($error)): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="login-form">

            <?= csrf_field() ?>

            <!-- Username Field -->
            <div class="login-field">

                <span class="field-icon">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="26"
                         height="26"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="#198754"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M5 21a7 7 0 0 1 14 0"></path>

                    </svg>
                </span>

                <input
                    type="text"
                    name="username"
                    placeholder="Username"
                    autocomplete="username"
                    required
                >

            </div>

            <!-- Password Field -->
            <div class="login-field">

                <span class="field-icon">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="26"
                         height="26"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="#198754"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>

                    </svg>
                </span>

                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- CAPTCHA -->
            <div class="captcha-row">

                <div class="captcha-code">
                    <?= e($_SESSION['captcha'] ?? '') ?>
                </div>

                <button
                    type="button"
                    class="captcha-refresh"
                    aria-label="Refresh CAPTCHA">

                    ⟳

                </button>

                <input
                    type="text"
                    name="captcha"
                    placeholder="Enter CAPTCHA"
                    required
                >

            </div>

            <!-- Login Button -->
            <button type="submit" class="login-button">
                Login
            </button>

        </form>

        <div class="login-links">

            <?php if ($showForgotPassword): ?>
                <a href="forgot_password.php">Forgot Password?</a>
                <span>|</span>
            <?php endif; ?>

            <a href="register.php">Register Here</a>

        </div>

    </section>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>