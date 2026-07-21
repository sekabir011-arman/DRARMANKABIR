<?php
$content = file_get_contents('/home/drarmank/public_html/api/staff/update.php');
// Fix the missing  in ?? ) pattern
$content = str_replace("?? )", "?? )", $content);
file_put_contents('/home/drarmank/public_html/api/staff/update.php', $content);
echo "Fixed: " . substr($content, , 100);
