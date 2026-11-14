<?php

require_once __DIR__ . '/../config/database.php';

$allPassed = true;

function schemaCheck($description, $passed) {
    global $allPassed;

    if ($passed) {
        echo "[PASS] $description\n";
    } else {
        echo "[FAIL] $description\n";
        $allPassed = false;
    }
}

function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function uniqueIndexExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
        AND NON_UNIQUE = 0
    ");

    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function foreignKeyExists($pdo, $table, $column, $referencedTable) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
        AND REFERENCED_TABLE_NAME = ?
    ");

    $stmt->execute([$table, $column, $referencedTable]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "==================================================\n";
echo " BAUG DATABASE SCHEMA AND SECURITY VERIFICATION\n";
echo "==================================================\n\n";

echo "1. REQUIRED DATABASE TABLES\n";
echo "--------------------------------------------\n";

$requiredTables = [
    'users',
    'savings',
    'loans',
    'transactions',
    'otps',
    'otp_resend_limits',
    'password_history',
    'password_recovery_attempts',
    'activity_log',
    'ip_blocks',
    'security_flags'
];

$stmt = $pdo->query("SHOW TABLES");
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($requiredTables as $table) {
    schemaCheck(
        "Table '$table' exists",
        in_array($table, $existingTables, true)
    );
}

echo "\n2. USER TABLE REQUIRED COLUMNS\n";
echo "--------------------------------------------\n";

$userColumns = [
    'id',
    'full_name',
    'id_number',
    'date_of_birth',
    'email',
    'username',
    'password',
    'role',
    'account_status',
    'sec_q1',
    'sec_a1',
    'sec_q2',
    'sec_a2',
    'sec_q3',
    'sec_a3',
    'privacy_consent',
    'privacy_consent_at',
    'email_verified',
    'failed_attempts',
    'locked_until',
    'lock_tier',
    'last_login_at'
];

foreach ($userColumns as $column) {
    schemaCheck(
        "users.$column exists",
        columnExists($pdo, 'users', $column)
    );
}

echo "\n3. SECURITY AND ACCOUNT CONSTRAINTS\n";
echo "--------------------------------------------\n";

schemaCheck(
    "Email has a UNIQUE index",
    uniqueIndexExists($pdo, 'users', 'email')
);

schemaCheck(
    "Username has a UNIQUE index",
    uniqueIndexExists($pdo, 'users', 'username')
);

schemaCheck(
    "Member ID has a UNIQUE index",
    uniqueIndexExists($pdo, 'users', 'id_number')
);

schemaCheck(
    "IP address has a UNIQUE index in ip_blocks",
    uniqueIndexExists($pdo, 'ip_blocks', 'ip_address')
);

echo "\n4. REQUIRED FOREIGN KEYS\n";
echo "--------------------------------------------\n";

$foreignKeys = [
    ['loans', 'user_id', 'users'],
    ['savings', 'user_id', 'users'],
    ['transactions', 'user_id', 'users'],
    ['otps', 'user_id', 'users'],
    ['password_history', 'user_id', 'users'],
    ['security_flags', 'user_id', 'users']
];

foreach ($foreignKeys as $fk) {
    schemaCheck(
        "{$fk[0]}.{$fk[1]} references {$fk[2]}.id",
        foreignKeyExists($pdo, $fk[0], $fk[1], $fk[2])
    );
}

echo "\n5. SECURITY-RELATED COLUMNS\n";
echo "--------------------------------------------\n";

$securityColumns = [
    ['users', 'email_verified'],
    ['users', 'failed_attempts'],
    ['users', 'locked_until'],
    ['users', 'lock_tier'],
    ['users', 'privacy_consent'],
    ['users', 'privacy_consent_at'],
    ['ip_blocks', 'failed_attempts'],
    ['ip_blocks', 'window_started_at'],
    ['ip_blocks', 'blocked_until'],
    ['security_flags', 'flag_type'],
    ['security_flags', 'severity'],
    ['security_flags', 'is_reviewed'],
    ['activity_log', 'event_type'],
    ['activity_log', 'ip_address'],
    ['activity_log', 'created_at']
];

foreach ($securityColumns as $item) {
    schemaCheck(
        "{$item[0]}.{$item[1]} exists",
        columnExists($pdo, $item[0], $item[1])
    );
}

echo "\n==================================================\n";

if ($allPassed) {
    echo "RESULT: PASS - ALL SCHEMA CHECKS COMPLETED!\n";
} else {
    echo "RESULT: FAIL - SOME SCHEMA CHECKS NEED ATTENTION.\n";
}

echo "==================================================\n";

exit($allPassed ? 0 : 1);