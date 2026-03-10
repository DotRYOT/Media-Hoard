<?php
require __DIR__ . '/../_inc.php';

header('Content-Type: application/json');

function parse_time_to_seconds($input)
{
  if (!is_string($input)) {
    return false;
  }

  $input = trim($input);
  if ($input === '') {
    return false;
  }

  if (is_numeric($input)) {
    return (float) $input;
  }

  if (!preg_match('/^\d{1,2}:\d{1,2}(:\d{1,2}(\.\d+)?)?$/', $input)) {
    return false;
  }

  $parts = explode(':', $input);
  if (count($parts) === 2) {
    $minutes = (int) $parts[0];
    $seconds = (float) $parts[1];
    return ($minutes * 60) + $seconds;
  }

  $hours = (int) $parts[0];
  $minutes = (int) $parts[1];
  $seconds = (float) $parts[2];
  return ($hours * 3600) + ($minutes * 60) + $seconds;
}

function get_video_duration($filePath)
{
  $ffprobeBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'ffprobe.exe' : 'ffprobe';
  $cmd = $ffprobeBinary . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath) . ' 2>&1';
  $output = [];
  $code = 0;
  exec($cmd, $output, $code);

  if ($code !== 0 || count($output) === 0) {
    return false;
  }

  $duration = trim(implode("\n", $output));
  if (!is_numeric($duration)) {
    return false;
  }

  return (float) $duration;
}

function has_audio_stream($filePath)
{
  $ffprobeBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'ffprobe.exe' : 'ffprobe';
  $cmd = $ffprobeBinary . ' -v error -select_streams a -show_entries stream=index -of csv=p=0 ' . escapeshellarg($filePath) . ' 2>&1';
  $output = [];
  $code = 0;
  exec($cmd, $output, $code);

  if ($code !== 0) {
    return false;
  }

  foreach ($output as $line) {
    if (trim($line) !== '') {
      return true;
    }
  }

  return false;
}

function regenerate_thumbnail($videoPath, $thumbPath)
{
  $configFile = __DIR__ . '/../../config.json';
  $frameTime = 5;
  $thumbWidth = 640;
  $thumbHeight = 360;

  if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (is_array($config)) {
      $frameTime = isset($config['frameTime']) ? (int) $config['frameTime'] : $frameTime;
      $thumbWidth = isset($config['thumbWidth']) ? (int) $config['thumbWidth'] : $thumbWidth;
      $thumbHeight = isset($config['thumbHeight']) ? (int) $config['thumbHeight'] : $thumbHeight;
    }
  }

  $ffmpegBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'ffmpeg.exe' : 'ffmpeg';
  $filterString = "scale={$thumbWidth}:{$thumbHeight}:force_original_aspect_ratio=1,pad={$thumbWidth}:{$thumbHeight}:(ow-iw)/2:(oh-ih)/2";
  $thumbCmd = $ffmpegBinary . ' -y -ss ' . escapeshellarg((string) $frameTime) . ' -i ' . escapeshellarg($videoPath) . ' -vf ' . escapeshellarg($filterString) . ' -vframes 1 ' . escapeshellarg($thumbPath) . ' 2>&1';
  $thumbOutput = [];
  $thumbCode = 0;
  exec($thumbCmd, $thumbOutput, $thumbCode);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['success' => false, 'error' => 'Invalid request body']);
  exit;
}

if (!isset($data['puid'], $data['startTime'], $data['endTime'])) {
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit;
}

$puid = filter_user_input($data['puid'], 'string');
if ($puid === false || $puid === '') {
  echo json_encode(['success' => false, 'error' => 'Invalid video id']);
  exit;
}

$start = parse_time_to_seconds((string) $data['startTime']);
$end = parse_time_to_seconds((string) $data['endTime']);

if ($start === false || $end === false) {
  echo json_encode(['success' => false, 'error' => 'Invalid time format. Use seconds or HH:MM:SS']);
  exit;
}

$start = max(0, (float) $start);
$end = max(0, (float) $end);

if ($end <= $start) {
  echo json_encode(['success' => false, 'error' => 'End time must be greater than start time']);
  exit;
}

$postsPath = __DIR__ . '/../../video/posts.json';
if (!file_exists($postsPath)) {
  echo json_encode(['success' => false, 'error' => 'Posts file not found']);
  exit;
}

$posts = json_decode(file_get_contents($postsPath), true);
if (!is_array($posts)) {
  echo json_encode(['success' => false, 'error' => 'Invalid posts data']);
  exit;
}

$currentPost = null;
foreach ($posts as $post) {
  if (isset($post['PUID']) && (string) $post['PUID'] === (string) $puid) {
    $currentPost = $post;
    break;
  }
}

if (!$currentPost || !isset($currentPost['video_path'])) {
  echo json_encode(['success' => false, 'error' => 'Video not found']);
  exit;
}

$videoRelativePath = ltrim((string) $currentPost['video_path'], '/');
$videoFilePath = __DIR__ . '/../../' . str_replace('/', DIRECTORY_SEPARATOR, $videoRelativePath);

