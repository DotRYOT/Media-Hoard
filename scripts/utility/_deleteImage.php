<?php
require_once "../_inc.php";

$PUID = $_REQUEST['puid'];
$json = json_decode(file_get_contents("../../img/imageFiles/images.json"), true);

// Find and remove the image using the PUID
$found = false;
foreach ($json as $key => $entry) {
  if (isset($entry['PUID']) && $entry['PUID'] === $PUID) {
    array_splice($json, $key, 1);
    $found = true;
    break;
  }
}

file_put_contents("../../img/imageFiles/images.json", json_encode($json, JSON_PRETTY_PRINT));

// Also remove from categories.json if exists
$categoriesFile = "../../img/categories.json";
if (file_exists($categoriesFile)) {
  $categories = json_decode(file_get_contents($categoriesFile), true);
  if (is_array($categories) && isset($categories[$PUID])) {
    unset($categories[$PUID]);
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
  }
}

$success = generateMessageUrl("Image deleted successfully.", 'success');
header("Location: ../../img/{$success}");