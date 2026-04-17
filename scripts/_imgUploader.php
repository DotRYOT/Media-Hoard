<?php

ob_start(); // Buffer all output so PHP warnings don't corrupt the JSON response

require "./_inc.php";

// Return a JSON error response and exit (safe for AJAX endpoints)
function jsonError($message) {
  ob_clean();
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => $message]);
  exit();
}

// === Configuration === //
$configFile = '../config.json';
$uploadDir = '../img/imageFiles/';
$imageJsonFile = '../img/imageFiles/images.json';
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$categoriesFile = '../img/categories.json';

// === Load Config === //
if (!file_exists($configFile)) {
  die("Config file not found: $configFile");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in config file.");
}

$maxFiles = isset($config['maxFiles']) ? (int) $config['maxFiles'] : 20;
if ($maxFiles < 1) {
  $maxFiles = 20;
}

// Make sure upload directory exists
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

// === Handle Upload === //

if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
  jsonError("No files uploaded or invalid request.");
}

$uploadedFiles = $_FILES['images'];
$fileCount = count($uploadedFiles['name']);

if ($fileCount > $maxFiles) {
  jsonError("You cannot upload more than $maxFiles images at once.");
}

$savedFilenames = [];
$newImagesData = [];

for ($i = 0; $i < $fileCount; $i++) {
  $name = $uploadedFiles['name'][$i];
  $tmpName = $uploadedFiles['tmp_name'][$i];
  $error = $uploadedFiles['error'][$i];

  // Log file upload error code
  if ($error !== UPLOAD_ERR_OK) {
    error_log("Upload error for file $name: code $error");
    continue; // skip this file, keep processing others
  }

  // Validate MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $tmpName);
  finfo_close($finfo);

  if (!in_array($mimeType, $allowedMimeTypes)) {
    error_log("Invalid MIME type for $name: $mimeType");
    continue; // skip this file, keep processing others
  }

  // Generate unique PUID
  $PUID = randStringGen(16, 'numbers');

  // Create folder for this image
  $folderPath = $uploadDir . $PUID;
  if (!mkdir($folderPath, 0777, true)) {
    error_log("Failed to create folder for PUID: $PUID");
    continue;
  }

  // Save image with filename like img_PUID.jpg
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $newFilename = "img_$PUID.$ext";
  $destination = $folderPath . '/' . $newFilename;

  // Log move attempt
  if (!move_uploaded_file($tmpName, $destination)) {
    error_log("Failed to move uploaded file: $name to $destination");
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
  jsonError("No images were successfully uploaded.");
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
  jsonError("Failed to load images.json: " . $e->getMessage());
}

if (json_last_error() !== JSON_ERROR_NONE) {
  jsonError("Failed to parse images.json: " . json_last_error_msg());
}

// Merge new images with existing ones
$updatedImages = array_merge($existingImages, $newImagesData);

// Write back to images.json
try {
  $jsonContent = json_encode($updatedImages, JSON_PRETTY_PRINT);
  if (file_put_contents($imageJsonFile, $jsonContent) === false) {
    jsonError("Failed to write to images.json");
  }
} catch (\Exception $e) {
  jsonError("Failed to write to images.json: " . $e->getMessage());
}

// === Handle Categories === //
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
if ($category !== '') {
  // Ensure categories.json exists
  if (!file_exists($categoriesFile)) {
    file_put_contents($categoriesFile, '{}');
  }

  // Load existing categories
  $existingCategories = json_decode(file_get_contents($categoriesFile), true);
  if (!is_array($existingCategories)) {
    $existingCategories = [];
  }

  // Add category to each uploaded image
  foreach ($newImagesData as $imageData) {
    $puid = $imageData['PUID'];
    // Split by comma if multiple categories provided
    $categoryList = array_map('trim', explode(',', $category));
    $categoryList = array_filter($categoryList, fn($c) => $c !== '');
    $categoryList = array_values(array_unique($categoryList));
    if (count($categoryList) > 0) {
      $existingCategories[$puid] = $categoryList;
    }
  }

  // Write back to categories.json
  file_put_contents($categoriesFile, json_encode($existingCategories, JSON_PRETTY_PRINT));
}

// === Success response === //
$savedCount = count($savedFilenames);
ob_clean();
header('Content-Type: application/json');
echo json_encode([
  "success" => true,
  "message" => "$savedCount image(s) uploaded successfully.",
  "files" => $savedFilenames
]);

exit();