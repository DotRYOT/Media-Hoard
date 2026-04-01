<?php
require_once '../version.php';
require_once '../scripts/_inc.php';

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);

$ytdlpVersion = getYtDlpVersion();
$isZipAvailable = class_exists('ZipArchive');

$videojsonFilePath = __DIR__ . "/../video/posts.json";
$cacheFilePath = __DIR__ . "/../cache/video_count.cache";

try {
  $totalVideos = countVideosWithCache($videojsonFilePath, $cacheFilePath);
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="./css/index.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
  <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
  <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/zoomies.js"></script>
</head>

<body>
  <?php
  displayMessage();
  ?>
  <nav>
    <div class="navLeft">
      <h3>MediaHoard <span><?= $version; ?></span></h3>
    </div>
    <div class="navRight">
      <div class="videoPostForm">
        <div class="hLine"></div>
        <button type="button" onclick="window.location.href='../'">
          <span class="gicon">home</span>
        </button>
        <button type="button" onclick="window.location.href='../settings/'">
          <span class="gicon">settings</span>
        </button>
        <div class="hLine"></div>
      </div>
    </div>
  </nav>

  <?php if (!$isZipAvailable): ?>
    <div class="topSettingsSection">
      <div class="settingsUpdateSection">
        <h3>ZIP Extension Required</h3>
        <p class="version">System updater needs PHP <strong>zip</strong> enabled in php.ini.</p>
        <button type="button" onclick="window.location.href='../readme.md'">
          <span class="gicon">help</span>
          <p>How to Enable ZIP</p>
        </button>
      </div>
    </div>
  <?php endif; ?>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Update System</h3>
      <p class="version">Current Version: <?= $version; ?></p>
      <button type="button" onclick="window.location.href='../scripts/updates/_update.php'">
        <span class="gicon">download</span>
        <p>Check for Updates</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Update YT-DLP</h3>
      <p class="version">Current Version: <?= $ytdlpVersion['version']; ?></p>
      <button type="button" onclick="window.location.href='../scripts/updates/_updateYTDLP.php'">
        <span class="gicon">download</span>
        <p>Check for Updates</p>
      </button>
    </div>
  </div>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Clean Up</h3>
      <p class="version">Empty dir's and temp files</p>
      <button type="button" onclick="window.location.href='../scripts/utility/_cleanUpTemp.php'">
        <span class="gicon">delete</span>
        <p>Clean Up</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Clean Up Stale Files</h3>
      <p class="version">Remove old/moved files outside the default structure</p>
      <button type="button" onclick="window.location.href='../scripts/utility/_cleanUpFiles.php'">
        <span class="gicon">folder_delete</span>
        <p>Clean Up Files</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Delete All Videos</h3>
      <p class="version">Total Videos: <?= $totalVideos; ?></p>
      <button type="button" id="deleteAllVideosButtonFirst"
        onclick="document.getElementById('deleteAllVideosButton').style.display = 'flex'; document.getElementById('deleteAllVideosButtonFirst').style.display = 'none';">
        <span class="gicon">delete</span>
        <p>Delete All Videos</p>
      </button>
      <button type="button" id="deleteAllVideosButton"
        onclick="document.getElementById('deleteAllVideosButtonFinal').style.display = 'flex'; document.getElementById('deleteAllVideosButton').style.display = 'none';"
        style="display: none;">
        <span class="gicon">delete</span>
        <p>Are you sure?</p>
      </button>
      <button type="button" class="deleteAllVideosButtonFinal" id="deleteAllVideosButtonFinal" style="display: none;"
        onclick="window.location.href='../scripts/utility/_deleteAllVideos.php'">
        <span class="gicon">delete_forever</span>
        <p>Delete All Videos</p>
      </button>
    </div>
  </div>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Fix File Structure</h3>
      <p class="version">Fixes the file structure</p>
      <button type="button" onclick="window.location.href='../setup.php?update=true'">
        <span class="gicon">folder_open</span>
        <p>Fix File Structure</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Delete All Images</h3>
      <p class="version">Deletes all images</p>
      <button type="button" id="deleteAllImagesButtonFirst"
        onclick="document.getElementById('deleteAllImagesButton').style.display = 'flex'; document.getElementById('deleteAllImagesButtonFirst').style.display = 'none';">
        <span class="gicon">image</span>
        <p>Delete All Images</p>
      </button>
      <button type="button" id="deleteAllImagesButton"
        onclick="document.getElementById('deleteAllImagesButtonFinal').style.display = 'flex'; document.getElementById('deleteAllImagesButton').style.display = 'none';"
        style="display: none;">
        <span class="gicon">delete</span>
        <p>Are you sure?</p>
      </button>
      <button type="button" class="deleteAllImagesButtonFinal" id="deleteAllImagesButtonFinal" style="display: none;"
        onclick="window.location.href='../scripts/utility/_deleteAllImages.php'">
        <span class="gicon">delete_forever</span>
        <p>Delete All Images</p>
      </button>
    </div>
  </div>

  <div class="VideoSettingsSection">
    <h3>Video Settings</h3>
    <form action="../scripts/utility/_settings.php" method="post" class="settingsForm">
      <div class="settingsRow">
        <p>Frame Time (in frames) <span>Default: 5</span></p>
        <input type="number" id="frameTime" name="frameTime" value="<?= $config['frameTime'] ?>">
      </div>
      <div class="settingsRow">
        <p>Thumbnail Width <span>Default: 1280</span></p>
        <input type="number" id="thumbWidth" name="thumbWidth" value="<?= $config['thumbWidth'] ?>">
      </div>
      <div class="settingsRow">
        <p>Thumbnail Height <span>Default: 720</span></p>
        <input type="number" id="thumbHeight" name="thumbHeight" value="<?= $config['thumbHeight'] ?>">
      </div>
      <div class="settingsRow">
        <p>Video Extension <span>Default: mp4</span></p>
        <input type="text" id="videoExtension" name="videoExtension" value="<?= $config['videoExtension'] ?>">
      </div>
      <div class="settingsRowCheckBox">
        <p>Open Media Tab <span>Default: false</span></p>
        <input type="checkbox" id="openMediaTab" name="openMediaTab" value="true" <?= $config['openMediaTab'] === 'true' ? 'checked' : '' ?>>
      </div>
      <div class="settingsRow">
        <p>Max Image Uploads Per Request <span>Default: 20</span></p>
        <input type="number" id="maxFiles" name="maxFiles" min="1" value="<?= isset($config['maxFiles']) ? $config['maxFiles'] : '20' ?>">
      </div>
      <button type="submit">Save</button>
    </form>
  </div>

</body>

</html>