<?php
// DB connectivity + schema sanity check for migration baseline
require '/home/drarmank/public_html/config.php';
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    echo "DB_OK ".DB_NAME."\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "TABLE_COUNT ".count($tables)."\n";
    echo "TABLES ".implode(",", array_slice($tables, , 60))."\n";
    // check key tables exist
    $need = ['patients','visits','prescriptions','appointments','admissions','beds','staff_users','patient_logins'];
    foreach ($need as $t) {
        echo ($t . "=" . (in_array($t, $tables) ? "YES" : "NO")) . "\n";
    }
    // sample patients count
    if (in_array('patients', $tables)) {
        $c = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        echo "PATIENTS_COUNT ".$c."\n";
    }
} catch (Exception $e) {
    echo "DB_FAIL ".$e->getMessage()."\n";
}
