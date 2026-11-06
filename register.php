<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$settings = security_settings();

$securityQuestions = [
    'Q1' => 'Question 1 ',
    'Q2' => 'Question 2 ',
    'Q3' => 'Question 3 ',
    'Q4' => 'Question 4 ',
    'Q5' => 'Question 5 ',  
    'Q6' => 'Question 6 ',
    'Q7' => 'Question 7 ',
    'Q8' => 'Question 8 '
];

$requiredQuestions = ['Q6', 'Q1', 'Q3'];

$errors = [];
$success = '';
$old = [];

$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $old = $_POST;

    // PERSONAL INFORMATION
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $familyName = trim($_POST['family_name'] ?? '');
    $nameExtension = trim($_POST['name_extension'] ?? '');

    $idNumber = trim($_POST['id_number'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    // ADDRESS
    $houseNo = trim($_POST['house_no'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    // ACCOUNT
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // MEMBER INFORMATION
    $memberCategory = $_POST['member_category'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $monthlySavings = trim($_POST['monthly_savings_target'] ?? '');

    // SECURITY QUESTIONS
    $questions = [
        $_POST['sec_q1'] ?? '',
        $_POST['sec_q2'] ?? '',
        $_POST['sec_q3'] ?? ''
    ];

    $answers = [
        trim($_POST['sec_a1'] ?? ''),
        trim($_POST['sec_a2'] ?? ''),
        trim($_POST['sec_a3'] ?? '')
    ];

    // NAME VALIDATION (FIXED)
    $namePattern = "/^[\p{L}\p{M} .'-]+$/u";
    $maxNameLength = $settings['max_name'] ?? 25;

    $nameFields = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'family_name' => $familyName
    ];

    foreach ($nameFields as $field => $value) {

        if (($field === 'first_name' || $field === 'family_name') && $value === '') {
            $errors[$field] = 'This field is required.';
        } elseif ($value !== '') {
            if (!preg_match($namePattern, $value)) {
                $errors[$field] = 'Please enter a valid name.';
            } elseif (mb_strlen($value, 'UTF-8') > $maxNameLength) {
                $errors[$field] = "Name must not exceed {$maxNameLength} characters.";
            }
        }
    }

    if (
        $nameExtension !== '' &&
        !preg_match("/^[a-zA-Z0-9 .'-]{1,20}$/", $nameExtension)
    ) {
        $errors['name_extension'] = 'Invalid name extension.';
    }

    // MEMBER ID
    if ($idNumber === '') {
        $errors['id_number'] = 'Member ID is required.';
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $idNumber)) {
        $errors['id_number'] = 'Use the format xxxx-xxxx.';
    }

    // DATE OF BIRTH VALIDATION (FIXED)
    $age = null;

    if (!empty($dateOfBirth)) {
        $dob = DateTime::createFromFormat('!Y-m-d', $dateOfBirth);
        if (!$dob) {
            $dob = DateTime::createFromFormat('!m/d/Y', $dateOfBirth);
        }

        if (!$dob) {
            $errors['date_of_birth'] = 'Enter a valid birthdate (e.g., YYYY-MM-DD).';
        } elseif ($dob > new DateTime('today')) {
            $errors['date_of_birth'] = 'Birthdate cannot be in the future.';
        } else {
            $age = $dob->diff(new DateTime('today'))->y;
            if ($age < 18) {
                $errors['date_of_birth'] = 'You must be at least 18 years old.';
            } else {
                $dateOfBirth = $dob->format('Y-m-d');
            }
        }
    } else {
        $errors['date_of_birth'] = 'Date of birth is required.';
    }

    // ADDRESS VALIDATION
    $addressFields = [
        'house_no' => [$houseNo, 'House number is required.'],
        'street' => [$street, 'Street is required.'],
        'barangay' => [$barangay, 'Barangay is required.'],
        'city' => [$city, 'City is required.'],
        'province' => [$province, 'Province is required.'],
        'zip_code' => [$zipCode, 'ZIP code is required.']
    ];

    foreach ($addressFields as $field => $data) {
        if ($data[0] === '') {
            $errors[$field] = $data[1];
        }
    }

    if (
        $zipCode !== '' &&
        !preg_match('/^\d{4}$/', $zipCode)
    ) {
        $errors['zip_code'] = 'ZIP code must contain exactly 4 digits.';
    }

    // EMAIL
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // USERNAME
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!validate_username_length($username)) {
        $errors['username'] = 'Username must contain 6-16 characters.';
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $username)) {
        $errors['username'] = 'Username must start with a letter and contain only letters and numbers.';
    }

    // PASSWORD
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (!validate_password_length($password)) {
        $errors['password'] = 'Password must contain 9-64 characters.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // MEMBER INFORMATION
    $allowedCategories = ['Regular', 'Associate', 'Youth'];

    if (!in_array($memberCategory, $allowedCategories, true)) {
        $errors['member_category'] = 'Select a valid member category.';
    }

    // OCCUPATION VALIDATION
    $occupationPattern = "/^[\p{L}\p{M} .'-]+$/u";

    if ($occupation === '') {
        $errors['occupation'] = 'Occupation is required.';
    } elseif (mb_strlen($occupation, 'UTF-8') > 50) {
        $errors['occupation'] = 'Occupation must not exceed 50 characters.';
    } elseif (!preg_match($occupationPattern, $occupation)) {
        $errors['occupation'] = 'Occupation may contain letters, spaces, periods, apostrophes, and hyphens only.';
    }

    if ($monthlySavings === '') {
        $errors['monthly_savings_target'] = 'Monthly savings target is required.';
    } elseif (
        !is_numeric($monthlySavings) ||
        !is_finite((float)$monthlySavings) ||
        (float)$monthlySavings < 0 ||
        (float)$monthlySavings > 9999999999.99
    ) {
        $errors['monthly_savings_target'] = 'Enter a valid savings target.';
    }

    // SECURITY QUESTIONS
    if ($questions !== $requiredQuestions) {
        $errors['security_questions'] = 'Your assigned questions must be Q6, Q1, and Q3 in that order.';
    }

    foreach ($answers as $index => $answer) {
        $length = mb_strlen($answer, 'UTF-8');

        if ($answer === '') {
            $errors['sec_a' . ($index + 1)] = 'Please provide your answer.';
        } elseif ($length < 2 || $length > 50) {
            $errors['sec_a' . ($index + 1)] = 'Answer must contain 2-50 characters.';
        } elseif (!preg_match("/^[\p{L}\p{M}\d .'-]+$/u", $answer)) { // FIXED REGEX
            $errors['sec_a' . ($index + 1)] = 'Answer contains invalid characters.';
        }
    }

    // PRIVACY CONSENT
    $privacyConsent = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1';

    if (!$privacyConsent) {
        $errors['privacy_consent'] = 'You must agree to the Privacy Notice.';
    }

    // DATABASE INSERTION
    if (empty($errors)) {

        try {

           // CHECK DUPLICATE MEMBER ID
            $idCheck = $pdo->prepare("
                SELECT id FROM users
                WHERE id_number = ?
                LIMIT 1
            ");
            $idCheck->execute([$idNumber]);

            if ($idCheck->fetch(PDO::FETCH_ASSOC)) {
                $errors['id_number'] = 'This ID Number is already registered.';
            }

            // CHECK DUPLICATE EMAIL
            $emailCheck = $pdo->prepare("
                SELECT id FROM users
                WHERE email = ?
                LIMIT 1
            ");
            $emailCheck->execute([$email]);

            if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {
                $errors['email'] = 'This email address is already registered.';
            }

            // CHECK DUPLICATE USERNAME
            $usernameCheck = $pdo->prepare("
                SELECT id FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $usernameCheck->execute([$username]);

            if ($usernameCheck->fetch(PDO::FETCH_ASSOC)) {
                $errors['username'] = 'This username is already registered.';
            }

            // CONTINUE ONLY IF NO DUPLICATES EXIST
            if (empty($errors)) {

                // FULL NAME
                $nameParts = array_filter([
                    $firstName,
                    $middleName,
                    $familyName,
                    $nameExtension
                ], fn($part) => $part !== '');

                $fullName = implode(' ', $nameParts);

                // HASH PASSWORD
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // HASH SECURITY ANSWERS
                $hashedAnswers = [];

                foreach ($answers as $answer) {

                    $normalizedAnswer = mb_strtolower(
                        trim($answer),
                        'UTF-8'
                    );

                    $hashedAnswers[] = password_hash(
                        $normalizedAnswer,
                        PASSWORD_DEFAULT
                    );
                }
                // GENERATE EMAIL VERIFICATION TOKEN
                $verificationToken = bin2hex(random_bytes(32));

                $verificationTokenHash = hash(
                'sha256',
                $verificationToken
                );

                $verificationExpiresAt = date(
                'Y-m-d H:i:s',
                time() + (24 * 60 * 60)
                );

                // INSERT MEMBER ACCOUNT
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (
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
                        password,
                        monthly_savings_target,
                        member_category,
                        occupation,
                        house_no,
                        street,
                        barangay,
                        city,
                        province,
                        zip_code,
                        sec_q1,
                        sec_a1,
                        sec_q2,
                        sec_a2,
                        sec_q3,
                        sec_a3,
                        privacy_consent,
                        privacy_consent_at,
                        email_verified,
                        account_status,
                        verification_token_hash,
                        verification_expires_at
                    ) VALUES (
                        :full_name,
                        :first_name,
                        :middle_name,
                        :family_name,
                        :name_extension,
                        :id_number,
                        :date_of_birth,
                        :age,
                        :email,
                        :username,
                        :password,
                        :monthly_savings_target,
                        :member_category,
                        :occupation,
                        :house_no,
                        :street,
                        :barangay,
                        :city,
                        :province,
                        :zip_code,
                        :sec_q1,
                        :sec_a1,
                        :sec_q2,
                        :sec_a2,
                        :sec_q3,
                        :sec_a3,
                        1,
                        NOW(),
                        0,
                        'pending',
                        :verification_token_hash,
                        :verification_expires_at
                    )
                ");

                $insertStmt->execute([
                    ':full_name' => $fullName,
                    ':first_name' => $firstName,
                    ':middle_name' => $middleName ?: null,
                    ':family_name' => $familyName,
                    ':name_extension' => $nameExtension ?: null,
                    ':id_number' => $idNumber,
                    ':date_of_birth' => $dateOfBirth,
                    ':age' => $age,
                    ':email' => $email,
                    ':username' => $username,
                    ':password' => $hashedPassword,
                    ':monthly_savings_target' => $monthlySavings,
                    ':member_category' => $memberCategory,
                    ':occupation' => $occupation,
                    ':house_no' => $houseNo,
                    ':street' => $street,
                    ':barangay' => $barangay,
                    ':city' => $city,
                    ':province' => $province,
                    ':zip_code' => $zipCode,
                    ':sec_q1' => $securityQuestions['Q6'],
                    ':sec_a1' => $hashedAnswers[0],
                    ':sec_q2' => $securityQuestions['Q1'],
                    ':sec_a2' => $hashedAnswers[1],
                    ':sec_q3' => $securityQuestions['Q3'],
                    ':sec_a3' => $hashedAnswers[2],
                    ':verification_token_hash' => $verificationTokenHash,
                    ':verification_expires_at' => $verificationExpiresAt
                ]);

                if ($insertStmt->rowCount() === 1) {

                    error_log('BAUG: New member account inserted. ID: ' . $pdo->lastInsertId());

                    $success = 'Your BAUG account has been registered successfully. Your account is pending approval and email verification.';

                    $old = [];

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrfToken = $_SESSION['csrf_token'];

                } else {
                    $errors['general'] = 'Account insertion was not successful.';
                }
            }

        } catch (PDOException $e) {
            error_log('BAUG Registration Error: ' . $e->getMessage());
            $errors['general'] = 'Registration failed. Database error: ' . $e->getMessage();
        }

    } else {
        $errors['general'] = 'Registration validation failed. Please check the highlighted fields below.';
    }

} 
// END POST REQUEST

$pageTitle = 'Member Registration';

require __DIR__ . '/includes/header.php';

function old_value($key) {
    global $old;
    return e($old[$key] ?? '');
}

function field_error($errors, $key) {
    if (!empty($errors[$key])) {
        echo '<small class="error-message" style="color:red;">' . e($errors[$key]) . '</small>';
    }
}

?>

<div class="registration-card">

    <?php if (!empty($errors['general'])): ?>
    <div class="error-message" style="color:red; margin-bottom:15px;">
        <?= e($errors['general']) ?>
    </div>
<?php endif; ?>

    <div class="section-title">
        <div>
            <h1>Member Registration</h1>

        </div>
    </div>

    <?php if (!empty($success)): ?>
        <script>
            alert(<?= json_encode($success) ?>);
        </script>
    <?php endif; ?>

    <form method="POST" action="register.php" class="registration-form">

        <?= csrf_field() ?>

        <!-- PERSONAL INFORMATION -->
        <section class="form-section">
            <div class="section-heading">
                <h2>Personal Information</h2>
                <p>Please provide your personal details.</p>
            </div>

            <div class="form-column">

                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" maxlength="25" required value="<?= old_value('first_name') ?>">
                    <?php field_error($errors, 'first_name'); ?>
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name <span class="optional">optional</span></label>
                    <input type="text" id="middle_name" name="middle_name" maxlength="25" value="<?= old_value('middle_name') ?>">
                    <?php field_error($errors, 'middle_name'); ?>
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name <span class="required">*</span></label>
                    <input type="text" id="family_name" name="family_name" maxlength="25" required value="<?= old_value('family_name') ?>">
                    <?php field_error($errors, 'family_name'); ?>
                </div>

                <div class="form-group">
                    <label for="name_extension">Name Extension <span class="optional">optional</span></label>
                    <input type="text" id="name_extension" name="name_extension" placeholder="Jr., Sr., III" value="<?= old_value('name_extension') ?>">
                    <?php field_error($errors, 'name_extension'); ?>
                </div>

                <div class="form-group">
                    <label for="id_number">Member ID Number <span class="required">*</span></label>
                    <input type="text" id="id_number" name="id_number" placeholder="xxxx-xxxx" required value="<?= old_value('id_number') ?>">
                    <?php field_error($errors, 'id_number'); ?>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required value="<?= old_value('date_of_birth') ?>">
                    <?php field_error($errors, 'date_of_birth'); ?>
                </div>

            </div>
        </section>

        <!-- ADDRESS -->
        <section class="form-section">
            <div class="section-heading">
                <h2>Address Information</h2>
                <p>Enter your current residential address.</p>
            </div>

            <div class="form-column">

                <div class="form-group">
                    <label for="house_no">House Number <span class="required">*</span></label>
                    <input type="text" id="house_no" name="house_no" required value="<?= old_value('house_no') ?>">
                    <?php field_error($errors, 'house_no'); ?>
                </div>

                <div class="form-group">
                    <label for="street">Street <span class="required">*</span></label>
                    <input type="text" id="street" name="street" required value="<?= old_value('street') ?>">
                    <?php field_error($errors, 'street'); ?>
                </div>

                <div class="form-group">
                    <label for="barangay">Barangay <span class="required">*</span></label>
                    <input type="text" id="barangay" name="barangay" required value="<?= old_value('barangay') ?>">
                    <?php field_error($errors, 'barangay'); ?>
                </div>

                <div class="form-group">
                    <label for="city">Municipality / City <span class="required">*</span></label>
                    <input type="text" id="city" name="city" required value="<?= old_value('city') ?>">
                    <?php field_error($errors, 'city'); ?>
                </div>

                <div class="form-group">
                    <label for="province">Province <span class="required">*</span></label>
                    <input type="text" id="province" name="province" required value="<?= old_value('province') ?>">
                    <?php field_error($errors, 'province'); ?>
                </div>

                <div class="form-group">
                    <label for="zip_code">ZIP Code <span class="required">*</span></label>
                    <input type="text" id="zip_code" name="zip_code" maxlength="4" required value="<?= old_value('zip_code') ?>">
                    <?php field_error($errors, 'zip_code'); ?>
                </div>

            </div>
        </section>

        <!-- ACCOUNT INFORMATION -->
        <section class="form-section">
            <div class="section-heading">
                <h2>Account Information</h2>
                <p>Create your login credentials.</p>
            </div>

            <div class="form-column">

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required value="<?= old_value('email') ?>">
                    <?php field_error($errors, 'email'); ?>
                </div>

                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" minlength="6" maxlength="16" required value="<?= old_value('username') ?>">
                    <small>6-16 characters. Start with a letter.</small>
                    <?php field_error($errors, 'username'); ?>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" minlength="9" maxlength="64" required>
                    <small>Password must contain 9-64 characters.</small>
                    <?php field_error($errors, 'password'); ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php field_error($errors, 'confirm_password'); ?>
                </div>

            </div>
        </section>

        <!-- MEMBER INFORMATION -->
        <section class="form-section">
            <div class="section-heading">
                <h2>BAUG Member Information</h2>
                <p>Provide your membership details.</p>
            </div>

            <div class="form-column">

                <div class="form-group">
                    <label for="member_category">Member Category <span class="required">*</span></label>
                    <select id="member_category" name="member_category" required>
                        <option value="">Select category</option>
                        <option value="Regular" <?= (($old['member_category'] ?? '') === 'Regular') ? 'selected' : '' ?>>Regular</option>
                        <option value="Associate" <?= (($old['member_category'] ?? '') === 'Associate') ? 'selected' : '' ?>>Associate</option>
                        <option value="Youth" <?= (($old['member_category'] ?? '') === 'Youth') ? 'selected' : '' ?>>Youth</option>
                    </select>
                    <?php field_error($errors, 'member_category'); ?>
                </div>

                <div class="form-group">
                    <label for="occupation">Occupation <span class="required">*</span></label>
                    <input type="text" id="occupation" name="occupation" required value="<?= old_value('occupation') ?>">
                    <?php field_error($errors, 'occupation'); ?>
                </div>

                <div class="form-group">
                    <label for="monthly_savings_target">Monthly Savings Target (₱) <span class="required">*</span></label>
                    <input type="number" id="monthly_savings_target" name="monthly_savings_target" min="0" step="0.01" required value="<?= old_value('monthly_savings_target') ?>">
                    <?php field_error($errors, 'monthly_savings_target'); ?>
                </div>

            </div>
        </section>

        <!-- SECURITY QUESTIONS -->
        <section class="form-section">
            <div class="section-heading">
                <h2>Security Questions</h2>
                <p>Provide answers that you can remember. These may be used for account recovery.</p>
            </div>

            <?php if (!empty($errors['security_questions'])): ?>
                <div class="error-message" style="color:red; margin-bottom:10px;">
                    <?= e($errors['security_questions']) ?>
                </div>
            <?php endif; ?>

            <div class="form-column">

                <?php
                $questionSlots = [
                    1 => 'Q6',
                    2 => 'Q1',
                    3 => 'Q3'
                ];
                ?>

                <?php foreach ($questionSlots as $number => $questionCode): ?>

                    <div class="form-group">
                        <label for="sec_q_display_<?= $number ?>">Security Question <?= $number ?> <span class="required">*</span></label>
                        <input type="hidden" name="sec_q<?= $number ?>" value="<?= e($questionCode) ?>">
                        <input type="text" id="sec_q_display_<?= $number ?>" name="sec_q_display_<?= $number ?>" value="<?= e($securityQuestions[$questionCode]) ?>" readonly>

                        <label for="sec_a<?= $number ?>" style="margin-top: 8px;">Your Answer <span class="required">*</span></label>
                        <input type="text" id="sec_a<?= $number ?>" name="sec_a<?= $number ?>" maxlength="50" required value="<?= old_value('sec_a' . $number) ?>">
                        <?php field_error($errors, 'sec_a' . $number); ?>
                    </div>

                <?php endforeach; ?>

            </div>
        </section>

        <!-- PRIVACY CONSENT -->
        <div class="privacy-consent" id="privacy-consent">
            <label class="checkbox-field">
                <input
                    type="checkbox"
                    name="privacy_consent"
                    value="1"
                    required
                    <?= !empty($old['privacy_consent']) ? 'checked' : '' ?>
                >

                <span>
                    I have read and agree to the collection and processing
                    of my personal data in accordance with the
                    <a href="privacy.php" target="_blank" class="privacy-link">
                        Privacy Notice
                    </a>
                    and the Data Privacy Act of 2012 (RA 10173).
                </span>
            </label>

            <?php field_error($errors, 'privacy_consent'); ?>
        </div>

        <!-- FORM BUTTONS -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Register Account</button>
            <a href="login.php" class="btn btn-secondary">Already have an account? Log in</a>
        </div>

    </form>

</div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>