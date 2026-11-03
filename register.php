<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

/* ==========================================
   SECURITY SETTINGS
========================================== */

$settings = security_settings();

/*
 * Replace these placeholder labels with the
 * exact eight questions from your handout.
 */
$securityQuestions = [
    'Q1' => 'Question 1 from the handout',
    'Q2' => 'Question 2 from the handout',
    'Q3' => 'Question 3 from the handout',
    'Q4' => 'Question 4 from the handout',
    'Q5' => 'Question 5 from the handout',
    'Q6' => 'Question 6 from the handout',
    'Q7' => 'Question 7 from the handout',
    'Q8' => 'Question 8 from the handout'
];

/* Seed-assigned questions */
$requiredQuestions = ['Q6', 'Q1', 'Q3'];

$errors = [];
$success = '';

/* ==========================================
   CSRF TOKEN
========================================== */

$csrfToken = csrf_token();

/* ==========================================
   OLD FORM VALUES
========================================== */

$old = $_POST;

/* ==========================================
   FORM SUBMISSION
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    /* PERSONAL INFORMATION */

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $familyName = trim($_POST['family_name'] ?? '');
    $nameExtension = trim($_POST['name_extension'] ?? '');

    $idNumber = trim($_POST['id_number'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    /* ADDRESS */

    $houseNo = trim($_POST['house_no'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    /* ACCOUNT INFORMATION */

    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    /* BAUG MEMBER INFORMATION */

    $memberCategory = $_POST['member_category'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $monthlySavings = trim($_POST['monthly_savings_target'] ?? '');

    /* SECURITY QUESTIONS */

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
       PERSONAL INFORMATION VALIDATION
    ====================================== */

    $namePattern = "/^[\p{L}\p{M} .'-]+$/u";

    $nameFields = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'family_name' => $familyName
    ];

    foreach ($nameFields as $field => $value) {

        if ($field === 'first_name' && $value === '') {
            $errors[$field] = 'First name is required.';
            continue;
        }

        if ($field === 'family_name' && $value === '') {
            $errors[$field] = 'Family name is required.';
            continue;
        }

        if ($value !== '' && !preg_match($namePattern, $value)) {
            $errors[$field] = 'Please enter a valid name.';
            continue;
        }

        if (mb_strlen($value, 'UTF-8') > $settings['max_name']) {
            $errors[$field] = 'Each name field must not exceed 25 characters.';
        }
    }

    if (
        $nameExtension !== '' &&
        !preg_match("/^[a-zA-Z0-9 .'-]{1,20}$/", $nameExtension)
    ) {
        $errors['name_extension'] = 'Invalid name extension.';
    }

    /* MEMBER ID */

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
            ($dateErrors['warning_count'] > 0 ||
             $dateErrors['error_count'] > 0)) ||
        $dob->format('Y-m-d') !== $dateOfBirth
    ) {
        $errors['date_of_birth'] = 'Please enter a valid birthdate.';
    } elseif ($dob > new DateTime('today')) {
        $errors['date_of_birth'] = 'Birthdate cannot be in the future.';
    } else {

        $age = $dob->diff(new DateTime('today'))->y;

        if ($age < 18) {
            $errors['date_of_birth'] = 'You must be at least 18 years old to register.';
        }
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

    if ($zipCode !== '' && !preg_match('/^\d{4}$/', $zipCode)) {
        $errors['zip_code'] = 'ZIP code must contain exactly 4 digits.';
    }

    /* ======================================
       ACCOUNT VALIDATION
    ====================================== */

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    /* USERNAME: 6-16 CHARACTERS */

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!validate_username_length($username)) {
        $errors['username'] = 'Username must contain 6-16 characters.';
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $username)) {
        $errors['username'] =
            'Username must start with a letter and contain only letters and numbers.';
    }

    /* PASSWORD: 9-64 CHARACTERS */

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (!validate_password_length($password)) {
        $errors['password'] = 'Password must contain 9-64 characters.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please re-enter your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    /* ======================================
       BAUG MEMBER VALIDATION
    ====================================== */

    $allowedCategories = [
        'Regular',
        'Associate',
        'Youth'
    ];

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

    if ($questions !== $requiredQuestions) {
        $errors['security_questions'] =
            'Your assigned security questions must be Q6, Q1, and Q3 in that order.';
    }

    foreach ($answers as $index => $answer) {

        $length = mb_strlen($answer, 'UTF-8');

        if ($answer === '') {
            $errors['sec_a' . ($index + 1)] =
                'Please provide your answer.';
        } elseif ($length < 2 || $length > 50) {
            $errors['sec_a' . ($index + 1)] =
                'Your answer must contain 2-50 characters.';
        } elseif (!preg_match("/^[\p{L}\p{M} ]+$/u", $answer)) {
            $errors['sec_a' . ($index + 1)] =
                'Your answer must contain only letters and spaces.';
        }
    }

    /* ======================================
       PRIVACY CONSENT
    ====================================== */

    $privacyConsent =
        isset($_POST['privacy_consent']) &&
        $_POST['privacy_consent'] === '1';

    if (!$privacyConsent) {
        $errors['privacy_consent'] =
            'You must agree to the Privacy Notice to register.';
    }

    /* ======================================
       DATABASE INSERTION
    ====================================== */

    if (empty($errors)) {

        try {

            /* CHECK DUPLICATE RECORDS */

            $checkStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id_number = ?
                   OR email = ?
                   OR username = ?
                LIMIT 1
            ");

            $checkStmt->execute([
                $idNumber,
                $email,
                $username
            ]);

            if ($checkStmt->fetch()) {

                $errors['general'] =
                    'The Member ID, email, or username is already registered.';

            } else {

                /* COMBINE FULL NAME */

                $nameParts = array_filter([
                    $firstName,
                    $middleName,
                    $familyName,
                    $nameExtension
                ], fn($part) => $part !== '');

                $fullName = implode(' ', $nameParts);

                /* HASH PASSWORD */

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /* HASH SECURITY ANSWERS */

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

                /* INSERT NEW MEMBER */

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
                        account_status
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
                        'pending'
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
                    ':sec_a3' => $hashedAnswers[2]
                ]);

                $success =
                    'Your BAUG account has been registered successfully. Your account is pending approval and email verification.';

                $old = [];

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {

            error_log(
                'BAUG Registration Error: ' . $e->getMessage()
            );

            $errors['general'] =
                'Registration failed. Please check your information and try again.';
        }
    }
}

/* ==========================================
   PAGE DISPLAY
========================================== */

$pageTitle = 'Member Registration';

require __DIR__ . '/includes/header.php';

?>