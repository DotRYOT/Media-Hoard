<?php

// This script handles the "favorite" action for a video. It receives a POST request with the video ID and updates the favorites list accordingly.

require "../_inc.php";

// Read raw POST body (expecting JSON)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

header('Content-Type: application/json');

if (!$data || !isset($data['puid'])) {
	echo json_encode(['success' => false, 'error' => 'Missing puid']);
	exit;
}

$puid = filter_user_input($data['puid'], 'string');
if ($puid === false || $puid === '') {
	echo json_encode(['success' => false, 'error' => 'Invalid puid']);
	exit;
}

$favFile = __DIR__ . '/../../video/favoriteVideos.json';
if (!file_exists($favFile)) {
	// create empty favorites file
	if (false === @file_put_contents($favFile, json_encode([]), LOCK_EX)) {
		echo json_encode(['success' => false, 'error' => 'Unable to create favorites file']);
		exit;
	}
}

$json = file_get_contents($favFile);
$favorites = json_decode($json, true);
if (!is_array($favorites)) $favorites = [];

$index = array_search($puid, $favorites, true);
if ($index === false) {
	// add
	$favorites[] = $puid;
	$action = 'added';
} else {
	// remove (toggle)
	array_splice($favorites, $index, 1);
	$action = 'removed';
}

if (false === @file_put_contents($favFile, json_encode(array_values($favorites), JSON_PRETTY_PRINT), LOCK_EX)) {
	echo json_encode(['success' => false, 'error' => 'Unable to write favorites file']);
	exit;
}

echo json_encode(['success' => true, 'action' => $action, 'puid' => $puid, 'favorites' => $favorites]);
