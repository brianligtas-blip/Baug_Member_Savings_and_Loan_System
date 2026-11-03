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

    // FIX: Changed `password_hash` to `password` to match your database column
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, password, role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // FIX: Updated verification to check $user['password']
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
            <span>♟</span>
            <h1>Member Login</h1>
        </div>

        <?php if (!empty($error)): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="login-form">

            <div class="login-field">
                <span>♟</span>
                <input
                    type="text"
                    name="username"
                    placeholder="Username"
                    required
                >
            </div>

            <div class="login-field">
                <span>🔒</span>
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    required
                >
            </div>

            <div class="captcha-row">

                <div class="captcha-code">
                    <?= e($_SESSION['captcha'] ?? '7K3P') ?>
                </div>

                <button type="button"
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

    <p class="login-tagline">
        Together for a Stronger Community
    </p>

</main>

<?php require 'includes/footer.php'; ?>