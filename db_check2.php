<?php
require_once '/home/drarmank/public_html/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== ALL TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  " . $row[] . "\n";
}

echo "\n=== USERS ===\n";
$stmt = $pdo->query("SELECT id, email, role, status, full_name FROM users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  #{$row['id']}: {$row['email']} ({$row['full_name']}) role={$row['role']} status={$row['status']}\n";
}

echo "\n=== PATIENTS ===\n";
$stmt = $pdo->query("SELECT id, full_name, register_number FROM patients");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  #{$row['id']}: {$row['full_name']} ({$row['register_number']})\n";
}
