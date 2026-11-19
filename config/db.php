<?php
/**
 * config/db.php — Database connection (PDO)
 *
 * UPDATE THESE 4 VALUES for your hosting environment.
 * - XAMPP/local default: host=localhost, user=root, pass='' (empty)
 * - Hostinger/cPanel: get these from your hosting control panel > MySQL Databases
 */

$DB_HOST = 'localhost';
$DB_NAME = 'bobo_bakers';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json");
    error_log("DB connection failed: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error"   => "Database connection failed. Please try again later."
    ]);
    exit;
}
