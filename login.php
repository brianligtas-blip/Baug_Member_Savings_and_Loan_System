<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    if (current_user()['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT id, full_name, username, password, role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } else {
            header('Location: dashboard.php');
        }

        exit;

    } else {

        $error = 'Invalid username or password. Please try again.';

    }
}

$pageTitle = 'Member Login';

require 'includes/header.php';

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

            <!-- Username Field -->
            <div class="login-field">

                <span class="field-icon">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="32"
                         height="32"
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
                         width="32"
                         height="32"
                         viewBox="0 0 24 24"
                         fill="#198754"
                         stroke="#198754"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <rect x="3" y="10" width="18" height="12" rx="2"></rect>

                        <path d="M7 10V7a5 5 0 0 1 10 0v3"
                              fill="none"
                              stroke="#198754"
                              stroke-width="2.5"></path>

                        <circle cx="12" cy="16" r="1.5" fill="white"></circle>

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
                    <?= e($_SESSION['captcha'] ?? '7K3P') ?>
                </div>

                <button
                    type="button"
                    class="captcha-refresh"
                    onclick="location.reload()">

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

<?php require 'includes/footer.php'; ?>