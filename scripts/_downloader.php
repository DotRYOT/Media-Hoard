<?php
require "./_inc.php"; // already pulls in _tools.php via require_once
require_once "./_tools.php";

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

// Check for duplicate YouTube video before downloading
$postsJsonPath = __DIR__ . '/../video/posts.json';
$existingPosts = file_exists($postsJsonPath) ? json_decode(file_get_contents($postsJsonPath), true) : [];
if (is_array($existingPosts)) {
  foreach ($existingPosts as $existingPost) {
    if (isset($existingPost['youtube_id']) && $existingPost['youtube_id'] === $video_id) {
      echo json_encode([
        'success'    => false,
        'message'    => 'This YouTube video has already been uploaded (ID: ' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . ').',
        'duplicate'  => true,
        'existingId' => $existingPost['PUID'],
      ]);
      exit;
    }
  }
}

// Cross-platform tool path detection
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Locate yt-dlp / ffmpeg robustly. The web server process does not inherit the
// interactive shell's PATH, so a plain `which yt-dlp` often fails even when the
// package is installed (pip --user, pyenv, conda, systemd PrivateUsers, ...).
// resolveYtDlp() also falls back to `python -m yt_dlp`.
$ytDlp = resolveYtDlp($isWindows, $config['ytDlpPath'] ?? null);

if ($ytDlp['type'] === 'missing') {
  error_log('yt-dlp lookup failed. Checked: ' . describeToolSearch($ytDlp['candidates'], 20));
  echo json_encode([
    'success' => false,
    'message' => 'yt-dlp not found by the web server. If you installed it with "pip install --user yt-dlp" (or pyenv/conda), it lives in your user directory which the http/nginx service cannot see - either run "sudo pacman -S yt-dlp" for a system-wide install, set "ytDlpPath" in config.json to the absolute path of the binary, or use the "install/update yt-dlp" option in the app.',
    'detail'  => 'Checked locations: ' . describeToolSearch($ytDlp['candidates']),
  ]);
  exit;
}

$ffmpeg = resolveFfmpeg($isWindows, $config['ffmpegPath'] ?? null);

if ($ffmpeg['command'] === '') {
  error_log('ffmpeg lookup failed. Checked: ' . describeToolSearch($ffmpeg['candidates'], 20));
  echo json_encode([
    'success' => false,
    'message' => 'ffmpeg not found. Install it with "sudo pacman -S ffmpeg" (Arch) or set "ffmpegPath" in config.json to its absolute path.',
    'detail'  => 'Checked locations: ' . describeToolSearch($ffmpeg['candidates']),
  ]);
  exit;
}

$ytdlpPath = $ytDlp['source'];
$ffmpegPath = $ffmpeg['path'];

// Job ID for progress tracking (digits only)
$jobId = preg_replace('/[^0-9]/', '', $_GET['jobId'] ?? '');
if (!$jobId) {
  $jobId = randStringGen(16, 'numbers');
}

// Ensure temp directory exists with proper permissions
$tempDir = __DIR__ . '/temp';
$tempVideosDir = $tempDir . '/videos';
if (!is_dir($tempDir)) {
  mkdir($tempDir, 0755, true);
  if (!$isWindows) {
    chmod($tempDir, 0755);
  }
}
if (!is_dir($tempVideosDir)) {
  mkdir($tempVideosDir, 0755, true);
  if (!$isWindows) {
    chmod($tempVideosDir, 0755);
  }
}

$progressFile = $tempDir . '/progress_' . $jobId . '.txt';
file_put_contents($progressFile, '');

// Temp output file
$tempId        = randStringGen(16, 'numbers');
$tempVideoFile = $tempVideosDir . '/' . $tempId . '.' . $videoExtension;

// Optional YouTube cookies file (fixes "Sign in to confirm you're not a bot"
// and age-restricted videos). Set "cookies" in config.json to the absolute
// path of a Netscape-format cookies.txt exported from a logged-in browser.
$cookieOpt = '';
if (!empty($config['cookies'])) {
  $cookiePath = $config['cookies'];
  if (!$isWindows && $cookiePath[0] !== '/') {
    $cookiePath = dirname(__DIR__) . '/' . $cookiePath; // allow project-relative paths
  }
  if (file_exists($cookiePath)) {
    $cookieOpt = ' --cookies ' . ($isWindows ? '"' . str_replace('\\', '/', $cookiePath) . '"' : escapeshellarg($cookiePath));
  } else {
    error_log('cookies file configured but not found: ' . $cookiePath);
  }
}

