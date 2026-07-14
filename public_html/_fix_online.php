<?php
$path = "/home/drarmank/public_html/assets/index-DJeWhCy-.js";
$content = file_get_contents($path);

$old = 'window.addEventListener("online", () => {
  _isOnlineCache = true;
});';

$new = 'window.addEventListener("online", () => {
  _isOnlineCache = true;
  if (typeof processContentOfflineQueue === "function") {
    processContentOfflineQueue();
  }
});';

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($path, $content);
    echo "Replacement successful\n";
} else {
    echo "Old string not found\n";
    $pos = strpos($content, 'window.addEventListener("online"');
    if ($pos !== false) {
        echo "Found at $pos\n";
        echo substr($content, $pos, 120) . "\n";
    } else {
        echo "Not found at all\n";
    }
}
