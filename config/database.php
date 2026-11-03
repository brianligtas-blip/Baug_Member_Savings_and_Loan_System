<?php

$host = "localhost";
$dbname = "baug_member_system";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]
    );

} catch (PDOException $e) {

    error_log("BAUG database connection error: " . $e->getMessage());

    http_response_code(500);
    exit("The system is temporarily unavailable. Please try again later.");
}