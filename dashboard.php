<?php
require_once 'includes/functions.php';
require_login();
require_once 'config/database.php';

$userId = current_user()['id'];

// 1. Savings Balance
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
    $balance = 0.00;
}

// 2. Pending Loans
$pendingLoan = 0.00;

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM loans
        WHERE user_id = ? AND status = 'Pending'
    ");

    $stmt->execute([$userId]);
    $pendingLoan = (float) $stmt->fetchColumn();

} catch (PDOException $e) {
    $pendingLoan = 0.00;
}

// 3. Member Registration Date
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
    $member = null;
}

// 4. Recent Transactions
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
    $transactions = [];
}

$pageTitle = 'Member Dashboard';

require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <h1 class="dashboard-heading">
            <span class="title-icon"> 
                Member Dashboard
        </h1>

        <!-- STAT CARDS -->
        <section class="proto-stats-grid">

            <!-- Savings Balance -->
            <div class="proto-card proto-green">
                <div class="stat-details">
                    <strong><?= money($balance) ?></strong>
                    <small>Savings Balance</small>
                </div>
            </div>

            <!-- Pending Loan -->
            <div class="proto-card proto-blue">
                <div class="stat-details">
                    <strong><?= money($pendingLoan) ?></strong>
                    <span class="card-label">Pending Loan</span>
                </div>
            </div>

            <!-- Member Since -->
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

        <!-- MAIN 2-COLUMN SECTION -->
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
                                    <?= ($tx['transaction_type'] ?? '') === 'Deposit'
                                        ? '↓ Deposit'
                                        : (($tx['transaction_type'] ?? '') === 'Withdrawal'
                                            ? '↑ Withdrawal'
                                            : '📄 ' . e($tx['transaction_type'] ?? 'Transaction')) ?>
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
                        <class="act-icon">
                        Deposit Savings
                    </a>

                    <a href="loans.php" class="btn-action blue-act">
                        <class="act-icon">
                        Apply for Loan
                    </a>

                    <a href="transactions.php" class="btn-action purple-act">
                        <class="act-icon">
                        View History
                    </a>

                </div>
            </div>

        </section>

    </main>

</div>

<?php require 'includes/footer.php'; ?>