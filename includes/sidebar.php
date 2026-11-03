<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$userRole = $_SESSION['user']['role'] ?? 'member';
$isAdmin = ($userRole === 'admin');
?>

<aside class="sidebar">
    <nav class="sidebar-nav">

        <?php if ($isAdmin): ?>

            <a href="admin.php"
               class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>">
                <span class="icon">⌂</span>
                Dashboard
            </a>

            <a href="manage_members.php"
                class="<?= $currentPage === 'manage_members.php' ? 'active' : '' ?>">
                <span class="icon">👥</span>
                Manage Members
            </a>

            <a href="loans.php"
               class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">
                <span class="icon">📄</span>
                Loan Applications
            </a>

            <a href="transactions.php"
               class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
                <span class="icon">📊</span>
                Transactions
            </a>

            <a href="reports_activities.php"
                class="<?= $currentPage === 'reports_activities.php' ? 'active' : '' ?>">
                <span class="icon">📋</span>
                Reports & Activities
            </a>

        <?php else: ?>

            <a href="dashboard.php"
               class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <span class="icon">⌂</span>
                Dashboard
            </a>

            <a href="savings.php"
               class="<?= $currentPage === 'savings.php' ? 'active' : '' ?>">
                <span class="icon">🐷</span>
                My Savings
            </a>

            <a href="loans.php"
               class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">
                <span class="icon">📄</span>
                Loan Management
            </a>

            <a href="transactions.php"
               class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
                <span class="icon">🕒</span>
                Transaction History
            </a>

            <a href="profile.php"
               class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <span class="icon">👤</span>
                My Profile
            </a>

        <?php endif; ?>

        <a href="logout.php" class="logout-link">
            <span class="icon">⎋</span>
            Logout
        </a>

    </nav>
</aside>