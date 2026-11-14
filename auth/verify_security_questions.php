<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Verify Security Questions';

$error = '';
$questions = [];
$userId = null;
$lockedUntil = null;

$maxAttempts = 5;
$lockoutMinutes = 15;

// Require an email from the forgot-password page.
if (empty($_SESSION['recovery_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$email = $_SESSION['recovery_email'];

try {

    // Retrieve the user's security questions and answer hashes.
    $stmt = $pdo->prepare("
        SELECT
            id,
            sec_q1,
            sec_a1,
            sec_q2,
            sec_a2,
            sec_q3,
            sec_a3
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if (!$user) {

        $error = 'Unable to verify this account. Please restart the recovery process.';

    } else {

        $userId = (int) $user['id'];

        $questions = [
            [
                'question' => $user['sec_q1'],
                'answer' => $user['sec_a1']
            ],
            [
                'question' => $user['sec_q2'],
                'answer' => $user['sec_a2']
            ],
            [
                'question' => $user['sec_q3'],
                'answer' => $user['sec_a3']
            ]
        ];

        foreach ($questions as $item) {

            if (empty($item['question']) || empty($item['answer'])) {

                $error = 'Security questions are not properly configured for this account.';
                break;
            }
        }
    }

    // Check existing recovery attempts.
    if ($userId !== null && $error === '') {

        $attemptStmt = $pdo->prepare("
            SELECT attempts, locked_until
            FROM password_recovery_attempts
            WHERE user_id = ?
        ");

        $attemptStmt->execute([$userId]);

        $attemptData = $attemptStmt->fetch();

        if ($attemptData && !empty($attemptData['locked_until'])) {

            $lockTimestamp = strtotime($attemptData['locked_until']);

            if ($lockTimestamp !== false && $lockTimestamp > time()) {

                $lockedUntil = $attemptData['locked_until'];

                $error = 'Too many incorrect attempts. Please try again after 15 minutes.';

            } else {

                // Lockout expired. Reset the attempt counter.
                $resetStmt = $pdo->prepare("
                    UPDATE password_recovery_attempts
                    SET attempts = 0,
                        locked_until = NULL
                    WHERE user_id = ?
                ");

                $resetStmt->execute([$userId]);
            }
        }
    }

} catch (PDOException $e) {

    $error = 'Unable to process your request. Please try again later.';
}

// Process submitted answers.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $userId !== null &&
    $error === ''
) {

    verify_csrf();

    $answers = [
        trim($_POST['answer1'] ?? ''),
        trim($_POST['answer2'] ?? ''),
        trim($_POST['answer3'] ?? '')
    ];

    $allCorrect = true;

    foreach ($answers as $index => $answer) {

        if ($answer === '') {
            $allCorrect = false;
            break;
        }

        $normalizedAnswer = mb_strtolower($answer, 'UTF-8');

        if (!password_verify(
            $normalizedAnswer,
            $questions[$index]['answer']
        )) {

            $allCorrect = false;
            break;
        }
    }

    try {

        if ($allCorrect) {

            // Clear failed recovery attempts after successful verification.
            $deleteStmt = $pdo->prepare("
                DELETE FROM password_recovery_attempts
                WHERE user_id = ?
            ");

            $deleteStmt->execute([$userId]);

            // Authorize password recovery.
            session_regenerate_id(true);

            $_SESSION['recovery_user_id'] = $userId;
            $_SESSION['recovery_verified'] = true;
            $_SESSION['recovery_verified_at'] = time();

            unset($_SESSION['recovery_email']);

            header('Location: reset_password.php');
            exit;

        } else {

            // Record one failed verification attempt.
            $recordStmt = $pdo->prepare("
                INSERT INTO password_recovery_attempts
                    (user_id, attempts, locked_until)
                VALUES
                    (?, 1, NULL)
                ON DUPLICATE KEY UPDATE
                    locked_until = CASE
                        WHEN attempts + 1 >= ?
                        THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END,
                    attempts = attempts + 1
            ");

            $recordStmt->execute([
                $userId,
                $maxAttempts
            ]);

            // Retrieve updated attempt information.
            $checkStmt = $pdo->prepare("
                SELECT attempts, locked_until
                FROM password_recovery_attempts
                WHERE user_id = ?
            ");

            $checkStmt->execute([$userId]);

            $updatedAttempts = $checkStmt->fetch();

            if (
                $updatedAttempts &&
                (int) $updatedAttempts['attempts'] >= $maxAttempts
            ) {

                $lockedUntil = $updatedAttempts['locked_until'];

                $error = 'You have reached the maximum of 5 incorrect attempts. Please try again after 15 minutes.';

            } else {

                $remaining = $maxAttempts -
                    (int) ($updatedAttempts['attempts'] ?? 0);

                $error = 'One or more answers are incorrect. You have '
                    . $remaining
                    . ' attempt(s) remaining.';
            }
        }

    } catch (PDOException $e) {

        $error = 'Unable to process your request. Please try again later.';
    }
}

require_once __DIR__ . '/../includes/header.php'; ?>

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

            Answer all three security questions correctly
            to continue resetting your password.

        </p>

        <?php if ($error): ?>

            <div class="error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($questions) && empty($lockedUntil)): ?>

            <form method="POST" class="login-form">

                <?= csrf_field() ?>

                <?php foreach ($questions as $index => $item): ?>

                    <div style="text-align: left; margin-bottom: 16px;">

                        <label style="display: block;
                                      font-weight: bold;
                                      margin-bottom: 7px;
                                      font-size: 13px;
                                      color: #064b2e;">

                            <?= e($item['question']) ?>

                        </label>

                        <div class="login-field">

                            <span>🔑</span>

                            <input
                                type="text"
                                name="answer<?= $index + 1 ?>"
                                placeholder="Enter your answer"
                                maxlength="50"
                                required
                            >

                        </div>

                    </div>

                <?php endforeach; ?>

                <button type="submit"
                        class="login-button"
                        style="margin-top: 8px;">

                    Verify Answers

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