<?php

require_once __DIR__ . '/../_inc.php';

$videojsonFilePath = "../../video/posts.json";
$cacheFilePath = "../../cache/video_count.cache";

if (file_exists($videojsonFilePath)) {
  unlink($videojsonFilePath);
}
if (file_exists($cacheFilePath)) {
  unlink($cacheFilePath);
}

$videoDir = "../../video";

if (is_dir($videoDir)) {
  try {
    deleteDirectory($videoDir);
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }
} else {
  echo "Directory '$videoDir' does not exist.";
}

$success = generateMessageUrl("All videos deleted successfully", 'success');
header("Location: ../../setup.php?update=true");
exit();
