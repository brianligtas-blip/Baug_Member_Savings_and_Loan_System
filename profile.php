
<?php
require_once 'includes/functions.php';
require_login();
require_once 'config/database.php';

$userId = current_user()['id'];
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['date_of_birth'] ?? '');
    $category = $_POST['member_category'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');

    $allowedCategories = ['Regular', 'Associate', 'Youth'];

    if (
        empty($fullName) ||
        empty($email) ||
        empty($birthdate) ||
        empty($category) ||
        empty($occupation)
    ) {
        $error = 'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Invalid member category.';

    } else {
        try {
            // Validate birth date
            $birthDateObject = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $birthdate
            );

            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                !$birthDateObject ||
                $birthDateObject->format('Y-m-d') !== $birthdate ||
                ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 ||
                     $dateErrors['error_count'] > 0))
            ) {
                throw new InvalidArgumentException('Invalid birth date.');

            }

            $today = new DateTimeImmutable('today');

            if ($birthDateObject > $today) {
                throw new InvalidArgumentException(
                    'Birth date cannot be in the future.'
                );
            }

            // Automatically calculate age
            $age = $today->diff($birthDateObject)->y;

            // Update profile using actual database column names
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    email = ?,
                    date_of_birth = ?,
                    age = ?,
                    member_category = ?,
                    occupation = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $fullName,
                $email,
                $birthdate,
                $age,
                $category,
                $occupation,
                $userId
            ]);

            $message = 'Profile updated successfully!';

            // Refresh session information
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['email'] = $email;

        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'This email address is already being used.';
            } else {
                error_log('Profile update error: ' . $e->getMessage());
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Fetch Latest User Profile Data
$user = current_user();

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$userId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dbUser) {
        $user = array_merge($user, $dbUser);
    }

} catch (PDOException $e) {
    error_log('Profile fetch error: ' . $e->getMessage());
}

$pageTitle = 'My Profile';

require 'includes/header.php';
?>

<div class="app-layout">

    <?php require 'includes/sidebar.php'; ?>

    <main class="app-content">

        <h1 class="dashboard-heading">
            <span class="title-icon">👤</span> My Profile
        </h1>

        <?php if ($message): ?>
            <p class="success"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <div class="proto-content-grid">

            <!-- LEFT COLUMN: MEMBER PROFILE CARD -->
            <div class="proto-box"
                 style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:30px;">

                <!-- AVATAR -->
                <div style="width:100px; height:100px; background:#e2f7e8; color:#075b37; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:50px; margin-bottom:15px; border:3px solid #087c43;">
                    👤
                </div>

                <h2 style="margin-bottom:5px; color:#064b2e; font-size:20px;">
                    <?= e($user['full_name'] ?? 'Member Name') ?>
                </h2>

                <p style="color:#666; font-size:13px; margin-bottom:25px;">
                    <?= e($user['email'] ?? '') ?>
                </p>

                <div style="width:100%; text-align:left; border-top:1px solid #edf5f0; padding-top:20px; display:flex; flex-direction:column; gap:12px; font-size:14px;">

                    <!-- DATE OF BIRTH -->
                    <div>
                        <strong style="color:#064b2e;">
                            Date of Birth:
                        </strong><br>

                        <span style="color:#444;">
                            <?= !empty($user['date_of_birth'])
                                ? date('F d, Y', strtotime($user['date_of_birth']))
                                : 'Not available' ?>
                        </span>
                    </div>

                    <!-- AGE -->
                    <div>
                        <strong style="color:#064b2e;">
                            Age:
                        </strong><br>

                        <span style="color:#444;">
                            <?= isset($user['age']) && $user['age'] !== null
                                ? e($user['age']) . ' years old'
                                : 'Not available' ?>
                        </span>
                    </div>

                    <!-- MEMBER CATEGORY -->
                    <div>
                        <strong style="color:#064b2e;">
                            Member Category:
                        </strong><br>

                        <span style="color:#444;">
                            <?= e($user['member_category'] ?? 'Not set') ?>
                        </span>
                    </div>

                    <!-- OCCUPATION -->
                    <div>
                        <strong style="color:#064b2e;">
                            Occupation:
                        </strong><br>

                        <span style="color:#444;">
                            <?= e($user['occupation'] ?? 'Not set') ?>
                        </span>
                    </div>

                </div>

            </div>

            <!-- RIGHT COLUMN: UPDATE FORM -->
            <div class="proto-box">

                <h2>Account Settings</h2>

                <form method="POST"
                    style="display:flex; flex-direction:column; gap:14px;">

                    <?= csrf_field() ?>

                <input type="hidden" name="update_profile" value="1">

                    <!-- FULL NAME -->
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= e($user['full_name'] ?? '') ?>"
                            required
                            style="width:100%; height:42px; padding:5px 12px; border-radius:6px; border:1px solid #cbd9d2;"
                        >
                    </div>

                    <!-- EMAIL -->
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?= e($user['email'] ?? '') ?>"
                            required
                            style="width:100%; height:42px; padding:5px 12px; border-radius:6px; border:1px solid #cbd9d2;"
                        >
                    </div>

                    <!-- DATE OF BIRTH -->
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            name="date_of_birth"
                            value="<?= e($user['date_of_birth'] ?? '') ?>"
                            required
                            max="<?= date('Y-m-d') ?>"
                            style="width:100%; height:42px; padding:5px 12px; border-radius:6px; border:1px solid #cbd9d2;"
                        >
                    </div>

                    <!-- MEMBER CATEGORY -->
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">
                            Member Category
                        </label>

                        <select
                            name="member_category"
                            required
                            style="width:100%; height:42px; padding:5px 12px; border-radius:6px; border:1px solid #cbd9d2;"
                        >
                            <option value="Regular"
                                <?= ($user['member_category'] ?? '') === 'Regular' ? 'selected' : '' ?>>
                                Regular
                            </option>

                            <option value="Associate"
                                <?= ($user['member_category'] ?? '') === 'Associate' ? 'selected' : '' ?>>
                                Associate
                            </option>

                            <option value="Youth"
                                <?= ($user['member_category'] ?? '') === 'Youth' ? 'selected' : '' ?>>
                                Youth
                            </option>
                        </select>
                    </div>

                    <!-- OCCUPATION -->
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">
                            Occupation
                        </label>

                        <input
                            type="text"
                            name="occupation"
                            value="<?= e($user['occupation'] ?? '') ?>"
                            required
                            style="width:100%; height:42px; padding:5px 12px; border-radius:6px; border:1px solid #cbd9d2;"
                        >
                    </div>

                    <!-- UPDATE BUTTON -->
                    <button
                        type="submit"
                        class="btn green"
                        style="width:100%; margin-top:10px;"
                    >
                        Update Information
                    </button>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require 'includes/footer.php'; ?>