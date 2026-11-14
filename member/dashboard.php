<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$userId = current_user()['id'];

/* =========================================
   1. SAVINGS BALANCE
========================================= */

$balance = 0.00;

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(
            SUM(
                CASE
                    WHEN transaction_type = 'Deposit' THEN amount
                    WHEN transaction_type = 'Withdrawal' THEN -amount
                    ELSE 0
                END
            ), 0
        )
        FROM transactions
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);
    $balance = (float) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Dashboard savings balance error: ' . $e->getMessage());
}

/* =========================================
   2. PENDING LOANS
========================================= */

$pendingLoan = 0.00;

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM loans
        WHERE user_id = ?
        AND status = 'Pending'
    ");

    $stmt->execute([$userId]);
    $pendingLoan = (float) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Dashboard pending loans error: ' . $e->getMessage());
}

/* =========================================
   3. MEMBER REGISTRATION DATE
========================================= */

$member = null;

try {
    $stmt = $pdo->prepare("
        SELECT created_at
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$userId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard member information error: ' . $e->getMessage());
}

/* =========================================
   4. RECENT TRANSACTIONS
========================================= */

$transactions = [];

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 3
    ");

    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard transactions error: ' . $e->getMessage());
}

/* =========================================
   PAGE CONFIGURATION
========================================= */

$pageTitle = 'Member Dashboard';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="app-layout member-dashboard">

    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-content">

        <!-- PAGE HEADING -->
        <div class="dashboard-page-header">
            <h1 class="dashboard-heading">Member Dashboard</h1>
            <p>Overview of your savings and loan activities.</p>
        </div>

        <!-- STATISTICS -->
        <section class="proto-stats-grid">

            <div class="proto-card">
                <div class="stat-details">
                    <span class="card-label">Savings Balance</span>
                    <strong><?= money($balance) ?></strong>
                </div>
            </div>

            <div class="proto-card">
                <div class="stat-details">
                    <span class="card-label">Pending Loan</span>
                    <strong><?= money($pendingLoan) ?></strong>
                </div>
            </div>

            <div class="proto-card">
                <div class="stat-details">
                    <span class="card-label">Member Since</span>
                    <strong>
                        <?= !empty($member['created_at'])
                            ? date('M Y', strtotime($member['created_at']))
                            : 'Not available' ?>
                    </strong>
                </div>
            </div>

        </section>

        <!-- MAIN CONTENT -->
        <section class="proto-content-grid">

            <!-- RECENT TRANSACTIONS -->
            <div class="proto-box">

                <div class="proto-box-head">
                    <h2>Recent Transactions</h2>

                    <a href="transactions.php" class="view-all">
                        View All
                    </a>
                </div>

                <div class="transaction-list">

                    <?php if (empty($transactions)): ?>

                        <div class="tx-empty">
                            <strong>No transactions yet</strong>
                            <small>Your transactions will appear here.</small>
                        </div>

                    <?php else: ?>

                        <?php foreach ($transactions as $tx): ?>

                            <div class="tx-row">

                                <div class="tx-type">
                                    <?= e($tx['transaction_type'] ?? 'Transaction') ?>
                                </div>

                                <div class="tx-amount">

                                    <strong>
                                        <?= money((float) ($tx['amount'] ?? 0)) ?>
                                    </strong>

                                    <small>
                                        <?= !empty($tx['created_at'])
                                            ? date('M d, Y', strtotime($tx['created_at']))
                                            : 'Date unavailable' ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

            <!-- QUICK ACTIONS -->
            <div class="proto-box">

                <h2>Quick Actions</h2>

                <div class="action-buttons">

                    <a href="savings.php" class="btn-action">
                        Deposit Savings
                    </a>

                    <a href="loans.php" class="btn-action">
                        Apply for Loan
                    </a>

                    <a href="transactions.php" class="btn-action">
                        View Transaction History
                    </a>

                </div>

            </div>

        </section>

    </main>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>