<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Verify Email';

$errors = [];
$success = '';
$notice = '';

if (empty($_SESSION['pending_registration_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId = (int) $_SESSION['pending_registration_user_id'];

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
                SELECT
                    last_sent_at,
                    window_started_at,
                    resend_count
                FROM otp_resend_limits
                WHERE user_id = ?
                AND purpose = 'register'
                FOR UPDATE
            ");

            $trackingStmt->execute([$userId]);
            $tracking = $trackingStmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new RuntimeException(
                    'OTP resend tracking information was not found.'
                );
            }

            // Check whether the 15-minute window has expired.
            $now = time();

            $windowStart = strtotime($tracking['window_started_at']);
            $lastSent = strtotime($tracking['last_sent_at']);

            $windowExpired = ($now >= ($windowStart + 900));

            $currentResendCount = $windowExpired
                ? 0
                : (int) $tracking['resend_count'];

            // Enforce the 60-second cooldown.
            $cooldownEnds = $lastSent + 60;

            if ($now < $cooldownEnds) {

                $wait = $cooldownEnds - $now;

                throw new RuntimeException(
                    'Please wait ' . $wait .
                    ' second(s) before requesting another code.'
                );
            }

            // Enforce a maximum of 3 resends within 15 minutes.
            if ($currentResendCount >= 3) {

                $wait = ($windowStart + 900) - $now;

                throw new RuntimeException(
                    'You have reached the maximum of 3 resends. Please wait ' .
                    max(1, $wait) .
                    ' second(s) before requesting another code.'
                );
            }

            // Get the member's email details.
            $userStmt = $pdo->prepare("
                SELECT full_name, email, email_verified
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException(
                    'The registration account could not be found.'
                );
            }

            if ((int) $user['email_verified'] === 1) {
                throw new RuntimeException(
                    'This email address has already been verified.'
                );
            }

            // Generate a secure six-digit OTP.
            $otp = (string) random_int(100000, 999999);

            // Hash the OTP before storing it.
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);

            // Calculate the five-minute expiration.
            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + (5 * 60)
            );

            /*
             * Insert the new OTP first.
             * The previous OTP is not cancelled yet.
             */
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
                    'register',
                    ?,
                    ?,
                    0,
                    NULL
                )
            ");

            $insertOtp->execute([
                $userId,
                $otpHash,
                $expiresAt
            ]);

            $newOtpId = (int) $pdo->lastInsertId();

            // Prepare the required email content.
            $emailMessage = "
                <h2>BAUG Email Verification</h2>

                <p>Hello " . e($user['full_name']) . ",</p>

                <p>Your new account verification code is:</p>

                <h1 style='letter-spacing: 6px;'>
                    {$otp}
                </h1>

                <p>This code expires in 5 minutes.</p>

                <p>Do not share this code with anyone.</p>

                <p>
                    If you did not request this code, ignore this email
                    and consider changing your password.
                </p>

                <p>BAUG Member Savings and Loan System</p>
            ";

            // Send the email before cancelling the previous OTP.
            $emailSent = send_email(
                $user['email'],
                $user['full_name'],
                'Verify Your BAUG Account',
                $emailMessage
            );

            if (!$emailSent) {
                throw new RuntimeException(
                    'The verification email could not be sent. Your previous code is still valid. Please try again.'
                );
            }

            // Cancel older unused registration OTPs.
            $cancelOldOtps = $pdo->prepare("
                UPDATE otps
                SET used_at = NOW()
                WHERE user_id = ?
                AND purpose = 'register'
                AND used_at IS NULL
                AND id <> ?
            ");

            $cancelOldOtps->execute([
                $userId,
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
                    AND purpose = 'register'
                ");

            } else {

                $updateTracking = $pdo->prepare("
                    UPDATE otp_resend_limits
                    SET
                        last_sent_at = NOW(),
                        resend_count = resend_count + 1
                    WHERE user_id = ?
                    AND purpose = 'register'
                ");
            }

            $updateTracking->execute([$userId]);

            $pdo->commit();

            $notice = 'A new verification code has been sent to your email address.';

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

            $errors['otp'] =
                'Unable to verify your email at this time. Please try again.';
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

            $stmt = $pdo->prepare("
                SELECT id, code_hash, expires_at, attempts, used_at
                FROM otps
                WHERE user_id = ?
                AND purpose = 'register'
                AND used_at IS NULL
                ORDER BY created_at DESC, id DESC
                LIMIT 1
            ");

            $stmt->execute([$userId]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$otpRecord) {

                $errors['otp'] = 'No valid verification code was found. Please request a new code.';

            } elseif ((int) $otpRecord['attempts'] >= 3) {

                $errors['otp'] = 'Too many incorrect attempts. Please request a new code.';

            } elseif (strtotime($otpRecord['expires_at']) < time()) {

                $errors['otp'] = 'Your verification code has expired. Please request a new code.';

            } elseif (!password_verify($otp, $otpRecord['code_hash'])) {

                $currentAttempts = (int) $otpRecord['attempts'];
                $newAttempts = $currentAttempts + 1;

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

                if ($newAttempts >= 3) {

                    $errors['otp'] = 'You have entered an incorrect code 3 times. This OTP has been cancelled. Please request a new code.';

                } else {

                    $remainingAttempts = 3 - $newAttempts;

                    $errors['otp'] = 'Incorrect verification code. You have '
                        . $remainingAttempts
                        . ' attempt(s) remaining.';
                }

            } else {

                try {

                    $pdo->beginTransaction();

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

                    unset($_SESSION['pending_registration_user_id']);

                    $success = 'Your email has been verified successfully. Your account is now awaiting administrator approval.';

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Keep technical details in the server error log.
                    error_log(
                        'BAUG OTP Resend Error: ' . $e->getMessage()
                    );

                    // Show a safe message to the user.
                    $errors['otp'] =
                        'Unable to verify your email at this time. Please try again.';
                }
            }
        }
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
        SELECT
            last_sent_at,
            window_started_at,
            resend_count
        FROM otp_resend_limits
        WHERE user_id = ?
        AND purpose = 'register'
        LIMIT 1
    ");

    $trackingStmt->execute([$userId]);
    $tracking = $trackingStmt->fetch(PDO::FETCH_ASSOC);

    if ($tracking) {

        $now = time();

        $lastSent = strtotime($tracking['last_sent_at']);
        $windowStart = strtotime($tracking['window_started_at']);

        $windowExpired = ($now >= ($windowStart + 900));

        $currentResendCount = $windowExpired
            ? 0
            : (int) $tracking['resend_count'];

        $remainingResends = max(
            0,
            3 - $currentResendCount
        );

        $availableAt = $lastSent + 60;

        if ($currentResendCount >= 3) {
            $availableAt = max(
                $availableAt,
                $windowStart + 900
            );
        }

        $resendWait = max(
            0,
            $availableAt - $now
        );
    }
}

require __DIR__ . '/../includes/header.php';

?>

<div class="registration-card">

    <div class="otp-page">

        <div class="otp-card">

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

                <?php if ($notice !== ''): ?>
                    <p class="success-message">
                        <?= e($notice) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <p class="error-message">
                        <?= e($errors['general']) ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="verify_otp.php">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="verify">

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

                    <button
                        type="submit"
                        class="btn btn-primary otp-verify-btn"
                    >
                        Verify Email
                    </button>

                </form>

                <div class="otp-resend-section">

                    <p>Did not receive the code?</p>

                    <form method="POST" action="verify_otp.php">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="resend"
                        >

                        <button
                            type="submit"
                            id="resendOtpButton"
                            class="btn btn-secondary"
                            <?= $resendWait > 0 ? 'disabled' : '' ?>
                        >
                            Resend Code
                        </button>

                    </form>

                    <p id="resendCountdown"
                       data-wait="<?= (int) $resendWait ?>"
                       data-remaining="<?= (int) $remainingResends ?>">

                        <?php if ($resendWait > 0): ?>

                            You can request another code in
                            <span id="countdownSeconds">
                                <?= (int) $resendWait ?>
                            </span>
                            second(s).

                        <?php elseif ($remainingResends > 0): ?>

                            You have <?= (int) $remainingResends ?>
                            resend(s) remaining in this 15-minute period.

                        <?php else: ?>

                            You have reached the resend limit.

                        <?php endif; ?>

                    </p>

                    <?php if (!empty($errors['resend'])): ?>
                        <small class="error-message">
                            <?= e($errors['resend']) ?>
                        </small>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

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

            resendButton.disabled = false;

            if (remaining > 0) {

                countdown.textContent =
                    'You have ' + remaining +
                    ' resend(s) remaining in this 15-minute period.';

            } else {

                countdown.textContent =
                    'You can now request another verification code.';
            }
        }

    }, 1000);

});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>