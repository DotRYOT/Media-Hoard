<?php

require_once __DIR__ . "/../_inc.php";

$imageDir = "../../img/imageFiles";

if (is_dir($imageDir)) {
  try {
    deleteDirectory($imageDir);
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }
} else {
  echo "Directory '$imageDir' does not exist.";
}

if (!unlink("../../img/categories.json")) {
  error_log("Failed to delete categories.json – maybe already gone.");
}

if (!unlink("../../img/favoriteImages.json")) {
  error_log("Failed to delete favoriteImages.json – maybe already gone.");
}

error_reporting(E_ALL);
ini_set("display_errors", 1);

$success = generateMessageUrl("All images deleted successfully", "success");
header("Location: ../../setup.php?update=true");
exit();
