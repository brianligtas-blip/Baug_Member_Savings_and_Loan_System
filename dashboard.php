
<?php

require_once 'includes/functions.php';
require_login();

require_once 'config/database.php';

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

require 'includes/header.php';

?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <!-- PAGE HEADING -->
        <h1 class="dashboard-heading">
            <span class="title-icon">
                <i class="fas fa-chart-line"></i>
            </span>
            Member Dashboard
        </h1>

        <!-- STAT CARDS -->
        <section class="proto-stats-grid">

            <!-- SAVINGS BALANCE -->
            <div class="proto-card proto-green">

                <div class="stat-details">
                    <strong><?= money($balance) ?></strong>
                    <small>Savings Balance</small>
                </div>

            </div>

            <!-- PENDING LOAN -->
            <div class="proto-card proto-blue">

                <div class="stat-details">
                    <strong><?= money($pendingLoan) ?></strong>
                    <span class="card-label">Pending Loan</span>
                </div>

            </div>

            <!-- MEMBER SINCE -->
            <div class="proto-card proto-purple">

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

        <!-- MAIN CONTENT GRID -->
        <section class="proto-content-grid">

            <!-- RECENT TRANSACTIONS -->
            <div class="proto-box">

                <div class="proto-box-head">

                    <h2>Recent Transactions</h2>

                    <a href="transactions.php" class="view-all">
                        View All →
                    </a>

                </div>

                <div class="transaction-list">

                    <?php if (empty($transactions)): ?>

                        <div class="tx-row">

                            <div class="tx-type">
                                No transactions yet
                            </div>

                            <div class="tx-amount">
                                <small>
                                    Your transactions will appear here.
                                </small>
                            </div>

                        </div>

                    <?php else: ?>

                        <?php foreach ($transactions as $tx): ?>

                            <div class="tx-row">

                                <div class="tx-type">

                                    <?php
                                    $type = $tx['transaction_type'] ?? 'Transaction';

                                    if ($type === 'Deposit') {
                                        echo '↓ Deposit';
                                    } elseif ($type === 'Withdrawal') {
                                        echo '↑ Withdrawal';
                                    } else {
                                        echo '📄 ' . e($type);
                                    }
                                    ?>

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

                    <a href="savings.php" class="btn-action green-act">

                        <i class="fas fa-piggy-bank act-icon"></i>
                        <span>Deposit Savings</span>

                    </a>

                    <a href="loans.php" class="btn-action blue-act">

                        <i class="fas fa-hand-holding-usd act-icon"></i>
                        <span>Apply for Loan</span>

                    </a>

                    <a href="transactions.php" class="btn-action purple-act">

                        <i class="fas fa-history act-icon"></i>
                        <span>View History</span>

                    </a>

                </div>

            </div>

        </section>

    </main>

</div>

<?php require 'includes/footer.php'; ?>