<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Verify Security Questions';

$error = '';
$success = '';
$questions = [];
$user = null;
$lockedUntil = null;
$remainingSeconds = 0;

$settings = security_settings();

// Require an identified account from the forgot-password page.
if (empty($_SESSION['recovery_user_id'])) {
    header('Location: forgot_password.php');
    exit;
}

$userId = (int) $_SESSION['recovery_user_id'];

try {

    // Retrieve account information and registered security questions.
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            email_verified,
            account_status,
            sec_q1,
            sec_a1,
            sec_q2,
            sec_a2,
            sec_q3,
            sec_a3,
            failed_attempts,
            lock_tier,
            locked_until
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        (int) $user['email_verified'] !== 1 ||
        $user['account_status'] !== 'active'
    ) {
        unset(
            $_SESSION['recovery_user_id'],
            $_SESSION['recovery_questions_verified'],
            $_SESSION['recovery_otp_verified'],
            $_SESSION['recovery_otp_verified_at']
        );

        header('Location: forgot_password.php');
        exit;
    }

    $questions = [
        1 => $user['sec_q1'],
        2 => $user['sec_q2'],
        3 => $user['sec_q3']
    ];

    foreach ($questions as $question) {
        if (empty($question)) {
            $error = 'Security questions are not properly configured for this account.';
            break;
        }
    }

    // Check whether the account is currently locked.
    if (!empty($user['locked_until'])) {

        $lockTimestamp = strtotime($user['locked_until']);

        if ($lockTimestamp !== false && $lockTimestamp > time()) {

            $lockedUntil = $user['locked_until'];
            $remainingSeconds = $lockTimestamp - time();

            $error = 'Your account is temporarily locked. Please wait until the lockout period ends.';
        }
    }

} catch (PDOException $e) {

    $error = 'Unable to process your request. Please try again later.';
}

