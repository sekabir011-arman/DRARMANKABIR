<?php
$host = '127...1';
$dbname = 'drarmank_drarmank_care';
$user = 'drarmank_drarmank_care_user';
$pass = 'zosid01197247219';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables (" . count($tables) . "): " . implode(", ", $tables) . "\n\n";

    foreach ($tables as $table) {
        $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo "=== $table ===\n";
        foreach ($cols as $c) {
            $nullable = $c['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $c['Default'] !== null ? ' default=' . $c['Default'] : '';
            echo "  {$c['Field']}: {$c['Type']} $nullable$default\n";
        }

        $fks = $db->query("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($table) . " AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (count($fks)) {
            echo "  Foreign Keys:\n";
            foreach ($fks as $fk) {
                echo "    {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
