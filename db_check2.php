<?php
require_once __DIR__ . '/public_html/api/database.php';
try {
    $db = Database::getInstance();
    $stmt = $db->query("SHOW CREATE TABLE audit_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['Create Table'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
