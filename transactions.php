
<?php

require_once 'includes/functions.php';
require_login();

require_once 'config/database.php';

$userId = current_user()['id'];

/* =========================================
   1. GET FILTER INPUTS
========================================= */

$typeFilter = trim($_GET['type'] ?? '');
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');

/* =========================================
   2. VALIDATE DATE FILTERS
========================================= */

$validDate = function ($date) {
    if ($date === '') {
        return true;
    }

    $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsedDate && $parsedDate->format('Y-m-d') === $date;
};

$filterError = '';

if (!$validDate($fromDate) || !$validDate($toDate)) {
    $filterError = 'Please enter valid dates.';
    $fromDate = '';
    $toDate = '';
}

if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {
    $filterError = 'The starting date cannot be later than the ending date.';
    $fromDate = '';
    $toDate = '';
}

/* =========================================
   3. BUILD TRANSACTION QUERY
========================================= */

$sql = "
    SELECT *
    FROM transactions
    WHERE user_id = ?
";

$params = [$userId];

$allowedTypes = [
    'Deposit',
    'Withdrawal',
    'Loan Payment'
];

if (
    $typeFilter !== '' &&
    $typeFilter !== 'All Transactions' &&
    in_array($typeFilter, $allowedTypes, true)
) {
    $sql .= " AND transaction_type = ?";
    $params[] = $typeFilter;

} elseif (
    $typeFilter !== '' &&
    $typeFilter !== 'All Transactions'
) {
    $typeFilter = '';
}

if ($fromDate !== '') {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $toDate;
}

$sql .= " ORDER BY created_at DESC";

/* =========================================
   4. FETCH TRANSACTIONS
========================================= */

$transactions = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Transaction history error: ' . $e->getMessage());
    $filterError = 'Unable to load transactions. Please try again later.';
}

/* =========================================
   5. PAGE CONFIGURATION
========================================= */

$pageTitle = 'Transaction History';

require 'includes/header.php';

?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <!-- PAGE HEADING -->
        <h1 class="dashboard-heading">
            <span class="title-icon">
                <i class="fas fa-history"></i>
            </span>
            Transaction History
        </h1>

        <!-- FILTER FORM -->
        <div class="proto-box" style="margin-bottom: 25px; padding: 18px 25px;">

            <form method="GET" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">

                <!-- TRANSACTION TYPE -->
                <select
                    name="type"
                    aria-label="Transaction type"
                    style="height: 38px; padding: 0 12px; border-radius: 6px; border: 1px solid #cbd9d2;"
                >
                    <option value="All Transactions"
                        <?= $typeFilter === 'All Transactions' || $typeFilter === '' ? 'selected' : '' ?>>
                        All Transactions
                    </option>

                    <option value="Deposit"
                        <?= $typeFilter === 'Deposit' ? 'selected' : '' ?>>
                        Deposit
                    </option>

                    <option value="Withdrawal"
                        <?= $typeFilter === 'Withdrawal' ? 'selected' : '' ?>>
                        Withdrawal
                    </option>

                    <option value="Loan Payment"
                        <?= $typeFilter === 'Loan Payment' ? 'selected' : '' ?>>
                        Loan Payment
                    </option>
                </select>

                <!-- FROM DATE -->
                <div style="display: flex; align-items: center; gap: 8px;">

                    <label for="from" style="font-size: 13px; font-weight: bold; color: #555;">
                        From
                    </label>

                    <input
                        type="date"
                        id="from"
                        name="from"
                        value="<?= e($fromDate) ?>"
                        style="height: 38px; padding: 0 10px; border-radius: 6px; border: 1px solid #cbd9d2;"
                    >

                </div>

                <!-- TO DATE -->
                <div style="display: flex; align-items: center; gap: 8px;">

                    <label for="to" style="font-size: 13px; font-weight: bold; color: #555;">
                        To
                    </label>

                    <input
                        type="date"
                        id="to"
                        name="to"
                        value="<?= e($toDate) ?>"
                        style="height: 38px; padding: 0 10px; border-radius: 6px; border: 1px solid #cbd9d2;"
                    >

                </div>

                <!-- FILTER BUTTON -->
                <button
                    type="submit"
                    class="btn green"
                    style="min-height: 38px; padding: 0 20px;"
                >
                    Filter
                </button>

                <!-- RESET BUTTON -->
                <?php if (
                    $typeFilter !== '' ||
                    $fromDate !== '' ||
                    $toDate !== ''
                ): ?>

                    <a
                        href="transactions.php"
                        style="color: #666; font-size: 13px; text-decoration: underline;"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>

            <?php if ($filterError !== ''): ?>

                <p style="color: #c62828; margin-top: 12px;">
                    <?= e($filterError) ?>
                </p>

            <?php endif; ?>

        </div>

        <!-- TRANSACTIONS TABLE CARD -->
        <div class="proto-box">

            <div style="overflow-x: auto;">

                <table style="width: 100%; border-collapse: collapse; text-align: left;">

                    <!-- TABLE HEADER -->
                    <thead>

                        <tr style="border-bottom: 2px solid #e0eee4; color: #064b2e; font-size: 14px;">

                            <th style="padding: 12px 15px;">
                                Date
                            </th>

                            <th style="padding: 12px 15px;">
                                Type
                            </th>

                            <th style="padding: 12px 15px;">
                                Amount
                            </th>

                            <th style="padding: 12px 15px; text-align: center;">
                                Status
                            </th>

                        </tr>

                    </thead>

                    <!-- TABLE BODY -->
                    <tbody>

                        <?php if (!empty($transactions)): ?>

                            <?php foreach ($transactions as $tx): ?>

                                <tr style="border-bottom: 1px solid #edf5f0; font-size: 14px;">

                                    <!-- DATE -->
                                    <td style="padding: 14px 15px; color: #444;">

                                        <?= !empty($tx['created_at'])
                                            ? date('M d, Y', strtotime($tx['created_at']))
                                            : 'Date unavailable' ?>

                                    </td>

                                    <!-- TYPE -->
                                    <td style="padding: 14px 15px; font-weight: bold; color: #142b20;">

                                        <?= e($tx['transaction_type'] ?? $tx['type'] ?? 'Transaction') ?>

                                    </td>

                                    <!-- AMOUNT -->
                                    <td style="padding: 14px 15px; font-weight: bold; color: #064b2e;">

                                        <?= money((float) ($tx['amount'] ?? 0)) ?>

                                    </td>

                                    <!-- STATUS -->
                                    <td style="padding: 14px 15px; text-align: center;">

                                        <span style="background: #e2f7e8; color: #075b37; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block;">

                                            <?= e($tx['status'] ?? 'Completed') ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

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