<?php

$projectDir = realpath(__DIR__ . '/../..');
$localVersionFile = $projectDir . '/version.php';
$remoteVersionUrl = 'https://raw.githubusercontent.com/DotRYOT/Media-Hoard/main/version.php';

function esc($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderPageStart()
{
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>MediaHoard Updater</title>';
  echo '<style>
    :root { color-scheme: dark; }
    body { margin: 0; font-family: Inter, Segoe UI, Arial, sans-serif; background: #0f1115; color: #e5e7eb; }
    .wrap { max-width: 880px; margin: 2rem auto; padding: 0 1rem; }
    .card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 1.1rem 1.2rem; }
    h2 { margin-top: 0; }
    .row { margin: .6rem 0; }
    .ok { color: #3fb950; }
    .warn { color: #d29922; }
    .err { color: #f85149; }
    pre { background: #0d1117; border: 1px solid #30363d; border-radius: 10px; padding: .8rem; overflow-x: auto; margin: .6rem 0; }
    a.btn { display: inline-block; text-decoration: none; background: #238636; color: #fff; border-radius: 10px; padding: .6rem .9rem; margin-top: .6rem; }
  </style></head><body><div class="wrap"><div class="card">';
  echo '<h2>MediaHoard Update Checker</h2>';
}

function renderPageEnd()
{
  echo '<a class="btn" href="../../setup.php?update=true">Return to Home</a>';
  echo '</div></div></body></html>';
}

function failAndExit($message)
{
  echo '<p class="err">' . esc($message) . '</p>';
  renderPageEnd();
  exit(1);
}

function info($message, $class = '')
{
  $className = $class ? ' class="' . esc($class) . '"' : '';
  echo '<p' . $className . '>' . esc($message) . '</p>';
  @flush();
}

function fetchRemoteFile($url)
{
  if (!function_exists('curl_init')) {
    $context = stream_context_create([
      'http' => [
        'timeout' => 15,
        'header' => "User-Agent: MediaHoard-Updater/1.0\r\n"
      ]
    ]);
    $result = @file_get_contents($url, false, $context);
    return $result === false ? null : $result;
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_USERAGENT, 'MediaHoard-Updater/1.0');
  $response = curl_exec($ch);
  $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false || $statusCode >= 400) {
    return null;
  }

  return $response;
}

function downloadBinaryFile($url, $destinationPath)
{
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MediaHoard-Updater/1.0');
    $data = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $statusCode >= 400) {
      return false;
    }
  } else {
    $context = stream_context_create([
      'http' => [
        'timeout' => 60,
        'header' => "User-Agent: MediaHoard-Updater/1.0\r\n"
      ]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
      return false;
    }
  }

  return file_put_contents($destinationPath, $data) !== false;
}

function removeDirectory($dir)
{
  if (!is_dir($dir)) {
    return;
  }

  $items = scandir($dir);
  if ($items === false) {
    return;
  }

  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }

    $path = $dir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path)) {
      removeDirectory($path);
    } else {
      @unlink($path);
    }
  }

  @rmdir($dir);
}

function shouldSkipPath($relativePath)
{
  $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
  $protectedPrefixes = [
    '.git/',
    'video/',
    'img/imageFiles/',
    'scripts/temp/',
    'cache/'
  ];

  $protectedFiles = [
    'config.json',
    'scripts/yt-dlp.exe',
    'video/posts.json',
    'video/favoriteVideos.json',
    'video/tags.json',
    'img/favoriteImages.json',
    'img/categories.json'
  ];

  foreach ($protectedPrefixes as $prefix) {
    if (strpos($normalized, $prefix) === 0) {
      return true;
    }
  }

  return in_array($normalized, $protectedFiles, true);
}

