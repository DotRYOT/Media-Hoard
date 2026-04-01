<?php
require_once __DIR__ . '/../_inc.php';

$projectRoot = realpath(__DIR__ . '/../..');

/**
 * Expected file structure for managed/static directories.
 * Paths are relative to the project root.
 *
 * Only files explicitly listed here are kept — anything else in these
 * directories will be deleted (stale CSS maps, renamed/moved files, etc.).
 *
 * Subdirectories within each entry are intentionally skipped to avoid
 * touching user data (PUID folders, etc.).
 */
$expectedStructure = [

  // Project root — static files only
  '' => [
    'config.json',
    'favicon.png',
    'index.php',
    'index.scss',
    'LICENSE',
    'readme.md',
    'setup.php',
    'version.php',
  ],

  // Compiled CSS assets
  'css' => [
    'imagePage.min.css',
    'index.min.css',
    'videoPage.min.css',
  ],

  // Settings page compiled CSS
  'settings/css' => [
    'index.min.css',
  ],

  // Settings page static files
  'settings' => [
    'index.php',
  ],

  // Video directory fixed files (PUID subdirs are user data — not touched)
  'video' => [
    '_video.php',
    'favoriteVideos.json',
    'posts.json',
    'tags.json',
  ],

  // Image directory fixed files
  'img' => [
    'favoriteImages.json',
    'index.php',
  ],

  // Image files directory fixed files (PUID subdirs are user data — not touched)
  'img/imageFiles' => [
    '_img.php',
    'images.json',
  ],

];

$deleted = [];
$failed  = [];

foreach ($expectedStructure as $relDir => $allowedFiles) {
  $dirPath = ($relDir === '') ? $projectRoot : $projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

  if (!is_dir($dirPath)) {
    continue;
  }

  $items = scandir($dirPath);
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }

    $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;

    // Only remove files — directories are skipped to protect user data
    if (is_dir($fullPath)) {
      continue;
    }

    // Never delete JSON files — they contain critical user data
    if (strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'json') {
      continue;
    }

    if (!in_array($item, $allowedFiles, true)) {
      if (@unlink($fullPath)) {
        $deleted[] = ($relDir === '' ? '' : $relDir . '/') . $item;
      } else {
        $failed[] = ($relDir === '' ? '' : $relDir . '/') . $item;
      }
    }
  }
}

if (!empty($failed)) {
  $msg = generateMessageUrl('Stale file cleanup done. Could not delete: ' . implode(', ', $failed), 'warning');
} elseif (!empty($deleted)) {
  $msg = generateMessageUrl('Deleted stale files: ' . implode(', ', $deleted), 'success');
} else {
  $msg = generateMessageUrl('No stale files found — everything looks clean.', 'success');
}

header("Location: ../../settings/$msg");
exit();
