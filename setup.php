<?php

// Make sure the /video directory exists
if (!is_dir("./video")) {
  mkdir("./video", 0755, true);
}

// Make sure the /scripts/temp directory exists
if (!is_dir("./scripts/temp")) {
  mkdir("./scripts/temp", 0755, true);
}

if (!is_dir("./img/imageFiles")) {
  mkdir("./img/imageFiles", 0755, true);
}

// Make sure the /scripts/temp/videos directory exists
if (!is_dir("./scripts/temp/videos")) {
  mkdir("./scripts/temp/videos", 0755, true);
}

// Make sure the /cache directory exists
if (!is_dir("./cache")) {
  mkdir("./cache", 0755, true);
}

// Make sure the /video/posts.json file exists
if (!file_exists("./video/posts.json")) {
  file_put_contents("./video/posts.json", json_encode([]));
}

// Make sure the /img/imageFiles/images.json file exists
if (!file_exists("./img/imageFiles/images.json")) {
  file_put_contents("./img/imageFiles/images.json", json_encode([]));
}

// Ensure proper permissions on Linux/Unix systems
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
  chmod("./video", 0755);
  chmod("./scripts/temp", 0755);
  chmod("./scripts/temp/videos", 0755);
  chmod("./img/imageFiles", 0755);
  chmod("./cache", 0755);
  if (file_exists("./video/posts.json")) chmod("./video/posts.json", 0644);
  if (file_exists("./img/imageFiles/images.json")) chmod("./img/imageFiles/images.json", 0644);
}

//
//
// Only copy scripts under this
//
//

if (!file_exists("./video/favoriteVideos.json")) {
  copy("./scripts/utility/favoriteVideos.json", "./video/favoriteVideos.json");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./video/favoriteVideos.json", 0644);
}

if (!file_exists("./video/tags.json")) {
  copy("./scripts/utility/videoTags.json", "./video/tags.json");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./video/tags.json", 0644);
}

if (!file_exists("./img/favoriteImages.json")) {
  copy("./scripts/utility/favoriteImages.json", "./img/favoriteImages.json");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./img/favoriteImages.json", 0644);
}

if (!file_exists("./img/categories.json")) {
  file_put_contents("./img/categories.json", json_encode(new stdClass()));
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./img/categories.json", 0644);
}

if (!file_exists("./video/_video.php")) {
  copy("./scripts/_video.php", "./video/_video.php");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./video/_video.php", 0644);
}

if (!file_exists("./img/imageFiles/_img.php")) {
  copy("./scripts/_img.php", "./img/imageFiles/_img.php");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./img/imageFiles/_img.php", 0644);
}

if (!file_exists("./config.json")) {
  copy("./scripts/utility/config.json", "./config.json");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./config.json", 0644);
}

if (!file_exists("./favicon.png")) {
  copy("./scripts/utility/favicon.png", "./favicon.png");
  if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') chmod("./favicon.png", 0644);
}

if (isset($_GET['update'])) {
  if ($_GET['update'] == "true") {
    header("Location: ./");
    exit();
  }
}