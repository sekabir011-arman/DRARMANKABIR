<?php
/**
 * Run Auth Conversion Migration
 */
require_once __DIR__ . '/public_html/config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('USE `' . DB_NAME . '`');
    
    $sql = file_get_contents(__DIR__ . '/server-data/migrations/003_auth_conversion.sql');
    
    // Split by semicolons, execute each non-empty statement
    $statements = explode(';', $sql);
    $count = 0;
    $errors = [];
    
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        // Skip comment-only lines
        $lines = explode("\n", $stmt);
        $allComments = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_starts_with($line, '--') && !str_starts_with($line, '#')) {
                $allComments = false;
                break;
            }
        }
        if ($allComments) continue;
        
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage() . ' (stmt: ' . substr($stmt, 0, 80) . '...)';
        }
    }
    
    echo "Migration completed.\n";
    echo "Executed $count statements.\n";
    
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $err) {
            echo "  - $err\n";
        }
    }
    
    // Verify
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables in database:\n";
    foreach ($tables as $t) {
        echo "  - $t\n";
    }
    
    // Check for our new columns
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nUsers table columns: " . implode(', ', $cols) . "\n";
    
} catch (Exception $e) {
    echo 'Migration failed: ' . $e->getMessage() . "\n";
    exit(1);
}