// Build yt-dlp command (forward slashes, double-quoted for Windows)
// $ytDlp['command'] is already shell-escaped and may be "python3 -m yt_dlp".
$tempFwd   = str_replace('\\', '/', $tempVideoFile);
$dlCommand = $ytDlp['command']
  . ' --newline'
  . ' --format "bestvideo[ext=' . $videoExtension . ']+bestaudio[ext=m4a]/bestvideo+bestaudio/best"'
  . ' --merge-output-format ' . $videoExtension
  . $cookieOpt
  . ' --output "' . $tempFwd . '"'
  . ' "' . $url . '"';

// Run yt-dlp via proc_open so progress streams live to the progress file.
// Give the child an extended PATH so yt-dlp can find ffmpeg for merging/remuxing.
$descriptorspec = [
  0 => ['pipe', 'r'],
  1 => ['file', $progressFile, 'w'],
  2 => ['file', $progressFile, 'a'],
];
$procEnv = null;
if (!$isWindows) {
  $extraDirs = [];
  if (!empty($ffmpeg['path'])) {
    $extraDirs[] = dirname($ffmpeg['path']);
  }
  $procEnv = ['PATH' => buildToolEnvPath($extraDirs)];
}
$process = proc_open($dlCommand, $descriptorspec, $pipes, null, $procEnv);
$dlReturn = -1;
if (is_resource($process)) {
  fclose($pipes[0]);
  $dlReturn = proc_close($process);
}

// Check the file; yt-dlp may have remuxed to a different extension
if (!file_exists($tempVideoFile)) {
  // Try common remux fallback (e.g. mkv)
  $fallbacks = ['mkv', 'webm', 'mp4'];
  foreach ($fallbacks as $ext) {
    $candidate = $tempVideosDir . '/' . $tempId . '.' . $ext;
    if (file_exists($candidate)) {
      $tempVideoFile  = $candidate;
      $videoExtension = $ext;
      break;
    }
  }
}

if (!file_exists($tempVideoFile)) {
  $logContent = file_exists($progressFile) ? (string)file_get_contents($progressFile) : '';
  if (file_exists($progressFile)) unlink($progressFile);

  // Surface the real yt-dlp error instead of a generic message.
  $reason = explainYtDlpLog($logContent, $dlReturn);
  error_log('yt-dlp download failed (exit ' . var_export($dlReturn, true) . "): \n" . $logContent);

  echo json_encode([
    'success' => false,
    'message' => $reason,
    'detail'  => substr($logContent, -2000),
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

// Ensure video directory exists with proper permissions
if (!is_dir($videoDir)) {
  mkdir($videoDir, 0755, true);
  if (!$isWindows) {
    chmod($videoDir, 0755);
  }
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
  echo json_encode(['success' => false, 'message' => 'Failed to move the downloaded video file. Check permissions.']);
  exit;
}

// Set proper permissions on the video file (Linux/Unix)
if (!$isWindows) {
  chmod($uploadPath, 0644);
}

// Generate thumbnail with ffmpeg
// Cross-platform command building
$filterString   = "scale={$thumbWidth}:{$thumbHeight}:force_original_aspect_ratio=1,pad={$thumbWidth}:{$thumbHeight}:(ow-iw)/2:(oh-ih)/2";

if ($isWindows) {
  // Windows: use forward slashes and double quotes
  $uploadPathFwd  = str_replace('\\', '/', $uploadPath);
  $framePathFwd   = str_replace('\\', '/', $framePath);
  $ffmpegExePath      = str_replace('\\', '/', $ffmpegPath);
  $thumbCommand   = '"' . $ffmpegExePath . '"'
    . ' -ss ' . (int)$frameTime
    . ' -i "' . $uploadPathFwd . '"'
    . ' -vf "' . $filterString . '"'
    . ' -vframes 1 "' . $framePathFwd . '"'
    . ' -y 2>&1';
} else {
  // Linux/Unix: use escapeshellarg for proper argument escaping
  $thumbCommand = escapeshellarg($ffmpegPath)
    . ' -ss ' . (int)$frameTime
    . ' -i ' . escapeshellarg($uploadPath)
    . ' -vf ' . escapeshellarg($filterString)
    . ' -vframes 1 ' . escapeshellarg($framePath)
    . ' -y 2>&1';
}
exec($thumbCommand, $thumbOutput, $thumbReturn);
if ($thumbReturn !== 0) {
  error_log('ffmpeg thumbnail failed: ' . implode("\n", $thumbOutput));
}

// Set proper permissions on thumbnail file (Linux/Unix)
if (!$isWindows && file_exists($framePath)) {
  chmod($framePath, 0644);
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
  'youtube_id'     => $video_id,
];

file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Clean up progress file
if (file_exists($progressFile)) {
  unlink($progressFile);
}

echo json_encode([
  'success'  => true,
  'redirect' => '?success=' . urlencode('New Video Posted'),
]);
exit;
