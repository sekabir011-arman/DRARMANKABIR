<?php
$path = '/home/drarmank/public_html/api/helpers.php';
$content = file_get_contents($path);

// Fix line 86: insert  after <= 
$content = str_replace(
    'if ($maxAttempts <= ) $maxAttempts = RATE_LIMIT_MAX;',
    'if ($maxAttempts <= ) $maxAttempts = RATE_LIMIT_MAX;',
    $content
);

// Fix line 87: insert  after <= 
$content = str_replace(
    'if ($windowSeconds <= ) $windowSeconds = RATE_LIMIT_WINDOW;',
    'if ($windowSeconds <= ) $windowSeconds = RATE_LIMIT_WINDOW;',
    $content
);

// Fix line 96: insert  after => 
$content = str_replace(
    "'count' => ,",
    "'count' => ,",
    $content
);

file_put_contents($path, $content);
echo "Fixes applied successfully\n";

// Verify
$lines = explode("\n", $content);
echo "Line 86: " . $lines[85] . "\n";
echo "Line 87: " . $lines[86] . "\n";
echo "Line 96: " . $lines[95] . "\n";
