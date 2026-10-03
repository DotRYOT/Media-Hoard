<?php

$frameTime = $_POST['frameTime'];
$thumbWidth = $_POST['thumbWidth'];
$thumbHeight = $_POST['thumbHeight'];
$videoExtension = $_POST['videoExtension'];
$openMediaTab = isset($_POST['openMediaTab']) ? 'true' : 'false';
$maxFilesInput = isset($_POST['maxFiles']) ? (int) $_POST['maxFiles'] : 100;
$maxFiles = max(1, $maxFilesInput);

/**
 * Validate an optional absolute path override for yt-dlp/ffmpeg.
 * Returns '' when the field is blank, null when the value must be rejected.
 */
function sanitizeToolPathOverride($value)
{
  $value = trim((string)$value);
  if ($value === '') {
    return '';
  }
  // Absolute unix path or Windows path (C:\..., C:/..., UNC)
  if (!preg_match('#^(/[^\r\n"\\\\]+|[A-Za-z]:[\\\\/][^\r\n"]*)#', $value)) {
    return null;
  }
  if (strlen($value) > 512) {
    return null;
  }
  return $value;
}

$configFile = __DIR__ . '/../../config.json';
if (!file_exists($configFile)) {
  die("Config file not found: $configFile");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in config file.");
}

$config['frameTime'] = $frameTime;
$config['thumbWidth'] = $thumbWidth;
$config['thumbHeight'] = $thumbHeight;
$config['videoExtension'] = $videoExtension;
$config['openMediaTab'] = $openMediaTab;
$config['maxFiles'] = (string) $maxFiles;

// Optional absolute paths for yt-dlp / ffmpeg. Needed when the tool is only
// installed for a user account (pip --user, pyenv, conda) that the web server
// cannot see through its PATH.
$ytDlpPathInput = sanitizeToolPathOverride($_POST['ytDlpPath'] ?? '');
$ffmpegPathInput = sanitizeToolPathOverride($_POST['ffmpegPath'] ?? '');
if ($ytDlpPathInput === null || $ffmpegPathInput === null) {
  header('Location: ../../settings/?error=' . urlencode('Tool paths must be absolute (e.g. /usr/bin/yt-dlp or C:\\tools\\yt-dlp.exe).'));
  exit();
}
if ($ytDlpPathInput !== '' && !file_exists($ytDlpPathInput)) {
  header('Location: ../../settings/?error=' . urlencode('yt-dlp path does not exist: ' . $ytDlpPathInput));
  exit();
}
if ($ffmpegPathInput !== '' && !file_exists($ffmpegPathInput)) {
  header('Location: ../../settings/?error=' . urlencode('ffmpeg path does not exist: ' . $ffmpegPathInput));
  exit();
}
if ($ytDlpPathInput === '') {
  unset($config['ytDlpPath']);
} else {
  $config['ytDlpPath'] = $ytDlpPathInput;
}
if ($ffmpegPathInput === '') {
  unset($config['ffmpegPath']);
} else {
  $config['ffmpegPath'] = $ffmpegPathInput;
}

file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

header('Location: ../../settings/');
exit();
