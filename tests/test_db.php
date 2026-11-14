<?php

require_once __DIR__ . '/../config/database.php';

echo " BAUG DATABASE CONNECTION AND TABLE VERIFICATION\n";

$allPassed = true;

// 1. Test database connection
try {
    $pdo->query("SELECT 1");

    echo "[PASS] Database connection successful.\n";

} catch (PDOException $e) {

    echo "[FAIL] Database connection failed.\n";
    exit(1);
}

// 2. Check selected database
try {
    $databaseName = $pdo->query("SELECT DATABASE()")->fetchColumn();

    if ($databaseName === 'baug_member_system') {
        echo "[PASS] Correct database selected: $databaseName\n";
    } else {
        echo "[FAIL] Incorrect database selected: $databaseName\n";
        $allPassed = false;
    }

} catch (PDOException $e) {
    echo "[FAIL] Could not identify the selected database.\n";
    $allPassed = false;
}

// 3. Retrieve existing tables
try {

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\nExisting Database Tables:\n";

    if (empty($tables)) {
        echo "[FAIL] No tables found.\n";
        $allPassed = false;
    } else {
        foreach ($tables as $table) {
            echo "[FOUND] $table\n";
        }
    }

} catch (PDOException $e) {
    echo "[FAIL] Could not retrieve database tables.\n";
    $allPassed = false;
}

// 4. Check essential BAUG tables
$requiredTables = [
    'users',
    'savings',
    'loans'
];

echo "\nRequired Table Verification:\n";

foreach ($requiredTables as $requiredTable) {

    if (in_array($requiredTable, $tables ?? [], true)) {
        echo "[PASS] Table '$requiredTable' exists.\n";
    } else {
        echo "[FAIL] Table '$requiredTable' is missing.\n";
        $allPassed = false;
    }
}

// 5. Final result
echo "\n==================================================\n";

if ($allPassed) {
    echo "RESULT: PASS - DATABASE CHECKS COMPLETED!\n";
} else {
    echo "RESULT: FAIL - PLEASE CHECK DATABASE TABLES.\n";
}

echo "==================================================\n";

exit($allPassed ? 0 : 1);