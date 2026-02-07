<?php
require_once './version.php';
if (!is_dir("./video") || !file_exists("./video/posts.json") || !file_exists("./video/_video.php") || !is_dir("./scripts/temp/videos")) {
  require_once './setup.php';
  echo "Setup complete! Please refresh the page.";
  exit();
}
require_once './scripts/_inc.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - Videos</title>
  <link rel="shortcut icon" href="./favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="./css/index.min.css">
  <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/zoomies.js"></script>
</head>

<body id="videosPage">
  <?php
  displayMessage();

  // Check to see if the user wants to download yt-dlp automatically
  if (!file_exists("./scripts/yt-dlp.exe")) {
    ?>
    <div class="updateAlert">
      <ion-icon name="help-outline" title="Update a program"></ion-icon>
      <h2>Do you want to update/install YT-DLP?</h2>
      <div class="answer">
        <a href="./scripts/updates/_updateYTDLP.php">
          <ion-icon name="checkmark-outline"></ion-icon>
        </a>
        <a href="./">
          <ion-icon name="close-circle-outline"></ion-icon>
        </a>
      </div>
    </div>
    <?php
  }
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
        <h3>Videos</h3>
        <button type="button" name="uploadMenu" onclick="toggleUploadtab()">
          <ion-icon name="cloud-upload-outline"></ion-icon>
          <p>Upload</p>
        </button>
        <button type="button" name="imagesPage" onclick="window.location.href='./img/'">
          <ion-icon name="image-outline"></ion-icon>
        </button>
        <button type="button" onclick="togglePageFiltertab()">
          <ion-icon name="filter-outline"></ion-icon>
        </button>
        <button type="button" onclick="window.location.href='./settings/'">
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
      <form action="./scripts/_downloader.php" id="webVideoUpload" method="get">
        <input type="text" name="url" placeholder="YouTube URL" required>
        <button id="submitButton" type="submit" onclick="toggleSpinner()" style="display: flex;">Download</button>
        <button id="loadingButton" type="button" name="loading" style="display: none;">Loading...</button>
      </form>
      <div class="vLine"></div>
      <form action="./scripts/_uploader.php" id="localVideoUpload" method="post" enctype="multipart/form-data">
        <div class="videoUpload">
          <button type="button" name="uploadFile" onclick="document.getElementById('fileUpload').click();">
            <ion-icon name="cloud-upload-outline"></ion-icon>
            <p>Upload Video</p>
          </button>
          <span id="fileNameDisplay" style="font-size: 14px; color: #888;"></span>
        </div>
        <input type="file" name="videos" id="fileUpload" accept="video/*" style="display: none;" required>
        <button type="submit" name="upload" style="display: flex;" onclick="toggleSpinner()">Upload</button>
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
  <div class="PostLoadedArea"></div>
  <script>
    document.getElementById("fileUpload").addEventListener("change", function () {
      const fileInput = this;
      const fileNameDisplay = document.getElementById("fileNameDisplay");

      if (fileInput.files.length > 0) {
        fileNameDisplay.textContent = fileInput.files[0].name;
      } else {
        fileNameDisplay.textContent = "";
      }
    });

    let allPosts = [];
    function createPostCard(post) {
      if (!post || !post.video_path || !post.title) return '';
      const decodedTitle = decodeHTMLEntities(post.title);
      const thumbnailPath = post.thumbnail_path;
      const videoUID = post.PUID;
      const date = new Date(post.Time * 1000).toLocaleDateString();
      return `
      <div class="post-card">
        <a href="./video/_video.php?id=${videoUID}&time=${post.Time}&title=${encodeURIComponent(post.title)}&video_path=${encodeURIComponent(post.video_path)}&thumbnail_path=${encodeURIComponent(post.thumbnail_path)}" class="post-link">
          <img src=".${thumbnailPath}" alt="${decodedTitle} thumbnail" loading="lazy" class="post-thumbnail">
          <h3 class="post-title">${decodedTitle}</h3>
        </a>
        <p class="post-date">Posted: ${date}</p>
      </div>
    `;
    }

    function loadPosts(data) {
      const container = document.querySelector('.PostLoadedArea');
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
      const container = document.querySelector('.PostLoadedArea');
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
        const container = document.querySelector('.PostLoadedArea');
        try {
          const resp = await fetch('./video/favoriteVideos.json');
          if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
          const favs = await resp.json();
          if (!Array.isArray(favs) || favs.length === 0) {
            container.innerHTML = `<div class="noPosts">No favorites yet.</div>`;
            return;
          }
          const favSet = new Set(favs);
          const filtered = allPosts.filter(p => favSet.has(p.PUID));
          if (filtered.length === 0) {
            container.innerHTML = `<div class="noPosts">No favorite posts found.</div>`;
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
      fetch('./video/posts.json')
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
          const container = document.querySelector('.PostLoadedArea');
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