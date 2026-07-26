<?php
// Fix helpers.php - restore stripped zero characters
$path = '/home/drarmank/public_html/api/helpers.php';
$content = file_get_contents($path);

// Fix: $userId > )  -> $userId > )
$content = str_replace("\$userId > )", "\$userId > )", $content);

// Fix: $patientId > ) -> $patientId > )
$content = str_replace("\$patientId > )", "\$patientId > )", $content);

// Fix: '127...1' -> '127...1'
$content = str_replace("'127...1'", "'127...1'", $content);

file_put_contents($path, $content);
echo "Fixed helpers.php\n";

// Verify
$lines = file($path);
foreach ($lines as $i => $line) {
    if (strpos($line, 'userId > ') !== false || strpos($line, 'patientId > ') !== false || strpos($line, '127...1') !== false) {
        echo "Line " . ($i+1) . ": " . $line;
    }
}

echo "\n--- Syntax check: ---\n";
passthru("php -l " . escapeshellarg($path) . " 2>&1");
