<?php
// Fix zero values stripped by heredoc/shell
$path = '/home/drarmank/public_html/api/helpers.php';
$content = file_get_contents($path);

$zero = '';

// Fix: $userId >  )  →  $userId > )
$content = preg_replace('/\$userId > \\)/', '$userId > ' . $zero . ')', $content);
$content = preg_replace('/\$patientId > \\)/', '$patientId > ' . $zero . ')', $content);
$content = preg_replace('/127\.\.\.1/', '127...1', $content);

file_put_contents($path, $content);
echo "Done\n";

// Verify
$lines = file($path);
for ($i = 258; $i <= 300; $i++) {
    if (isset($lines[$i-1])) {
        echo "Line $i: " . rtrim($lines[$i-1]) . "\n";
    }
}
