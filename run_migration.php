<?php
require_once '/home/drarmank/public_html/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected.\n";

    // Run all pending migrations in order
    $migrations = [
        '004_staff_registration_fixes.sql',
        '005_daily_progress_notes.sql',
        '006_handovers.sql',
        '007_medication_admin_records.sql',
        '008_discharge_checklists.sql',
        '009_admin_sessions.sql',
        '010_visits_discharge_summary.sql',
        '011_chat_messages.sql',
        '012_referrals.sql',
        '013_teleconsults.sql',
        '014_consent_forms.sql'
    ];

    foreach ($migrations as $migration) {
        $path = '/home/drarmank/server-data/migrations/' . $migration;
        if (!file_exists($path)) {
            echo "  SKIP (not found): $migration\n";
            continue;
        }
        $sql = file_get_contents($path);
        // Split by semicolons and execute each statement
        $statements = explode(';', $sql);
        $count = ;
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt) || str_starts_with($stmt, '--')) continue;
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Skip "already exists" errors
                if (str_contains($e->getMessage(), 'already exists') || 
                    str_contains($e->getMessage(), 'Duplicate column') ||
                    str_contains($e->getMessage(), 'Base table or view already exists')) {
                    echo "  NOTE (already applied): {$e->getMessage()}\n";
                } else {
                    throw $e;
                }
            }
        }
        echo "  OK: $migration ($count statements)\n";
    }

    // Verify admin_sessions now exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
    echo "\nadmin_sessions: " . ($stmt->fetch() ? "EXISTS" : "STILL MISSING") . "\n";

    // Show all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables: " . count($tables) . "\n";

} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
