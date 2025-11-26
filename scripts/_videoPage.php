<?php
require_once '../version.php';
$videoUID = $_GET['id'];
$videoTime = $_GET['time'];
$videoTitle = $_GET['title'];
$videoPath = $_GET['video_path'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Video</title>
  <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="../css/videoPage.min.css">
</head>

<body id="videosPage">
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
  <div class="leftVideoSection">
    <div class="video-wrapper">
      <video preload="auto">
        <source src="..<?= $videoPath; ?>" type="video/mp4">
        Your browser does not support the video tag.
      </video>
      <div class="controls">
        <button id="play-pause"><ion-icon name="play"></ion-icon></button>

        <!-- Volume control group -->
        <div class="volume-control">
          <button id="volume"><ion-icon name="volume-high"></ion-icon></button>
          <div class="volume-slider">
            <input type="range" min="0" max="1" step="0.05" value="1">
          </div>
        </div>

        <div class="progress-bar">
          <div class="buffer-bar"></div>
        </div>
        <button id="fullscreen">
          <ion-icon name="expand"></ion-icon>
        </button>
      </div>
    </div>
    <div class="belowVideo">
      <div class="videoContent">
        <div class="videoTitle">
          <?= $videoTitle; ?>
        </div>
        <div class="timeStamp">
          <?= date('Y-m-d H:i:s', $videoTime); ?>
        </div>
      </div>
      <div class="videoSettings">
        <button type="button" name="settings" onclick="toggleSettingsTab()">
          <ion-icon name="settings-outline"></ion-icon>
        </button>
      </div>
    </div>
  </div>
  <div class="settingsPopup" id="settingsPopup" style="display: none;">
    <div class="container">
      <div class="VideoSettings">
        <h3>Video Settings</h3>
        <button type="button" name="closeMenu" onclick="toggleSettingsTab()">
          <ion-icon name="close-outline"></ion-icon>
        </button>
      </div>
      <div class="deleteVideoContainer">
        <p>
          <strong>Delete Video</strong>
        </p>
        <button type="button" name="Delete Video" id="DeleteVideoStageOne" style="display: flex;"
          onclick="document.getElementById('DeleteVideoStageTwo').style.display = 'flex'; document.getElementById('DeleteVideoStageOne').style.display = 'none';">
          <ion-icon name="trash-outline"></ion-icon>
          <p>Delete Video</p>
        </button>
        <button type="button" name="Delete Video" id="DeleteVideoStageTwo" style="display: none;"
          onclick="document.getElementById('DeleteVideoStageFinal').style.display = 'flex'; document.getElementById('DeleteVideoStageTwo').style.display = 'none';">
          <ion-icon name="trash-outline"></ion-icon>
          <p>Are you sure?</p>
        </button>
        <button type="button" name="Delete Video" class="deleteAllVideosButtonFinal" id="DeleteVideoStageFinal"
          style="display: none;"
          onclick="window.location.href='../scripts/utility/_deleteVideo.php?puid=<?= $videoUID; ?>'">
          <ion-icon name="trash-outline"></ion-icon>
          <p>Delete Video!</p>
        </button>
      </div>
    </div>
  </div>
  <div class="rightVideoSection">
    <div class="PostLoadedAreaVideoPage"></div>
    <script>
      function toggleSettingsTab() {
        const settingsPopup = document.querySelector('.settingsPopup');
        settingsPopup.style.display = settingsPopup.style.display === 'none' ? 'flex' : 'none';
      }

      let allPosts = [];
      function createPostCard(post) {
        if (!post || !post.video_path || !post.title) return '';
        const decodedTitle = decodeHTMLEntities(post.title);
        const thumbnailPath = post.thumbnail_path;
        const videoUID = post.PUID;
        const date = new Date(post.Time * 1000).toLocaleDateString();
        return `
      <div class="post-card">
        <a href="../video/_video.php?id=${videoUID}&time=${post.Time}&title=${encodeURIComponent(post.title)}&video_path=${encodeURIComponent(post.video_path)}&thumbnail_path=${encodeURIComponent(post.thumbnail_path)}" class="post-link">
          <img src="../${thumbnailPath}" alt="${decodedTitle} thumbnail" loading="lazy" class="post-thumbnail">
          <h3 class="post-title">${decodedTitle}</h3>
        </a>
        <p class="post-date">Posted: ${date}</p>
      </div>
    `;
      }

      function renderPosts(posts) {
        const container = document.querySelector('.PostLoadedAreaVideoPage');
        container.innerHTML = posts.map(post => createPostCard(post)).join('');
      }

      function loadPosts(data) {
        const container = document.querySelector('.PostLoadedAreaVideoPage');
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
        renderPosts(sortByRandom(data));
      }

      function sortByRandom(posts) {
        const shuffled = [...posts];
        for (let i = shuffled.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
      }

      function decodeHTMLEntities(text) {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
      }

      function fetchAndLoadPosts() {
        fetch('../video/posts.json')
          .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
          })
          .then(data => {
            loadPosts(data);
          })
          .catch(error => {
            console.error("Fetch error:", error.message);
            const container = document.querySelector('.PostLoadedAreaVideoPage');
            if (container) {
              container.innerHTML = `<div class="noPosts">Error loading posts. Please try again later.</div>`;
            }
          });
      }

      document.addEventListener('DOMContentLoaded', fetchAndLoadPosts);
    </script>
  </div>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <script src="../scripts/videoPlayer.js"></script>
</body>

</html>