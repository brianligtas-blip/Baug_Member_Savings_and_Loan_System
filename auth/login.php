
<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

/*
|--------------------------------------------------------------------------
| CAPTCHA GENERATOR
|--------------------------------------------------------------------------
*/

function baug_generate_login_captcha(): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha = '';

    for ($i = 0; $i < 5; $i++) {
        $captcha .= $characters[
            random_int(0, strlen($characters) - 1)
        ];
    }

    return $captcha;
}

// Refresh CAPTCHA when requested.
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha'] = baug_generate_login_captcha();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| REDIRECT ALREADY AUTHENTICATED USERS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| HANDLE LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    // CAPTCHA VALIDATION
    $expectedCaptcha = $_SESSION['captcha'] ?? '';

    if (
        $expectedCaptcha === '' ||
        !hash_equals((string) $expectedCaptcha, $captcha)
    ) {

        $error = 'Invalid CAPTCHA. Please try again.';

        unset($_SESSION['captcha']);

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
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // FORGOT PASSWORD LINK VISIBILITY
        if (
            $user &&
            (
                (int) $user['failed_attempts'] >= 2 ||
                (int) $user['lock_tier'] > 0
            )
        ) {
            $showForgotPassword = true;
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK TEMPORARY LOCKOUT
        |--------------------------------------------------------------------------
        */

        if ($user && !empty($user['locked_until'])) {

            $lockExpiry = strtotime($user['locked_until']);

            if ($lockExpiry !== false && $lockExpiry > time()) {

                $remaining = $lockExpiry - time();

                $error = "Your account is temporarily locked. Please try again in {$remaining} seconds.";

            } else {

                // Reset failed attempts after the lockout expires.
                // Keep lock_tier for progressive lockout.
                $resetLock = $pdo->prepare("
                    UPDATE users
                    SET
                        failed_attempts = 0,
                        locked_until = NULL
                    WHERE id = ?
                ");

                $resetLock->execute([$user['id']]);

                $user['failed_attempts'] = 0;
                $user['locked_until'] = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY PASSWORD
        |--------------------------------------------------------------------------
        */

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

                    /*
                    |--------------------------------------------------------------------------
                    | GENERATE LOGIN OTP
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $pdo->beginTransaction();

                        // Generate a secure six-digit OTP.
                        $otp = (string) random_int(100000, 999999);

                        // Hash OTP before storing it.
                        $otpHash = password_hash(
                            $otp,
                            PASSWORD_DEFAULT
                        );

                        // Five-minute expiration.
                        $expiresAt = date(
                            'Y-m-d H:i:s',
                            time() + (5 * 60)
                        );

                        // Insert the new login OTP.
                        $insertOtp = $pdo->prepare("
                            INSERT INTO otps (
                                user_id,
                                purpose,
                                code_hash,
                                expires_at,
                                attempts,
                                used_at
                            ) VALUES (
                                ?,
                                'login',
                                ?,
                                ?,
                                0,
                                NULL
                            )
                        ");

                        $insertOtp->execute([
                            $user['id'],
                            $otpHash,
                            $expiresAt
                        ]);

                        $newOtpId = (int) $pdo->lastInsertId();

                        // Prepare login verification email.
                        $subject = 'Your BAUG Login Verification Code';

                        $message = "
                            <div style='
                                font-family: Arial, sans-serif;
                                color: #26352b;
                                line-height: 1.6;
                            '>

                                <h2 style='color: #176b3a;'>
                                    BAUG Login Verification
                                </h2>

                                <p>
                                    Hello " . e($user['full_name']) . ",
                                </p>

                                <p>
                                    We received a login request for your
                                    BAUG Member Savings and Loan account.
                                </p>

                                <p>
                                    Enter this verification code to
                                    complete your login:
                                </p>

                                <h1 style='
                                    letter-spacing: 8px;
                                    color: #176b3a;
                                    font-size: 32px;
                                '>
                                    {$otp}
                                </h1>

                                <p>
                                    This code expires in 5 minutes.
                                </p>

                                <p>
                                    Do not share this code with anyone.
                                </p>

                                <p>
                                    If you did not attempt to log in,
                                    please secure your account.
                                </p>

                                <p>
                                    <strong>
                                        BAUG Member Savings and Loan System
                                    </strong>
                                </p>

                            </div>
                        ";

                        // Send OTP email.
                        $emailSent = send_email(
                            $user['email'],
                            $user['full_name'],
                            $subject,
                            $message
                        );

                        if (!$emailSent) {
                            throw new RuntimeException(
                                'The login verification email could not be sent.'
                            );
                        }

                        // Cancel previous unused login OTPs.
                        $cancelOldOtps = $pdo->prepare("
                            UPDATE otps
                            SET used_at = NOW()
                            WHERE user_id = ?
                            AND purpose = 'login'
                            AND used_at IS NULL
                            AND id <> ?
                        ");

                        $cancelOldOtps->execute([
                            $user['id'],
                            $newOtpId
                        ]);

                        /*
                         * Initialize the login resend tracker.
                         * Initial OTP is not counted as a resend.
                         */
                        $trackingStmt = $pdo->prepare("
                            INSERT INTO otp_resend_limits (
                                user_id,
                                purpose,
                                last_sent_at,
                                window_started_at,
                                resend_count
                            ) VALUES (
                                ?,
                                'login',
                                NOW(),
                                NOW(),
                                0
                            )
                            ON DUPLICATE KEY UPDATE
                                last_sent_at = NOW(),
                                window_started_at = NOW(),
                                resend_count = 0
                        ");

                        $trackingStmt->execute([
                            $user['id']
                        ]);

                        $pdo->commit();

                        /*
                        |--------------------------------------------------------------------------
                        | CREATE PENDING LOGIN STATE
                        |--------------------------------------------------------------------------
                        | Do not create the authenticated user session here.
                        | The user must verify the login OTP first.
                        */

                        unset(
                            $_SESSION['user'],
                            $_SESSION['last_activity'],
                            $_SESSION['pending_registration_user_id']
                        );

                        $_SESSION['pending_login_user_id'] =
                            (int) $user['id'];

                        header('Location: verify_otp.php');
                        exit;

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        error_log(
                            'BAUG Login OTP Error: ' . $e->getMessage()
                        );

                        $error =
                            'Unable to send your login verification code. Please try again later.';
                    }
                }

            } else {

                // Same message for unknown username and incorrect password.
                $error = 'Invalid username or password. Please try again.';

                /*
                |--------------------------------------------------------------------------
                | COUNT FAILED PASSWORD ATTEMPTS
                |--------------------------------------------------------------------------
                */

                if ($user) {

                    $failedAttempts =
                        (int) $user['failed_attempts'] + 1;

                    $lockTier = (int) $user['lock_tier'];

                    if ($failedAttempts >= 3) {

                        // Progressive lockout.
                        $lockTier = min($lockTier + 1, 3);

                        $lockDurations = [
                            1 => 21,
                            2 => 42,
                            3 => 84
                        ];

                        $lockSeconds = $lockDurations[$lockTier];

                        $lockStmt = $pdo->prepare("
                            UPDATE users
                            SET
                                failed_attempts = 0,
                                lock_tier = ?,
                                locked_until = DATE_ADD(
                                    NOW(),
                                    INTERVAL ? SECOND
                                )
                            WHERE id = ?
                        ");

                        $lockStmt->execute([
                            $lockTier,
                            $lockSeconds,
                            $user['id']
                        ]);

                        $error =
                            "Too many failed login attempts. Your account is temporarily locked for {$lockSeconds} seconds.";

                        // SECURITY ALERT EMAIL
                        if (!empty($user['email'])) {

                            $subject =
                                'BAUG Security Alert: Account Temporarily Locked';

                            $message = "
                                <h2>Security Alert</h2>

                                <p>
                                    Hello " . e($user['full_name']) . ",
                                </p>

                                <p>
                                    We detected multiple unsuccessful
                                    login attempts on your BAUG account.
                                </p>

                                <p>
                                    <strong>Account:</strong>
                                    " . e($user['username']) . "
                                </p>

                                <p>
                                    <strong>Lockout duration:</strong>
                                    {$lockSeconds} seconds
                                </p>

                                <p>
                                    <strong>Date and time:</strong>
                                    " . date('Y-m-d H:i:s') . "
                                </p>

                                <p>
                                    If this activity was not performed
                                    by you, please secure your account
                                    and contact the administrator.
                                </p>

                                <p>
                                    BAUG Member Savings and Loan System
                                </p>
                            ";

                            send_email(
                                $user['email'],
                                $user['full_name'],
                                $subject,
                                $message
                            );
                        }

                    } else {

                        $updateAttempts = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = ?
                            WHERE id = ?
                        ");

                        $updateAttempts->execute([
                            $failedAttempts,
                            $user['id']
                        ]);
                    }

                    // Show Forgot Password after two failures.
                    if ($failedAttempts >= 2 || $lockTier > 0) {
                        $showForgotPassword = true;
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GENERATE CAPTCHA
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = baug_generate_login_captcha();
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
            <p class="error">
                <?= e($error) ?>
            </p>
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

                        <rect width="18" height="11"
                              x="3" y="11" rx="2" ry="2"></rect>

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

                <a
                    href="login.php?refresh_captcha=1"
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

                <a href="forgot_password.php">
                    Forgot Password?
                </a>

                <span>|</span>

            <?php endif; ?>

            <a href="register.php">
                Register Here
            </a>

        </div>

    </section>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>