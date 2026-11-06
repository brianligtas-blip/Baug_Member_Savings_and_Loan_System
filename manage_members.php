<?php
require_once 'includes/functions.php';
require_admin();

require_once 'config/database.php';

$message = '';
$error = '';

/* CSRF TOKEN */
if (empty($_SESSION['manage_members_csrf'])) {
    $_SESSION['manage_members_csrf'] = bin2hex(random_bytes(32));
}

/* ==========================================
   UPDATE MEMBER ACCOUNT STATUS
========================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_member_status'])
) {
    $token = $_POST['csrf_token'] ?? '';
    $memberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['new_status'] ?? '';

    if (!hash_equals($_SESSION['manage_members_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif (
        !$memberId ||
        !in_array($newStatus, ['active', 'locked'], true)
    ) {
        $error = 'Invalid member information.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = :status
                WHERE id = :id
                  AND role = 'member'
            ");

            $stmt->execute([
                ':status' => $newStatus,
                ':id' => $memberId
            ]);

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                  AND role = 'member'
            ");

            $check->execute([$memberId]);

            if ($check->fetch()) {
                $message = 'Member account status updated successfully.';
            } else {
                $error = 'Member account not found.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to update member account.';
        }
    }
}

/* ==========================================
   SEARCH MEMBERS
========================================== */

$search = trim($_GET['search'] ?? '');

try {
    $sql = "
        SELECT
            id,
            full_name,
            first_name,
            middle_name,
            family_name,
            name_extension,
            id_number,
            date_of_birth,
            age,
            email,
            username,
            monthly_savings_target,
            member_category,
            occupation,
            house_no,
            street,
            barangay,
            city,
            province,
            zip_code,
            account_status,
            created_at
        FROM users
        WHERE role = 'member'
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                full_name LIKE :search
                OR id_number LIKE :search
                OR email LIKE :search
                OR username LIKE :search
            )
        ";

        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $members = [];
    $error = $error ?: 'Unable to retrieve member records. Please check your database columns.';
}

