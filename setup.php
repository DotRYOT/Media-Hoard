<?php

$root = __DIR__;
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

function setupDirectory($path)
{
  if (is_dir($path)) {
    return;
  }

  if (!mkdir($path, 0755, true) && !is_dir($path)) {
    throw new RuntimeException("Unable to create directory: {$path}");
  }
}

function setupFile($path, $contents)
{
  if (file_exists($path)) {
    return;
  }

  if (file_put_contents($path, $contents, LOCK_EX) === false) {
    throw new RuntimeException("Unable to create file: {$path}");
  }
}

foreach ([
  $root . '/video',
  $root . '/scripts/temp',
  $root . '/img/imageFiles',
  $root . '/scripts/temp/videos'
] as $directory) {
  setupDirectory($directory);
}

setupFile($root . '/video/posts.json', json_encode([]));
setupFile($root . '/img/imageFiles/images.json', json_encode([]));

// Ensure proper permissions on Linux/Unix systems
if (!$isWindows) {
  foreach ([$root . '/video', $root . '/scripts/temp', $root . '/scripts/temp/videos', $root . '/img/imageFiles'] as $directory) {
    chmod($directory, 0755);
  }
  chmod($root . '/video/posts.json', 0644);
  chmod($root . '/img/imageFiles/images.json', 0644);
}

//
//
// Only copy scripts under this
//
//

if (!file_exists($root . '/video/favoriteVideos.json')) {
  if (!copy($root . '/scripts/utility/favoriteVideos.json', $root . '/video/favoriteVideos.json')) {
    throw new RuntimeException('Unable to copy video favorites file. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/video/favoriteVideos.json', 0664);
}

if (!file_exists($root . '/video/tags.json')) {
  if (!copy($root . '/scripts/utility/videoTags.json', $root . '/video/tags.json')) {
    throw new RuntimeException('Unable to copy video tags file. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/video/tags.json', 0644);
}

if (!file_exists($root . '/img/favoriteImages.json')) {
  if (!copy($root . '/scripts/utility/favoriteImages.json', $root . '/img/favoriteImages.json')) {
    throw new RuntimeException('Unable to copy image favorites file. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/img/favoriteImages.json', 0664);
}

if (!file_exists($root . '/img/categories.json')) {
  setupFile($root . '/img/categories.json', json_encode(new stdClass()));
  if (!$isWindows) chmod($root . '/img/categories.json', 0644);
}

if (!file_exists($root . '/video/_video.php')) {
  if (!copy($root . '/scripts/_video.php', $root . '/video/_video.php')) {
    throw new RuntimeException('Unable to copy video handler. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/video/_video.php', 0644);
}

if (!file_exists($root . '/img/imageFiles/_img.php')) {
  if (!copy($root . '/scripts/_img.php', $root . '/img/imageFiles/_img.php')) {
    throw new RuntimeException('Unable to copy image handler. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/img/imageFiles/_img.php', 0644);
}

if (!file_exists($root . '/config.json')) {
  if (!copy($root . '/scripts/utility/config.json', $root . '/config.json')) {
    throw new RuntimeException('Unable to copy configuration file. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/config.json', 0644);
}

if (!file_exists($root . '/favicon.png')) {
  if (!copy($root . '/scripts/utility/favicon.png', $root . '/favicon.png')) {
    throw new RuntimeException('Unable to copy favicon. Check the project permissions.');
  }
  if (!$isWindows) chmod($root . '/favicon.png', 0644);
}

if (isset($_GET['update'])) {
  if ($_GET['update'] == "true") {
    header("Location: ./");
    exit();
  }
}
