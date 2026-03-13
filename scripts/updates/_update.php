<?php

$repoUrl = 'https://github.com/DotRYOT/Media-Hoard.git';
$branch = 'main';
$projectDir = realpath(__DIR__ . '/../..');
$localVersionFile = $projectDir . '/version.php';
$remoteVersionUrl = 'https://raw.githubusercontent.com/DotRYOT/Media-Hoard/refs/heads/main/version.php';

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

function runGit($projectDir, $args)
{
  $gitDirArg = escapeshellarg($projectDir);
  $safeDirArg = escapeshellarg($projectDir);
  $command = 'git -c safe.directory=' . $safeDirArg . ' -C ' . $gitDirArg . ' ' . $args . ' 2>&1';
  exec($command, $output, $code);
  $text = implode("\n", $output);

  echo '<pre>' . esc('$ ' . $command . "\n" . $text) . '</pre>';

  if ($code !== 0) {
    throw new RuntimeException('Git command failed: ' . $args);
  }

  return $text;
}

renderPageStart();

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

if (!is_dir($projectDir . '/.git')) {
  failAndExit('This installation is not a Git checkout (.git missing).');
}

try {
  runGit($projectDir, '--version');

  $remotes = runGit($projectDir, 'remote');
  if (strpos($remotes, 'origin') === false) {
    info('Origin remote not found. Adding origin...', 'warn');
    runGit($projectDir, 'remote add origin ' . escapeshellarg($repoUrl));
  }

  runGit($projectDir, 'fetch origin --prune');
  runGit($projectDir, 'checkout ' . escapeshellarg($branch));
  runGit($projectDir, 'reset --hard ' . escapeshellarg('origin/' . $branch));

  include $localVersionFile;
  $updatedVersion = isset($version) ? trim($version) : 'unknown';
  info('Update complete. Current version: ' . $updatedVersion, 'ok');
} catch (Throwable $exception) {
  failAndExit($exception->getMessage());
}

renderPageEnd();
