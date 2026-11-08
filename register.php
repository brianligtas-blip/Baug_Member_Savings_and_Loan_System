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

    // NAME EXTENSION
    $nameExtensionChoice = trim($_POST['name_extension_type'] ?? '');
    $nameExtensionOther = $_POST['name_extension_other'] ?? '';
    $nameExtension = '';

    if ($nameExtensionChoice === 'Jr.' || $nameExtensionChoice === 'Sr.') {
        $nameExtension = $nameExtensionChoice;
    } elseif ($nameExtensionChoice === 'Other') {
        $nameExtension = $nameExtensionOther;
    }

    $idNumber = trim($_POST['id_number'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    // ADDRESS
    $purokStreet = trim($_POST['purok_street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    // ACCOUNT
    $emailInput = $_POST['email'] ?? '';
    $email = strtolower(trim($emailInput));
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

    // NAME VALIDATION

    $namePattern = '/^\p{Lu}[\p{Ll}\p{M}]*(?: \p{Lu}[\p{Ll}\p{M}]*)*$/u';

    $repeatedLetterPattern = '/(.)\1{2}/iu';

    $maxNameLength = (int)($settings['max_name'] ?? 25);

    $nameFields = [
        'first_name' => [
            'value' => $firstName,
            'label' => 'First Name',
            'required' => true
        ],
        'middle_name' => [
            'value' => $middleName,
            'label' => 'Middle Name',
            'required' => false
        ],
        'family_name' => [
            'value' => $familyName,
            'label' => 'Family Name',
            'required' => true
        ]
    ];

    foreach ($nameFields as $field => $data) {

        $value = trim($data['value']);
        $label = $data['label'];

        if ($data['required'] && $value === '') {
            $errors[$field] = "{$label} is required.";
            continue;
        }

        if ($value === '') {
            continue;
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($length < 2) {

            $errors[$field] = "{$label} must contain at least 2 characters.";

        } elseif ($length > $maxNameLength) {

            $errors[$field] = "{$label} must not exceed {$maxNameLength} characters.";

        } elseif (preg_match('/\p{N}/u', $value)) {

            $errors[$field] = "Numbers are not allowed in {$label}.";

        } elseif (strpos($value, '  ') !== false) {

            $errors[$field] = "Double spaces are not allowed in {$label}.";

        } elseif (preg_match('/[^\p{L}\p{M} ]/u', $value)) {

            $errors[$field] = "Special characters are not allowed in {$label}.";

        } elseif (preg_match($repeatedLetterPattern, $value)) {

            $errors[$field] = "Three consecutive identical letters are not allowed in {$label}.";

        } elseif (mb_strtoupper($value, 'UTF-8') === $value) {

            $errors[$field] = "{$label} must not contain all capital letters.";

        } elseif (preg_match('/^\p{Ll}/u', $value)) {

            $errors[$field] = "The first letter of {$label} must be capitalized.";

        } elseif (!preg_match($namePattern, $value)) {

            $errors[$field] = "Each word in {$label} must start with a capital letter.";
        }
    }

    // NAME EXTENSION VALIDATION

    if ($nameExtensionChoice === '') {

        // No extension selected.
        $nameExtension = '';

    } elseif ($nameExtensionChoice === 'Jr.' || $nameExtensionChoice === 'Sr.') {

        $nameExtension = $nameExtensionChoice;

    } elseif ($nameExtensionChoice === 'Other') {

        $allowedOtherExtensions = ['I', 'II', 'III', 'IV', 'IX', 'X'];

        if (!in_array($nameExtensionOther, $allowedOtherExtensions, true)) {

            $errors['name_extension'] = 'For Other, enter exactly one of: I, II, III, IV, IX, or X.';

        } else {

            // Preserve the exact accepted value.
            $nameExtension = $nameExtensionOther;
        }

    } else {

        $errors['name_extension'] = 'Select Jr., Sr., or Other.';
    }

    // MEMBER ID VALIDATION

    if ($idNumber === '') {

        $errors['id_number'] = 'Member ID is required.';

    } elseif (!preg_match('/^\d{4}-\d{4}$/', $idNumber)) {

        $errors['id_number'] = 'Use the format xxxx-xxxx.';
    }

    // DATE OF BIRTH VALIDATION

    $age = null;

    if ($dateOfBirth === '') {

        $errors['date_of_birth'] = 'Date of birth is required.';

    } else {

        $dob = DateTime::createFromFormat('!Y-m-d', $dateOfBirth);
        $dateErrors = DateTime::getLastErrors();

    // Reject invalid dates, including normalized dates.
    if (
        !$dob ||
        ($dateErrors !== false &&
        ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
        $dob->format('Y-m-d') !== $dateOfBirth
    ) {

        $errors['date_of_birth'] = 'Enter a valid birthdate.';

    } elseif ($dob > new DateTime('today')) {

        $errors['date_of_birth'] = 'Birthdate cannot be in the future.';

    } else {

        // Always calculate age on the server.
        $age = $dob->diff(new DateTime('today'))->y;

        if ($age < 18) {

            $errors['date_of_birth'] = 'You must be at least 18 years old to register.';

        } else {

            $dateOfBirth = $dob->format('Y-m-d');
        }
    }
}

    // ADDRESS VALIDATION

    // PUROK / STREET
    if ($purokStreet === '') {

        $errors['purok_street'] = 'Purok / Street is required.';

    } elseif (
        mb_strlen($purokStreet, 'UTF-8') < 3 ||
        mb_strlen($purokStreet, 'UTF-8') > 50
    ) {

        $errors['purok_street'] = 'Purok / Street must contain 3–50 characters.';

    } elseif (!preg_match('/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u', $purokStreet)) {

        $errors['purok_street'] = 'Use letters, numbers, and single spaces only. Double spaces are not allowed.';   
    }


    // TITLE CASE VALIDATION
    $addressNamePattern = '/^\p{Lu}[\p{Ll}\p{M}]*(?: \p{Lu}[\p{Ll}\p{M}]*)*$/u';

    $addressFields = [
        'barangay' => [
            'value' => $barangay,
            'label' => 'Barangay'
        ],
        'city' => [
            'value' => $city,
            'label' => 'Municipality / City'
        ],
        'province' => [
            'value' => $province,
            'label' => 'Province'
        ],
        'country' => [
            'value' => $country,
            'label' => 'Country'
        ]
    ];

    foreach ($addressFields as $field => $details) {

        $value = $details['value'];
        $label = $details['label'];

        if ($value === '') {

            $errors[$field] = $label . ' is required.';

        } elseif (
            mb_strlen($value, 'UTF-8') < 2 ||
            mb_strlen($value, 'UTF-8') > 50
        ) {

            $errors[$field] = $label . ' must contain 2–50 characters.';

        } elseif (!preg_match($addressNamePattern, $value)) {

            $errors[$field] = $label . ' must use Title Case and letters with single spaces only.';
        }
    }


    // ZIP CODE
    if ($zipCode === '') {

        $errors['zip_code'] = 'ZIP Code is required.';

    } elseif (!preg_match('/^\d{4}$/', $zipCode)) {

        $errors['zip_code'] = 'ZIP Code must contain exactly 4 digits.';
    }

    
    // EMAIL VALIDATION

    $emailInput = (string)($_POST['email'] ?? '');
    $email = strtolower(trim($emailInput));

    $emailLength = mb_strlen($emailInput, 'UTF-8');

    if (trim($emailInput) === '') {

        $errors['email'] = 'Email is required.';

    } elseif (preg_match('/\s/', $emailInput)) {

        $errors['email'] = 'Email must not contain spaces.';

    } elseif ($emailLength > 100) {

        $errors['email'] = 'Email must not exceed 100 characters.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors['email'] = 'Enter a valid email address.';
    }
    error_log('EMAIL DEBUG: ' . json_encode([
    'received_length' => $emailLength,
    'received_email' => $emailInput
]));


    // USERNAME VALIDATION

    if ($username === '') {

        $errors['username'] = 'Username is required.';

    } elseif (!validate_username_length($username)) {

        $errors['username'] = 'Username must contain 6-16 characters.';

    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $username)) {

        $errors['username'] = 'Username must start with a letter and contain only letters and numbers.';
    }

    // PASSWORD VALIDATION

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

    // MEMBER INFORMATION VALIDATION

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

    // MONTHLY SAVINGS TARGET
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

    
    // SECURITY QUESTIONS VALIDATION

    if ($questions !== $requiredQuestions) {

        $errors['security_questions'] = 'Your assigned questions must be Q6, Q1, and Q3 in that order.';
    }

    // Allow letters and single spaces only.
    $answerPattern = '/^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u';

    foreach ($answers as $index => $answer) {

        $field = 'sec_a' . ($index + 1);

        $length = mb_strlen($answer, 'UTF-8');

        if ($answer === '') {

            $errors[$field] = 'Please provide your answer.';

        } elseif ($length < 2 || $length > 50) {

            $errors[$field] = 'Answer must contain 2-50 characters.';

        } elseif (!preg_match($answerPattern, $answer)) {

            $errors[$field] = 'Answer must contain letters and single spaces only.';
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

            
            // CHECK DUPLICATE EMAIL
            $emailCheck = $pdo->prepare("
                SELECT id
                FROM users
                WHERE LOWER(email) = LOWER(?)
                LIMIT 1
            ");

            $emailCheck->execute([$email]);

            if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {

                $errors['email'] = 'This email is already registered.';
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

                // CREATE FULL NAME
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
                        purok_street,
                        barangay,
                        city,
                        province,
                        country,
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
                        :purok_street,
                        :barangay,
                        :city,
                        :province,
                        :country,
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

                    ':purok_street' => $purokStreet,
                    ':barangay' => $barangay,
                    ':city' => $city,
                    ':province' => $province,
                    ':country' => $country,
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

                $newUserId = (int) $pdo->lastInsertId();

                // GENERATE SIX-DIGIT REGISTRATION OTP
                $otp = (string) random_int(100000, 999999);

                // HASH OTP BEFORE SAVING
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);

                // OTP EXPIRES AFTER FIVE MINUTES
                $otpExpiresAt = date(
                    'Y-m-d H:i:s',
                    time() + (5 * 60)
                );

                // REMOVE PREVIOUS UNUSED REGISTRATION OTP
                $deleteOtp = $pdo->prepare("
                    DELETE FROM otps
                    WHERE user_id = ?
                    AND purpose = 'register'
                    AND used_at IS NULL
                ");

                $deleteOtp->execute([$newUserId]);

                // SAVE OTP HASH
                $otpStmt = $pdo->prepare("
                    INSERT INTO otps (
                        user_id,
                        purpose,
                        code_hash,
                        expires_at,
                        attempts
                    ) VALUES (
                        ?,
                        'register',
                        ?,
                        ?,
                        0
                    )
                ");

                $otpStmt->execute([
                    $newUserId,
                    $otpHash,
                    $otpExpiresAt
                ]);

                // SEND OTP THROUGH EXISTING PHPMailer FUNCTION
                $emailMessage = "
                    <h2>BAUG Email Verification</h2>
                    <p>Hello " . e($fullName) . ",</p>
                    <p>Your registration verification code is:</p>
                    <h1 style='letter-spacing: 6px;'>$otp</h1>
                    <p>This code expires in 5 minutes.</p>
                    <p>Do not share this code with anyone.</p>
                    <p>BAUG Member Savings and Loan System</p>
                ";

                $emailSent = send_email(
                    $email,
                    $fullName,
                    'BAUG Registration Verification Code',
                    $emailMessage
                );

                $_SESSION['pending_registration_user_id'] = $newUserId;

                if ($emailSent) {

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: verify_otp.php');
                    exit;

                } else {

                    error_log('BAUG: Registration OTP email failed for user ID ' . $newUserId);

                    $success = 'Your account was registered, but the OTP email could not be sent. Please contact the administrator.';

                    $old = [];

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrfToken = $_SESSION['csrf_token'];
                }

                } else {

                    $errors['general'] = 'Account insertion was not successful.';
                }
            }

        } catch (PDOException $e) {

            error_log('BAUG Registration Error: ' . $e->getMessage());

            $errors['general'] = 'Registration failed. Please try again later or contact the administrator.';
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

    <form method="POST" action="register.php" class="registration-form" novalidate>

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
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        maxlength="25"
                        required
                        value="<?= old_value('first_name') ?>"
                    >
                    <?php field_error($errors, 'first_name'); ?>
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name <span class="optional">optional</span></label>
                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        maxlength="25"
                        value="<?= old_value('middle_name') ?>"
                    >
                    <?php field_error($errors, 'middle_name'); ?>
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="family_name"
                        name="family_name"
                        maxlength="25"
                        required
                        value="<?= old_value('family_name') ?>"
                    >
                    <?php field_error($errors, 'family_name'); ?>
                </div>

                <div class="form-group">
                    <label for="name_extension_type">Name Extension <span class="optional">optional</span></label>

                    <select id="name_extension_type" name="name_extension_type">
                        <option value="" <?= (($old['name_extension_type'] ?? '') === '') ? 'selected' : '' ?>>
                            No extension
                        </option>

                        <option value="Jr." <?= (($old['name_extension_type'] ?? '') === 'Jr.') ? 'selected' : '' ?>>
                            Jr.
                        </option>

                        <option value="Sr." <?= (($old['name_extension_type'] ?? '') === 'Sr.') ? 'selected' : '' ?>>
                            Sr.
                        </option>

                        <option value="Other" <?= (($old['name_extension_type'] ?? '') === 'Other') ? 'selected' : '' ?>>
                            Other
                        </option>
                    </select>

                    <div
                        id="other-extension-group"
                        style="display: <?= (($old['name_extension_type'] ?? '') === 'Other') ? 'block' : 'none' ?>; margin-top: 8px;"
                    >
                        <label for="name_extension_other">Other Extension</label>

                        <input
                            type="text"
                            id="name_extension_other"
                            name="name_extension_other"
                            placeholder="I, II, III, IV, IX, or X"
                            maxlength="3"
                            value="<?= e($old['name_extension_other'] ?? '') ?>"
                        >
                    </div>

                    <?php field_error($errors, 'name_extension'); ?>
                </div>

                <div class="form-group">
                    <label for="id_number">Member ID Number <span class="required">*</span></label>
                    <input
                        type="text"
                        id="id_number"
                        name="id_number"
                        placeholder="xxxx-xxxx"
                        required
                        value="<?= old_value('id_number') ?>"
                    >
                    <?php field_error($errors, 'id_number'); ?>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                    <input
                        type="date"
                        id="date_of_birth"
                        name="date_of_birth"
                        required
                        value="<?= old_value('date_of_birth') ?>"
                    >
                    <?php field_error($errors, 'date_of_birth'); ?>
                </div>

                <div class="form-group">
                    <label for="age">Age</label>
                     <input
                        type="number"
                        id="age"
                        name="age_display"
                        readonly
                        placeholder="Automatically calculated"
                    >
                    <small>Age is automatically calculated from your birthdate.</small>
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

                <!-- PUROK / STREET -->
                <div class="form-group">
                    <label for="purok_street">
                        Purok / Street <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="purok_street"
                        name="purok_street"
                        minlength="3"
                        maxlength="50"
                        required
                        placeholder="Enter your purok / street"
                        value="<?= old_value('purok_street') ?>"
                    >

                    <?php field_error($errors, 'purok_street'); ?>
                </div>


                <!-- BARANGAY -->
                <div class="form-group">
                    <label for="barangay">
                        Barangay <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="barangay"
                        name="barangay"
                        minlength="2"
                        maxlength="50"
                        required
                        placeholder="Enter your barangay"
                        value="<?= old_value('barangay') ?>"
                    >

                    <?php field_error($errors, 'barangay'); ?>
                </div>


                <!-- MUNICIPALITY / CITY -->
                <div class="form-group">
                    <label for="city">
                        Municipality / City <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="city"
                        name="city"
                        minlength="2"
                        maxlength="50"
                        required
                        placeholder="Enter your municipality / city"
                        value="<?= old_value('city') ?>"
                    >

                    <?php field_error($errors, 'city'); ?>
                </div>


                <!-- PROVINCE -->
                <div class="form-group">
                    <label for="province">
                        Province <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="province"
                        name="province"
                        minlength="2"
                        maxlength="50"
                        required
                        placeholder="Enter your province"
                        value="<?= old_value('province') ?>"
                    >

                    <?php field_error($errors, 'province'); ?>
                </div>


                <!-- COUNTRY -->
                <div class="form-group">
                    <label for="country">
                        Country <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="country"
                        name="country"
                        minlength="2"
                        maxlength="50"
                        required
                        placeholder="Enter your country"
                        value="<?= old_value('country') ?>"
                    >

                    <?php field_error($errors, 'country'); ?>
                </div>


                <!-- ZIP CODE -->
                <div class="form-group">
                    <label for="zip_code">
                        ZIP Code <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="zip_code"
                        name="zip_code"
                        minlength="4"
                        maxlength="4"
                        inputmode="numeric"
                        required
                        placeholder="e.g. 8600"
                        value="<?= old_value('zip_code') ?>"
                    >

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
                    <label for="email">
                        Email Address <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="email"
                        name="email"
                        autocomplete="email"
                        placeholder="example@gmail.com"
                        value="<?= old_value('email') ?>"
                    >

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

                        <label for="sec_q_display_<?= $number ?>">
                            Security Question <?= $number ?> <span class="required">*</span>
                        </label>

                        <input type="hidden" name="sec_q<?= $number ?>" value="<?= e($questionCode) ?>">

                        <input
                            type="text"
                            id="sec_q_display_<?= $number ?>"
                            name="sec_q_display_<?= $number ?>"
                            value="<?= e($securityQuestions[$questionCode]) ?>"
                            readonly
                        >

                        <label for="sec_a<?= $number ?>" style="margin-top: 8px;">
                            Your Answer <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="sec_a<?= $number ?>"
                            name="sec_a<?= $number ?>"
                            maxlength="50"
                            required
                            value="<?= old_value('sec_a' . $number) ?>"
                        >

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

<script>
document.addEventListener('DOMContentLoaded', function () {

    // AUTOMATIC AGE CALCULATION

    const birthdateInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');

    function calculateAge() {

        const birthdateValue = birthdateInput.value;

        if (!birthdateValue) {
            ageInput.value = '';
            return;
        }

        const birthdate = new Date(birthdateValue + 'T00:00:00');
        const today = new Date();

        let calculatedAge =
            today.getFullYear() - birthdate.getFullYear();

        const birthdayNotReached =
            today.getMonth() < birthdate.getMonth() ||
            (
                today.getMonth() === birthdate.getMonth() &&
                today.getDate() < birthdate.getDate()
            );

        if (birthdayNotReached) {
            calculatedAge--;
        }

        ageInput.value = calculatedAge >= 0 ? calculatedAge : '';
    }

    birthdateInput.addEventListener('change', calculateAge);
    birthdateInput.addEventListener('input', calculateAge);

    // Calculate age when the page loads, including after validation errors.
    calculateAge();

    const nameMaxLength = <?= (int)($settings['max_name'] ?? 25) ?>;

    const nameInputs = [
        document.getElementById('first_name'),
        document.getElementById('middle_name'),
        document.getElementById('family_name')
    ].filter(Boolean);

    // Title Case, letters only, and single spaces.
    const namePattern = /^\p{Lu}[\p{Ll}\p{M}]*(?: \p{Lu}[\p{Ll}\p{M}]*)*$/u;

    // Three identical consecutive letters are prohibited.
    const repeatedLetterPattern = /(.)\1{2}/iu;

    function validateName(input) {

        const value = input.value;

        input.setCustomValidity('');

        // Middle Name is optional.
        if (value === '' && input.id === 'middle_name') {
            return;
        }

        if (value.length < 2) {

            input.setCustomValidity('Name must contain at least 2 characters.');

        } else if ([...value].length > nameMaxLength) {

            input.setCustomValidity(
                'Name must not exceed ' + nameMaxLength + ' characters.'
            );

        } else if (!namePattern.test(value)) {

            input.setCustomValidity(
                'Use Title Case, letters only, and single spaces between words.'
            );

        } else if (repeatedLetterPattern.test(value)) {

            input.setCustomValidity(
                'Three identical letters in a row are not allowed.'
            );
        }
    }

    // NAME EXTENSION — OTHER FIELD

    const extensionSelect = document.getElementById('name_extension_type');
    const otherGroup = document.getElementById('other-extension-group');
    const otherInput = document.getElementById('name_extension_other');

    const allowedExtensions = ['I', 'II', 'III', 'IV', 'IX', 'X'];

    function updateExtensionField() {

        const showOther = extensionSelect.value === 'Other';

        otherGroup.style.display = showOther ? 'block' : 'none';

        otherInput.required = showOther;

        if (!showOther) {
            otherInput.setCustomValidity('');
        }
    }

    function validateOtherExtension() {

        otherInput.setCustomValidity('');

        if (
            extensionSelect.value === 'Other' &&
            !allowedExtensions.includes(otherInput.value)
        ) {

            otherInput.setCustomValidity(
                'Enter exactly one of: I, II, III, IV, IX, or X.'
            );
        }
    }

    extensionSelect.addEventListener('change', updateExtensionField);

    otherInput.addEventListener('input', validateOtherExtension);

    // FORM SUBMISSION VALIDATION

    const form = document.querySelector('.registration-form');

    form.addEventListener('submit', function (event) {


        validateOtherExtension();

        if (!form.checkValidity()) {

            event.preventDefault();

            form.reportValidity();
        }
    });

    updateExtensionField();

});
</script>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>