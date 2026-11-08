<?php
require_once 'includes/functions.php';
require_admin();
require_once 'config/database.php';

$pageTitle = 'Loan Management - Admin';

// Inspect actual table columns dynamically to prevent SQL errors
$columns = $pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_COLUMN);

// Safely match column names
$amountCol = in_array('amount', $columns) ? 'amount' : (in_array('loan_amount', $columns) ? 'loan_amount' : 'requested_amount');
$purposeCol = in_array('purpose', $columns) ? 'purpose' : (in_array('loan_purpose', $columns) ? 'loan_purpose' : 'NULL AS purpose');
$dateCol = in_array('created_at', $columns) ? 'created_at' : (in_array('application_date', $columns) ? 'application_date' : (in_array('date_applied', $columns) ? 'date_applied' : 'NULL AS created_at'));

$query = "
    SELECT 
        loans.id,
        loans.{$amountCol} AS amount,
        {$purposeCol},
        loans.status,
        {$dateCol} AS created_at,
        users.full_name,
        users.email
    FROM loans
    INNER JOIN users ON loans.user_id = users.id
";

// Add ORDER BY safely if date column exists
if ($dateCol !== 'NULL AS created_at') {
    $query .= " ORDER BY loans.{$dateCol} DESC";
} else {
    $query .= " ORDER BY loans.id DESC";
}

$loans = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
/* Center only the Actions header and cell contents */
th.text-center,
td.text-center {
    text-align: center;
}

.loan-actions-center {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
</style>

<div class="app-layout">

    <?php require 'includes/admin_sidebar.php'; ?>

    <main class="app-main">
        <h1 class="page-title">Loan Management</h1>
        <p class="muted">Review, approve, or reject member loan applications.</p>

        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-head">
                <h2>All Loan Applications</h2>
            </div>

            <?php if (empty($loans)): ?>
                <p class="muted">No loan applications found.</p>
            <?php else: ?>
                <table class="table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                            <th style="padding: 10px;">Member</th>
                            <th style="padding: 10px;">Purpose</th>
                            <th style="padding: 10px;">Amount</th>
                            <th style="padding: 10px;">Date</th>
                            <th style="padding: 10px;">Status</th>
                            <th class="text-center" style="padding: 10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 10px;">
                                    <strong><?= e($loan['full_name']) ?></strong><br>
                                    <small class="muted"><?= e($loan['email']) ?></small>
                                </td>
                                <td style="padding: 10px;"><?= e($loan['purpose'] ?? 'N/A') ?></td>
                                <td style="padding: 10px; font-weight: 600;"><?= e(money($loan['amount'])) ?></td>
                                <td style="padding: 10px;">
                                    <?= !empty($loan['created_at']) ? e(date('M d, Y', strtotime($loan['created_at']))) : 'N/A' ?>
                                </td>
                                <td style="padding: 10px;">
                                    <span class="status-badge <?= strtolower($loan['status']) ?>">
                                        <?= ucfirst(e($loan['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center" style="padding: 10px;">
                                    <?php if (strtolower($loan['status']) === 'pending'): ?>
                                        <div class="loan-actions-center">
                                            <a href="approve_loan.php?id=<?= $loan['id'] ?>" class="btn-action" style="padding: 4px 10px; font-size: 0.8rem;">Approve</a>
                                            <a href="reject_loan.php?id=<?= $loan['id'] ?>" class="btn-action" style="padding: 4px 10px; font-size: 0.8rem; background: #fee2e2; color: #dc2626;">Reject</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

</div>

<?php require 'includes/footer.php'; ?>