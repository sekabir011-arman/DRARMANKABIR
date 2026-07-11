#!/usr/bin/php
<?php
/**
 * Database Migration Runner
 * 
 * Usage: php migrate.php
 * 
 * Executes SQL migration files in order from server-data/migrations/
 * Only runs files that haven't been executed yet.
 */

$config = __DIR__ . '/public_html/config.php';
if (!file_exists($config)) {
    die("config.php not found. Run this from the project root.\n");
}

require_once $config;

// ─── Parse command line ────────────────────────────────────────────────────
$options = getopt('', ['fresh', 'seed', 'file:']);
$isFresh = isset($options['fresh']);
$seedOnly = isset($options['seed']);
$specificFile = $options['file'] ?? null;

// ─── Database connection ───────────────────────────────────────────────────
try {
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "✓ Connected to MySQL\n";
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// ─── Fresh migration ───────────────────────────────────────────────────────
if ($isFresh) {
    $confirm = readline("WARNING: This will DROP ALL TABLES in " . DB_NAME . ". Continue? (yes/no): ");
    if (strtolower(trim($confirm)) !== 'yes') {
        die("Aborted.\n");
    }
    
    try {
        $pdo->exec("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
        echo "✗ Dropped database\n";
    } catch (PDOException $e) {
        echo "  (Database may not exist: " . $e->getMessage() . ")\n";
    }
}

// ─── Ensure database exists ────────────────────────────────────────────────
$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");
echo "✓ Database '" . DB_NAME . "' ready\n";

// ─── Create migrations tracking table ──────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        checksum VARCHAR(64) NOT NULL
    )
");

// Get already executed migrations
$executed = $pdo->query("SELECT filename, checksum FROM _migrations")->fetchAll(PDO::FETCH_KEY_PAIR);

// ─── Find migration files ──────────────────────────────────────────────────
$migrationsDir = __DIR__ . '/server-data/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
    echo "  Created migrations directory\n";
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit;
}

// ─── Execute migrations ────────────────────────────────────────────────────
$executedCount = 0;
$skippedCount = 0;

foreach ($files as $file) {
    $filename = basename($file);
    
    // Filter specific file if requested
    if ($specificFile && $filename !== $specificFile) {
        continue;
    }
    
    // Seed-only mode
    if ($seedOnly && !str_contains($filename, 'seed')) {
        continue;
    }
    
    $content = file_get_contents($file);
    $checksum = hash('sha256', $content);
    
    // Check if already executed
    if (isset($executed[$filename])) {
        if ($executed[$filename] === $checksum) {
            echo "  → {$filename} (already executed, skipping)\n";
            $skippedCount++;
            continue;
        }
        echo "  → {$filename} (checksum changed, re-executing)\n";
    } else {
        echo "  → {$filename} (executing)\n";
    }
    
    // Split by delimiter and execute each statement
    $statements = explode(';', $content);
    $pdo->beginTransaction();
    
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        
        // Record migration
        if (isset($executed[$filename])) {
            $pdo->prepare("UPDATE _migrations SET checksum = :checksum, executed_at = NOW() WHERE filename = :filename")
                ->execute([':checksum' => $checksum, ':filename' => $filename]);
        } else {
            $pdo->prepare("INSERT INTO _migrations (filename, checksum) VALUES (:filename, :checksum)")
                ->execute([':filename' => $filename, ':checksum' => $checksum]);
        }
        
        $pdo->commit();
        echo "  ✓ {$filename} executed successfully\n";
        $executedCount++;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "  ✗ {$filename} FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ─── Summary ────────────────────────────────────────────────────────────────
echo "\n── Migration Summary ───────────────────────────────────────────\n";
echo "  Executed: {$executedCount}\n";
echo "  Skipped:  {$skippedCount}\n";
echo "  Total:    " . count($files) . "\n";
echo "─────────────────────────────────────────────────────────────────\n";

// ─── Final check ───────────────────────────────────────────────────────────
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tableCount = count($tables);
echo "\nDatabase '{$tableCount}' tables:\n";
foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo "  • {$table} ({$count} rows)\n";
}

echo "\n✓ Migration complete!\n";
