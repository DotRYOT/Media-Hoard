<?php
header('Content-Type: application/json');

// Accept only numeric job ID
$jobId = preg_replace('/[^0-9]/', '', $_GET['id'] ?? '');
if (!$jobId) {
  echo json_encode(['percent' => 0]);
  exit;
}

$progressFile = __DIR__ . '/temp/progress_' . $jobId . '.txt';
if (!file_exists($progressFile)) {
  echo json_encode(['percent' => 0]);
  exit;
}

$content = file_get_contents($progressFile);

// Parse the highest [download] XX.X% line from yt-dlp output
preg_match_all('/\[download\]\s+([\d.]+)%/', $content, $matches);
$percent = !empty($matches[1]) ? (float)end($matches[1]) : 0;

echo json_encode(['percent' => round($percent, 1)]);
