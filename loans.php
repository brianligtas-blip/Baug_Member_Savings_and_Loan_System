<?php
require_once 'includes/functions.php';
require_login();
require_once 'config/database.php';

$userId = current_user()['id'];
$message = '';
$error = '';

// Handle Loan Application Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = trim($_POST['purpose'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);

    if (empty($purpose)) {
        $error = 'Please select a loan purpose.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid loan amount.';
    } else {
        $inserted = false;

        // Try standard column names (user_id, purpose, amount, status)
        try {
            $stmt = $pdo->prepare("INSERT INTO loans (user_id, purpose, amount, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$userId, $purpose, $amount]);
            $inserted = true;
        } catch (PDOException $e) {}

        // Fallback 1: loan_purpose and loan_amount
        if (!$inserted) {
            try {
                $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_purpose, loan_amount, status) VALUES (?, ?, ?, 'Pending')");
                $stmt->execute([$userId, $purpose, $amount]);
                $inserted = true;
            } catch (PDOException $e) {}
        }

        // Fallback 2: purpose and loan_amount
        if (!$inserted) {
            try {
                $stmt = $pdo->prepare("INSERT INTO loans (user_id, purpose, loan_amount, status) VALUES (?, ?, ?, 'Pending')");
                $stmt->execute([$userId, $purpose, $amount]);
                $inserted = true;
            } catch (PDOException $e) {}
        }

        // Fallback 3: loan_purpose and principal_amount
        if (!$inserted) {
            try {
                $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_purpose, principal_amount, status) VALUES (?, ?, ?, 'Pending')");
                $stmt->execute([$userId, $purpose, $amount]);
                $inserted = true;
            } catch (PDOException $e) {}
        }

        if ($inserted) {
            $message = 'Your loan application has been submitted successfully and is currently under review.';
        } else {
            $error = 'Failed to submit loan application. Please check phpMyAdmin for your exact `loans` table column names.';
        }
    }
}

// Fetch Latest Loan Status
$latestLoan = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $latestLoan = $stmt->fetch();
} catch (PDOException $e) {
    $latestLoan = null;
}

$pageTitle = 'Loan Management';
require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <h1 class="dashboard-heading">
            <span class="title-icon">🏛️</span> Loan Management
        </h1>

        <?php if ($message): ?>
            <p class="success"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <div class="proto-content-grid">

            <!-- APPLY FOR A LOAN CARD -->
            <div class="proto-box">
                <h2>Apply for a Loan</h2>

                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">

                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px;">Loan Purpose</label>
                        <select name="purpose" required style="width: 100%; height: 42px; padding: 5px 12px; border-radius: 6px; border: 1px solid #cbd9d2;">
                            <option value="">Select purpose</option>
                            <option value="Personal Loan">Personal Loan</option>
                            <option value="Business Expansion">Business Expansion</option>
                            <option value="Emergency Loan">Emergency Loan</option>
                            <option value="Education / Tuition">Education / Tuition</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px;">Requested Amount (₱)</label>
                        <input
                            type="number"
                            name="amount"
                            min="100"
                            step="0.01"
                            placeholder="e.g. 10,000.00"
                            required
                            style="width: 100%; height: 42px; padding: 5px 12px; border-radius: 6px; border: 1px solid #cbd9d2;"
                        >
                    </div>

                    <button type="submit" class="btn green" style="width: 100%; margin-top: 5px;">
                        Submit Application
                    </button>

                </form>
            </div>

            <!-- LOAN STATUS CARD -->
            <div class="proto-box" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 250px;">

                <h2 style="align-self: flex-start; margin-bottom: 20px;">Loan Status</h2>

                <?php if ($latestLoan): ?>
                    <div style="font-size: 48px; margin-bottom: 10px;">
                        <?= ($latestLoan['status'] ?? '') === 'Approved' ? '✅' : (($latestLoan['status'] ?? '') === 'Rejected' ? '❌' : '🛡️') ?>
                    </div>

                    <strong style="font-size: 18px; color: #064b2e; margin-bottom: 5px;">
                        Your loan application is currently <?= e(strtolower($latestLoan['status'] ?? 'under review')) ?>.
                    </strong>

                    <p style="font-size: 13px; color: #666; margin-top: 5px;">
                        Application Date: 
                        <strong>
                            <?= !empty($latestLoan['application_date']) ? date('M d, Y', strtotime($latestLoan['application_date'])) : (!empty($latestLoan['created_at']) ? date('M d, Y', strtotime($latestLoan['created_at'])) : date('M d, Y')) ?>
                        </strong>
                    </p>
                <?php else: ?>
                    <div style="font-size: 40px; color: #aaa; margin-bottom: 10px;">📄</div>
                    <p style="color: #777; font-style: italic;">No active loan applications found.</p>
                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require 'includes/footer.php'; ?>