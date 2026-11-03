
<?php
require_once __DIR__ . '/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login and role information
$isLoggedIn = is_logged_in();
$userRole = $_SESSION['role'] ?? 'member';
$isAdmin = ($userRole === 'admin');

// Select the correct dashboard
$homeUrl = $isAdmin ? 'admin.php' : 'dashboard.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$pageTitle = $pageTitle ?? 'Baug Member Savings and Loan System';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>

<body>

<header class="public-header">

    <!-- LOGO AND SYSTEM NAME -->
    <a href="<?= e($isLoggedIn ? $homeUrl : 'index.php') ?>"
       class="brand">

        <img
            src="assets/baug_logo.png"
            alt="Baug Cooperative Logo"
        >

        <div class="brand-text">
            <strong>Baug Member</strong>
            <span>Savings and Loan System</span>
        </div>

    </a>

    <!-- NAVIGATION LINKS -->
    <nav class="public-nav">

        <?php if ($isLoggedIn): ?>

            <a href="<?= e($homeUrl) ?>">
                <?= $isAdmin ? 'Admin Dashboard' : 'Home' ?>
            </a>

            <a href="logout.php" class="logout-nav">
                Log-out
            </a>

        <?php elseif ($currentPage === 'login.php'): ?>

            <a href="index.php">Home</a>
            <a href="register.php">Register</a>

        <?php elseif ($currentPage === 'register.php'): ?>

            <a href="index.php">Home</a>
            <a href="login.php">Log-in</a>

        <?php else: ?>

            <a href="index.php">Home</a>
            <a href="register.php">Register</a>
            <a href="login.php" class="login-nav">Log-in</a>

        <?php endif; ?>

    </nav>

</header>
</body>
</html>