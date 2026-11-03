<?php
require_once 'includes/functions.php';
require_admin();

require_once 'config/database.php';

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

require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-main">

        <!-- PAGE TITLE -->

        <h1 class="page-title">
            <span>▥</span> Admin Dashboard
        </h1>

        <p class="muted">
            Welcome to the BAUG Member Savings and Loan System
            Administration Panel.
        </p>

        <!-- DASHBOARD STATISTICS -->

        <section class="stats-grid">

            <div class="stat green-stat">
                <span>👥</span>

                <strong>
                    <?= number_format($members) ?>
                </strong>

                <small>Total Members</small>
            </div>

            <div class="stat blue-stat">
                <span>📄</span>

                <strong>
                    <?= number_format($pendingLoans) ?>
                </strong>

                <small>Pending Loans</small>
            </div>

            <div class="stat purple-stat">
                <span>🪙</span>

                <strong>
                    <?= e(money($totalSavings)) ?>
                </strong>

                <small>Total Savings</small>
            </div>

        </section>

        <!-- RECENT ACTIVITIES AND QUICK ACTIONS -->

        <section class="content-grid">

            <!-- RECENT ACTIVITIES -->

            <div class="card" id="activities">

                <div class="card-head">

                    <h2>Recent Activities</h2>

                    <a href="transactions.php">
                        View All →
                    </a>

                </div>

                <?php if (empty($activities)): ?>

                    <p class="muted">
                        No recent activities found.
                    </p>

                <?php else: ?>

                    <?php foreach ($activities as $activity): ?>

                        <div class="list-row">

                            <div>

                                <strong>
                                    <?= e($activity['full_name']) ?>
                                </strong>

                                <small>
                                    <?= e($activity['transaction_type']) ?>
                                    —
                                    <?= e(money($activity['amount'])) ?>
                                </small>

                            </div>

                            <small>
                                <?= e(date(
                                    'M d, Y',
                                    strtotime($activity['created_at'])
                                )) ?>
                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <!-- QUICK ACTIONS -->

            <div class="card" id="quick-actions">

                <h2>Quick Actions</h2>

                <a href="loans.php" class="quick-action blue-action">
                    📄 &nbsp; Review Loans
                </a>

                <a href="transactions.php" class="quick-action">
                    💰 &nbsp; View Transactions
                </a>

            </div>

        </section>

    </main>

</div>

<?php require 'includes/footer.php'; ?>