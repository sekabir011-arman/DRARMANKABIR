<?php
/**
 * Migration 004: Staff Registration Fixes
 * Creates missing admin_sessions table, fixes audit_logs ENUM, adds user columns.
 */

require_once '/home/drarmank/public_html/config.php';

echo "Starting Migration 004...\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // ─── 1. Create admin_sessions table ─────────────────────────────────────
    echo "Creating admin_sessions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_sessions (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id        BIGINT UNSIGNED NOT NULL,
            token           VARCHAR(255) NOT NULL UNIQUE,
            ip_address      VARCHAR(45) DEFAULT NULL,
            user_agent      TEXT DEFAULT NULL,
            expires_at      TIMESTAMP NOT NULL,
            last_activity   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_as_admin (admin_id),
            INDEX idx_as_token (token),
            INDEX idx_as_expires (expires_at),
            FOREIGN KEY (admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ admin_sessions created\n";
    
    // ─── 2. Fix audit_logs.action ENUM → VARCHAR(50) ───────────────────────
    echo "Fixing audit_logs.action ENUM...\n";
    $pdo->exec("ALTER TABLE audit_logs MODIFY COLUMN action VARCHAR(50) NOT NULL");
    echo "  ✓ audit_logs.action is now VARCHAR(50)\n";
    
    // ─── 3. Add registration fields to users table ─────────────────────────
    echo "Adding missing columns to users table...\n";
    
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('designation', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN designation VARCHAR(100) DEFAULT NULL AFTER full_name");
        echo "  ✓ Added users.designation\n";
    } else {
        echo "  - users.designation already exists\n";
    }
    
    if (!in_array('degree', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN degree VARCHAR(255) DEFAULT NULL AFTER specialization");
        echo "  ✓ Added users.degree\n";
    } else {
        echo "  - users.degree already exists\n";
    }
    
    if (!in_array('hospital_name', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN hospital_name VARCHAR(255) DEFAULT NULL AFTER degree");
        echo "  ✓ Added users.hospital_name\n";
    } else {
        echo "  - users.hospital_name already exists\n";
    }
    
    echo "\nMigration 004 completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
