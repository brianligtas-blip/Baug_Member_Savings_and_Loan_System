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
$lockRemaining = 0;

$settings = security_settings();

/*
|--------------------------------------------------------------------------
| RESTORE ACTIVE ACCOUNT LOCKOUT ON PAGE LOAD
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['login_locked_user_id'])) {

    $lockedUserStmt = $pdo->prepare("
        SELECT id, locked_until
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $lockedUserStmt->execute([
        (int) $_SESSION['login_locked_user_id']
    ]);

    $lockedUser = $lockedUserStmt->fetch(PDO::FETCH_ASSOC);

    if ($lockedUser && !empty($lockedUser['locked_until'])) {

        $lockExpiry = strtotime($lockedUser['locked_until']);

        if ($lockExpiry !== false && $lockExpiry > time()) {

            $lockRemaining = $lockExpiry - time();

            $error = 'Your account is temporarily locked.';

        } else {

            unset($_SESSION['login_locked_user_id']);

        }

    } else {

        unset($_SESSION['login_locked_user_id']);

    }
}

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

    /*
    |--------------------------------------------------------------------------
    | CHECK IP BLOCK
    |--------------------------------------------------------------------------
    */

    $ipStatus = check_ip_block($pdo);

    if ($ipStatus['blocked']) {

        $remaining = (int) $ipStatus['remaining'];

        $error = "Too many login attempts from your IP address. Please try again in {$remaining} seconds.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | CAPTCHA VALIDATION
        |--------------------------------------------------------------------------
        */

        $expectedCaptcha = $_SESSION['captcha'] ?? '';

        if (
            $expectedCaptcha === '' ||
            !hash_equals((string) $expectedCaptcha, $captcha)
        ) {

            $error = 'Invalid CAPTCHA. Please try again.';

            // Record CAPTCHA failure.
            log_activity(
                $pdo,
                'CAPTCHA_FAILED',
                $username,
                null,
                'Incorrect or missing login CAPTCHA.'
            );

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

                    $lockRemaining = $remaining;

                    $_SESSION['login_locked_user_id'] = (int) $user['id'];

                    $error = "Your account is temporarily locked.";

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

                if (
                    $user &&
                    validate_username_length($username) &&
                    preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $username) &&
                    validate_password_length($password) &&
                    password_verify($password, $user['password'])
                ) {

                    // CHECK EMAIL VERIFICATION
                    if ((int) $user['email_verified'] !== 1) {

                        $error = 'Please verify your email before logging in.';

                    // CHECK ACCOUNT STATUS
                    } elseif ($user['account_status'] === 'pending') {

                        $error = 'Invalid username or password.';

                    } elseif ($user['account_status'] === 'locked') {

                        $error = 'Invalid username or password.';

                    } elseif ($user['account_status'] !== 'active') {

                        $error = 'Invalid username or password.';

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
                    $error = 'Invalid username or password.';

                    // Record unsuccessful login attempt.
                    log_activity(
                        $pdo,
                        'LOGIN_FAILED',
                        $username,
                        $user ? (int) $user['id'] : null,
                        'Unsuccessful login attempt.'
                    );

                    // Count failed attempts from this IP across all usernames.
                    $ipResult = record_ip_failure($pdo, 600);

                    // Record the IP blocking event when the threshold is reached.
                    if ($ipResult['blocked']) {

                        log_activity(
                            $pdo,
                            'IP_BLOCKED',
                            $username,
                            $user ? (int) $user['id'] : null,
                            'IP reached 13 failed login attempts within 10 minutes and was blocked for 10 minutes.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | COUNT FAILED PASSWORD ATTEMPTS
                    |--------------------------------------------------------------------------
                    */

                    if ($user) {

                        $failedAttempts = 0;
                        $lockTier = 0;
                        $newLockoutCreated = false;
                        $lockoutId = null;
                        $lockedUntil = null;

                        try {

                            // Start a transaction to prevent simultaneous lockout processing.
                            $pdo->beginTransaction();

                            // Get the latest user data and lock this user's database row.
                            $lockUserStmt = $pdo->prepare("
                                SELECT
                                    id,
                                    full_name,
                                    email,
                                    username,
                                    failed_attempts,
                                    lock_tier,
                                    locked_until
                                FROM users
                                WHERE id = ?
                                LIMIT 1
                                FOR UPDATE
                            ");

                            $lockUserStmt->execute([(int) $user['id']]);

                            $lockedUser = $lockUserStmt->fetch(PDO::FETCH_ASSOC);

                            if (!$lockedUser) {
                                throw new RuntimeException('User record not found during lockout processing.');
                            }

                            // Use the latest database values.
                            $user = $lockedUser;

                            $failedAttempts = (int) $user['failed_attempts'];
                            $lockTier = (int) $user['lock_tier'];

                            $accountIsLocked = false;

                            // Check whether another request has already locked this account.
                            if (!empty($user['locked_until'])) {

                                $existingLockExpiry = strtotime($user['locked_until']);

                                if (
                                    $existingLockExpiry !== false &&
                                    $existingLockExpiry > time()
                                ) {

                                    $lockRemaining = $existingLockExpiry - time();

                                    $_SESSION['login_locked_user_id'] = (int) $user['id'];

                                    $error = 'Your account is temporarily locked.';

                                    $accountIsLocked = true;

                                } else {

                                    // Previous lockout has expired.
                                    $failedAttempts++;
                                }

                            } else {

                                $failedAttempts++;
                            }

                            // Only process a new failure if the account is not currently locked.
                            if (!$accountIsLocked) {

                                if ($failedAttempts >= 3) {

                                    // Progressive lockout.
                                    $lockTier = min($lockTier + 1, 3);

                                    $lockDurations = [
                                        1 => $settings['lock_tier_1'],
                                        2 => $settings['lock_tier_2'],
                                        3 => $settings['lock_tier_3']
                                    ];

                                    $lockSeconds = $lockDurations[$lockTier];

                                    $lockRemaining = $lockSeconds;

                                    $_SESSION['login_locked_user_id'] = (int) $user['id'];

                                    // Update the account lockout.
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

                                    // Retrieve the exact lockout expiration time.
                                    $expiryStmt = $pdo->prepare("
                                        SELECT locked_until
                                        FROM users
                                        WHERE id = ?
                                        LIMIT 1
                                    ");

                                    $expiryStmt->execute([(int) $user['id']]);

                                    $lockedUntil = $expiryStmt->fetchColumn();
                                   
                                    // Register this lockout event only if this tier has no alert yet.
                                    $alertStmt = $pdo->prepare("
                                        INSERT IGNORE INTO lockout_alerts (
                                            user_id,
                                            lock_tier,
                                            locked_until,
                                            email_sent
                                        ) VALUES (?, ?, ?, 0)
                                    ");

                                    $alertStmt->execute([
                                        $user['id'],
                                        $lockTier,
                                        $lockedUntil
                                    ]);

                                    // Send an email only if a new tier record was created.
                                    if ($alertStmt->rowCount() === 1) {

                                        $lockoutId = (int) $pdo->lastInsertId();

                                        $newLockoutCreated = true;

                                    } else {

                                        $lockoutId = null;

                                        $newLockoutCreated = false;
                                    }


                                    $error =
                                        "Too many failed login attempts. Your account is temporarily locked for {$lockSeconds} seconds.";

                                } else {

                                    // Update failed attempts without locking the account.
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
                            }

                            // Commit database changes before sending email.
                            $pdo->commit();

                        } catch (Throwable $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            error_log('BAUG LOCKOUT ERROR: ' . $e->getMessage());

                            $error = 'Unable to process your login attempt. Please try again.';
                        }

                        // Send an alert only when a new lockout event was created.
                        if (
                            $newLockoutCreated &&
                            !empty($user['email']) &&
                            $lockoutId !== null
                        ) {

                            $subject = 'Failed sign-in attempts on your account';

                            $philippineTime = (
                                new DateTimeImmutable(
                                    'now',
                                    new DateTimeZone('Asia/Manila')
                                )
                            )->format('F j, Y h:i:s A');

                            $ipAddress = get_client_ip();

                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

                            $forgotPasswordUrl =
                                'http://localhost/Baug_Member_Savings_and_Loan_System/auth/forgot_password.php';

                            $message = "
                                <h2>BAUG Security Alert</h2>

                                <p>
                                    Hello " . e($user['full_name']) . ",
                                </p>

                                <p>
                                    We detected multiple unsuccessful login attempts
                                    on your BAUG account.
                                </p>

                                <p>
                                    <strong>Account:</strong>
                                    " . e($user['username']) . "
                                </p>

                                <p>
                                    <strong>Number of failed attempts:</strong>
                                    {$failedAttempts}
                                </p>

                                <p>
                                    <strong>Lockout tier:</strong>
                                    {$lockTier}
                                </p>

                                <p>
                                    <strong>Lockout duration:</strong>
                                    {$lockSeconds} seconds
                                </p>

                                <p>
                                    <strong>Date and time (Philippine Time):</strong>
                                    " . e($philippineTime) . "
                                </p>

                                <p>
                                    <strong>IP address:</strong>
                                    " . e($ipAddress) . "
                                </p>

                                <p>
                                    <strong>Browser / Device:</strong>
                                    " . e($userAgent) . "
                                </p>

                                <p>
                                    If this was not you, reset your password now.
                                </p>

                                <p>
                                    <a href='" . e($forgotPasswordUrl) . "'>
                                        Reset Your Password
                                    </a>
                                </p>

                                <p>
                                    BAUG Member Savings and Loan System
                                </p>
                            ";

                            $emailSent = send_email(
                                $user['email'],
                                $user['full_name'],
                                $subject,
                                $message
                            );

                            // Mark the email as sent only when sending succeeds.
                            if ($emailSent) {

                                $sentStmt = $pdo->prepare("
                                    UPDATE lockout_alerts
                                    SET email_sent = 1
                                    WHERE id = ?
                                ");

                                $sentStmt->execute([$lockoutId]);

                                log_activity(
                                    $pdo,
                                    'ALERT_EMAIL_SENT',
                                    $user['username'],
                                    (int) $user['id'],
                                    'Account lockout security alert email sent.'
                                );
                            }
                        }

                        // Record the lockout tier in the activity log.
                        if ($newLockoutCreated) {

                            log_activity(
                                $pdo,
                                'LOCKOUT_TIER_' . $lockTier,
                                $user['username'],
                                (int) $user['id'],
                                "Account locked for {$lockSeconds} seconds."
                            );
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

        <?php if ($lockRemaining > 0): ?>

            <p class="lockout-countdown">
                Try again in
                <strong id="lockoutTimer">
                    <?= (int) $lockRemaining ?>
                </strong>
                seconds.
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

                <div class="password-input-wrapper">

                <input
                    type="password"
                    id="loginPassword"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="toggleLoginPassword()"
                    aria-label="Show password"
                >
                    Show
                </button>

            </div>

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
            <button
                type="submit"
                class="login-button"
                id="loginButton"
                <?= $lockRemaining > 0 ? 'disabled' : '' ?>
            >
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

            <?php if ($lockRemaining > 0): ?>

                <span
                    id="registerLink"
                    class="disabled-register-link"
                    aria-disabled="true"
                >
                    Register Here
                </span>

            <?php else: ?>

                <a href="register.php" id="registerLink">
                    Register Here
                </a>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>