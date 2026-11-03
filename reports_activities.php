
<?php
require_once 'includes/functions.php';
require_admin();

require_once 'config/database.php';

/* ==========================================
   REPORT SUMMARY
========================================== */

try {
    $summaryQuery = $pdo->query("
        SELECT
            COUNT(*) AS total_transactions,

            COALESCE(SUM(
                CASE
                    WHEN LOWER(transaction_type) = 'deposit'
                    THEN amount
                    ELSE 0
                END
            ), 0) AS total_deposits,

            COALESCE(SUM(
                CASE
                    WHEN LOWER(transaction_type) = 'withdrawal'
                    THEN amount
                    ELSE 0
                END
            ), 0) AS total_withdrawals

        FROM transactions
    ");

    $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

    /* ==========================================
       TRANSACTION ACTIVITIES
    ========================================== */

    $activityQuery = $pdo->query("
        SELECT
            transactions.id,
            transactions.transaction_type,
            transactions.amount,
            transactions.created_at,
            users.full_name,
            users.id_number

        FROM transactions

        INNER JOIN users
            ON transactions.user_id = users.id

        ORDER BY transactions.created_at DESC
    ");

    $activities = $activityQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());

    $summary = [
        'total_transactions' => 0,
        'total_deposits' => 0,
        'total_withdrawals' => 0
    ];

    $activities = [];

    $error = 'Unable to retrieve reports and activities.';
}

$pageTitle = 'Reports & Activities';
require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-main">

        <!-- PAGE HEADING -->
        <div class="dashboard-heading">
            <div>
                <h1 class="page-title">
                    Reports & Activities
                </h1>

                <p class="muted">
                    View transaction summaries and monitor member activities.
                </p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- REPORT SUMMARY -->
        <section class="stats-grid">

            <div class="stat green-stat">
                <span>📊</span>
                <strong>
                    <?= number_format((int)$summary['total_transactions']) ?>
                </strong>
                <small>Total Transactions</small>
            </div>

            <div class="stat blue-stat">
                <span>💰</span>
                <strong>
                    <?= e(money($summary['total_deposits'])) ?>
                </strong>
                <small>Total Deposits</small>
            </div>

            <div class="stat purple-stat">
                <span>💸</span>
                <strong>
                    <?= e(money($summary['total_withdrawals'])) ?>
                </strong>
                <small>Total Withdrawals</small>
            </div>

        </section>

        <!-- TRANSACTION ACTIVITY TABLE -->
        <section class="card members-section">

            <div class="card-head">
                <div>
                    <h2>Transaction Activities</h2>

                    <p class="muted">
                        Complete record of member deposits and withdrawals.
                    </p>
                </div>
            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Member Name</th>
                            <th>Member ID</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($activities)): ?>

                            <tr>
                                <td colspan="6">
                                    No transaction activities found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($activities as $activity): ?>

                                <tr>

                                    <td>
                                        #<?= (int)$activity['id'] ?>
                                    </td>

                                    <td>
                                        <?= e($activity['full_name']) ?>
                                    </td>

                                    <td>
                                        <?= e($activity['id_number']) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge">
                                            <?= e(ucfirst($activity['transaction_type'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(money($activity['amount'])) ?>
                                    </td>

                                    <td>
                                        <?= e(date(
                                            'M d, Y h:i A',
                                            strtotime($activity['created_at'])
                                        )) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<?php require 'includes/footer.php'; ?>