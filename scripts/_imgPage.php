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
    <button type="button" id="favoriteBtn" name="favorite" aria-pressed="false" onclick="favoriteImage()">
      <ion-icon id="favoriteIcon" name="star-outline"></ion-icon>
    </button>
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

    function favoriteImage() {
      const puid = '<?= $PUID; ?>';
      fetch('../../scripts/utility/_favoriteImage.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ puid })
      })
        .then(response => response.json())
        .then(data => {
          if (data && data.success) {
            // toggle icon based on action
            const icon = document.getElementById('favoriteIcon');
            const btn = document.getElementById('favoriteBtn');
            if (data.action === 'added') {
              if (icon) icon.setAttribute('name', 'star');
              if (btn) btn.setAttribute('aria-pressed', 'true');
            } else if (data.action === 'removed') {
              if (icon) icon.setAttribute('name', 'star-outline');
              if (btn) btn.setAttribute('aria-pressed', 'false');
            }
          } else {
            console.error('Favorite toggle failed', data);
          }
        })
        .catch(error => {
          console.error("Favorite error:", error);
        });
    }

    // Set initial favorite icon state on load
    function setInitialFavoriteState() {
      const puid = '<?= $PUID; ?>';
      fetch('../../img/favoriteImages.json')
        .then(resp => {
          if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
          return resp.json();
        })
        .then(favs => {
          if (!Array.isArray(favs)) return;
          const isFav = favs.includes(puid);
          const icon = document.getElementById('favoriteIcon');
          const btn = document.getElementById('favoriteBtn');
          if (isFav) {
            if (icon) icon.setAttribute('name', 'star');
            if (btn) btn.setAttribute('aria-pressed', 'true');
          } else {
            if (icon) icon.setAttribute('name', 'star-outline');
            if (btn) btn.setAttribute('aria-pressed', 'false');
          }
        })
        .catch(err => {
          // silently ignore — favorites file may not exist yet
        });
    }

    document.addEventListener('DOMContentLoaded', setInitialFavoriteState);
  </script>
  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js" crossorigin></script>
</body>

</html>