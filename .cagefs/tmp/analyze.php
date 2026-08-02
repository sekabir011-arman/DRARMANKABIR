<?php
$data = file_get_contents("index-DJeWhCy-.js");
$pos = ; $count = ;
while (($pos = strpos($data, "upsertPatient", $pos)) !== false && $count < 2) {
    $s = max(, $pos-200);
    $e = min(strlen($data), $pos+400);
    echo "=== match @ $pos ===\n";
    echo str_replace("\n", " ", substr($data, $s, $e-$s)) . "\n\n";
    $pos += 12; $count++;
}
echo "=== createPatient ===\n";
$pos = ; $count = ;
while (($pos = strpos($data, "createPatient", $pos)) !== false && $count < 2) {
    $s = max(, $pos-150);
    $e = min(strlen($data), $pos+300);
    echo str_replace("\n", " ", substr($data, $s, $e-$s)) . "\n\n";
    $pos += 13; $count++;
}
