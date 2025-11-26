<?php

// Make sure the /video directory exists
if (!is_dir("./video")) {
  mkdir("./video");
}

// Make sure the /scripts/temp directory exists
if (!is_dir("./scripts/temp")) {
  mkdir("./scripts/temp");
}

if (!is_dir("./img/imageFiles")) {
  mkdir("./img/imageFiles");
}

// Make sure the /scripts/temp/videos directory exists
if (!is_dir("./scripts/temp/videos")) {
  mkdir("./scripts/temp/videos");
}

// Make sure the /cache directory exists
if (!is_dir("./cache")) {
  mkdir("./cache");
}

// Make sure the /video/posts.json file exists
if (!file_exists("./video/posts.json")) {
  file_put_contents("./video/posts.json", json_encode([]));
}

// Make sure the /video/posts.json file exists
if (!file_exists("./img/imageFiles/images.json")) {
  file_put_contents("./img/imageFiles/images.json", json_encode([]));
}

// Copy the _videoPage.php to the video directory
if (!file_exists("./video/_video.php")) {
  copy("./scripts/_video.php", "./video/_video.php");
}

// Copy the _img.php to the img directory
if (!file_exists("./img/imageFiles/_img.php")) {
  copy("./scripts/_img.php", "./img/imageFiles/_img.php");
}

// Copy the config.json to the root directory
if (!file_exists("./config.json")) {
  copy("./scripts/config.json", "./config.json");
}

// Copy the .htaccess file to the root directory
if (!file_exists("./.htaccess")) {
  copy("./scripts/utility/.htaccess", "./.htaccess");
}

// Copy the favicon.png file to the root directory
if (!file_exists("./favicon.png")) {
  copy("./scripts/utility/favicon.png", "./favicon.png");
}

if (isset($_GET['update'])) {
  if ($_GET['update'] == "true") {
    header("Location: ./");
    exit();
  }
}