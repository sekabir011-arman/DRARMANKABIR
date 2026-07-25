<?php
require_once '/home/drarmank/public_html/config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Check admin_sessions
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
    echo "admin_sessions: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
    
    // Check if migration 004 other parts were applied
    $stmt = $pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'action'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "audit_logs.action type: " . ($row ? $row['Type'] : 'N/A') . "\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'designation'");
    echo "users.designation: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'degree'");
    echo "users.degree: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'hospital_name'");
    echo "users.hospital_name: " . ($stmt->fetch() ? "EXISTS" : "MISSING") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