function copyDirectoryWithProtection($sourceDir, $targetDir)
{
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($iterator as $item) {
    $sourcePath = $item->getPathname();
    $relativePath = substr($sourcePath, strlen($sourceDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    if (shouldSkipPath($relativePath)) {
      continue;
    }

    $destinationPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if ($item->isDir()) {
      if (!is_dir($destinationPath)) {
        mkdir($destinationPath, 0777, true);
      }
      continue;
    }

    $destinationDir = dirname($destinationPath);
    if (!is_dir($destinationDir)) {
      mkdir($destinationDir, 0777, true);
    }

    if (!copy($sourcePath, $destinationPath)) {
      throw new RuntimeException('Failed to copy file: ' . $relativePath);
    }
  }
}

function runZipUpdate($projectDir)
{
  if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP Zip extension is required for non-git updates.');
  }

  $archiveUrl = 'https://codeload.github.com/DotRYOT/Media-Hoard/zip/refs/heads/main';
  $tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mediahoard_update_' . uniqid();
  $zipFile = $tempBase . '.zip';

  if (!downloadBinaryFile($archiveUrl, $zipFile)) {
    throw new RuntimeException('Failed to download update archive from GitHub.');
  }

  if (!mkdir($tempBase, 0777, true) && !is_dir($tempBase)) {
    @unlink($zipFile);
    throw new RuntimeException('Failed to create temporary directory for update.');
  }

  $zip = new ZipArchive();
  if ($zip->open($zipFile) !== true) {
    @unlink($zipFile);
    removeDirectory($tempBase);
    throw new RuntimeException('Failed to open downloaded update archive.');
  }

  if (!$zip->extractTo($tempBase)) {
    $zip->close();
    @unlink($zipFile);
    removeDirectory($tempBase);
    throw new RuntimeException('Failed to extract update archive.');
  }
  $zip->close();

  $entries = scandir($tempBase);
  $rootFolder = null;
  if ($entries !== false) {
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $fullPath = $tempBase . DIRECTORY_SEPARATOR . $entry;
      if (is_dir($fullPath)) {
        $rootFolder = $fullPath;
        break;
      }
    }
  }

  if ($rootFolder === null) {
    @unlink($zipFile);
    removeDirectory($tempBase);
    throw new RuntimeException('Extracted archive is missing root folder.');
  }

  copyDirectoryWithProtection($rootFolder, $projectDir);

  @unlink($zipFile);
  removeDirectory($tempBase);
}

renderPageStart();
@set_time_limit(120);
@ignore_user_abort(true);
@ob_implicit_flush(true);

if ($projectDir === false || !is_dir($projectDir)) {
  failAndExit('Project directory could not be resolved.');
}

if (!file_exists($localVersionFile)) {
  failAndExit('Local version.php not found.');
}

include $localVersionFile;
if (!isset($version) || trim($version) === '') {
  failAndExit('Local version variable is missing in version.php.');
}

$localVersion = trim($version);
$remoteVersionFile = fetchRemoteFile($remoteVersionUrl);

if ($remoteVersionFile === null) {
  failAndExit('Could not fetch remote version information from GitHub.');
}

if (!preg_match('/\$version\s*=\s*[\"\']([^\"\']+)[\"\']\s*;/', $remoteVersionFile, $matches)) {
  failAndExit('Remote version format is invalid.');
}

$remoteVersion = trim($matches[1]);

info('Local Version: ' . $localVersion);
info('Remote Version: ' . $remoteVersion);

if (version_compare($localVersion, $remoteVersion, '>=')) {
  info('Already up to date. No update required.', 'ok');
  renderPageEnd();
  exit(0);
}

info('New version detected. Starting update...', 'warn');

try {
  info('Using ZIP update mode...', 'warn');
  runZipUpdate($projectDir);

  include $localVersionFile;
  $updatedVersion = isset($version) ? trim($version) : 'unknown';
  info('Update complete. Current version: ' . $updatedVersion, 'ok');
} catch (Throwable $exception) {
  failAndExit($exception->getMessage());
}

renderPageEnd();
