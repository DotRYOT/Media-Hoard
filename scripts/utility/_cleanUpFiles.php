<?php
require_once __DIR__ . '/../_inc.php';

$projectRoot = realpath(__DIR__ . '/../..');

/**
 * Parse .gitignore into an array of pattern strings.
 */
function parseGitignore(string $path): array
{
  if (!file_exists($path)) {
    return [];
  }
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $patterns = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
      continue;
    }
    $patterns[] = $line;
  }
  return $patterns;
}

/**
 * Returns true if $relPath (relative to project root, forward-slashes)
 * matches any pattern in $patterns using basic gitignore semantics.
 */
function isGitIgnored(string $relPath, array $patterns): bool
{
  $relPath  = str_replace('\\', '/', $relPath);
  $filename = basename($relPath);

  foreach ($patterns as $pattern) {
    // Skip negation patterns — not needed here
    if ($pattern[0] === '!') {
      continue;
    }

    // Directory-only patterns (e.g. "video/") — test if path starts with it
    if (substr($pattern, -1) === '/') {
      $dir = rtrim($pattern, '/');
      if ($relPath === $dir || strpos($relPath, $dir . '/') === 0) {
        return true;
      }
      continue;
    }

    // Root-anchored pattern (starts with /) — match against the full rel path
    if ($pattern[0] === '/') {
      if (fnmatch(ltrim($pattern, '/'), $relPath)) {
        return true;
      }
      continue;
    }

    // Pattern containing a slash — match against the relative path
    if (strpos($pattern, '/') !== false) {
      if (fnmatch($pattern, $relPath)) {
        return true;
      }
      continue;
    }

    // Plain name or glob — match against filename or full rel path
    if (fnmatch($pattern, $filename) || fnmatch($pattern, $relPath)) {
      return true;
    }
  }

  return false;
}

$gitignorePatterns = parseGitignore($projectRoot . '/.gitignore');

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
    '.gitignore',
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
    'categories.json',
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

    // Never delete dotfiles (.gitignore, .htaccess, etc.)
    if ($item[0] === '.') {
      continue;
    }

    // Respect .gitignore — locally-present gitignored files are kept for dev/testing
    $relFilePath = ($relDir === '' ? '' : $relDir . '/') . $item;
    if (isGitIgnored($relFilePath, $gitignorePatterns)) {
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
