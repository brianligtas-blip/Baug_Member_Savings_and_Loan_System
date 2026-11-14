<?php

require_once __DIR__ . '/../includes/functions.php';

// BAUG SEED VERIFICATION

// Assigned seed digits
$D1 = 6;
$D2 = 5;

// Get actual project security settings
$actual = security_settings();

// Calculate expected values using the handout formulas
$expected = [

    'min_username' => 6 + ($D1 % 3),
    'max_username' => 16 + ($D2 % 5),

    'min_password' => 8 + ($D2 % 4),

    'max_name' => 20 + $D2,

    'forgot_after' => 2 + ($D1 % 2),

    'lock_tier_1' => 15 + $D1,
    'lock_tier_2' => 30 + (2 * $D1),
    'lock_tier_3' => 60 + (4 * $D1),

    'otp_minutes' => 3 + ($D2 % 3),

    'idle_minutes' => 5 + ($D1 % 5),

    'ip_threshold' => 8 + $D2
];

// Display project information
echo "BAUG MEMBER SAVINGS AND LOAN SYSTEM SEED CHECK | D1 = $D1 | D2 = $D2\n\n";

echo "----------------------------------------------------------\n";

$allPassed = true;

foreach ($expected as $key => $expectedValue) {

    $actualValue = $actual[$key] ?? null;

    $passed = (
        $actualValue !== null &&
        (int) $actualValue === (int) $expectedValue
    );

    if (!$passed) {
        $allPassed = false;
    }

    $status = $passed ? "[PASS]" : "[FAIL]";

    printf(
        "%-20s Expected: %-5s Actual: %-5s %s\n",
        $key,
        $expectedValue,
        $actualValue === null ? "MISSING" : $actualValue,
        $status
    );
}

// Display calculated authentication questions
$question6 = 'Q' . (($D2 % 8) + 1);
$question1 = 'Q' . ((($D2 + 3) % 8) + 1);
$question3 = 'Q' . ((($D2 + 5) % 8) + 1);

echo "\nMy three authentication squestions:\n";
echo "Question 6: What was the first song you learned by heart?\n";
echo "Question 1: What was the name of your first pet?\n";
echo "Question 3: Who was your favorite teacher in high school?\n";


if ($allPassed) {
    echo "RESULT: ALL VALUES MATCH THE SEED TABLE!\n";
} else {
    echo "RESULT: FAIL - PLEASE CHECK YOUR SETTINGS.\n";
}
