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
                Dashboard
            </a>

            <a href="manage_members.php"
                class="<?= $currentPage === 'manage_members.php' ? 'active' : '' ?>">
                Manage Members
            </a>

            <a href="loans.php"
               class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">
                Loan Applications
            </a>

            <a href="transactions.php"
               class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
                Transactions
            </a>

            <a href="reports_activities.php"
                class="<?= $currentPage === 'reports_activities.php' ? 'active' : '' ?>">
                Reports & Activities
            </a>

        <?php else: ?>

            <a href="dashboard.php"
               class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <a href="savings.php"
               class="<?= $currentPage === 'savings.php' ? 'active' : '' ?>">
                My Savings
            </a>

            <a href="loans.php"
               class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">
                Loan Management
            </a>

            <a href="transactions.php"
               class="<?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
                Transaction History
            </a>

            <a href="profile.php"
               class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                My Profile
            </a>

        <?php endif; ?>

        <a href="logout.php" class="logout-link">
            <span class="icon">⎋</span>
            Logout
        </a>

    </nav>
</aside>