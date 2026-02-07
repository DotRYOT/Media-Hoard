<?php
require_once '../version.php';
if (!is_dir("./imageFiles") || !file_exists("./imageFiles/images.json") || !file_exists("./imageFiles/_img.php")) {
  header("Location: ../setup.php?update=true");
  exit();
}
require_once '../scripts/_inc.php';
$config = json_decode(file_get_contents('../config.json'), true);
$openMediaTab = $config['openMediaTab'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - Images</title>
  <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="../css/imagePage.min.css">
  <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/zoomies.js"></script>
</head>

<body id="imagesPage">
  <?php
  displayMessage();
  ?>
  <div id="spinner" style="display: none;">
    <l-zoomies size="150" stroke="5" bg-opacity="0.1" speed="1.4" color="#ff4500"></l-zoomies>
  </div>
  <nav>
    <div class="navLeft">
      <h3>MediaHoard <span><?= $version; ?></span></h3>
    </div>
    <div class="navRight">
      <div class="videoPostForm">
        <h3>Images</h3>
        <button type="button" name="uploadMenu" onclick="toggleUploadtab()">
          <ion-icon name="cloud-upload-outline"></ion-icon>
          <p>Upload</p>
        </button>
        <button type="button" name="videoPage" onclick="window.location.href='../'">
          <ion-icon name="videocam-outline"></ion-icon>
        </button>
        <button type="button" onclick="togglePageFiltertab()">
          <ion-icon name="filter-outline"></ion-icon>
        </button>
        <button type="button" onclick="window.location.href='../settings/'">
          <ion-icon name="settings-outline"></ion-icon>
        </button>
      </div>
    </div>
  </nav>
  <div class="uploadMenu" id="uploadMenu" style="display: none;">
    <div class="uploadContainer">
      <div class="topUploadTitle">
        <h3>Choose an option</h3>
        <button type="button" onclick="toggleUploadtab()">
          <ion-icon name="close-outline"></ion-icon>
        </button>
      </div>
      <form id="localImageUpload" method="post" enctype="multipart/form-data">
        <div style="display: flex; align-items: center; gap: 10px;">
          <button type="button" onclick="document.getElementById('fileUpload').click();">
            <ion-icon name="cloud-upload-outline"></ion-icon>
            <p>Upload Images</p>
          </button>
          <span id="fileNameDisplay" style="font-size: 14px; color: #888;">No file selected</span>
          <div id="status"></div>
        </div>
        <input type="file" name="images[]" id="fileUpload" accept="image/*" multiple required style="display: none;">
        <button type="submit" name="upload">Upload</button>
      </form>
    </div>
  </div>
  <div class="pageFiltertab" style="display: none;">
    <div class="filterTab">
      <button>Random</button>
      <button>Newest</button>
      <button>Oldest</button>
      <button>Favorites</button>
    </div>
  </div>
  <div class="ImageGrid"></div>
  <script>
    document.getElementById("localImageUpload").addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('../scripts/_imgUploader.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          document.getElementById("status").innerText = data.message;
          fetchAndLoadPosts();
          document.getElementById("fileUpload").value = "";
          document.getElementById("fileNameDisplay").textContent = "No file selected";

          console.log("Uploaded files:", data.files);
        })
        .catch(err => {
          document.getElementById("status").innerText = "Upload failed.";
          console.error(err);
        });
    });

    let allPosts = [];
    function createPostCard(post) {
      const PUID = post.PUID;
      const image_path = post.image_path;
      const target = '<?= $config['openMediaTab'] ?>' === 'true' ? '_blank' : '_self';
      return `
      <div class="image-card">
        <a href="./imageFiles/_img.php?puid=${PUID}&filePath=${image_path}" target="${target}" class="image-link">
          <img src="..${image_path}" alt="thumbnail" loading="lazy" class="image-thumbnail">
        </a>
      </div>
    `;
    }

    function loadPosts(data) {
      const container = document.querySelector('.ImageGrid');
      if (!container) return;
      if (!Array.isArray(data)) {
        container.innerHTML = `<div class="noPosts">Invalid data format.</div>`;
        return;
      }
      if (data.length === 0) {
        container.innerHTML = `<div class="noPosts">No posts available.</div>`;
        return;
      }
      allPosts = data;
      renderPosts(sortByNewest(data));
    }

    function renderPosts(posts) {
      const container = document.querySelector('.ImageGrid');
      container.innerHTML = posts.map(post => createPostCard(post)).join('');
    }

    function sortByNewest(posts) {
      return [...posts].sort((a, b) => b.Time - a.Time);
    }

    function sortByOldest(posts) {
      return [...posts].sort((a, b) => a.Time - b.Time);
    }

    function sortByRandom(posts) {
      const shuffled = [...posts];
      for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
      }
      return shuffled;
    }

    function setupFilterButtons() {
      document.querySelector('.filterTab button:nth-child(1)').addEventListener('click', () => {
        renderPosts(sortByRandom(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(2)').addEventListener('click', () => {
        renderPosts(sortByNewest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(3)').addEventListener('click', () => {
        renderPosts(sortByOldest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(4)').addEventListener('click', async () => {
        const container = document.querySelector('.ImageGrid');
        try {
          const resp = await fetch('./favoriteImages.json');
          if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
          const favs = await resp.json();
          if (!Array.isArray(favs) || favs.length === 0) {
            container.innerHTML = `<div class="noPosts">No favorite images yet.</div>`;
            return;
          }
          const favSet = new Set(favs);
          const filtered = allPosts.filter(p => favSet.has(p.PUID));
          if (filtered.length === 0) {
            container.innerHTML = `<div class="noPosts">No favorite images found.</div>`;
            return;
          }
          renderPosts(sortByNewest(filtered));
        } catch (error) {
          console.error('Favorites fetch error:', error.message || error);
          container.innerHTML = `<div class="noPosts">Error loading favorites. Please try again later.</div>`;
        }
      });
    }

    function decodeHTMLEntities(text) {
      const textArea = document.createElement('textarea');
      textArea.innerHTML = text;
      return textArea.value;
    }

    function toggleSpinner() {
      const spinner = document.querySelector('#spinner');
      const submitButton = document.querySelector('#submitButton');
      const loadingButton = document.querySelector('#loadingButton');
      submitButton.style.display = submitButton.style.display === 'none' ? 'flex' : 'none';
      loadingButton.style.display = loadingButton.style.display === 'none' ? 'flex' : 'none';
      spinner.style.display = spinner.style.display === 'none' ? 'flex' : 'none';
    }

    function togglePageFiltertab() {
      const pageFiltertab = document.querySelector('.pageFiltertab');
      pageFiltertab.style.display = pageFiltertab.style.display === 'none' ? 'block' : 'none';
    }

    function toggleUploadtab() {
      const uploadMenu = document.querySelector('.uploadMenu');
      uploadMenu.style.display = uploadMenu.style.display === 'none' ? 'flex' : 'none';
    }

    function fetchAndLoadPosts() {
      fetch('./imageFiles/images.json')
        .then(response => {
          if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
          return response.json();
        })
        .then(data => {
          loadPosts(data);
          setupFilterButtons();
        })
        .catch(error => {
          console.error("Fetch error:", error.message);
          const container = document.querySelector('.ImageGrid');
          if (container) {
            container.innerHTML = `<div class="noPosts">Error loading posts. Please try again later.</div>`;
          }
        });
    }

    document.addEventListener('DOMContentLoaded', fetchAndLoadPosts);
  </script>

  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js" crossorigin></script>
</body>

</html>