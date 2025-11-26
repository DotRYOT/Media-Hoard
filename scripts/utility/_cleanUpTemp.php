<?php
require_once __DIR__ . '/../_inc.php';

$videoJsonFile = "../../video/posts.json";
$videoRootDir = "../../video";

if (!file_exists($videoJsonFile)) {
  die("posts.json not found at: " . realpath($videoJsonFile));
}

$jsonData = file_get_contents($videoJsonFile);
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in posts.json");
}

$validPUIDs = array_filter(array_map(function ($entry) {
  return $entry['PUID'] ?? null;
}, $data));

if (file_exists($videoRootDir)) {
  $items = scandir($videoRootDir);
  foreach ($items as $item) {
    $fullPath = $videoRootDir . '/' . $item;
    if ($item === '.' || $item === '..' || !is_dir($fullPath))
      continue;
    if (!in_array($item, $validPUIDs)) {
      try {
        deleteDirectory($fullPath);
      } catch (Exception $e) {
        error_log("Failed to delete directory {$fullPath}: " . $e->getMessage());
      }
    }
  }
}

$success = generateMessageUrl("Orphaned video folders deleted successfully", 'success');
header("Location: ../../settings/$success");
exit();