if (!file_exists($videoFilePath)) {
  echo json_encode(['success' => false, 'error' => 'Video file not found']);
  exit;
}

$duration = get_video_duration($videoFilePath);
if ($duration !== false) {
  if ($start >= $duration) {
    echo json_encode(['success' => false, 'error' => 'Start time is beyond video duration']);
    exit;
  }

  if ($end > $duration) {
    $end = $duration;
  }

  if (($end - $start) >= $duration - 0.01) {
    echo json_encode(['success' => false, 'error' => 'Section removes the entire video']);
    exit;
  }
}

$videoDir = dirname($videoFilePath);
$extension = pathinfo($videoFilePath, PATHINFO_EXTENSION);
$tmpOutputPath = $videoDir . DIRECTORY_SEPARATOR . 'edited_' . $puid . '_' . time() . '.' . $extension;
$backupPath = $videoFilePath . '.bak';

$startArg = number_format($start, 3, '.', '');
$endArg = number_format($end, 3, '.', '');

$ffmpegBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'ffmpeg.exe' : 'ffmpeg';
$hasAudio = has_audio_stream($videoFilePath);
$isIntroCut = $start <= 0.001;
$isOutroCut = ($duration !== false) ? ($end >= ($duration - 0.001)) : false;

$output = [];
$code = 0;

if ($hasAudio) {
  if ($isIntroCut) {
    $filterWithAudio = "[0:v]trim=start={$endArg},setpts=PTS-STARTPTS[v];[0:a]atrim=start={$endArg},asetpts=PTS-STARTPTS[a]";
  } elseif ($isOutroCut) {
    $filterWithAudio = "[0:v]trim=0:{$startArg},setpts=PTS-STARTPTS[v];[0:a]atrim=0:{$startArg},asetpts=PTS-STARTPTS[a]";
  } else {
    $filterWithAudio = "[0:v]trim=0:{$startArg},setpts=PTS-STARTPTS[v0];[0:v]trim=start={$endArg},setpts=PTS-STARTPTS[v1];[0:a]atrim=0:{$startArg},asetpts=PTS-STARTPTS[a0];[0:a]atrim=start={$endArg},asetpts=PTS-STARTPTS[a1];[v0][a0][v1][a1]concat=n=2:v=1:a=1[v][a]";
  }

  $commandWithAudio = $ffmpegBinary . ' -y -fflags +genpts -i ' . escapeshellarg($videoFilePath) . ' -filter_complex ' . escapeshellarg($filterWithAudio) . ' -map "[v]" -map "[a]" -c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 128k -avoid_negative_ts make_zero -movflags +faststart ' . escapeshellarg($tmpOutputPath) . ' 2>&1';
  exec($commandWithAudio, $output, $code);
} else {
  if ($isIntroCut) {
    $filterVideoOnly = "[0:v]trim=start={$endArg},setpts=PTS-STARTPTS[v]";
  } elseif ($isOutroCut) {
    $filterVideoOnly = "[0:v]trim=0:{$startArg},setpts=PTS-STARTPTS[v]";
  } else {
    $filterVideoOnly = "[0:v]trim=0:{$startArg},setpts=PTS-STARTPTS[v0];[0:v]trim=start={$endArg},setpts=PTS-STARTPTS[v1];[v0][v1]concat=n=2:v=1:a=0[v]";
  }

  $commandVideoOnly = $ffmpegBinary . ' -y -fflags +genpts -i ' . escapeshellarg($videoFilePath) . ' -filter_complex ' . escapeshellarg($filterVideoOnly) . ' -map "[v]" -c:v libx264 -preset veryfast -crf 23 -avoid_negative_ts make_zero -movflags +faststart ' . escapeshellarg($tmpOutputPath) . ' 2>&1';
  exec($commandVideoOnly, $output, $code);
}

if ($code !== 0 || !file_exists($tmpOutputPath)) {
  echo json_encode([
    'success' => false,
    'error' => 'Unable to process video section removal',
    'details' => implode("\n", $output)
  ]);
  exit;
}

@unlink($backupPath);
if (!@rename($videoFilePath, $backupPath)) {
  @unlink($tmpOutputPath);
  echo json_encode(['success' => false, 'error' => 'Unable to create backup of original video']);
  exit;
}

if (!@rename($tmpOutputPath, $videoFilePath)) {
  @rename($backupPath, $videoFilePath);
  @unlink($tmpOutputPath);
  echo json_encode(['success' => false, 'error' => 'Unable to replace original video']);
  exit;
}

@unlink($backupPath);

if (isset($currentPost['thumbnail_path'])) {
  $thumbRelativePath = ltrim((string) $currentPost['thumbnail_path'], '/');
  $thumbPath = __DIR__ . '/../../' . str_replace('/', DIRECTORY_SEPARATOR, $thumbRelativePath);
  regenerate_thumbnail($videoFilePath, $thumbPath);
}

$newDuration = get_video_duration($videoFilePath);

echo json_encode([
  'success' => true,
  'puid' => $puid,
  'startRemoved' => $start,
  'endRemoved' => $end,
  'duration' => $newDuration
]);
