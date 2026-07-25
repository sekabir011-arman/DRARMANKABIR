<?php
require_once '/home/drarmank/public_html/config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
    echo "admin_sessions: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_accounts'");
    echo "admin_accounts: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
