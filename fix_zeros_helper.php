<?php
// Fix zero stripped from helpers.php
$path = '/home/drarmank/public_html/api/helpers.php';
$c = file_get_contents($path);
$zero = 480; // '' chr(48) in decimal

// Fix "> " by replacing the exact pattern using binary-safe approach
$needle1 = "\$userId > \x29";  // $userId > )
$replacement1 = "\$userId > \x29"; // $userId > )
$c = str_replace($needle1, $replacement1, $c);

$needle2 = "\$patientId > \x29";  // $patientId > )
$replacement2 = "\$patientId > \x29"; // $patientId > )
$c = str_replace($needle2, $replacement2, $c);

$needle3 = "'127\x2e\x2e\x2e1'";  // '127...1'
$replacement3 = "'127...1'";
$c = str_replace($needle3, $replacement3, $c);

file_put_contents($path, $c);

// Check the result
$lines = file($path);
for ($i = 260; $i < 280; $i++) {
    if (isset($lines[$i])) {
        echo "Line " . ($i+1) . ": " . $lines[$i];
    }
}
echo "\n=== Syntax check ===\n";
system("php -l " . escapeshellarg($path) . " 2>&1");
