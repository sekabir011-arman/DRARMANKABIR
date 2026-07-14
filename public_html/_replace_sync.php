<?php
$path = "/home/drarmank/public_html/assets/index-DJeWhCy-.js";
$content = file_get_contents($path);

$old = "function addToContentOfflineQueue(payload, updatedAt) {
  const queue = getContentOfflineQueue();
  queue.push({
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    payload,
    updatedAt: updatedAt || new Date().toISOString(),
    retryCount: 0,
    queuedAt: new Date().toISOString()
  });
  setContentOfflineQueue(queue);
}";

$new = "function addToContentOfflineQueue(payload, updatedAt) {
  const queue = getContentOfflineQueue();
  queue.push({
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    payload,
    updatedAt: updatedAt || new Date().toISOString(),
    retryCount: 0,
    queuedAt: new Date().toISOString()
  });
  setContentOfflineQueue(queue);
}
function addToOfflineQueue(key, value) {
  if (!navigator.onLine) {
    addToContentOfflineQueue({ [key]: value }, new Date().toISOString());
  }
}";

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($path, $content);
    echo "Replacement successful\n";
} else {
    echo "Old string not found\n";
    // Debug
    $pos = strpos($content, "function addToContentOfflineQueue");
    if ($pos !== false) {
        echo "Found at position: $pos\n";
        echo substr($content, $pos, 300) . "\n";
    }
}
