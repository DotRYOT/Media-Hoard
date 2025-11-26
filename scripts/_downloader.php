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

$url = $_GET['url'];
$video_id = null;
$parsed_url = parse_url($url);

$videoExtension = $config['videoExtension'] ?? 'mp4';

// Handle normal YouTube links (e.g., https://www.youtube.com/watch?v=VIDEO_ID)
if (isset($parsed_url['query'])) {
  parse_str($parsed_url['query'], $query_params);
  if (isset($query_params['v'])) {
    $video_id = $query_params['v'];
  }
}

// Handle YouTube short links (e.g., https://youtu.be/VIDEO_ID)
if (!$video_id && isset($parsed_url['host']) && isset($parsed_url['path'])) {
  if ($parsed_url['host'] === 'youtu.be') {
    $video_id = trim($parsed_url['path'], '/');
  }
}

// Output the results
if ($video_id) {
  echo "Video ID: " . htmlspecialchars($video_id);
} else {
  echo "Invalid YouTube URL.";
  $error = "Invalid YouTube URL.";
  handleError($error);
}

// Generate random filename
$randNumber = randStringGen(16, 'numbers');
$outputFileName = './temp/videos/' . $randNumber . '.' . $videoExtension;

// Command to download the video (commented out for now)
$command = 'yt-dlp.exe --format "bestvideo[ext=' . $videoExtension . ']+bestaudio[ext=m4a]" --output "' . $outputFileName . '" ' . $url;
$output = [];
$returnVar = 0;

exec($command, $output, $returnVar);

// Fetch the video title
$title = getYoutubeVideoTitleScrape($video_id);
$safeTitle = urlencode($title);

if (!file_exists($outputFileName)) {
  echo "Video did not download!";
  $error = "Video did not download!";
  handleError($error);
}

// Redirect to the download page with the sanitized title
header('Location: ./_downloadedVideo.php/?url=' . $randNumber . '.' . $videoExtension . '&title=' . $safeTitle);
exit();