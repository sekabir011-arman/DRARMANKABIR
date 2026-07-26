<?php
/**
 * Fix helpers.php - restore stripped numeric values
 * 
 * The number zero is being stripped from files. This script
 * writes the correct content using chr(48) to avoid character stripping issues.
 */
$path = '/home/drarmank/public_html/api/helpers.php';
$content = file_get_contents($path);

$zero = chr(48);

// Fix line 265: $userId > )  -> $userId > )
$content = preg_replace(
    '/\\$userId !== null && \\$userId > \\)/',
    '\$userId !== null && $userId > ' . $zero . ')',
    $content
);

// Fix line 274: $patientId > )  -> $patientId > )
$content = preg_replace(
    '/\\$patientId !== null && \\$patientId > \\)/',
    '\$patientId !== null && $patientId > ' . $zero . ')',
    $content
);

// Fix line 293: '127...1' -> '127...1'
$content = preg_replace(
    "/'127\\.\\.\\.1'/",
    "'127." . $zero . "." . $zero . ".1'",
    $content
);

file_put_contents($path, $content);
echo "Fixed\n";

// Verify
$lines = file($path);
foreach ([264, 273, 292] as $idx) {
    echo "Line " . ($idx + 1) . ": " . rtrim($lines[$idx]) . "\n";
}

echo "\nSyntax: ";
passthru("php -l " . escapeshellarg($path) . " 2>&1");
