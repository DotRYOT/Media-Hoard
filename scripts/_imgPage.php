<?php
$PUID = $_GET['puid'];
$ImageFilePath = $_GET['filePath'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $PUID; ?></title>
  <link rel="stylesheet" href="../../css/imagePage.min.css">
  <link rel="shortcut icon" href="../../favicon.png" type="image/x-icon">
</head>

<body class="imageViewerBody">
  <div class="settingsMenu" style="display: none;">
    <div class="settingsMenuItem">
      <div class="settingsMenuContainer">
        <h3>Settings</h3>
        <button type="button" id="deleteAllImagesButtonFirst"
          onclick="document.getElementById('deleteAllImagesButton').style.display = 'flex'; document.getElementById('deleteAllImagesButtonFirst').style.display = 'none';">
          <ion-icon name="images-outline"></ion-icon>
          <p>Delete Image</p>
        </button>
        <button type="button" id="deleteAllImagesButton"
          onclick="document.getElementById('deleteAllImagesButtonFinal').style.display = 'flex'; document.getElementById('deleteAllImagesButton').style.display = 'none';"
          style="display: none;">
          <ion-icon name="trash-outline"></ion-icon>
          <p>Are you sure?</p>
        </button>
        <button type="button" class="deleteAllImagesButtonFinal" id="deleteAllImagesButtonFinal" style="display: none;"
          onclick="window.location.href='../../scripts/utility/_deleteImage.php?puid=<?= $PUID; ?>'">
          <ion-icon name="trash-outline"></ion-icon>
          <p>Delete Image</p>
        </button>
      </div>
    </div>
  </div>
  <div class="settingsButton">
    <button type="button" onclick="toggleSettingsMenu()">
      <ion-icon name="ellipsis-vertical-outline"></ion-icon>
    </button>
  </div>
  <img class="imageViewer" src="../..<?= $ImageFilePath; ?>" alt="">
  <script>
    document.querySelector('.imageViewer').addEventListener('click', function () {
      this.classList.toggle('zoomed');
    });

    function toggleSettingsMenu() {
      document.querySelector('.settingsMenu').style.display = document.querySelector('.settingsMenu').style.display === 'flex' ? 'none' : 'flex';
    }
  </script>
  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js" crossorigin></script>
</body>

</html>