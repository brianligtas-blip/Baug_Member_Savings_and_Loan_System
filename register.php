
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
require_once 'config/database.php';

/* ==========================================
   SECURITY QUESTIONS
========================================== */

$securityQuestions = [
    'mother_maiden' => "What is your mother's maiden name?",
    'first_school' => "What was the name of your first school?",
    'birth_city' => "In what city were you born?",
    'first_pet' => "What was the name of your first pet?",
    'favorite_teacher' => "Who was your favorite teacher?"
];

$errors = [];
$success = '';

/* CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* OLD FORM VALUES */
$old = $_POST;

/* ==========================================
   FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Personal information
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $familyName = trim($_POST['family_name'] ?? '');
    $nameExtension = trim($_POST['name_extension'] ?? '');

    $idNumber = trim($_POST['id_number'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    // Address
    $houseNo = trim($_POST['house_no'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    // Account information
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // BAUG member information
    $memberCategory = $_POST['member_category'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $monthlySavings = trim($_POST['monthly_savings_target'] ?? '');

    // Security questions
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

    /* ======================================
       CSRF VALIDATION
    ====================================== */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors['general'] = 'Invalid form submission. Please refresh the page.';
    }

    /* ======================================
       PERSONAL INFORMATION VALIDATION
    ====================================== */

    $namePattern = "/^[\p{L}\p{M} .'-]+$/u";

    if ($firstName === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (!preg_match($namePattern, $firstName)) {
        $errors['first_name'] = 'Please enter a valid first name.';
    }

    if ($middleName !== '' && !preg_match($namePattern, $middleName)) {
        $errors['middle_name'] = 'Please enter a valid middle name.';
    }

    if ($familyName === '') {
        $errors['family_name'] = 'Family name is required.';
    } elseif (!preg_match($namePattern, $familyName)) {
        $errors['family_name'] = 'Please enter a valid family name.';
    }

    if (
        $nameExtension !== '' &&
        !preg_match("/^[a-zA-Z0-9 .'-]{1,20}$/", $nameExtension)
    ) {
        $errors['name_extension'] = 'Invalid name extension.';
    }

    /* ID NUMBER */

    if ($idNumber === '') {
        $errors['id_number'] = 'Member ID number is required.';
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $idNumber)) {
        $errors['id_number'] = 'ID number must follow xxxx-xxxx format.';
    }

    /* DATE OF BIRTH AND AGE */

    $age = null;

    $dob = DateTime::createFromFormat('!Y-m-d', $dateOfBirth);
    $dateErrors = DateTime::getLastErrors();

    if (
        !$dob ||
        ($dateErrors !== false &&
            ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
        $dob->format('Y-m-d') !== $dateOfBirth
    ) {
        $errors['date_of_birth'] = 'Please enter a valid birthdate.';
    } elseif ($dob > new DateTime('today')) {
        $errors['date_of_birth'] = 'Birthdate cannot be in the future.';
    } else {
        $age = $dob->diff(new DateTime('today'))->y;
    }

    /* ======================================
       ADDRESS VALIDATION
    ====================================== */

    $addressFields = [
        'house_no' => [$houseNo, 'House number is required.'],
        'street' => [$street, 'Street is required.'],
        'barangay' => [$barangay, 'Barangay is required.'],
        'city' => [$city, 'Municipality/City is required.'],
        'province' => [$province, 'Province is required.'],
        'zip_code' => [$zipCode, 'ZIP code is required.']
    ];

    foreach ($addressFields as $field => $data) {
        if ($data[0] === '') {
            $errors[$field] = $data[1];
        }
    }

    if ($zipCode !== '' && !preg_match('/^[A-Za-z0-9 -]{3,20}$/', $zipCode)) {
        $errors['zip_code'] = 'Please enter a valid ZIP code.';
    }

    /* ======================================
       ACCOUNT VALIDATION
    ====================================== */

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Username must be 3-30 characters and contain only letters, numbers, and underscores.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must contain at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please re-enter your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    /* ======================================
       BAUG MEMBER VALIDATION
    ====================================== */

    $allowedCategories = ['Regular', 'Associate', 'Youth'];

    if (!in_array($memberCategory, $allowedCategories, true)) {
        $errors['member_category'] = 'Please select a valid member category.';
    }

    if ($occupation === '') {
        $errors['occupation'] = 'Occupation is required.';
    }

    if ($monthlySavings === '') {
        $errors['monthly_savings_target'] = 'Monthly savings target is required.';
    } elseif (
        !is_numeric($monthlySavings) ||
        !is_finite((float)$monthlySavings) ||
        (float)$monthlySavings < 0 ||
        (float)$monthlySavings > 9999999999.99
    ) {
        $errors['monthly_savings_target'] = 'Please enter a valid savings target.';
    }

    /* ======================================
       SECURITY QUESTIONS VALIDATION
    ====================================== */

    if (
        count(array_unique($questions)) !== 3 ||
        count(array_filter(
            $questions,
            fn($q) => array_key_exists($q, $securityQuestions)
        )) !== 3
    ) {
        $errors['security_questions'] = 'Please select three different valid security questions.';
    }

    foreach ($answers as $index => $answer) {
        if ($answer === '') {
            $errors['sec_a' . ($index + 1)] =
                'Please provide your answer to this security question.';
        } elseif (strlen($answer) > 255) {
            $errors['sec_a' . ($index + 1)] =
                'Your answer must not exceed 255 characters.';
        }
    }

    /* ======================================
       PRIVACY CONSENT VALIDATION
    ====================================== */

    $privacyConsent = isset($_POST['privacy_consent'])
        && $_POST['privacy_consent'] === '1';

    if (!$privacyConsent) {
        $errors['privacy_consent'] =
            'You must agree to the privacy notice to register.';
    }

    /* ======================================
       DATABASE INSERTION
    ====================================== */

    if (empty($errors)) {
        try {

            // Check duplicate ID, email, and username.
            $checkStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id_number = :id_number
                   OR email = :email
                   OR username = :username
                LIMIT 1
            ");

            $checkStmt->execute([
                ':id_number' => $idNumber,
                ':email' => $email,
                ':username' => $username
            ]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $errors['general'] =
                    'The Member ID, email, or username is already registered.';
            } else {

                // Combine names for the existing full_name column.
                $nameParts = [
                    $firstName,
                    $middleName,
                    $familyName,
                    $nameExtension
                ];

                $fullName = implode(
                    ' ',
                    array_filter($nameParts, fn($part) => $part !== '')
                );

                // Hash the password.
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Hash the security answers.
                $hashedAnswers = [];

                foreach ($answers as $answer) {
                    $normalizedAnswer = strtolower(trim($answer));

                    $hashedAnswers[] = password_hash(
                        $normalizedAnswer,
                        PASSWORD_DEFAULT
                    );
                }

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
                        privacy_consent_at
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
                        :privacy_consent,
                        NOW()
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
                    ':sec_q1' => $securityQuestions[$questions[0]],
                    ':sec_a1' => $hashedAnswers[0],
                    ':sec_q2' => $securityQuestions[$questions[1]],
                    ':sec_a2' => $hashedAnswers[1],
                    ':sec_q3' => $securityQuestions[$questions[2]],
                    ':sec_a3' => $hashedAnswers[2],
                    ':privacy_consent' => 1
                ]);

                $success =
                    'Your BAUG account has been registered successfully! Your account is pending approval.';

                // Clear submitted values after successful registration.
                $old = [];

                // Generate a new CSRF token.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {

            // Log technical details privately.
            error_log('BAUG Registration Error: ' . $e->getMessage());

            $errors['general'] =
                'Registration failed. Please check your information and try again.';
        }
    }
}

