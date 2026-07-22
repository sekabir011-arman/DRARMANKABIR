<?php
$file = file_get_contents('source_build/dr.armankabir-main/src/frontend/src/pages/Staff.tsx');
$search = '.split("T")[]';
$replace = ".split('T')[]";
$file = str_replace($search, $replace, $file);
file_put_contents('source_build/dr.armankabir-main/src/frontend/src/pages/Staff.tsx', $file);
echo "Done";
