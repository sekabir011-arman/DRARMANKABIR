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

$fixed = ;

// Fix line 86: replace null byte between <= and ) with 
$old = substr($lines[85], , );
$pos = strpos($lines[85], '<= ');
if ($pos !== false) {
    $charAfter = $lines[85][$pos + 3];
    echo "Char after <= on line 86: ord=" . ord($charAfter) . " char='$charAfter'\n";
    
    // Replace the character between <= and ) with 
    $before = substr($lines[85], , $pos + 3);
    $after = substr($lines[85], $pos + 3 + 1); // skip the bad char and the )
    $lines[85] = $before . '' . $after;
    echo "Fixed line 86\n";
    $fixed++;
}

// Fix line 87: same
$pos = strpos($lines[86], '<= ');
if ($pos !== false) {
    $charAfter = $lines[86][$pos + 3];
    echo "Char after <= on line 87: ord=" . ord($charAfter) . " char='$charAfter'\n";
    
    $before = substr($lines[86], , $pos + 3);
    $after = substr($lines[86], $pos + 3 + 1);
    $lines[86] = $before . '' . $after;
    echo "Fixed line 87\n";
    $fixed++;
}

// Fix line 96: count => , -> count => ,
$pos = strpos($lines[95], "'count' => ");
if ($pos !== false) {
    $charAfterArrow = $lines[95][$pos + 10]; // after "=> "
    echo "Char after => on line 96: ord=" . ord($charAfterArrow) . " char='$charAfterArrow'\n";
    
    $before = substr($lines[95], , $pos + 10);
    $after = substr($lines[95], $pos + 10 + 1); // skip the bad char
    $lines[95] = $before . '' . $after;
    echo "Fixed line 96\n";
    $fixed++;
}

if ($fixed > ) {
    file_put_contents($path, implode("\n", $lines));
    echo "Wrote $fixed fixes\n";
} else {
    echo "No fixes needed\n";
}
