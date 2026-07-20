<?php
$path = __DIR__ . '/../public_html/api/helpers.php';
$content = file_get_contents($path);

// Show raw bytes of problematic lines
$lines = explode("\n", $content);
echo "Line 86 raw: ";
for ($i = ; $i < strlen($lines[85]); $i++) {
    printf("%02x ", ord($lines[85][$i]));
}
echo "\n";

echo "Line 87 raw: ";
for ($i = ; $i < strlen($lines[86]); $i++) {
    printf("%02x ", ord($lines[86][$i]));
}
echo "\n";

echo "Line 96 raw: ";
for ($i = ; $i < strlen($lines[95]); $i++) {
    printf("%02x ", ord($lines[95][$i]));
}
echo "\n";
