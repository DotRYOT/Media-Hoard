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

// Check if a file was uploaded
if (!isset($_FILES['videos']) || $_FILES['videos']['error'] !== UPLOAD_ERR_OK) {
  $error = "File upload error.";
  handleError($error);
  exit($error);
}

$videoExtension = $config['videoExtension'] ?? 'mp4';

// Get uploaded file info
$uploadedFile = $_FILES['videos'];
$fileName = $uploadedFile['name'];
$tmpName = $uploadedFile['tmp_name'];
$fileSize = $uploadedFile['size'];
$fileError = $uploadedFile['error'];

// Validate file type is a video
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpName);
finfo_close($finfo);

if (strpos($mimeType, 'video/') !== 0) {
  $error = "Uploaded file is not a valid video.";
  handleError($error);
  exit($error);
}

// Generate random filename
$randNumber = randStringGen(16, 'numbers');
$outputFileName = './temp/videos/' . $randNumber . '.' . $videoExtension;

// Move uploaded file to desired location and convert if needed
if ($videoExtension === 'mp4') {
  // If same format, just move the file
  if (!move_uploaded_file($tmpName, $outputFileName)) {
    $error = "Failed to move uploaded file.";
    handleError($error);
    exit($error);
  }
} else {
  if (!move_uploaded_file($tmpName, $outputFileName)) {
    $error = "Failed to process uploaded file.";
    handleError($error);
    exit($error);
  }
}

// Use original filename as title for download
$safeTitle = urlencode(basename($fileName, pathinfo($fileName, PATHINFO_EXTENSION)));

// Redirect to the download page with the sanitized title
header('Location: ./_downloadedVideo.php/?url=' . $randNumber . '.' . $videoExtension . '&title=' . $safeTitle);
exit();