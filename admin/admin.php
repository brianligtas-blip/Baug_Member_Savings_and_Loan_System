<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

// LOG ADMIN DASHBOARD ACCESS
log_activity(
    $pdo,
    'ADMIN_VIEWED_DASHBOARD',
    current_user()['username'] ?? null,
    (int) current_user()['id'],
    'Administrator successfully viewed the admin dashboard.'
);

/* ==========================================
   DASHBOARD STATISTICS
========================================== */

// Total registered members
$members = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'member'
")->fetchColumn();

// Pending loan applications
$pendingLoans = (int) $pdo->query("
    SELECT COUNT(*)
    FROM loans
    WHERE LOWER(status) = 'pending'
")->fetchColumn();

// Total savings: deposits minus withdrawals
$totalSavings = (float) $pdo->query("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN LOWER(transaction_type) = 'deposit'
                    THEN amount
                WHEN LOWER(transaction_type) = 'withdrawal'
                    THEN -amount
                ELSE 0
            END
        ), 0
    )
    FROM transactions
")->fetchColumn();

/* ==========================================
   RECENT ACTIVITIES
========================================== */

$activities = $pdo->query("
    SELECT
        transactions.id,
        transactions.transaction_type,
        transactions.amount,
        transactions.created_at,
        users.full_name
    FROM transactions
    INNER JOIN users
        ON transactions.user_id = users.id
    ORDER BY transactions.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Admin Dashboard';

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Layout for Recent Transactions */
.list-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f2f5;
    gap: 16px;
}

.list-row:last-child {
    border-bottom: none;
}

.transaction-details {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.transaction-details strong {
    color: #1a1a1a;
    font-weight: 700;
}

.transaction-details small {
    color: #4b5563;
}

/* Force date to stay on the far right in a single line */
.transaction-date {
    white-space: nowrap;
    color: #6b7280;
    font-size: 0.88rem;
    font-weight: 500;
}
</style>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="app-main">

        <!-- PAGE TITLE -->

        <h1 class="page-title">
            Admin Dashboard
        </h1>

        <p class="muted">
            Monitor member accounts, loan applications, and savings transactions
            through the BAUG Member Savings and Loan System.
        </p>

        <!-- DASHBOARD STATISTICS -->

        <section class="stats-grid">

            <div class="stat green-stat">

                <strong>
                    <?= number_format($members) ?>
                </strong>

                <small>Registered Members</small>
            </div>

            <div class="stat blue-stat">

                <strong>
                    <?= number_format($pendingLoans) ?>
                </strong>

                <small>Pending Loan Applications</small>
            </div>

            <div class="stat purple-stat">

                <strong>
                    <?= e(money($totalSavings)) ?>
                </strong>

                <small>Total Savings Balance</small>
            </div>

        </section>

        <!-- RECENT ACTIVITIES AND QUICK ACTIONS -->

        <section class="content-grid">

            <!-- RECENT ACTIVITIES -->

            <div class="card" id="activities">

                <div class="card-head">

                    <h2>Recent Transactions</h2>

                    <a href="admin_transactions.php">
                        View All &rarr;
                    </a>

                </div>

                <?php if (empty($activities)): ?>

                    <p class="muted">
                        No transactions have been recorded yet.
                    </p>

                <?php else: ?>

                    <?php foreach ($activities as $activity): ?>

                        <div class="list-row">

                            <div class="transaction-details">

                                <strong>
                                    <?= e($activity['full_name']) ?>
                                </strong>

                                <small>
                                    <?= e(ucfirst($activity['transaction_type'])) ?>
                                    &mdash;
                                    <?= e(money($activity['amount'])) ?>
                                </small>

                            </div>

                            <span class="transaction-date">
                                <?= e(date(
                                    'M d, Y',
                                    strtotime($activity['created_at'])
                                )) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <!-- QUICK ACTIONS -->

            <div class="card" id="quick-actions">

                <h2>Quick Actions</h2>

                <a href="admin_loans.php" class="quick-action blue-action">
                    Review Loan Applications
                </a>

                <a href="admin_transactions.php" class="quick-action">
                    Manage Transactions
                </a>

            </div>

        </section>

    </main>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>