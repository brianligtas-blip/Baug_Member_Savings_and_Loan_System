<?php
require_once 'includes/functions.php';
require_login();
require_once 'config/database.php';

$userId = current_user()['id'];
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);

    // Get current balance using transaction_type
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

    $currentBalance = (float) $stmt->fetchColumn();

    if (!in_array($type, ['Deposit', 'Withdrawal'], true) || $amount <= 0) {

        $message = 'Please enter a valid transaction amount.';
        $messageType = 'error';

    } elseif ($type === 'Withdrawal' && $amount > $currentBalance) {

        $message = 'Insufficient savings balance.';
        $messageType = 'error';

    } else {

        // FIX: Removed `status` from INSERT statement
        $stmt = $pdo->prepare("
            INSERT INTO transactions
            (user_id, transaction_type, amount)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$userId, $type, $amount]);

        $message = $type . ' transaction recorded successfully.';
        $messageType = 'success';
    }
}

// Get current balance using transaction_type
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

// Savings history
$stmt = $pdo->prepare("SELECT id, transaction_type, amount, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'My Savings';  

require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <h1 class="dashboard-heading">
            <class="title-icon"> My Savings
        </h1>

        <!-- CURRENT BALANCE BANNER -->
        <div class="proto-card proto-green" style="margin-bottom: 25px; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div>
                    <small style="text-transform: uppercase; letter-spacing: 1px;">Current Balance</small>
                    <strong style="font-size: 30px; display: block;"><?= money($balance) ?></strong>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <p class="<?= $messageType ?>"><?= e($message) ?></p>
        <?php endif; ?>

        <div class="proto-content-grid">

            <!-- MAKE A SAVINGS TRANSACTION -->
            <div class="proto-box">
                <h2>Make a Savings Transaction</h2>

                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">

                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px;">Transaction Type</label>
                        <select name="type" required style="width: 100%; height: 42px; padding: 5px 12px; border-radius: 6px; border: 1px solid #cbd9d2;">
                            <option value="Deposit">Deposit</option>
                            <option value="Withdrawal">Withdrawal</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px;">Amount (₱)</label>
                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            placeholder="Enter amount"
                            required
                            style="width: 100%; height: 42px; padding: 5px 12px; border-radius: 6px; border: 1px solid #cbd9d2;"
                        >
                    </div>

                    <button type="submit" class="btn green" style="width: 100%; margin-top: 5px;">
                        Submit Transaction
                    </button>

                </form>
            </div>

            <!-- SAVINGS HISTORY -->
            <div class="proto-box">

                <div class="proto-box-head">
                    <h2>Savings History</h2>
                    <a href="transactions.php" class="view-all">View All →</a>
                </div>

                <div class="transaction-list">

                    <?php foreach ($history as $item): ?>

                        <div class="tx-row">
                            <div class="tx-type">
                                <?= ($item['transaction_type'] === 'Deposit') ? '↓ Deposit' : (($item['transaction_type'] === 'Withdrawal') ? '↓ Withdrawal' : e($item['transaction_type'])) ?>
                            </div>

                            <div class="tx-amount">
                                <strong><?= money((float) $item['amount']) ?></strong>
                                <small><?= date('M d, Y', strtotime($item['created_at'])) ?></small>
                            </div>
                        </div>

                    <?php endforeach; ?>

                    <?php if (empty($history)): ?>
                        <p style="color: #777; font-style: italic;">No savings history yet.</p>
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require 'includes/footer.php'; ?>