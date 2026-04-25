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
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
  <link rel="shortcut icon" href="../../favicon.png" type="image/x-icon">
</head>

<body class="imageViewerBody">
  <div class="settingsMenu" style="display: none;">
    <div class="settingsMenuItem">
      <div class="settingsMenuContainer">
        <div class="categoryHeader">
          <p>Settings</p>
          <button type="button" name="toggleSettingsMenu" onclick="toggleSettingsMenu()">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3">
              <path
                d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
            </svg>
          </button>
        </div>
        <div class="categorySection">
          <label for="categoryInput">Categories/People:</label>
          <div class="categoryInputWrap">
            <input type="text" id="categoryInput" placeholder="Enter names separated by commas">
            <button type="button" id="saveCategoryBtn" onclick="saveCategories()">
              <span class="gicon">save</span>
            </button>
          </div>
          <div id="categoryList" class="categoryChips"></div>
        </div>
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
      <button type="button" id="categoryBtn" onclick="toggleSettingsMenu()" aria-label="Edit categories"
        title="Edit categories">
        <span class="gicon">person</span>
      </button>
      <button type="button"
        onclick="window.open('../..<?= htmlspecialchars($ImageFilePath, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>', '_blank')"
        aria-label="Open original image">
        <span class="gicon">open_in_new</span>
      </button>
      <button type="button" onclick="toggleSettingsMenu()" aria-label="Open image options">
        <span class="gicon">more_vert</span>
      </button>
    </div>
  </div>
  <img class="imageViewer" src="../..<?= htmlspecialchars($ImageFilePath, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
    alt="Image <?= htmlspecialchars($PUID, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" decoding="async" draggable="false">
  <script>
    const imageViewerEl = document.querySelector('.imageViewer');

    // Prevent default drag behavior
    imageViewerEl.addEventListener('dragstart', (e) => e.preventDefault());

    function setZoomState(isZoomed, clickX = null, clickY = null) {
      if (!imageViewerEl) return;
      imageViewerEl.classList.toggle('zoomed', isZoomed);
      document.body.classList.toggle('zoom-active', isZoomed);

      if (isZoomed && clickX !== null && clickY !== null) {
        // Wait for transition to start, then scroll to click position
        requestAnimationFrame(() => {
          const scrollX = clickX * 2 - window.innerWidth / 2;
          const scrollY = clickY * 2 - window.innerHeight / 2;
          window.scrollTo({
            left: Math.max(0, scrollX),
            top: Math.max(0, scrollY),
            behavior: 'instant'
          });
        });
      } else if (!isZoomed) {
        window.scrollTo(0, 0);
      }
    }

    function toggleZoomState(event) {
      if (!imageViewerEl) return;
      const isZoomed = imageViewerEl.classList.contains('zoomed');

      if (!isZoomed && event) {
        // Zooming in - scroll to click position
        const rect = imageViewerEl.getBoundingClientRect();
        const clickX = event.clientX - rect.left;
        const clickY = event.clientY - rect.top;
        setZoomState(true, clickX, clickY);
      } else {
        // Zooming out
        setZoomState(false);
      }
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
        toggleZoomState(null);
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

    // Category management
    let currentCategories = [];

    function loadCategories() {
      const puid = '<?= $PUID; ?>';
      fetch(`../../scripts/utility/_imageCategories.php?puid=${encodeURIComponent(puid)}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success && Array.isArray(data.categories)) {
            currentCategories = data.categories;
            renderCategories();
            document.getElementById('categoryInput').value = currentCategories.join(', ');
          }
        })
        .catch(err => console.error('Failed to load categories:', err));
    }

    function renderCategories() {
      const container = document.getElementById('categoryList');
      if (!container) return;
      if (currentCategories.length === 0) {
        container.innerHTML = '<span style="color: #888; font-size: 12px;">No categories assigned</span>';
        return;
      }
      container.innerHTML = currentCategories.map(cat =>
        `<span class="category-chip">${cat}</span>`
      ).join('');
    }

    function saveCategories() {
      const puid = '<?= $PUID; ?>';
      const input = document.getElementById('categoryInput');
      const categories = input.value.split(',').map(c => c.trim()).filter(c => c !== '');

      fetch('../../scripts/utility/_imageCategories.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ puid, categories })
      })
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            currentCategories = data.categories || [];
            renderCategories();
            input.value = currentCategories.join(', ');
          } else {
            console.error('Failed to save categories:', data.error);
          }
        })
        .catch(err => console.error('Error saving categories:', err));
    }

    document.addEventListener('DOMContentLoaded', loadCategories);
  </script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('../../sw.js')
          .catch(err => console.warn('[SW] Registration failed:', err));
      });
    }
  </script>
  <style>
    .categorySection {
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .categorySection label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: #ccc;
    }

    .categoryInputWrap {
      display: flex;
      gap: 8px;
      margin-bottom: 10px;
    }

    .categoryInputWrap input {
      flex: 1;
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px solid #444;
      background: #222;
      color: #fff;
      font-size: 14px;
    }

    .categoryInputWrap button {
      padding: 8px 12px;
      border-radius: 6px;
      border: none;
      background: #ff4500;
      color: #fff;
      cursor: pointer;
    }

    .categoryChips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .category-chip {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 999px;
      padding: 4px 12px;
      font-size: 12px;
      color: #fff;
    }
  </style>
</body>

</html>