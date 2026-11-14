<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$pageTitle = 'Transaction History - Admin';

// Fetch all system transactions
$transactions = $pdo->query("
    SELECT 
        transactions.id,
        transactions.transaction_type,
        transactions.amount,
        transactions.created_at,
        users.full_name
    FROM transactions
    INNER JOIN users ON transactions.user_id = users.id
    ORDER BY transactions.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="app-main">
        <h1 class="page-title">Transaction History</h1>
        <p class="muted">View system-wide deposit and withdrawal records.</p>

        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-head">
                <h2>All System Transactions</h2>
            </div>

            <?php if (empty($transactions)): ?>
                <p class="muted">No transactions found.</p>
            <?php else: ?>
                <table class="table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                            <th style="padding: 10px;">Date</th>
                            <th style="padding: 10px;">Member</th>
                            <th style="padding: 10px;">Type</th>
                            <th style="padding: 10px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 10px;"><?= e(date('M d, Y', strtotime($tx['created_at']))) ?></td>
                                <td style="padding: 10px;"><strong><?= e($tx['full_name']) ?></strong></td>
                                <td style="padding: 10px;"><?= ucfirst(e($tx['transaction_type'])) ?></td>
                                <td style="padding: 10px; font-weight: 600; color: <?= strtolower($tx['transaction_type']) === 'deposit' ? '#059669' : '#dc2626' ?>;">
                                    <?= strtolower($tx['transaction_type']) === 'deposit' ? '+' : '-' ?> <?= e(money($tx['amount'])) ?>
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