<?php
require "../_inc.php";

$downloadUrl = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe";
$releaseApiUrl = "https://api.github.com/repos/yt-dlp/yt-dlp/releases/latest";

$localDir = realpath(__DIR__ . '/..');
$localFilePath = $localDir . '/yt-dlp.exe';
$tempFilePath = $localDir . '/yt-dlp.exe.tmp';
$backupFilePath = $localDir . '/yt-dlp.exe.bak';

function redirectMessage($message, $type = 'success')
{
  $status = generateMessageUrl($message, $type);
  header("Location: ../../{$status}");
  exit;
}

function fetchLatestYtDlpVersion($apiUrl)
{
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MediaHoard-Updater/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
      return ['success' => false, 'message' => $curlError ?: 'Failed to fetch latest yt-dlp release metadata.'];
    }
  } else {
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 20,
        'header' => "User-Agent: MediaHoard-Updater/1.0\r\nAccept: application/vnd.github+json\r\n"
      ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);
    if ($response === false) {
      return ['success' => false, 'message' => 'Failed to fetch latest yt-dlp release metadata.'];
    }
  }

  $payload = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE || !isset($payload['tag_name'])) {
    return ['success' => false, 'message' => 'Invalid response while checking latest yt-dlp version.'];
  }

  return ['success' => true, 'version' => trim((string) $payload['tag_name'])];
}

$localVersionResult = getYtDlpVersion();
$localVersion = $localVersionResult['success'] ? trim((string) $localVersionResult['version']) : '';

$remoteVersionResult = fetchLatestYtDlpVersion($releaseApiUrl);
if (!$remoteVersionResult['success']) {
  redirectMessage($remoteVersionResult['message'], 'error');
}

$remoteVersion = $remoteVersionResult['version'];

if ($localVersion !== '' && $localVersion === $remoteVersion) {
  redirectMessage("YT-DLP already up to date ({$localVersion})", 'success');
}

if (file_exists($tempFilePath)) {
  @unlink($tempFilePath);
}

$downloadResult = downloadFile($downloadUrl, $tempFilePath);
if (!$downloadResult['success']) {
  redirectMessage($downloadResult['message'], 'error');
}

if (!file_exists($tempFilePath) || filesize($tempFilePath) <= 0) {
  @unlink($tempFilePath);
  redirectMessage('Downloaded yt-dlp file is invalid.', 'error');
}

if (file_exists($backupFilePath)) {
  @unlink($backupFilePath);
}

if (file_exists($localFilePath)) {
  if (!@rename($localFilePath, $backupFilePath)) {
    @unlink($tempFilePath);
    redirectMessage('Failed to prepare existing yt-dlp binary for update.', 'error');
  }
}

$replaceSuccess = @rename($tempFilePath, $localFilePath);
if (!$replaceSuccess) {
  @unlink($tempFilePath);
  if (file_exists($backupFilePath) && !file_exists($localFilePath)) {
    @rename($backupFilePath, $localFilePath);
  }
  redirectMessage('Failed to install updated yt-dlp binary.', 'error');
}

$newVersionResult = getYtDlpVersion();
if (!$newVersionResult['success']) {
  @unlink($localFilePath);
  if (file_exists($backupFilePath)) {
    @rename($backupFilePath, $localFilePath);
  }
  redirectMessage('Installed yt-dlp binary failed validation and was rolled back.', 'error');
}

if (file_exists($backupFilePath)) {
  @unlink($backupFilePath);
}

$installedVersion = trim((string) $newVersionResult['version']);
redirectMessage("YT-DLP updated to {$installedVersion}", 'success');