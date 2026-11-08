<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? 'member');

// Kon admin ang naka-login, automatic nga mag-load sa admin sidebar
if ($role === 'admin') {
    require __DIR__ . '/admin_sidebar.php';
    return;
}
?>

<aside class="sidebar">
    <div class="sidebar-label">MEMBER MENU</div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <a href="savings.php" class="<?= $currentPage === 'savings.php' ? 'active' : '' ?>">
            <i class="fas fa-wallet"></i>
            <span>My Savings</span>
        </a>

        <a href="loans.php" class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Loan Management</span>
        </a>

        <a href="transactions.php" class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Transaction History</span>
        </a>

        <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
    </nav>
</aside>