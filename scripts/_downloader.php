<?php
require "./_inc.php";

// Allow the download to run as long as needed
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: application/json');

// Load config.json
$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
  echo json_encode(['success' => false, 'message' => 'Config file not found.']);
  exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  echo json_encode(['success' => false, 'message' => 'Invalid config file.']);
  exit;
}

$videoExtension = $config['videoExtension'] ?? 'mp4';
$frameTime      = (int)($config['frameTime'] ?? 20);
$thumbWidth     = (int)($config['thumbWidth'] ?? 1280);
$thumbHeight    = (int)($config['thumbHeight'] ?? 720);

// Validate input URL
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if (empty($url)) {
  echo json_encode(['success' => false, 'message' => 'No URL provided.']);
  exit;
}

$url = filter_var($url, FILTER_SANITIZE_URL);
if (!filter_var($url, FILTER_VALIDATE_URL)) {
  echo json_encode(['success' => false, 'message' => 'Invalid URL.']);
  exit;
}

// Extract YouTube video ID
$parsed   = parse_url($url);
$video_id = null;

if (isset($parsed['query'])) {
  parse_str($parsed['query'], $qp);
  if (isset($qp['v'])) {
    $video_id = $qp['v'];
  }
}

if (!$video_id && isset($parsed['host'], $parsed['path']) && $parsed['host'] === 'youtu.be') {
  $video_id = trim($parsed['path'], '/');
}

if (!$video_id) {
  echo json_encode(['success' => false, 'message' => 'Could not parse a YouTube video ID from the URL.']);
  exit;
}

// Tool paths — prefer executables in the scripts folder, fall back to system PATH
$ytdlpPath  = file_exists(__DIR__ . '/yt-dlp.exe')  ? __DIR__ . '/yt-dlp.exe'  : 'yt-dlp.exe';
$ffmpegPath = file_exists(__DIR__ . '/ffmpeg.exe')  ? __DIR__ . '/ffmpeg.exe' : 'ffmpeg.exe';

if (!file_exists(__DIR__ . '/yt-dlp.exe') && !shell_exec('where yt-dlp.exe 2>nul')) {
  echo json_encode(['success' => false, 'message' => 'yt-dlp.exe not found. Please install it first.']);
  exit;
}

// Temp output file
$tempId        = randStringGen(16, 'numbers');
$tempVideoFile = __DIR__ . '/temp/videos/' . $tempId . '.' . $videoExtension;

// Download video
$dlCommand = escapeshellarg($ytdlpPath)
  . ' --format ' . escapeshellarg('bestvideo[ext=' . $videoExtension . ']+bestaudio[ext=m4a]')
  . ' --output ' . escapeshellarg($tempVideoFile)
  . ' ' . escapeshellarg($url);

exec($dlCommand, $dlOutput, $dlReturn);

if (!file_exists($tempVideoFile)) {
  echo json_encode([
    'success' => false,
    'message' => 'Video download failed. Check yt-dlp and the URL.',
    'detail'  => implode("\n", $dlOutput),
  ]);
  exit;
}

// Fetch and sanitize title
$title      = getYoutubeVideoTitleScrape($video_id);
$videoTitle = preg_replace(
  '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F1E6}-\x{1F1FF}]|[\x{1F900}-\x{1F9FF}]/u',
  '',
  $title
);
$videoTitle = trim($videoTitle);

// Generate unique post ID
$PUID = randStringGen(16, 'numbers');
$Time = time();

$newVideoName  = "file_{$PUID}.{$videoExtension}";
$videoDir      = __DIR__ . "/../video/{$PUID}";
$uploadPath    = "{$videoDir}/{$newVideoName}";
$frameFileName = "frame_{$PUID}.jpg";
$framePath     = "{$videoDir}/{$frameFileName}";

if (!is_dir($videoDir)) {
  mkdir($videoDir, 0777, true);
}

// Move temp video to final location
$moved = false;
if (rename($tempVideoFile, $uploadPath)) {
  $moved = true;
} elseif (copy($tempVideoFile, $uploadPath)) {
  unlink($tempVideoFile);
  $moved = true;
}

if (!$moved) {
  echo json_encode(['success' => false, 'message' => 'Failed to move the downloaded video file.']);
  exit;
}

// Generate thumbnail with ffmpeg
// Use forward-slash paths; wrap in double quotes for Windows cmd compatibility
$filterString   = "scale={$thumbWidth}:{$thumbHeight}:force_original_aspect_ratio=1,pad={$thumbWidth}:{$thumbHeight}:(ow-iw)/2:(oh-ih)/2";
$uploadPathFwd  = str_replace('\\', '/', $uploadPath);
$framePathFwd   = str_replace('\\', '/', $framePath);
$ffmpegExe      = str_replace('\\', '/', $ffmpegPath);
$thumbCommand   = '"' . $ffmpegExe . '"'
  . ' -ss ' . (int)$frameTime
  . ' -i "' . $uploadPathFwd . '"'
  . ' -vf "' . $filterString . '"'
  . ' -vframes 1 "' . $framePathFwd . '"'
  . ' -y 2>&1';
exec($thumbCommand, $thumbOutput, $thumbReturn);
if ($thumbReturn !== 0) {
  error_log('ffmpeg thumbnail failed: ' . implode("\n", $thumbOutput));
}

// Update posts.json
$jsonFile = __DIR__ . '/../video/posts.json';
$posts    = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
if (!is_array($posts)) {
  $posts = [];
}

$posts[] = [
  'PUID'           => $PUID,
  'Time'           => $Time,
  'video_path'     => "/video/{$PUID}/{$newVideoName}",
  'thumbnail_path' => "/video/{$PUID}/{$frameFileName}",
  'title'          => $videoTitle,
];

file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Clear video count cache
$cacheFile = __DIR__ . '/../cache/video_count.cache';
if (file_exists($cacheFile)) {
  unlink($cacheFile);
}

echo json_encode([
  'success'  => true,
  'redirect' => '/?success=' . urlencode('New Video Posted'),
]);
exit;