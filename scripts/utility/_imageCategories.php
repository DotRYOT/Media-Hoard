<?php
require __DIR__ . '/../_inc.php';

header('Content-Type: application/json');

$categoriesFile = __DIR__ . '/../../img/categories.json';

function read_categories_map($categoriesFile)
{
  if (!file_exists($categoriesFile)) {
    @file_put_contents($categoriesFile, json_encode(new stdClass()), LOCK_EX);
  }

  $raw = @file_get_contents($categoriesFile);
  if ($raw === false || $raw === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function write_categories_map($categoriesFile, $map)
{
  return @file_put_contents($categoriesFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

// GET request - retrieve categories
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $map = read_categories_map($categoriesFile);
  
  // If requesting for a specific image
  if (isset($_GET['puid'])) {
    $puid = filter_user_input($_GET['puid'], 'string');
    if ($puid === false || $puid === '') {
      echo json_encode(['success' => false, 'error' => 'Invalid puid']);
      exit;
    }

    $categories = isset($map[$puid]) && is_array($map[$puid]) ? $map[$puid] : [];
    echo json_encode(['success' => true, 'puid' => $puid, 'categories' => $categories]);
    exit;
  }

  // Return all categories
  echo json_encode(['success' => true, 'categoriesMap' => $map]);
  exit;
}

// Only accept POST for modifications
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

$incomingCategories = [];
if (isset($data['categories']) && is_array($data['categories'])) {
  $incomingCategories = $data['categories'];
} elseif (isset($data['categories']) && is_string($data['categories'])) {
  $incomingCategories = explode(',', $data['categories']);
}

$cleaned = [];
foreach ($incomingCategories as $category) {
  if (!is_string($category)) {
    continue;
  }

  $category = trim($category);
  if ($category === '') {
    continue;
  }

  $category = filter_user_input($category, 'string');
  // Keep original case for person names
  $category = trim($category);
  if ($category === '' || $category === false) {
    continue;
  }

  $cleaned[] = $category;
}

$cleaned = array_values(array_unique($cleaned));
$cleaned = array_slice($cleaned, 0, 25); // Max 25 categories per image

$map = read_categories_map($categoriesFile);

if (count($cleaned) === 0) {
  unset($map[$puid]);
} else {
  $map[$puid] = $cleaned;
}

if (!write_categories_map($categoriesFile, $map)) {
  echo json_encode(['success' => false, 'error' => 'Unable to write categories file']);
  exit;
}

echo json_encode(['success' => true, 'puid' => $puid, 'categories' => ($map[$puid] ?? [])]);
