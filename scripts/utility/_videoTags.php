<?php
require __DIR__ . '/../_inc.php';

header('Content-Type: application/json');

$tagsFile = __DIR__ . '/../../video/tags.json';

function read_tags_map($tagsFile)
{
  if (!file_exists($tagsFile)) {
    @file_put_contents($tagsFile, json_encode(new stdClass()), LOCK_EX);
  }

  $raw = @file_get_contents($tagsFile);
  if ($raw === false || $raw === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function write_tags_map($tagsFile, $map)
{
  return @file_put_contents($tagsFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $map = read_tags_map($tagsFile);
  if (isset($_GET['puid'])) {
    $puid = filter_user_input($_GET['puid'], 'string');
    if ($puid === false || $puid === '') {
      echo json_encode(['success' => false, 'error' => 'Invalid puid']);
      exit;
    }

    $tags = isset($map[$puid]) && is_array($map[$puid]) ? $map[$puid] : [];
    echo json_encode(['success' => true, 'puid' => $puid, 'tags' => $tags]);
    exit;
  }

  echo json_encode(['success' => true, 'tagsMap' => $map]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Unsupported method']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['puid'])) {
  echo json_encode(['success' => false, 'error' => 'Missing puid']);
  exit;
}

$puid = filter_user_input($data['puid'], 'string');
if ($puid === false || $puid === '') {
  echo json_encode(['success' => false, 'error' => 'Invalid puid']);
  exit;
}

$incomingTags = [];
if (isset($data['tags']) && is_array($data['tags'])) {
  $incomingTags = $data['tags'];
} elseif (isset($data['tags']) && is_string($data['tags'])) {
  $incomingTags = explode(',', $data['tags']);
}

$cleaned = [];
foreach ($incomingTags as $tag) {
  if (!is_string($tag)) {
    continue;
  }

  $tag = trim($tag);
  if ($tag === '') {
    continue;
  }

  $tag = filter_user_input($tag, 'string');
  $tag = trim(strtolower($tag));
  if ($tag === '' || $tag === false) {
    continue;
  }

  $cleaned[] = $tag;
}

$cleaned = array_values(array_unique($cleaned));
$cleaned = array_slice($cleaned, 0, 25);

$map = read_tags_map($tagsFile);

if (count($cleaned) === 0) {
  unset($map[$puid]);
} else {
  $map[$puid] = $cleaned;
}

if (!write_tags_map($tagsFile, $map)) {
  echo json_encode(['success' => false, 'error' => 'Unable to write tags file']);
  exit;
}

echo json_encode(['success' => true, 'puid' => $puid, 'tags' => ($map[$puid] ?? [])]);
