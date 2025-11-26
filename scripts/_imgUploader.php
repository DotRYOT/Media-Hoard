<?php

require "./_inc.php";

// === Configuration === //
$configFile = '../config.json';
$uploadDir = '../img/imageFiles/';
$imageJsonFile = '../img/imageFiles/images.json';
$maxFiles = 20;
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// === Load Config === //
if (!file_exists($configFile)) {
  die("Config file not found: $configFile");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in config file.");
}

// Make sure upload directory exists
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

// === Handle Upload === //

if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
  $error = "No files uploaded or invalid request.";
  handleError($error);
  exit($error);
}

$uploadedFiles = $_FILES['images'];
$fileCount = count($uploadedFiles['name']);

if ($fileCount > $maxFiles) {
  $error = "You cannot upload more than $maxFiles images at once.";
  handleError($error);
  exit($error);
}

$savedFilenames = [];
$newImagesData = [];

for ($i = 0; $i < $fileCount; $i++) {
  $name = $uploadedFiles['name'][$i];
  $tmpName = $uploadedFiles['tmp_name'][$i];
  $error = $uploadedFiles['error'][$i];

  // Log file upload error code
  if ($error !== UPLOAD_ERR_OK) {
    $error = "Error uploading file: $name (Error Code: $error)";
    handleError($error);
    continue;
  }

  // Validate MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $tmpName);
  finfo_close($finfo);

  if (!in_array($mimeType, $allowedMimeTypes)) {
    $error = "File '$name' is not a valid image (MIME Type: $mimeType).";
    handleError($error);
    continue;
  }

  // Generate unique PUID
  $PUID = randStringGen(16, 'numbers');

  // Create folder for this image
  $folderPath = $uploadDir . $PUID;
  if (!mkdir($folderPath, 0777, true)) {
    $error = "Failed to create folder for PUID: $PUID";
    handleError($error);
    continue;
  }

  // Save image with filename like img_PUID.jpg
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $newFilename = "img_$PUID.$ext";
  $destination = $folderPath . '/' . $newFilename;

  // Log move attempt
  if (!move_uploaded_file($tmpName, $destination)) {
    $error = "Failed to move uploaded file: $name to $destination";
    handleError($error);
    continue;
  }

  // Add to new images data
  $newImagesData[] = [
    "PUID" => $PUID,
    "Time" => time(),
    "image_path" => "/img/imageFiles/$PUID/$newFilename"
  ];

  $savedFilenames[] = $newFilename;
}

if (empty($savedFilenames)) {
  $error = "No images were successfully uploaded.";
  handleError($error);
  exit($error);
}

// === Update images.json === //

// Ensure images.json exists
if (!file_exists($imageJsonFile)) {
  // If file doesn't exist, create it with empty array
  file_put_contents($imageJsonFile, '[]');
}

// Load existing images.json
try {
  $existingImages = json_decode(file_get_contents($imageJsonFile), true);
} catch (\Exception $e) {
  $error = "Failed to load images.json: " . $e->getMessage();
  handleError($error);
  exit($error);
}

if (json_last_error() !== JSON_ERROR_NONE) {
  $error = "Failed to parse images.json: " . json_last_error_msg();
  handleError($error);
  exit($error);
}

// Merge new images with existing ones
$updatedImages = array_merge($existingImages, $newImagesData);

// Write back to images.json
try {
  $jsonContent = json_encode($updatedImages, JSON_PRETTY_PRINT);
  if (file_put_contents($imageJsonFile, $jsonContent) === false) {
    $error = "Failed to write to images.json";
    handleError($error);
    exit($error);
  }
} catch (\Exception $e) {
  $error = "Failed to write to images.json: " . $e->getMessage();
  handleError($error);
  exit($error);
}

// === Success response === //
echo json_encode([
  "success" => true,
  "message" => "$fileCount image(s) uploaded successfully.",
  "files" => $savedFilenames
]);

exit();