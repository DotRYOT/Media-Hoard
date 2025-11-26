<?php

$jsonFilePath = "../../video/posts.json";

if (!isset($_REQUEST['puid'])) {
  die(json_encode(["success" => false, "message" => "Missing video ID."]));
}

$puid = $_REQUEST['puid'];

// Validate PUID format (simple alphanumeric check)
if (!preg_match('/^[a-zA-Z0-9]+$/', $puid)) {
  die(json_encode(["success" => false, "message" => "Invalid video ID."]));
}

// Load JSON data
if (!file_exists($jsonFilePath)) {
  die(json_encode(["success" => false, "message" => "JSON file not found."]));
}

$jsonData = file_get_contents($jsonFilePath);
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  die(json_encode(["success" => false, "message" => "Invalid JSON in file."]));
}

// Find and remove the video entry by PUID
$found = false;
foreach ($data as $key => $entry) {
  if (isset($entry['PUID']) && $entry['PUID'] === $puid) {
    unset($data[$key]);
    $found = true;
    break;
  }
}

if (!$found) {
  die(json_encode(["success" => false, "message" => "Video not found in JSON."]));
}

// Re-index array after deletion
$data = array_values($data);

// Save updated JSON back to file
if (file_put_contents($jsonFilePath, json_encode($data, JSON_PRETTY_PRINT)) === false) {
  die(json_encode(["success" => false, "message" => "Failed to update JSON file."]));
}

// Define paths - YOU CAN CUSTOMIZE THESE AS NEEDED
$videoDir = '../../video/' . $puid . '/';
$videoFile = $videoDir . 'file_' . $puid . '.mp4';
$thumbnailFile = $videoDir . 'frame_' . $puid . '.jpg';

// Delete video file
if (file_exists($videoFile)) {
  if (!unlink($videoFile)) {
    die(json_encode(["success" => false, "message" => "Failed to delete video file."]));
  }
}

// Delete thumbnail file
if (file_exists($thumbnailFile)) {
  if (!unlink($thumbnailFile)) {
    die(json_encode(["success" => false, "message" => "Failed to delete thumbnail."]));
  }
}

if (is_dir($videoDir) && count(scandir($videoDir)) === 2) { // Only '.' and '..'
  rmdir($videoDir);
}

require_once "../_inc.php";

$message = "Video deleted successfully.";

$success = generateMessageUrl($message, 'success');
header("Location: ../../{$success}");
exit();