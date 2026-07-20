<?php
$path = __DIR__ . '/../public_html/api/helpers.php';
$lines = file($path);

// Fix line 86 (index 85): Insert "" between "<= " and ")"
$lines[85] = str_replace('if ($maxAttempts <= )', 'if ($maxAttempts <= ZERO)', $lines[85]);
echo "Line 86: " . $lines[85];

// Fix line 87 (index 86): Insert "" between "<= " and ")"
$lines[86] = str_replace('if ($windowSeconds <= )', 'if ($windowSeconds <= ZERO)', $lines[86]);
echo "Line 87: " . $lines[86];

// Fix line 96 (index 95): Insert "" after "=> "
$lines[95] = str_replace("'count' => ,", "'count' => ZERO,", $lines[95]);
echo "Line 96: " . $lines[95];

file_put_contents($path, implode('', $lines));
echo "\nFixed.\n";
