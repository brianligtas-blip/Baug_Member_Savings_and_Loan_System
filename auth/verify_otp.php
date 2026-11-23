<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

$pageTitle = 'OTP Verification';

$errors = [];
$success = '';
$notice = '';

/*
|--------------------------------------------------------------------------
| DETERMINE VERIFICATION PURPOSE FROM THE SESSION
|--------------------------------------------------------------------------
| Never trust a purpose value submitted by the browser.
*/

$registrationMode = !empty($_SESSION['pending_registration_user_id']);
$loginMode = !empty($_SESSION['pending_login_user_id']);

if ($registrationMode && !$loginMode) {
    $purpose = 'register';
    $userId = (int) $_SESSION['pending_registration_user_id'];
    $pageTitle = 'Verify Your Email';
} elseif ($loginMode && !$registrationMode) {
    $purpose = 'login';
    $userId = (int) $_SESSION['pending_login_user_id'];
    $pageTitle = 'Verify Your Login';
} else {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| GET ACCOUNT DETAILS
|--------------------------------------------------------------------------
*/

$userStmt = $pdo->prepare("
    SELECT id, full_name, email, email_verified, account_status, role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    unset(
        $_SESSION['pending_registration_user_id'],
        $_SESSION['pending_login_user_id']
    );

    header('Location: login.php');
    exit;
}

if ($purpose === 'register' && (int) $user['email_verified'] === 1) {
    unset($_SESSION['pending_registration_user_id']);
    header('Location: login.php');
    exit;
}

if (
    $purpose === 'login' &&
    (
        (int) $user['email_verified'] !== 1 ||
        $user['account_status'] !== 'active'
    )
) {
    unset($_SESSION['pending_login_user_id']);
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? 'verify';

    /*
    |--------------------------------------------------------------------------
    | RESEND OTP
    |--------------------------------------------------------------------------
    */

    if ($action === 'resend') {

        try {

            $pdo->beginTransaction();

            // Lock the resend tracking record.
            $trackingStmt = $pdo->prepare("
                SELECT last_sent_at, window_started_at, resend_count
                FROM otp_resend_limits
                WHERE user_id = ?
                AND purpose = ?
                FOR UPDATE
            ");

            $trackingStmt->execute([$userId, $purpose]);
            $tracking = $trackingStmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new RuntimeException(
                    'OTP resend information is unavailable. Please return to the login page and try again.'
                );
            }

            $now = time();

            $windowStart = strtotime($tracking['window_started_at']);
            $lastSent = strtotime($tracking['last_sent_at']);

            $windowExpired = ($now >= ($windowStart + 900));

            $currentResendCount = $windowExpired
                ? 0
                : (int) $tracking['resend_count'];

            // 60-second cooldown.
            $cooldownEnds = $lastSent + 60;

            if ($now < $cooldownEnds) {
                throw new RuntimeException(
                    'Please wait ' . ($cooldownEnds - $now) .
                    ' second(s) before requesting another code.'
                );
            }

            // Maximum of 3 resends within 15 minutes.
            if ($currentResendCount >= 3) {
                $wait = ($windowStart + 900) - $now;

                throw new RuntimeException(
                    'You have reached the maximum of 3 resends. Please wait ' .
                    max(1, $wait) .
                    ' second(s) before requesting another code.'
                );
            }

            // Recheck the account before sending another OTP.
            $userStmt = $pdo->prepare("
                SELECT full_name, email, email_verified, account_status
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException('The account could not be found.');
            }

            if (
                $purpose === 'register' &&
                (int) $user['email_verified'] === 1
            ) {
                throw new RuntimeException(
                    'This email address has already been verified.'
                );
            }

            if (
                $purpose === 'login' &&
                (
                    (int) $user['email_verified'] !== 1 ||
                    $user['account_status'] !== 'active'
                )
            ) {
                throw new RuntimeException(
                    'This account is not currently available for login.'
                );
            }

            // Generate and hash a secure six-digit OTP.
            $otp = (string) random_int(100000, 999999);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + (5 * 60)
            );

            $insertOtp = $pdo->prepare("
                INSERT INTO otps (
                    user_id,
                    purpose,
                    code_hash,
                    expires_at,
                    attempts,
                    used_at
                ) VALUES (?, ?, ?, ?, 0, NULL)
            ");

            $insertOtp->execute([
                $userId,
                $purpose,
                $otpHash,
                $expiresAt
            ]);

            $newOtpId = (int) $pdo->lastInsertId();

            // Prepare the appropriate email.
            if ($purpose === 'register') {

                $subject = 'Verify Your BAUG Account';
                $heading = 'BAUG Email Verification';
                $description = 'Your new account verification code is:';

            } else {

                $subject = 'Your BAUG Login Verification Code';
                $heading = 'BAUG Login Verification';
                $description = 'Use this code to complete your login:';
            }

            $emailMessage = "
                <div style='font-family:Arial,sans-serif;color:#26352b;line-height:1.6;'>
                    <h2 style='color:#176b3a;'>{$heading}</h2>

                    <p>Hello " . e($user['full_name']) . ",</p>

                    <p>{$description}</p>

                    <h1 style='
                        letter-spacing:8px;
                        color:#176b3a;
                        font-size:32px;
                    '>{$otp}</h1>

                    <p>This code expires in 5 minutes.</p>

                    <p>Do not share this code with anyone.</p>

                    <p>If you did not request this code, you may ignore this email.</p>

                    <p>
                        <strong>BAUG Member Savings and Loan System</strong>
                    </p>
                </div>
            ";

            // Send the new OTP before cancelling the previous one.
            $emailSent = send_email(
                $user['email'],
                $user['full_name'],
                $subject,
                $emailMessage
            );

            if (!$emailSent) {
                throw new RuntimeException(
                    'The verification email could not be sent. Your previous code is still valid. Please try again.'
                );
            }

            // Cancel older unused OTPs for this purpose.
            $cancelOldOtps = $pdo->prepare("
                UPDATE otps
                SET used_at = NOW()
                WHERE user_id = ?
                AND purpose = ?
                AND used_at IS NULL
                AND id <> ?
            ");

            $cancelOldOtps->execute([
                $userId,
                $purpose,
                $newOtpId
            ]);

            // Update resend tracking.
            if ($windowExpired) {

                $updateTracking = $pdo->prepare("
                    UPDATE otp_resend_limits
                    SET
                        last_sent_at = NOW(),
                        window_started_at = NOW(),
                        resend_count = 1
                    WHERE user_id = ?
                    AND purpose = ?
                ");

            } else {

                $updateTracking = $pdo->prepare("
                    UPDATE otp_resend_limits
                    SET
                        last_sent_at = NOW(),
                        resend_count = resend_count + 1
                    WHERE user_id = ?
                    AND purpose = ?
                ");
            }

            $updateTracking->execute([$userId, $purpose]);

            $pdo->commit();

            $notice = 'A new verification code has been sent to your registered email address.';

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('BAUG OTP Resend Database Error: ' . $e->getMessage());

            $errors['resend'] =
                'A system error occurred while resending your code. Please try again later.';

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors['resend'] = $e->getMessage();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('BAUG OTP Resend Error: ' . $e->getMessage());

            $errors['resend'] =
                'Unable to resend your code at this time. Please try again.';
        }

    /*
    |--------------------------------------------------------------------------
    | VERIFY OTP
    |--------------------------------------------------------------------------
    */

    } elseif ($action === 'verify') {

        $otp = trim($_POST['otp'] ?? '');

        if (!preg_match('/^\d{6}$/', $otp)) {

            $errors['otp'] = 'Enter a valid six-digit verification code.';

        } else {

            try {

                $pdo->beginTransaction();

                // Lock the latest unused OTP to prevent simultaneous reuse.
                $stmt = $pdo->prepare("
                    SELECT id, code_hash, expires_at, attempts, used_at
                    FROM otps
                    WHERE user_id = ?
                    AND purpose = ?
                    AND used_at IS NULL
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([$userId, $purpose]);
                $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$otpRecord) {

                    $pdo->rollBack();

                    $errors['otp'] =
                        'No valid verification code was found. Please request a new code.';

                } elseif ((int) $otpRecord['attempts'] >= 3) {

                    $pdo->rollBack();

                    $errors['otp'] =
                        'Too many incorrect attempts. Please request a new code.';

                } elseif (strtotime($otpRecord['expires_at']) < time()) {

                    $pdo->rollBack();

                    $errors['otp'] =
                        'Your verification code has expired. Please request a new code.';

                } elseif (!password_verify($otp, $otpRecord['code_hash'])) {

                    $newAttempts = (int) $otpRecord['attempts'] + 1;

                    $updateAttempts = $pdo->prepare("
                        UPDATE otps
                        SET
                            attempts = attempts + 1,
                            used_at = CASE
                                WHEN attempts + 1 >= 3 THEN NOW()
                                ELSE used_at
                            END
                        WHERE id = ?
                        AND used_at IS NULL
                        AND attempts < 3
                    ");

                    $updateAttempts->execute([$otpRecord['id']]);

                    $pdo->commit();

                    if ($newAttempts >= 3) {

                        $errors['otp'] =
                            'You have entered an incorrect code 3 times. This OTP has been cancelled. Please request a new code.';

                    } else {

                        $remainingAttempts = 3 - $newAttempts;

                        $errors['otp'] =
                            'Incorrect verification code. You have ' .
                            $remainingAttempts .
                            ' attempt(s) remaining.';
                    }

                } else {

                    // Mark this OTP as used.
                    $useOtp = $pdo->prepare("
                        UPDATE otps
                        SET used_at = NOW()
                        WHERE id = ?
                        AND used_at IS NULL
                        AND attempts < 3
                        AND expires_at >= NOW()
                    ");

                    $useOtp->execute([$otpRecord['id']]);

                    if ($useOtp->rowCount() !== 1) {
                        throw new RuntimeException(
                            'This verification code is no longer valid.'
                        );
                    }

                    if ($purpose === 'register') {

                        $verifyUser = $pdo->prepare("
                            UPDATE users
                            SET email_verified = 1
                            WHERE id = ?
                            AND email_verified = 0
                        ");

                        $verifyUser->execute([$userId]);

                        if ($verifyUser->rowCount() !== 1) {
                            throw new RuntimeException(
                                'The account could not be verified.'
                            );
                        }

                       $pdo->commit();

                        // Record successful email verification.
                        log_activity(
                            $pdo,
                            'registration_email_verified',
                            $user['email'],
                            $userId,
                            'Member successfully verified their registration email.'
                        );

                        unset($_SESSION['pending_registration_user_id']);

                        $success =
                            'Your email has been verified successfully. Your account is now awaiting administrator approval.';

                    } else {

                        // Recheck the account before creating a login session.
                        $loginUserStmt = $pdo->prepare("
                            SELECT id, full_name, username, role,
                                   email_verified, account_status
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                            FOR UPDATE
                        ");

                        $loginUserStmt->execute([$userId]);
                        $loginUser = $loginUserStmt->fetch(PDO::FETCH_ASSOC);

                        if (
                            !$loginUser ||
                            (int) $loginUser['email_verified'] !== 1 ||
                            $loginUser['account_status'] !== 'active'
                        ) {
                            throw new RuntimeException(
                                'This account is no longer eligible to log in.'
                            );
                        }

                        // Reset failed login attempts only after OTP succeeds.
                        $updateLogin = $pdo->prepare("
                            UPDATE users
                            SET
                                failed_attempts = 0,
                                locked_until = NULL,
                                lock_tier = 0,
                                last_login_at = NOW()
                            WHERE id = ?
                        ");

                        $updateLogin->execute([$userId]);

                        $pdo->commit();

                        // Record successful login after OTP verification.
                        log_activity(
                            $pdo,
                            'login_success',
                            $loginUser['username'],
                            $userId,
                            'Member successfully completed login OTP verification.'
                        );

                        // Create the authenticated session only now.
                        session_regenerate_id(true);

                        $_SESSION['user'] = [
                            'id' => (int) $loginUser['id'],
                            'full_name' => $loginUser['full_name'],
                            'username' => $loginUser['username'],
                            'role' => $loginUser['role']
                        ];

                        $_SESSION['last_activity'] = time();

                        unset($_SESSION['pending_login_user_id']);

                        if ($loginUser['role'] === 'admin') {
                            header('Location: ../admin/admin.php');
                        } else {
                            header('Location: ../member/dashboard.php');
                        }

                        exit;
                    }
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('BAUG OTP Verification Error: ' . $e->getMessage());

                $errors['otp'] =
                    'Unable to verify your code at this time. Please try again.';
            }
        }

    } else {

        $errors['general'] = 'Invalid request. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| GET RESEND COUNTDOWN INFORMATION
|--------------------------------------------------------------------------
*/

$resendWait = 0;
$remainingResends = 3;

if ($success === '') {

    $trackingStmt = $pdo->prepare("
        SELECT last_sent_at, window_started_at, resend_count
        FROM otp_resend_limits
        WHERE user_id = ?
        AND purpose = ?
        LIMIT 1
    ");

    $trackingStmt->execute([$userId, $purpose]);
    $tracking = $trackingStmt->fetch(PDO::FETCH_ASSOC);

    if ($tracking) {

        $now = time();

        $lastSent = strtotime($tracking['last_sent_at']);
        $windowStart = strtotime($tracking['window_started_at']);

        $windowExpired = ($now >= ($windowStart + 900));

        $currentResendCount = $windowExpired
            ? 0
            : (int) $tracking['resend_count'];

        $remainingResends = max(0, 3 - $currentResendCount);

        $availableAt = $lastSent + 60;

        if ($currentResendCount >= 3) {
            $availableAt = max($availableAt, $windowStart + 900);
        }

        $resendWait = max(0, $availableAt - $now);
    }
}

require __DIR__ . '/../includes/header.php';

?>

<main class="otp-page">

    <section class="otp-card">

        <p class="otp-eyebrow">BAUG MEMBER SYSTEM</p>

        <h1>
            <?= $purpose === 'register'
                ? 'Verify Your Email'
                : 'Verify Your Login' ?>
        </h1>

        <p class="otp-description">
            <?= $purpose === 'register'
                ? 'Your account is almost ready. Enter the six-digit verification code sent to your registered email address.'
                : 'For your account security, enter the six-digit login verification code sent to your registered email address.' ?>
        </p>

        <?php if ($success !== ''): ?>

            <div class="otp-alert otp-alert-success">
                <?= e($success) ?>
            </div>

            <a href="login.php" class="otp-button">
                Continue to Login
            </a>

        <?php else: ?>

            <?php if ($notice !== ''): ?>
                <div class="otp-alert otp-alert-success">
                    <?= e($notice) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="otp-alert otp-alert-error">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="verify_otp.php" class="otp-form">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="verify">

                <label for="otp">Six-Digit Verification Code</label>

                <input
                    type="text"
                    id="otp"
                    name="otp"
                    class="otp-input"
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    required
                    autofocus
                >

                <?php if (!empty($errors['otp'])): ?>
                    <p class="otp-field-error">
                        <?= e($errors['otp']) ?>
                    </p>
                <?php endif; ?>

                <button type="submit" class="otp-button">
                    <?= $purpose === 'register'
                        ? 'Verify Email'
                        : 'Verify Login' ?>
                </button>

            </form>

            <div class="otp-divider"></div>

            <div class="otp-resend-section">

                <p class="otp-resend-question">
                    Didn't receive your code?
                </p>

                <form method="POST" action="verify_otp.php">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="resend">

                    <button
                        type="submit"
                        id="resendOtpButton"
                        class="otp-resend-button"
                        <?= $resendWait > 0 || $remainingResends <= 0
                            ? 'disabled'
                            : '' ?>
                    >
                        Resend Verification Code
                    </button>

                </form>

                <p
                    id="resendCountdown"
                    class="otp-countdown"
                    data-wait="<?= (int) $resendWait ?>"
                    data-remaining="<?= (int) $remainingResends ?>"
                >
                    <?php if ($resendWait > 0): ?>

                        You can request another code in
                        <span id="countdownSeconds">
                            <?= (int) $resendWait ?>
                        </span>
                        seconds.

                    <?php elseif ($remainingResends > 0): ?>

                        <?= (int) $remainingResends ?>
                        resend(s) remaining in this 15-minute period.

                    <?php else: ?>

                        You have reached the resend limit.

                    <?php endif; ?>
                </p>

                <?php if (!empty($errors['resend'])): ?>
                    <p class="otp-field-error">
                        <?= e($errors['resend']) ?>
                    </p>
                <?php endif; ?>

            </div>

            <a href="login.php" class="otp-back-link">
                Back to Login
            </a>

        <?php endif; ?>

    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const countdown = document.getElementById('resendCountdown');
    const resendButton = document.getElementById('resendOtpButton');
    const secondsDisplay = document.getElementById('countdownSeconds');

    if (!countdown || !resendButton) {
        return;
    }

    let seconds = Number(countdown.dataset.wait);
    const remaining = Number(countdown.dataset.remaining);

    if (seconds <= 0) {
        return;
    }

    const timer = setInterval(function () {

        seconds--;

        if (secondsDisplay) {
            secondsDisplay.textContent = seconds;
        }

        if (seconds <= 0) {

            clearInterval(timer);

            if (remaining > 0) {

                resendButton.disabled = false;

                countdown.textContent =
                    remaining +
                    ' resend(s) remaining in this 15-minute period.';

            } else {

                resendButton.disabled = true;

                countdown.textContent =
                    'Your resend limit has been reached. Refresh this page later to check availability.';
            }
        }

    }, 1000);

});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>