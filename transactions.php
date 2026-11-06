<?php
require_once 'includes/functions.php';
require_login();
require_once 'config/database.php';

$userId = current_user()['id'];

// Get filter inputs
$typeFilter = $_GET['type'] ?? '';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';

// Build Query
$sql = "SELECT * FROM transactions WHERE user_id = ?";
$params = [$userId];

// Filter by transaction_type (Deposit, Withdrawal, Loan Payment, etc.)
if (!empty($typeFilter) && $typeFilter !== 'All Transactions') {
    $sql .= " AND transaction_type = ?";
    $params[] = $typeFilter;
}

if (!empty($fromDate)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $toDate;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = 'Transaction History';
require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <h1 class="dashboard-heading">
            <class="title-icon"> 
                Transaction History
            </class>
        </h1>

        <!-- FILTER FORM -->
        <div class="proto-box" style="margin-bottom: 25px; padding: 18px 25px;">
            <form method="GET" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                
                <select name="type" style="height: 38px; padding: 0 12px; border-radius: 6px; border: 1px solid #cbd9d2;">
                    <option value="All Transactions" <?= $typeFilter === 'All Transactions' ? 'selected' : '' ?>>All Transactions</option>
                    <option value="Deposit" <?= $typeFilter === 'Deposit' ? 'selected' : '' ?>>Deposit</option>
                    <option value="Withdrawal" <?= $typeFilter === 'Withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                    <option value="Loan Payment" <?= $typeFilter === 'Loan Payment' ? 'selected' : '' ?>>Loan Payment</option>
                </select>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 13px; font-weight: bold; color: #555;">From</label>
                    <input type="date" name="from" value="<?= e($fromDate) ?>" style="height: 38px; padding: 0 10px; border-radius: 6px; border: 1px solid #cbd9d2;">
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 13px; font-weight: bold; color: #555;">To</label>
                    <input type="date" name="to" value="<?= e($toDate) ?>" style="height: 38px; padding: 0 10px; border-radius: 6px; border: 1px solid #cbd9d2;">
                </div>

                <button type="submit" class="btn green" style="min-height: 38px; padding: 0 20px;">
                    Filter
                </button>
                
                <?php if (!empty($typeFilter) || !empty($fromDate) || !empty($toDate)): ?>
                    <a href="transactions.php" style="color: #666; font-size: 13px; text-decoration: underline;">Reset</a>
                <?php endif; ?>

            </form>
        </div>

        <!-- TRANSACTIONS TABLE CARD -->
        <div class="proto-box">
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    
                    <thead>
                        <tr style="border-bottom: 2px solid #e0eee4; color: #064b2e; font-size: 14px;">
                            <th style="padding: 12px 15px;">Date</th>
                            <th style="padding: 12px 15px;">Type</th>
                            <th style="padding: 12px 15px;">Amount</th>
                            <th style="padding: 12px 15px; text-align: center;">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr style="border-bottom: 1px solid #edf5f0; font-size: 14px;">
                                <td style="padding: 14px 15px; color: #444;">
                                    <?= date('M d, Y', strtotime($tx['created_at'])) ?>
                                </td>

                                <td style="padding: 14px 15px; font-weight: bold; color: #142b20;">
                                    <!-- FIX: Fallback to transaction_type instead of array key "type" -->
                                    <?= e($tx['transaction_type'] ?? $tx['type'] ?? 'Transaction') ?>
                                </td>

                                <td style="padding: 14px 15px; font-weight: bold; color: #064b2e;">
                                    <?= money((float) ($tx['amount'] ?? 0)) ?>
                                </td>

                                <td style="padding: 14px 15px; text-align: center;">
                                    <!-- FIX: Safe check for status with 'Completed' default pill -->
                                    <span style="background: #e2f7e8; color: #075b37; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block;">
                                        <?= e($tx['status'] ?? 'Completed') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="4" style="padding: 20px; text-align: center; color: #777; font-style: italic;">
                                    No transactions found matching the selected criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>

    </main>

</div>

<?php require 'includes/footer.php'; ?>