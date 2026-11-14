<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    if (current_user()['role'] === 'admin') {
        header('Location: ../admin/admin.php');
    } else {
        header('Location: ../member/dashboard.php');
    }
    exit;
}

$error = '';

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

        // Clear CAPTCHA after checking it.
        unset($_SESSION['captcha']);

        // FIND USER
        $stmt = $pdo->prepare("
            SELECT id, full_name, username, password, role
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // VERIFY PASSWORD
        if ($user && password_verify($password, $user['password'])) {

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

        } else {

            $error = 'Invalid username or password. Please try again.';

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

            <a href="forgot_password.php">Forgot Password?</a>

            <span>|</span>

            <a href="register.php">Register Here</a>

        </div>

    </section>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>