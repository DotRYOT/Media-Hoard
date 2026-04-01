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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
  <link rel="shortcut icon" href="../../favicon.png" type="image/x-icon">
</head>

<body class="imageViewerBody">
  <div class="settingsMenu" style="display: none;">
    <div class="settingsMenuItem">
      <div class="settingsMenuContainer">
        <h3>Settings</h3>
        <button type="button" id="deleteAllImagesButtonFirst"
          onclick="document.getElementById('deleteAllImagesButton').style.display = 'flex'; document.getElementById('deleteAllImagesButtonFirst').style.display = 'none';">
          <span class="gicon">image</span>
          <p>Delete Image</p>
        </button>
        <button type="button" id="deleteAllImagesButton"
          onclick="document.getElementById('deleteAllImagesButtonFinal').style.display = 'flex'; document.getElementById('deleteAllImagesButton').style.display = 'none';"
          style="display: none;">
          <span class="gicon">delete</span>
          <p>Are you sure?</p>
        </button>
        <button type="button" class="deleteAllImagesButtonFinal" id="deleteAllImagesButtonFinal" style="display: none;"
          onclick="window.location.href='../../scripts/utility/_deleteImage.php?puid=<?= $PUID; ?>'">
          <span class="gicon">delete_forever</span>
          <p>Delete Image</p>
        </button>
      </div>
    </div>
  </div>
  <div class="viewerTopBar">
    <button type="button" class="viewerBackButton" onclick="goBackToGallery()" aria-label="Back to gallery">
      <span class="gicon">arrow_back</span>
    </button>
    <div class="viewerMeta">
      <h4>Image <?= htmlspecialchars($PUID, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h4>
      <p>Click image or press Z to zoom</p>
    </div>
    <div class="settingsButton">
      <button type="button" id="favoriteBtn" name="favorite" aria-pressed="false" onclick="favoriteImage()">
        <span id="favoriteIcon" class="gicon">star_border</span>
      </button>
      <button type="button" onclick="window.open('../..<?= htmlspecialchars($ImageFilePath, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>', '_blank')" aria-label="Open original image">
        <span class="gicon">open_in_new</span>
      </button>
      <button type="button" onclick="toggleSettingsMenu()" aria-label="Open image options">
        <span class="gicon">more_vert</span>
      </button>
    </div>
  </div>
  <img class="imageViewer" src="../..<?= htmlspecialchars($ImageFilePath, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" alt="Image <?= htmlspecialchars($PUID, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" decoding="async">
  <script>
    const imageViewerEl = document.querySelector('.imageViewer');

    function setZoomState(isZoomed) {
      if (!imageViewerEl) return;
      imageViewerEl.classList.toggle('zoomed', isZoomed);
      document.body.classList.toggle('zoom-active', isZoomed);
    }

    function toggleZoomState() {
      if (!imageViewerEl) return;
      const isZoomed = imageViewerEl.classList.contains('zoomed');
      setZoomState(!isZoomed);
    }

    imageViewerEl.addEventListener('click', toggleZoomState);

    function goBackToGallery() {
      if (window.history.length > 1) {
        window.history.back();
        return;
      }
      window.location.href = '../../img/';
    }

    function toggleSettingsMenu() {
      document.querySelector('.settingsMenu').style.display = document.querySelector('.settingsMenu').style.display === 'flex' ? 'none' : 'flex';
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'z' || event.key === 'Z') {
        toggleZoomState();
      }
      if (event.key === 'Escape') {
        const settingsMenu = document.querySelector('.settingsMenu');
        if (settingsMenu.style.display === 'flex') {
          settingsMenu.style.display = 'none';
          return;
        }
        setZoomState(false);
      }
    });

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
              if (icon) icon.textContent = 'star';
              if (btn) btn.setAttribute('aria-pressed', 'true');
            } else if (data.action === 'removed') {
              if (icon) icon.textContent = 'star_border';
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
            if (icon) icon.textContent = 'star';
            if (btn) btn.setAttribute('aria-pressed', 'true');
          } else {
            if (icon) icon.textContent = 'star_border';
            if (btn) btn.setAttribute('aria-pressed', 'false');
          }
        })
        .catch(err => {
          // silently ignore — favorites file may not exist yet
        });
    }

    document.addEventListener('DOMContentLoaded', setInitialFavoriteState);
  </script>
</body>

</html>