<?php

$frameTime = $_POST['frameTime'];
$thumbWidth = $_POST['thumbWidth'];
$thumbHeight = $_POST['thumbHeight'];
$videoExtension = $_POST['videoExtension'];
$openMediaTab = isset($_POST['openMediaTab']) ? 'true' : 'false';

$configFile = __DIR__ . '/../../config.json';
if (!file_exists($configFile)) {
  die("Config file not found: $configFile");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  die("Invalid JSON in config file.");
}

$config['frameTime'] = $frameTime;
$config['thumbWidth'] = $thumbWidth;
$config['thumbHeight'] = $thumbHeight;
$config['videoExtension'] = $videoExtension;
$config['openMediaTab'] = $openMediaTab;

file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

header('Location: ../../settings/');
exit();

