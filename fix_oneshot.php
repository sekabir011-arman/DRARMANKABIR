<?php
// Fix zero values stripped from PHP files
$files = [
    '/home/drarmank/public_html/api/prescriptions/update.php',
    '/home/drarmank/public_html/api/prescriptions/create.php',
];

$zero = chr(48);

foreach ($files as $path) {
    $content = file_get_contents($path);
    
    // Fix: $input['id'] ?? )  →  $input['id'] ?? )
    $content = preg_replace("/\\\$input\['id'\] \?\? \)/", "\$input['id'] ?? {$zero})", $content);
    
    // Fix: (int)$med['is_prn'] : )  →  (int)$med['is_prn'] : )
    $content = preg_replace("/\(int\)\\\$med\['is_prn'\] : \)/", "(int)\$med['is_prn'] : {$zero})", $content);
    
    // Fix: :is_prn' => ... ) at end
    $content = preg_replace("/\(int\)\\\$med\['is_prn'\] : \)/", "(int)\$med['is_prn'] : {$zero})", $content);
    
    file_put_contents($path, $content);
    echo "Fixed: $path\n";
}

// Verify syntax
foreach ($files as $path) {
    $output = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    echo "$path: $output\n";
}