$pageTitle = 'Manage Members';
require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-main">

        <!-- PAGE HEADING -->
        <div class="dashboard-heading">
            <div>
                <h1 class="page-title">Manage Members</h1>
            </div>
        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if ($message): ?>
            <div class="alert success">
                <?= esc($message) ?>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if ($error): ?>
            <div class="alert error">
                <?= esc($error) ?>
            </div>
        <?php endif; ?>

        <!-- MEMBER SUMMARY -->
        <section class="stats-grid">
            <div class="stat green-stat">
                <strong><?= count($members) ?></strong>
                <small>Members Found</small>
            </div>
        </section>

        <!-- REGISTERED MEMBERS -->
        <section class="card members-section">

            <div class="card-head">
                <div>
                    <h2>Registered Members</h2>
                    <p class="muted">
                        Search and manage member accounts.
                    </p>
                </div>
            </div>

            <!-- SEARCH FORM -->
            <form
                method="GET"
                action="manage_members.php"
                class="member-search"
            >
                <input
                    type="text"
                    name="search"
                    placeholder="Search name, member ID, email or username"
                    value="<?= esc($search) ?>"
                >

                <button type="submit">Search</button>

                <a href="manage_members.php">Clear</a>
            </form>

            <!-- MEMBERS TABLE -->
            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($members)): ?>

                            <tr>
                                <td colspan="7">
                                    No member records found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($members as $member): ?>

                                <tr>
                                    <td>
                                        <?= esc($member['id_number']) ?>
                                    </td>

                                    <td>
                                        <?= esc($member['full_name']) ?>
                                    </td>

                                    <td>
                                        <?= esc($member['email']) ?>
                                    </td>

                                    <td>
                                        <?= esc($member['member_category']) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge">
                                            <?= esc(
                                                ucfirst($member['account_status'] ?? 'pending')
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= esc(date(
                                            'M d, Y',
                                            strtotime($member['created_at'])
                                        )) ?>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="member-actions">

                                            <!-- VIEW BUTTON -->
                                            <button
                                                type="button"
                                                class="btn pale"
                                                onclick="document.getElementById('memberModal<?= (int)$member['id'] ?>').showModal()"
                                            >
                                                View
                                            </button>

                                            <!-- ACTIVATE / LOCK BUTTON -->
                                            <form
                                                method="POST"
                                                action="manage_members.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= esc($_SESSION['manage_members_csrf']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="member_id"
                                                    value="<?= (int)$member['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="update_member_status"
                                                    value="1"
                                                >

                                                <?php if ($member['account_status'] === 'active'): ?>

                                                    <input
                                                        type="hidden"
                                                        name="new_status"
                                                        value="locked"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn danger"
                                                        onclick="return confirm('Are you sure you want to lock this member account?')"
                                                    >
                                                        Lock
                                                    </button>

                                                <?php else: ?>

                                                    <input
                                                        type="hidden"
                                                        name="new_status"
                                                        value="active"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn green"
                                                        onclick="return confirm('Are you sure you want to activate or unlock this member account?')"
                                                    >
                                                        Activate
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

        <!-- ==================================
             MEMBER INFORMATION MODALS
        =================================== -->

        <?php foreach ($members as $member): ?>

            <dialog
                id="memberModal<?= (int)$member['id'] ?>"
                class="member-modal"
            >

                <!-- MODAL HEADER -->
                <div class="member-modal-header">

                    <h2>Member Information</h2>

                    <form method="dialog">
                        <button
                            type="submit"
                            class="modal-close"
                            aria-label="Close"
                        >
                            &times;
                        </button>
                    </form>

                </div>

                <!-- MODAL BODY -->
                <div class="member-modal-body">

                    <h3>Personal Information</h3>

                    <p>
                        <strong>Full Name:</strong>
                        <?= esc($member['full_name']) ?>
                    </p>

                    <p>
                        <strong>Member ID:</strong>
                        <?= esc($member['id_number']) ?>
                    </p>

                    <p>
                        <strong>Date of Birth:</strong>
                        <?= esc($member['date_of_birth']) ?>
                    </p>

                    <p>
                        <strong>Age:</strong>
                        <?= esc($member['age'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= esc($member['email']) ?>
                    </p>

                    <p>
                        <strong>Username:</strong>
                        <?= esc($member['username']) ?>
                    </p>

                    <p>
                        <strong>Category:</strong>
                        <?= esc($member['member_category']) ?>
                    </p>

                    <p>
                        <strong>Occupation:</strong>
                        <?= esc($member['occupation']) ?>
                    </p>

                    <p>
                        <strong>Monthly Savings Target:</strong>
                        ₱<?= number_format(
                            (float)$member['monthly_savings_target'],
                            2
                        ) ?>
                    </p>

                    <hr>

                    <h3>Address Information</h3>

                    <p>
                        <strong>House No.:</strong>
                        <?= esc($member['house_no'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>Street:</strong>
                        <?= esc($member['street'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>Barangay:</strong>
                        <?= esc($member['barangay'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>City:</strong>
                        <?= esc($member['city'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>Province:</strong>
                        <?= esc($member['province'] ?? 'N/A') ?>
                    </p>

                    <p>
                        <strong>ZIP Code:</strong>
                        <?= esc($member['zip_code'] ?? 'N/A') ?>
                    </p>

                    <hr>

                    <h3>Account Information</h3>

                    <p>
                        <strong>Account Status:</strong>
                        <?= esc(
                            ucfirst($member['account_status'] ?? 'pending')
                        ) ?>
                    </p>

                    <p>
                        <strong>Date Registered:</strong>
                        <?= esc($member['created_at']) ?>
                    </p>

                </div>

                <!-- MODAL FOOTER -->
                <div class="member-modal-footer">
                    <form method="dialog">
                        <button type="submit" class="btn green">
                            Close
                        </button>
                    </form>
                </div>

            </dialog>

        <?php endforeach; ?>

    </main>

</div>

<?php require 'includes/footer.php'; ?>