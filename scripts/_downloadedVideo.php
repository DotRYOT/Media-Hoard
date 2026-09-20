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

// Define paths (use absolute paths for ffmpeg compatibility)
$FileUrl = $_GET['url'];
$FilePath = __DIR__ . "/temp/videos/" . $FileUrl;
$newVideoName = "file_{$PUID}.{$videoExtension}";
$uploadVideoPath = __DIR__ . "/../video/{$PUID}/{$newVideoName}";

$frameFileName = "frame_{$PUID}.jpg";
$frameFilePath = __DIR__ . "/../video/{$PUID}/{$frameFileName}";

// Ensure video directory exists with proper permissions
if (!is_dir(dirname($uploadVideoPath))) {
  if (!mkdir(dirname($uploadVideoPath), 0755, true)) {
    die("Failed to create video directory. Check permissions.");
  }
  // Set proper permissions on Linux/Unix systems
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    chmod(dirname($uploadVideoPath), 0755);
  }
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
  error_log("Failed to move video from $FilePath to $uploadVideoPath");
  die("Error moving video file. Check permissions.");
}

// Set proper permissions on the video file (Linux/Unix)
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
  chmod($uploadVideoPath, 0644);
}

// Build the FFmpeg command with cross-platform support
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$ffmpegExe = $isWindows ? 'ffmpeg.exe' : 'ffmpeg';

// Check for ffmpeg in multiple locations
$ffmpegPath = null;

// First, check if ffmpeg exists in scripts folder
if (file_exists(__DIR__ . '/' . $ffmpegExe)) {
  $ffmpegPath = __DIR__ . '/' . $ffmpegExe;
} else {
  // Try common absolute paths first (faster and more reliable)
  $commonPaths = [
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/bin/ffmpeg',
    '/snap/bin/ffmpeg'
  ];
  
  foreach ($commonPaths as $path) {
    if (file_exists($path) && is_executable($path)) {
      $ffmpegPath = $path;
      break;
    }
  }
  
  // If not found in common paths, search in system PATH
  if (!$ffmpegPath) {
    if ($isWindows) {
      $whereOutput = shell_exec('where ' . escapeshellarg($ffmpegExe) . ' 2>nul');
      if ($whereOutput && trim($whereOutput) !== '') {
        $paths = explode("\n", trim($whereOutput));
        foreach ($paths as $path) {
          $path = trim($path);
          if (file_exists($path)) {
            $ffmpegPath = $path;
            break;
          }
        }
      }
    } else {
      // Use full path to which command and capture stderr
      $whichOutput = shell_exec('/usr/bin/which ' . escapeshellarg($ffmpegExe) . ' 2>/dev/null');
      if ($whichOutput && trim($whichOutput) !== '') {
        $ffmpegPath = trim($whichOutput);
      }
      
      // Final fallback: try executing ffmpeg directly to verify it exists
      if (!$ffmpegPath) {
        $testOutput = shell_exec(escapeshellarg($ffmpegExe) . ' -version 2>&1 | head -1');
        if ($testOutput && strpos($testOutput, 'ffmpeg version') !== false) {
          $ffmpegPath = $ffmpegExe; // Use command name directly, let exec() find it in PATH
        }
      }
    }
  }
}

if (!$ffmpegPath || !file_exists($ffmpegPath)) {
  // Additional debug: log what we tried
  error_log("FFmpeg search failed. Tried: scripts folder, /usr/bin/ffmpeg, /usr/local/bin/ffmpeg, which command");
  error_log("PHP OS: " . PHP_OS . ", isWindows: " . ($isWindows ? 'true' : 'false'));
  die("ffmpeg not found. Please install it first. Location: /usr/bin/ffmpeg");
}

$filterString = "scale={$thumbWidth}:{$thumbHeight}:force_original_aspect_ratio=1,pad={$thumbWidth}:{$thumbHeight}:(ow-iw)/2:(oh-ih)/2";

if ($isWindows) {
  // Windows: use forward slashes and double quotes
  $uploadPathFwd = str_replace('\\', '/', $uploadVideoPath);
  $framePathFwd = str_replace('\\', '/', $frameFilePath);
  $ffmpegExePath = str_replace('\\', '/', $ffmpegPath);
  $thumbnailCommand = '"' . $ffmpegExePath . '" -ss ' . $frameTime . ' -i "' . $uploadPathFwd . '" ';
  $thumbnailCommand .= '-vf "' . $filterString . '" ';
  $thumbnailCommand .= '-vframes 1 "' . $framePathFwd . '" 2>&1';
} else {
  // Linux/Unix: use absolute path for ffmpeg to avoid PATH issues
  // Always use the full path we found earlier
  $thumbnailCommand = escapeshellarg($ffmpegPath) . ' -ss ' . $frameTime . ' -i ' . escapeshellarg($uploadVideoPath) . ' ';
  $thumbnailCommand .= '-vf ' . escapeshellarg($filterString) . ' ';
  $thumbnailCommand .= '-vframes 1 ' . escapeshellarg($frameFilePath) . ' 2>&1';
  
  // Log the command for debugging
  error_log("FFmpeg command: " . $thumbnailCommand);
}

// Execute the command with explicit PATH environment variable
if (!$isWindows) {
  // Set up environment with proper PATH for Linux
  $env = [
    'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'
  ];
  exec($thumbnailCommand, $output, $returnVar, $env);
} else {
  exec($thumbnailCommand, $output, $returnVar);
}

if ($returnVar !== 0) {
  error_log("Thumbnail generation failed: " . implode("\n", $output));
}

// Set proper permissions on thumbnail file (Linux/Unix)
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && file_exists($frameFilePath)) {
  chmod($frameFilePath, 0644);
}

// Prepare JSON data
$json_file = '../video/posts.json';
$posts = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
if (!is_array($posts)) {
  $posts = [];
}

// Check for duplicate using file hash (if provided)
$fileHash = isset($_GET['hash']) ? preg_replace('/[^a-f0-9]/', '', strtolower($_GET['hash'])) : null;
if ($fileHash) {
  foreach ($posts as $post) {
    if (isset($post['hash']) && $post['hash'] === $fileHash) {
      $error = generateMessageUrl('This video has already been uploaded (duplicate file detected).', 'error');
      header("Location: ../../$error");
      exit;
    }
  }
}

$new_post = [
  'PUID' => $PUID,
  'Time' => $Time,
  'video_path' => "/video/{$PUID}/{$newVideoName}",
  'thumbnail_path' => "/video/{$PUID}/{$frameFileName}",
  'title' => $videoTitle,
];

if ($fileHash) {
  $new_post['hash'] = $fileHash;
}

$posts[] = $new_post;

// Write to JSON file
$json_data = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($json_file, $json_data) === false) {
  die("Failed to write to JSON file. Check permissions.");
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
