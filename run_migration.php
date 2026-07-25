<?php
/**
 * Migration 004: Staff Registration Fixes — run now
 * Creates: admin_sessions table, fixes audit_logs.action ENUM, adds user columns
 */
require_once '/home/drarmank/public_html/config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Starting Migration 004...\n";

try {
    $pdo->beginTransaction();

    // 1. admin_sessions table
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
    echo "  ✅ admin_sessions table created\n";

    // 2. Fix audit_logs.action ENUM → VARCHAR(50)
    $pdo->exec("ALTER TABLE audit_logs MODIFY COLUMN action VARCHAR(50) NOT NULL");
    echo "  ✅ audit_logs.action changed to VARCHAR(50)\n";

    // 3. Add designation, degree, hospital_name to users
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('designation', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN designation VARCHAR(20) DEFAULT NULL AFTER full_name");
        echo "  ✅ users.designation added\n";
    } else {
        echo "  ⏭ users.designation already exists\n";
    }
    
    if (!in_array('degree', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN degree VARCHAR(255) DEFAULT NULL AFTER specialization");
        echo "  ✅ users.degree added\n";
    } else {
        echo "  ⏭ users.degree already exists\n";
    }
    
    if (!in_array('hospital_name', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN hospital_name VARCHAR(255) DEFAULT NULL AFTER degree");
        echo "  ✅ users.hospital_name added\n";
    } else {
        echo "  ⏭ users.hospital_name already exists\n";
    }

    $pdo->commit();
    echo "\n✅ Migration 004 complete!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
