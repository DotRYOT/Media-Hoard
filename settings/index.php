<?php
require_once '../version.php';
require_once '../scripts/_inc.php';

$configFile = __DIR__ . '/../config.json';
$config = json_decode(file_get_contents($configFile), true);

// $ytdlpVersion = getYtDlpVersion();
$ytdlpVersion['version'] = "2024.05.18";

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
          <ion-icon name="home-outline"></ion-icon>
        </button>
        <button type="button" onclick="window.location.href='../settings/'">
          <ion-icon name="settings-outline"></ion-icon>
        </button>
        <div class="hLine"></div>
      </div>
    </div>
  </nav>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Update System</h3>
      <p class="version">Current Version: <?= $version; ?></p>
      <button type="button" onclick="window.location.href='../scripts/updates/_update.php'">
        <ion-icon name="cloud-download-outline"></ion-icon>
        <p>Check for Updates</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Update YT-DLP</h3>
      <p class="version">Current Version: <?= $ytdlpVersion['version']; ?></p>
      <button type="button" onclick="window.location.href='../scripts/updates/_updateYTDLP.php'">
        <ion-icon name="cloud-download-outline"></ion-icon>
        <p>Check for Updates</p>
      </button>
    </div>
  </div>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Clean Up</h3>
      <p class="version">Empty dir's and temp files</p>
      <button type="button" onclick="window.location.href='../scripts/utility/_cleanUpTemp.php'">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Clean Up</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Delete All Videos</h3>
      <p class="version">Total Videos: <?= $totalVideos; ?></p>
      <button type="button" id="deleteAllVideosButtonFirst"
        onclick="document.getElementById('deleteAllVideosButton').style.display = 'flex'; document.getElementById('deleteAllVideosButtonFirst').style.display = 'none';">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Delete All Videos</p>
      </button>
      <button type="button" id="deleteAllVideosButton"
        onclick="document.getElementById('deleteAllVideosButtonFinal').style.display = 'flex'; document.getElementById('deleteAllVideosButton').style.display = 'none';"
        style="display: none;">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Are you sure?</p>
      </button>
      <button type="button" class="deleteAllVideosButtonFinal" id="deleteAllVideosButtonFinal" style="display: none;"
        onclick="window.location.href='../scripts/utility/_deleteAllVideos.php'">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Delete All Videos</p>
      </button>
    </div>
  </div>

  <div class="topSettingsSection">
    <div class="settingsUpdateSection">
      <h3>Fix File Structure</h3>
      <p class="version">Fixes the file structure</p>
      <button type="button" onclick="window.location.href='../setup.php?update=true'">
        <ion-icon name="file-tray-full-outline"></ion-icon>
        <p>Fix File Structure</p>
      </button>
    </div>

    <div class="settingsUpdateSection">
      <h3>Delete All Images</h3>
      <p class="version">Deletes all images</p>
      <button type="button" id="deleteAllImagesButtonFirst"
        onclick="document.getElementById('deleteAllImagesButton').style.display = 'flex'; document.getElementById('deleteAllImagesButtonFirst').style.display = 'none';">
        <ion-icon name="images-outline"></ion-icon>
        <p>Delete All Images</p>
      </button>
      <button type="button" id="deleteAllImagesButton"
        onclick="document.getElementById('deleteAllImagesButtonFinal').style.display = 'flex'; document.getElementById('deleteAllImagesButton').style.display = 'none';"
        style="display: none;">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Are you sure?</p>
      </button>
      <button type="button" class="deleteAllImagesButtonFinal" id="deleteAllImagesButtonFinal" style="display: none;"
        onclick="window.location.href='../scripts/utility/_deleteAllImages.php'">
        <ion-icon name="trash-outline"></ion-icon>
        <p>Delete All Images</p>
      </button>
    </div>
  </div>

  <div class="VideoSettingsSection">
    <h3>Video Settings</h3>
    <form action="../scripts/utility/_videoSettings.php" method="post" class="settingsForm">
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
      <button type="submit">Save</button>
    </form>
  </div>

  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js" crossorigin></script>
</body>

</html>