<?php

require_once __DIR__ . '/functions.php';

// Project base URL
$baseUrl = '/Baug_Member_Savings_and_Loan_System/';

// Login and role information
$isLoggedIn = is_logged_in();

$currentUser = current_user();
$userRole = $currentUser['role'] ?? 'member';

$isAdmin = ($userRole === 'admin');

// Select dashboard
$homeUrl = $isAdmin
    ? $baseUrl . 'admin/admin.php'
    : $baseUrl . 'member/dashboard.php';

// Current page
$currentPage = basename($_SERVER['PHP_SELF']);

// Detect current folder
$currentDirectory = basename(dirname($_SERVER['PHP_SELF']));

// Page title
$pageTitle = $pageTitle ?? 'Baug Member Savings and Loan System';

// Navigation URLs
$homePageUrl = $baseUrl . 'home.php';
$loginPageUrl = $baseUrl . 'auth/login.php';
$registerPageUrl = $baseUrl . 'auth/register.php';
$logoutPageUrl = $baseUrl . 'auth/logout.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet"
          href="<?= e($baseUrl) ?>assets/style.css">

          <?php if ($currentPage === 'security_dashboard.php'): ?>
            <link rel="stylesheet"
                href="<?= e($baseUrl) ?>assets/css/security_dashboard.css">
        <?php endif; ?>

    <script
        src="<?= e($baseUrl) ?>assets/app.js"
        defer>
    </script>

</head>

<body data-session-warning="<?= $isLoggedIn ? 'enabled' : 'disabled' ?>">

<header class="public-header">

    <!-- LOGO AND SYSTEM NAME -->
    <a href="<?= e($isLoggedIn ? $homeUrl : $homePageUrl) ?>"
       class="brand">

        <img
            src="<?= e($baseUrl) ?>assets/baug_logo.png"
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

            <a href="<?= e($logoutPageUrl) ?>"
               class="logout-nav">
                Log-out
            </a>

        <?php else: ?>

            <a href="<?= e($homePageUrl) ?>">
                Home
            </a>

            <?php if ($currentPage === 'login.php'): ?>

                <a href="<?= e($registerPageUrl) ?>">
                    Register
                </a>

            <?php elseif ($currentPage === 'register.php'): ?>

                <a href="<?= e($loginPageUrl) ?>">
                    Log-in
                </a>

            <?php else: ?>

                <a href="<?= e($registerPageUrl) ?>">
                    Register
                </a>

                <a href="<?= e($loginPageUrl) ?>"
                   class="login-nav">
                    Log-in
                </a>

            <?php endif; ?>

        <?php endif; ?>

    </nav>

</header>