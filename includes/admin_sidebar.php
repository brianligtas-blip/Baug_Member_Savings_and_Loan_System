<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-label">ADMIN MENU</div>

    <nav class="sidebar-nav">
        <a href="admin.php" class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <a href="manage_members.php" class="<?= $currentPage === 'manage_members.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Manage Members</span>
        </a>

        <a href="admin_loans.php" class="<?= $currentPage === 'admin_loans.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Loan Management</span>
        </a>

        <a href="admin_transactions.php" class="<?= $currentPage === 'admin_transactions.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Transaction History</span>
        </a>

        <a href="admin_reports_activities.php" class="<?= $currentPage === 'admin_reports_activities.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reports & Activities</span>
        </a>
    </nav>
</aside>