/* ==========================================
   PAGE DISPLAY
========================================== */

$pageTitle = 'Member Registration';

require 'includes/header.php';

?>

<div class="registration-screen">

    <div class="registration-card">

        <div class="section-title">
            <span>👤</span>
            <h1>Member Registration</h1>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="error">
                <?= e($errors['general']) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?= e($success) ?>
                <p>
                    <a href="login.php">Proceed to Login</a>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>

        <form method="POST" action="register.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <!-- PERSONAL INFORMATION -->

            <h2 class="form-section-title">
                Personal Information
            </h2>

            <div class="register-columns">

                <div class="form-column">

                    <div class="field">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name"
                            value="<?= e($old['first_name'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['first_name'])): ?>
                            <span class="field-error"><?= e($errors['first_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Middle Name <span class="optional">(Optional)</span></label>
                        <input type="text" name="middle_name"
                            value="<?= e($old['middle_name'] ?? '') ?>"
                            maxlength="100">
                        <?php if (isset($errors['middle_name'])): ?>
                            <span class="field-error"><?= e($errors['middle_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Family Name <span class="required">*</span></label>
                        <input type="text" name="family_name"
                            value="<?= e($old['family_name'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['family_name'])): ?>
                            <span class="field-error"><?= e($errors['family_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Name Extension <span class="optional">(Optional)</span></label>
                        <input type="text" name="name_extension"
                            placeholder="Jr., Sr., III"
                            value="<?= e($old['name_extension'] ?? '') ?>"
                            maxlength="20">
                        <?php if (isset($errors['name_extension'])): ?>
                            <span class="field-error"><?= e($errors['name_extension']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Member ID Number <span class="required">*</span></label>
                        <input type="text" name="id_number"
                            placeholder="xxxx-xxxx"
                            value="<?= e($old['id_number'] ?? '') ?>"
                            maxlength="9" pattern="[0-9]{4}-[0-9]{4}" required>
                        <?php if (isset($errors['id_number'])): ?>
                            <span class="field-error"><?= e($errors['id_number']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Date of Birth <span class="required">*</span></label>
                        <input type="date" name="date_of_birth"
                            value="<?= e($old['date_of_birth'] ?? '') ?>"
                            required>
                        <?php if (isset($errors['date_of_birth'])): ?>
                            <span class="field-error"><?= e($errors['date_of_birth']) ?></span>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ADDRESS -->

                <div class="form-column">

                    <h3 class="form-section-title">Address Information</h3>

                    <div class="field">
                        <label>House Number <span class="required">*</span></label>
                        <input type="text" name="house_no"
                            value="<?= e($old['house_no'] ?? '') ?>"
                            maxlength="50" required>
                        <?php if (isset($errors['house_no'])): ?>
                            <span class="field-error"><?= e($errors['house_no']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Street <span class="required">*</span></label>
                        <input type="text" name="street"
                            value="<?= e($old['street'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['street'])): ?>
                            <span class="field-error"><?= e($errors['street']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Barangay <span class="required">*</span></label>
                        <input type="text" name="barangay"
                            value="<?= e($old['barangay'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['barangay'])): ?>
                            <span class="field-error"><?= e($errors['barangay']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Municipality / City <span class="required">*</span></label>
                        <input type="text" name="city"
                            value="<?= e($old['city'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['city'])): ?>
                            <span class="field-error"><?= e($errors['city']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" name="province"
                            value="<?= e($old['province'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['province'])): ?>
                            <span class="field-error"><?= e($errors['province']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>ZIP Code <span class="required">*</span></label>
                        <input type="text" name="zip_code"
                            value="<?= e($old['zip_code'] ?? '') ?>"
                            maxlength="20" required>
                        <?php if (isset($errors['zip_code'])): ?>
                            <span class="field-error"><?= e($errors['zip_code']) ?></span>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

            <!-- ACCOUNT INFORMATION -->

            <h2 class="form-section-title">Account Information</h2>

            <div class="register-columns">

                <div class="form-column">

                    <div class="field">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email"
                            value="<?= e($old['email'] ?? '') ?>"
                            maxlength="150" required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="field-error"><?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username"
                            value="<?= e($old['username'] ?? '') ?>"
                            maxlength="30" required>
                        <?php if (isset($errors['username'])): ?>
                            <span class="field-error"><?= e($errors['username']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password"
                            minlength="8" required>
                        <?php if (isset($errors['password'])): ?>
                            <span class="field-error"><?= e($errors['password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Re-enter Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password"
                            minlength="8" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="field-error"><?= e($errors['confirm_password']) ?></span>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- BAUG INFORMATION -->

                <div class="form-column">

                    <div class="field">
                        <label>Member Category <span class="required">*</span></label>
                        <select name="member_category" required>
                            <option value="">Select category</option>
                            <?php foreach (['Regular', 'Associate', 'Youth'] as $category): ?>
                                <option value="<?= e($category) ?>"
                                    <?= ($old['member_category'] ?? '') === $category ? 'selected' : '' ?>>
                                    <?= e($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['member_category'])): ?>
                            <span class="field-error"><?= e($errors['member_category']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Occupation <span class="required">*</span></label>
                        <input type="text" name="occupation"
                            value="<?= e($old['occupation'] ?? '') ?>"
                            maxlength="100" required>
                        <?php if (isset($errors['occupation'])): ?>
                            <span class="field-error"><?= e($errors['occupation']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label>Monthly Savings Target (₱) <span class="required">*</span></label>
                        <input type="number" name="monthly_savings_target"
                            min="0" step="0.01"
                            value="<?= e($old['monthly_savings_target'] ?? '') ?>"
                            required>
                        <?php if (isset($errors['monthly_savings_target'])): ?>
                            <span class="field-error"><?= e($errors['monthly_savings_target']) ?></span>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

            <!-- SECURITY QUESTIONS -->

            <h2 class="form-section-title">Security Questions</h2>

            <p style="font-size:13px; color:#557264; margin-bottom:15px;">
                Select three different questions and provide your answers.
                Remember your answers because they may be needed for account recovery.
            </p>

            <div class="register-columns">

                <?php for ($i = 0; $i < 3; $i++): ?>

                    <div class="form-column">

                        <div class="field">
                            <label>Security Question <?= $i + 1 ?>
                                <span class="required">*</span>
                            </label>

                            <select name="sec_q<?= $i + 1 ?>" required>
                                <option value="">Select a question</option>

                                <?php foreach ($securityQuestions as $key => $question): ?>
                                    <option value="<?= e($key) ?>"
                                        <?= ($old['sec_q' . ($i + 1)] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= e($question) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                            <?php if (isset($errors['security_questions'])): ?>
                                <span class="field-error"><?= e($errors['security_questions']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label>Answer <?= $i + 1 ?>
                                <span class="required">*</span>
                            </label>

                            <input type="text"
                                name="sec_a<?= $i + 1 ?>"
                                maxlength="255"
                                required>

                            <?php if (isset($errors['sec_a' . ($i + 1)])): ?>
                                <span class="field-error"><?= e($errors['sec_a' . ($i + 1)]) ?></span>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endfor; ?>

            </div>

            <!-- PRIVACY CONSENT -->

            <h2 class="form-section-title">Privacy Consent</h2>

            <div class="privacy-consent">

                <label class="checkbox-field">
                    <p>
                        Please read our
                        <a href="privacy.php" target="_blank">Privacy Notice</a>
                        before registering.
                    </p>

                    <label class="checkbox-field">
                        <input type="checkbox"
                        name="privacy_consent"
                            value="1"
                        <?= isset($old['privacy_consent']) ? 'checked' : '' ?>
                        required>

                    <span>I have read the Privacy Notice and agree to the
                        collection and processing of my personal data
                        in accordance with the Data Privacy Act of 2012
                        (RA 10173).
                    <span class="required">*</span>
                </label>

                <?php if (isset($errors['privacy_consent'])): ?>
                    <span class="field-error">
                        <?= e($errors['privacy_consent']) ?>
                    </span>
                <?php endif; ?>

            </div>

            <!-- BUTTONS -->

            <div class="form-actions">

                <button type="submit" class="btn green">
                    Create Account
                </button>

                <a href="login.php" class="btn pale">
                    Cancel
                </a>

            </div>

            <div class="register-login-link">
                Already have an account?
                <a href="login.php">Log-in</a>
            </div>

        </form>

        <?php endif; ?>

    </div>

</div>

<?php require 'includes/footer.php'; ?>