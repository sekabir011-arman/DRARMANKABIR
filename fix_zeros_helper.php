<?php
$path = '/home/drarmank/public_html/api/helpers.php';
$content = file_get_contents($path);

$zero = chr(48);
$dotZero = chr(46) . chr(48) . chr(48);

// Fix all stripped zeros
$search = [
    "\$userId > " . chr(41),  // $userId > )
    "\$patientId > " . chr(41),  // $patientId > )
];
$replace = [
    "\$userId > " . $zero . chr(41),  // $userId > )
    "\$patientId > " . $zero . chr(41),  // $patientId > )
];

$content = str_replace($search, $replace, $content);

// Fix IP address 127...1 pattern
$content = preg_replace('/127\.\.\.1/', '127' . $dotZero . $dotZero . '1', $content);

file_put_contents($path, $content);
echo "Fixed helpers.php\n";

// Check for any remaining issues
$lines = file($path);
foreach ($lines as $i => $line) {
    if (preg_match('/\$\w+ > \)/', $line) || preg_match('/127\.\.\.1/', $line)) {
        echo "STILL BROKEN Line " . ($i+1) . ": " . $line;
    }
}

// Verify syntax
passthru("php -l " . escapeshellarg($path) . " 2>&1");
