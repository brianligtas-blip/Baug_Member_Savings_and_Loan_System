<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($email) || empty($newPassword)) {
        $error = 'Palihug sulati ang imong email ug bag-ong password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Palihug magbutang ug tamang email address.';
    } else {
        try {
            // Susiha kung naa ba ang email sa database
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // I-update ang password sa user
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $user['id']]);

                $message = 'Na-reset na ang imong password! Mahimo ka na nga mag-login.';
            } else {
                $error = 'Wala makita ang ingon niana nga email address sa sistema.';
            }
        } catch (PDOException $e) {
            $error = 'Dunay nahitabong sayop sa pag-reset sa password.';
        }
    }
}

$pageTitle = 'Forgot Password';
require 'includes/header.php';
?>

<div class="login-screen">

    <div class="login-card" style="max-width: 440px; text-align: center;">

        <!-- ICON UG TITLE -->
        <div class="login-title" style="flex-direction: column; gap: 8px; margin-bottom: 12px;">
            <span style="font-size: 40px; color: #075b37;">🔒</span>
            <h1 style="font-size: 22px; color: #064b2e;">Forgot Password?</h1>
        </div>

        <p style="font-size: 13px; color: #557264; margin-bottom: 25px; line-height: 1.4;">
            Ibutang ang imong email address aron ma-verify ug ma-reset ang password sa imong account.
        </p>

        <?php if ($message): ?>
            <div class="success" style="margin-bottom: 18px;"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error" style="margin-bottom: 18px;"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- FORGOT PASSWORD FORM -->
        <form method="POST" class="login-form">

            <div style="text-align: left;">
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 13px; color: #064b2e;">
                    Email Address
                </label>
                
                <div class="login-field">
                    <span>✉</span>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Enter your email address" 
                        value="<?= e($_POST['email'] ?? '') ?>" 
                        required
                    >
                </div>
            </div>

            <div style="text-align: left; margin-top: 12px;">
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 13px; color: #064b2e;">
                    New Password
                </label>
                
                <div class="login-field">
                    <span>🔑</span>
                    <input 
                        type="password" 
                        name="new_password" 
                        placeholder="Enter new password" 
                        required
                    >
                </div>
            </div>

            <button type="submit" class="login-button" style="margin-top: 18px;">
                Reset Password
            </button>

        </form>

        <!-- BACK TO LOGIN LINK -->
        <div class="login-links" style="margin-top: 22px;">
            <a href="login.php" style="color: #087c43; font-weight: bold; text-decoration: none;">
                Back to Login
            </a>
        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>