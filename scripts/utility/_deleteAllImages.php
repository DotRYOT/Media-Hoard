<?php

require_once __DIR__ . '/../_inc.php';

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

$success = generateMessageUrl("All images deleted successfully", 'success');
header("Location: ../../setup.php?update=true");
exit();
