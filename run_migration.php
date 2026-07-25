<?php
require_once '/home/drarmank/public_html/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // ─── 1. Create admin_sessions table ──────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "admin_sessions: CREATED\n";

    // ─── 2. Fix audit_logs.action ENUM → VARCHAR ─────────────────────────────
    $pdo->exec("ALTER TABLE audit_logs MODIFY COLUMN action VARCHAR(50) NOT NULL");
    echo "audit_logs.action: VARCHAR(50)\n";

    // ─── 3. Add registration fields to users ─────────────────────────────────
    $cols = ['designation VARCHAR(10) DEFAULT NULL AFTER full_name',
             'degree VARCHAR(255) DEFAULT NULL AFTER specialization',
             'hospital_name VARCHAR(255) DEFAULT NULL AFTER degree'];
    foreach ($cols as $col) {
        $name = explode(' ', $col)[];
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$name'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col");
            echo "users.$name: ADDED\n";
        } else {
            echo "users.$name: ALREADY EXISTS\n";
        }
    }

    echo "\nMigration 004 applied successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