// Process the selected security question.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $user &&
    $error === ''
) {

    verify_csrf();

    $questionIndex = (int) ($_POST['question_index'] ?? 0);

    $answer = trim($_POST['answer'] ?? '');
    $confirmAnswer = trim($_POST['confirm_answer'] ?? '');

    if (!isset($questions[$questionIndex])) {

        $error = 'Please select a valid security question.';

    } elseif ($answer === '' || $confirmAnswer === '') {

        $error = 'Please complete both answer fields.';

    } elseif (
        mb_strtolower($answer, 'UTF-8') !==
        mb_strtolower($confirmAnswer, 'UTF-8')
    ) {

        // A mismatch is not counted as an incorrect security answer.
        $error = 'Your answers do not match. Please enter the same answer in both fields.';

    } else {

        $answerColumn = 'sec_a' . $questionIndex;
        $normalizedAnswer = mb_strtolower($answer, 'UTF-8');

        $answerCorrect = password_verify(
            $normalizedAnswer,
            $user[$answerColumn]
        );

        if (!$answerCorrect) {

            // Record the failed attempt against the IP address.
            $ipResult = record_ip_failure($pdo, 600);

            if (!empty($ipResult['blocked'])) {
                log_activity(
                    $pdo,
                    'IP_BLOCKED',
                    $user['email'],
                    $userId,
                    'IP address temporarily blocked after repeated failed security verification attempts.'
                );
            }

            try {

                $pdo->beginTransaction();

                // Lock the account row before updating failed attempts.
                $lockStmt = $pdo->prepare("
                    SELECT
                        failed_attempts,
                        lock_tier,
                        locked_until
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $lockStmt->execute([$userId]);
                $account = $lockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$account) {
                    throw new RuntimeException('Account could not be verified.');
                }

                // Do not count another attempt while the account is locked.
                if (
                    !empty($account['locked_until']) &&
                    strtotime($account['locked_until']) > time()
                ) {

                    $pdo->commit();

                    $lockedUntil = $account['locked_until'];
                    $remainingSeconds =
                        strtotime($lockedUntil) - time();

                    $error = 'Your account is temporarily locked. Please wait until the lockout period ends.';

                } else {

                    $failedAttempts =
                        (int) $account['failed_attempts'] + 1;

                    $lockTier = (int) $account['lock_tier'];

                    $newLockoutCreated = false;
                    $lockSeconds = 0;

                    if ($failedAttempts >= 3) {

                        $lockTier = min($lockTier + 1, 3);

                        $lockDurations = [
                            1 => $settings['lock_tier_1'],
                            2 => $settings['lock_tier_2'],
                            3 => $settings['lock_tier_3']
                        ];

                        $lockSeconds = $lockDurations[$lockTier];

                        $updateStmt = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = 0,
                                lock_tier = ?,
                                locked_until = DATE_ADD(
                                    NOW(),
                                    INTERVAL ? SECOND
                                )
                            WHERE id = ?
                        ");

                        $updateStmt->execute([
                            $lockTier,
                            $lockSeconds,
                            $userId
                        ]);

                        // Send only one alert for each lockout tier.
                        $alertStmt = $pdo->prepare("
                            INSERT IGNORE INTO lockout_alerts (
                                user_id,
                                lock_tier,
                                locked_until,
                                email_sent
                            )
                            SELECT
                                id,
                                ?,
                                locked_until,
                                0
                            FROM users
                            WHERE id = ?
                        ");

                        $alertStmt->execute([
                            $lockTier,
                            $userId
                        ]);

                        $newLockoutCreated =
                            $alertStmt->rowCount() === 1;

                        $lockedUntilStmt = $pdo->prepare("
                            SELECT locked_until
                            FROM users
                            WHERE id = ?
                        ");

                        $lockedUntilStmt->execute([$userId]);

                        $lockedUntil =
                            $lockedUntilStmt->fetchColumn();

                        $remainingSeconds = $lockSeconds;

                    } else {

                        $updateStmt = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = ?
                            WHERE id = ?
                        ");

                        $updateStmt->execute([
                            $failedAttempts,
                            $userId
                        ]);
                    }

                    $pdo->commit();

                    // Send a lockout alert only when a new tier is reached.
                    if (
                        $failedAttempts >= 3 &&
                        $newLockoutCreated
                    ) {

                        $subject =
                            'Failed sign-in attempts on your account';

                        $ipAddress = get_client_ip();

                        $userAgent =
                            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

                        $philippineTime = date(
                            'F j, Y g:i:s A'
                        );

                        $forgotPasswordUrl =
                            'http://' .
                            $_SERVER['HTTP_HOST'] .
                            '/Baug_Member_Savings_and_Loan_System/auth/forgot_password.php';

                        $message = "
                            <h2>BAUG Security Alert</h2>

                            <p>Hello " . e($user['full_name']) . ",</p>

                            <p>
                                Your account has reached lockout tier
                                <strong>" . (int) $lockTier . "</strong>
                                after " . (int) $failedAttempts . "
                                failed verification attempts.
                            </p>

                            <p>
                                <strong>Lockout duration:</strong>
                                " . (int) $lockSeconds . " seconds
                            </p>

                            <p>
                                <strong>Date and time:</strong>
                                " . e($philippineTime) . " (Philippine Time)
                            </p>

                            <p>
                                <strong>IP address:</strong>
                                " . e($ipAddress) . "
                            </p>

                            <p>
                                <strong>User agent:</strong>
                                " . e($userAgent) . "
                            </p>

                            <p>
                                If this was not you, reset your password now.
                            </p>

                            <p>
                                <a href=\"" . e($forgotPasswordUrl) . "\">
                                    Forgot Password
                                </a>
                            </p>
                        ";

                        $emailSent = send_email(
                            $user['email'],
                            $user['full_name'],
                            $subject,
                            $message
                        );

                        if ($emailSent) {

                            $sentStmt = $pdo->prepare("
                                UPDATE lockout_alerts
                                SET email_sent = 1
                                WHERE user_id = ?
                                  AND lock_tier = ?
                            ");

                            $sentStmt->execute([
                                $userId,
                                $lockTier
                            ]);

                            log_activity(
                                $pdo,
                                'ALERT_EMAIL_SENT',
                                $user['email'],
                                $userId,
                                'Lockout tier ' . $lockTier .
                                ' security alert email sent.'
                            );
                        }
                    }

                    log_activity(
                        $pdo,
                        'SECURITY_QUESTION_FAILED',
                        $user['email'],
                        $userId,
                        'Incorrect security question answer submitted.'
                    );

                    if ($failedAttempts >= 3) {

                        log_activity(
                            $pdo,
                            'LOCKOUT_TIER_' . $lockTier,
                            $user['email'],
                            $userId,
                            'Account locked after repeated failed security verification attempts.'
                        );

                        $error = 'Too many incorrect attempts. Your account is temporarily locked.';

                    } else {

                        $remaining = 3 - $failedAttempts;

                        $error = 'Your answer is incorrect. You have '
                            . $remaining
                            . ' attempt(s) remaining before your account is locked.';
                    }
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Unable to process your request. Please try again later.';
            }

        } else {

            // Correct answer: generate the password-reset OTP.
            try {

                $pdo->beginTransaction();

                // Recheck account eligibility.
                $accountStmt = $pdo->prepare("
                    SELECT
                        id,
                        full_name,
                        email,
                        email_verified,
                        account_status,
                        locked_until
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $accountStmt->execute([$userId]);
                $currentUser = $accountStmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$currentUser ||
                    (int) $currentUser['email_verified'] !== 1 ||
                    $currentUser['account_status'] !== 'active'
                ) {
                    throw new RuntimeException(
                        'This account is not eligible for password recovery.'
                    );
                }

                if (
                    !empty($currentUser['locked_until']) &&
                    strtotime($currentUser['locked_until']) > time()
                ) {
                    throw new RuntimeException(
                        'Your account is temporarily locked. Please try again later.'
                    );
                }

                // Invalidate previous unused reset OTPs.
                $cancelOtp = $pdo->prepare("
                    UPDATE otps
                    SET used_at = NOW()
                    WHERE user_id = ?
                      AND purpose = 'reset'
                      AND used_at IS NULL
                ");

                $cancelOtp->execute([$userId]);

                // Generate a new six-digit reset OTP.
                $otp = (string) random_int(100000, 999999);

                $otpHash = password_hash(
                    $otp,
                    PASSWORD_DEFAULT
                );

                $expiresAt = date(
                    'Y-m-d H:i:s',
                    time() + ($settings['otp_minutes'] * 60)
                );

                $purpose = 'reset';

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

                // Initialize the reset OTP resend tracker.
                $limitStmt = $pdo->prepare("
                    INSERT INTO otp_resend_limits (
                        user_id,
                        purpose,
                        last_sent_at,
                        window_started_at,
                        resend_count
                    ) VALUES (?, 'reset', NOW(), NOW(), 0)
                    ON DUPLICATE KEY UPDATE
                        last_sent_at = NOW(),
                        window_started_at = NOW(),
                        resend_count = 0
                ");

                $limitStmt->execute([$userId]);

                // Prepare the reset OTP email.
                $subject =
                    'Your BAUG Password Reset Verification Code';

                $message = "
                    <h2>BAUG Password Reset Verification</h2>

                    <p>Hello " . e($currentUser['full_name']) . ",</p>

                    <p>
                        Your password reset verification code is:
                    </p>

                    <h1 style=\"letter-spacing: 6px;\">" .
                        e($otp) .
                    "</h1>

                    <p>
                        This code expires in " .
                        (int) $settings['otp_minutes'] .
                        " minutes.
                    </p>

                    <p>
                        If you did not request a password reset,
                        please ignore this email and secure your account.
                    </p>
                ";

                $emailSent = send_email(
                    $currentUser['email'],
                    $currentUser['full_name'],
                    $subject,
                    $message
                );

                if (!$emailSent) {
                    throw new RuntimeException(
                        'Unable to send the password reset verification code.'
                    );
                }

                $pdo->commit();

                // Authorize the reset OTP verification step.
                session_regenerate_id(true);

                $_SESSION['recovery_questions_verified'] = true;

                unset(
                    $_SESSION['recovery_verified'],
                    $_SESSION['recovery_verified_at'],
                    $_SESSION['recovery_otp_verified'],
                    $_SESSION['recovery_otp_verified_at']
                );

                log_activity(
                    $pdo,
                    'password_reset_otp_sent',
                    $currentUser['email'],
                    $userId,
                    'Password reset OTP sent after successful security question verification.'
                );

                header('Location: verify_otp.php');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 480px; text-align: center;">

        <div class="login-title"
             style="flex-direction: column; gap: 8px; margin-bottom: 12px;">

            <span style="font-size: 40px; color: #075b37;">🛡️</span>

            <h1 style="font-size: 22px; color: #064b2e;">
                Verify Your Identity
            </h1>

        </div>

        <p style="font-size: 13px;
                  color: #557264;
                  margin-bottom: 22px;
                  line-height: 1.5;">

            Select one of your registered security questions
            and enter your answer to continue resetting your password.

        </p>

        <?php if ($error): ?>

            <div class="error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($questions) && empty($lockedUntil)): ?>

            <form method="POST" class="login-form">

                <?= csrf_field() ?>

                <div style="text-align: left; margin-bottom: 16px;">

                    <label for="question_index"
                           style="display: block;
                                  font-weight: bold;
                                  margin-bottom: 7px;
                                  font-size: 13px;
                                  color: #064b2e;">

                        Select Your Security Question

                    </label>

                    <div class="login-field">

                        <span>❓</span>

                        <select
                            name="question_index"
                            id="question_index"
                            required
                            style="width: 100%;"
                        >

                            <option value="">Choose a question</option>

                            <?php foreach ($questions as $index => $question): ?>

                                <option
                                    value="<?= (int) $index ?>"
                                    <?= (
                                        (string) ($_POST['question_index'] ?? '') ===
                                        (string) $index
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($question) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div style="text-align: left; margin-bottom: 16px;">

                    <label for="answer"
                           style="display: block;
                                  font-weight: bold;
                                  margin-bottom: 7px;
                                  font-size: 13px;
                                  color: #064b2e;">

                        Your Answer

                    </label>

                    <div class="login-field">

                        <span>🔑</span>

                        <input
                            type="password"
                            name="answer"
                            id="answer"
                            placeholder="Enter your answer"
                            maxlength="50"
                            required
                        >

                    </div>

                </div>

                <div style="text-align: left; margin-bottom: 16px;">

                    <label for="confirm_answer"
                           style="display: block;
                                  font-weight: bold;
                                  margin-bottom: 7px;
                                  font-size: 13px;
                                  color: #064b2e;">

                        Re-enter Answer

                    </label>

                    <div class="login-field">

                        <span>🔑</span>

                        <input
                            type="password"
                            name="confirm_answer"
                            id="confirm_answer"
                            placeholder="Re-enter your answer"
                            maxlength="50"
                            required
                        >

                    </div>

                </div>

                <button type="submit"
                        class="login-button"
                        style="margin-top: 8px;">

                    Verify Answer

                </button>

            </form>

        <?php endif; ?>

        <div class="login-links" style="margin-top: 22px;">

            <a href="forgot_password.php"
               style="color: #087c43;
                      font-weight: bold;
                      text-decoration: none;">

                Start Again

            </a>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>