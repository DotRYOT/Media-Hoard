<?php
require "./_inc.php";

// Load config.json
$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
  die("Config file not found: $configFile");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in config file.");
}

// Read config values with defaults
$frameTime = $config['frameTime'] ?? 20;
$thumbWidth = $config['thumbWidth'] ?? 1280;
$thumbHeight = $config['thumbHeight'] ?? 720;
$videoExtension = $config['videoExtension'] ?? 'mp4';

// Generate unique identifier and timestamp
$PUID = randStringGen(16, 'numbers');
$Time = time();

// Sanitize title by removing emojis
$videoTitle = preg_replace(
  '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F1E6}-\x{1F1FF}]|[\x{1F900}-\x{1F9FF}]/u',
  '',
  $_GET['title']
);

// Define paths
$FileUrl = $_GET['url'];
$FilePath = "./temp/videos/" . $FileUrl;
$newVideoName = "file_{$PUID}.{$videoExtension}";
$uploadVideoPath = "../video/{$PUID}/{$newVideoName}";

$frameFileName = "frame_{$PUID}.jpg";
$frameFilePath = "../video/{$PUID}/{$frameFileName}";

if (!is_dir("../video/{$PUID}/")) {
  mkdir("../video/{$PUID}/", 0777, true);
}

// Attempt to move the video file
$uploadSuccess = false;
if (rename($FilePath, $uploadVideoPath)) {
  $uploadSuccess = true;
} elseif (copy($FilePath, $uploadVideoPath)) {
  unlink($FilePath);
  $uploadSuccess = true;
}

if (!$uploadSuccess) {
  die("Error moving video file to $uploadVideoPath. Check permissions and paths.");
}

// Build the FFmpeg command
$ffmpegPath = "ffmpeg.exe";
$filterString = "scale={$thumbWidth}:{$thumbHeight}:force_original_aspect_ratio=1,pad={$thumbWidth}:{$thumbHeight}:(ow-iw)/2:(oh-ih)/2";
$thumbnailCommand = "{$ffmpegPath} -ss {$frameTime} -i " . escapeshellarg($uploadVideoPath) . " ";
$thumbnailCommand .= "-vf " . escapeshellarg($filterString) . " ";
$thumbnailCommand .= "-vframes 1 " . escapeshellarg($frameFilePath) . " 2>&1";

// Execute the command
exec($thumbnailCommand, $output, $returnVar);

if ($returnVar !== 0) {
  error_log("Thumbnail generation failed: " . implode("\n", $output));
}

// Prepare JSON data
$json_file = '../video/posts.json';
$posts = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

$new_post = [
  'PUID' => $PUID,
  'Time' => $Time,
  'video_path' => "/video/{$PUID}/{$newVideoName}",
  'thumbnail_path' => "/video/{$PUID}/{$frameFileName}",
  'title' => $videoTitle
];

$posts[] = $new_post;

// Write to JSON file
$json_data = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($json_file, $json_data) === false) {
  die("Failed to write to JSON file.");
}

// Delete the cache file
$cacheFile = __DIR__ . '/../cache/video_count.cache';
if (file_exists($cacheFile)) {
  unlink($cacheFile);
}

// Redirect to success page
$success = generateMessageUrl("New Video Posted", 'success');
header("Location: ../../$success");
exit;