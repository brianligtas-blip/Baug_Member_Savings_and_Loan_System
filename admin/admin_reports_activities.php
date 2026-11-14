<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$pageTitle = 'Reports & Activities - Admin';

// Safely inspect transactions columns for system logs/reports
$txColumns = $pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN);
$dateCol = in_array('created_at', $txColumns) ? 'created_at' : (in_array('transaction_date', $txColumns) ? 'transaction_date' : 'id');

// Fetch recent system-wide activity logs
$activities = $pdo->query("
    SELECT 
        transactions.id,
        transactions.transaction_type,
        transactions.amount,
        transactions.{$dateCol} AS activity_date,
        users.full_name,
        users.role
    FROM transactions
    INNER JOIN users ON transactions.user_id = users.id
    ORDER BY transactions.id DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch summary metrics for report view
$totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$totalTransactions = (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="app-main">
        <h1 class="page-title">Reports & Activities</h1>
        <p class="muted">Monitor system logs, audit trails, and member activity reports.</p>

        <!-- Summary Stat Cards -->
        <section class="stats-grid" style="margin-top: 1.5rem;">
            <div class="stat green-stat">
                <strong><?= number_format($totalMembers) ?></strong>
                <small>Total Members</small>
            </div>
            <div class="stat blue-stat">
                <strong><?= number_format($totalTransactions) ?></strong>
                <small>Total Transactions Logged</small>
            </div>
        </section>

        <!-- Activity Audit Trail -->
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-head">
                <h2>System Activity Audit Trail</h2>
            </div>

            <?php if (empty($activities)): ?>
                <p class="muted">No activity logs found.</p>
            <?php else: ?>
                <table class="table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                            <th style="padding: 10px;">User</th>
                            <th style="padding: 10px;">Role</th>
                            <th style="padding: 10px;">Action / Type</th>
                            <th style="padding: 10px;">Amount</th>
                            <th style="padding: 10px;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $act): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 10px;">
                                    <strong><?= e($act['full_name']) ?></strong>
                                </td>
                                <td style="padding: 10px;">
                                    <span style="font-size: 0.8rem; background: #e5e7eb; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                        <?= e($act['role']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px;"><?= ucfirst(e($act['transaction_type'])) ?></td>
                                <td style="padding: 10px; font-weight: 600;"><?= e(money($act['amount'])) ?></td>
                                <td style="padding: 10px; color: #6b7280; font-size: 0.85rem;">
                                    <?= !empty($act['activity_date']) ? e(date('M d, Y h:i A', strtotime($act['activity_date']))) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>