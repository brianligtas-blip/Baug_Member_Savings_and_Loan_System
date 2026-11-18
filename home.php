<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    if (current_user()['role'] === 'admin') {
        header('Location: admin/admin.php');
    } else {
        header('Location: member/dashboard.php');
    }

    exit;
}

$pageTitle = 'Home | Baug Member Savings and Loan System';

require 'includes/header.php';
?>

<main class="home-screen">

    <section class="home-content">

        <div class="home-welcome">
            <h1>Save. Borrow.</h1>
            <h1>Grow Together.</h1>

            <div class="home-actions">
                <a href="auth/login.php" class="home-login-btn">
                    Member Login
                </a>

                <a href="auth/register.php" class="home-register-btn">
                    Become a Member
                </a>
            </div>
        </div>

    </section>

</main>

<?php require 'includes/footer.php'; ?>