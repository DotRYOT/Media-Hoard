<?php
require "../_inc.php";

$url = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe";

$localDir = __DIR__ . '/..';
$localFilePath = $localDir . '/yt-dlp.exe';

$result = downloadFile($url, $localFilePath);

if ($result['success']) {
  $success = generateMessageUrl("YT-DLP updated", 'success');
  header("Location: ../../{$success}");
  exit();
} else {
  $error = generateMessageUrl($result['message'], 'error');
  header("Location: ../../{$error}");
  exit;
}