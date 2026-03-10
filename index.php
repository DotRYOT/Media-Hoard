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
      <button>Playlists</button>
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
    let tagsMap = {};
    let activeTag = '';
    const tagsEndpoint = './scripts/utility/_videoTags.php';

    function normalizeTag(tag) {
      return String(tag || '').trim().toLowerCase();
    }

    function setTagInUrl(tag) {
      const url = new URL(window.location.href);
      if (tag) {
        url.searchParams.set('tag', tag);
      } else {
        url.searchParams.delete('tag');
      }
      window.history.replaceState({}, '', url.toString());
    }

    function ensureTagStyles() {
      if (document.getElementById('video-tag-styles')) return;
      const style = document.createElement('style');
      style.id = 'video-tag-styles';
      style.textContent = `
        .post-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .tag-chip {
          border: 1px solid rgba(255,255,255,0.2);
          background: rgba(255,255,255,0.08);
          color: inherit;
          border-radius: 999px;
          padding: 4px 10px;
          font-size: 12px;
          cursor: pointer;
        }

        .playlist-hub {
          display: grid;
          gap: 12px;
          grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .playlist-card {
          border: 1px solid rgba(255,255,255,0.18);
          border-radius: 12px;
          padding: 12px;
          background: rgba(255,255,255,0.03);
        }

        .playlist-title {
          font-size: 16px;
          margin-bottom: 8px;
          font-weight: 700;
        }

        .playlist-meta {
          font-size: 12px;
          opacity: 0.8;
          margin-bottom: 10px;
        }

        .playlist-actions {
          display: flex;
          gap: 8px;
        }

        .playlist-actions button {
          border: none;
          border-radius: 999px;
          padding: 6px 12px;
          cursor: pointer;
          font-weight: 600;
        }

        .playlist-play {
          background: #fff;
          color: #000;
        }

        .playlist-view {
          background: rgba(255,255,255,0.2);
          color: #fff;
        }
      `;
      document.head.appendChild(style);
    }

    function buildVideoUrl(post, tag = activeTag) {
      const tagQuery = tag ? `&tag=${encodeURIComponent(tag)}` : '';
      return `./video/_video.php?id=${post.PUID}&time=${post.Time}&title=${encodeURIComponent(post.title)}&video_path=${encodeURIComponent(post.video_path)}&thumbnail_path=${encodeURIComponent(post.thumbnail_path)}${tagQuery}`;
    }

    function buildPlaylists() {
      const playlistMap = new Map();

      allPosts.forEach(post => {
        const tags = getPostTags(post);
        tags.forEach(tag => {
          const normalized = normalizeTag(tag);
          if (!normalized) return;
          if (!playlistMap.has(normalized)) {
            playlistMap.set(normalized, []);
          }
          playlistMap.get(normalized).push(post);
        });
      });

      const playlists = Array.from(playlistMap.entries()).map(([tag, posts]) => ({
        tag,
        posts: sortByNewest(posts),
        count: posts.length
      }));

      playlists.sort((a, b) => b.count - a.count || a.tag.localeCompare(b.tag));
      return playlists;
    }

    function renderPlaylistsHub() {
      const container = document.querySelector('.PostLoadedArea');
      const playlists = buildPlaylists();

      if (playlists.length === 0) {
        container.innerHTML = `<div class="noPosts">No playlists yet. Add tags to videos first.</div>`;
        return;
      }

      container.innerHTML = `
        <div class="playlist-hub">
          ${playlists.map(playlist => `
            <div class="playlist-card">
              <div class="playlist-title">#${playlist.tag}</div>
              <div class="playlist-meta">${playlist.count} video${playlist.count === 1 ? '' : 's'}</div>
              <div class="playlist-actions">
                <button type="button" class="playlist-play" data-tag="${encodeURIComponent(playlist.tag)}">Play</button>
                <button type="button" class="playlist-view" data-tag="${encodeURIComponent(playlist.tag)}">View</button>
              </div>
            </div>
          `).join('')}
        </div>
      `;
    }

    function playPlaylist(tag) {
      const normalized = normalizeTag(tag);
      const container = document.querySelector('.PostLoadedArea');
      if (!normalized) return;

      const filtered = sortByNewest(allPosts.filter(post => {
        const tags = getPostTags(post).map(normalizeTag);
        return tags.includes(normalized);
      }));

      if (filtered.length === 0) {
        container.innerHTML = `<div class="noPosts">No videos found for #${normalized}.</div>`;
        return;
      }

      window.location.href = buildVideoUrl(filtered[0], normalized);
    }

    function getPostTags(post) {
      if (!post || !post.PUID) return [];
      const tags = tagsMap[post.PUID];
      return Array.isArray(tags) ? tags : [];
    }
    function createPostCard(post) {
      if (!post || !post.video_path || !post.title) return '';
      const decodedTitle = decodeHTMLEntities(post.title);
      const thumbnailPath = post.thumbnail_path;
      const videoUID = post.PUID;
      const date = new Date(post.Time * 1000).toLocaleDateString();
      const tags = getPostTags(post);
      const tagsHtml = tags.length
        ? `<div class="post-tags">${tags.map(tag => `<button type="button" class="tag-chip" data-tag="${encodeURIComponent(tag)}">#${tag}</button>`).join('')}</div>`
        : '';
      return `
      <div class="post-card">
        <a href="${buildVideoUrl(post)}" class="post-link">
          <img src=".${thumbnailPath}" alt="${decodedTitle} thumbnail" loading="lazy" class="post-thumbnail">
          <h3 class="post-title">${decodedTitle}</h3>
        </a>
        <p class="post-date">Posted: ${date}</p>
        ${tagsHtml}
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
      if (activeTag) {
        applyTagFilter(activeTag);
      } else {
        renderPosts(sortByNewest(data));
      }
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
        activeTag = '';
        setTagInUrl('');
        renderPosts(sortByRandom(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(2)').addEventListener('click', () => {
        activeTag = '';
        setTagInUrl('');
        renderPosts(sortByNewest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(3)').addEventListener('click', () => {
        activeTag = '';
        setTagInUrl('');
        renderPosts(sortByOldest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(4)').addEventListener('click', async () => {
        activeTag = '';
        setTagInUrl('');
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

      document.querySelector('.filterTab button:nth-child(5)').addEventListener('click', () => {
        activeTag = '';
        setTagInUrl('');
        renderPlaylistsHub();
      });

      document.querySelector('.PostLoadedArea').addEventListener('click', (event) => {
        const playlistPlay = event.target.closest('.playlist-play');
        if (playlistPlay) {
          event.preventDefault();
          const rawTag = decodeURIComponent(playlistPlay.dataset.tag || '');
          playPlaylist(rawTag);
          return;
        }

        const playlistView = event.target.closest('.playlist-view');
        if (playlistView) {
          event.preventDefault();
          const rawTag = decodeURIComponent(playlistView.dataset.tag || '');
          applyTagFilter(rawTag);
          return;
        }

        const target = event.target.closest('.tag-chip');
        if (!target) return;
        event.preventDefault();
        const rawTag = decodeURIComponent(target.dataset.tag || '');
        applyTagFilter(rawTag);
      });
    }

    function applyTagFilter(tag) {
      const normalized = normalizeTag(tag);
      const container = document.querySelector('.PostLoadedArea');

      if (!normalized) {
        activeTag = '';
        setTagInUrl('');
        renderPosts(sortByNewest(allPosts));
        return;
      }

      activeTag = normalized;
      setTagInUrl(normalized);

      const filtered = allPosts.filter(post => {
        const tags = getPostTags(post).map(normalizeTag);
        return tags.includes(normalized);
      });

      if (filtered.length === 0) {
        container.innerHTML = `<div class="noPosts">No videos found for #${normalized}.</div>`;
        return;
      }

      renderPosts(sortByNewest(filtered));
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
      Promise.all([
        fetch('./video/posts.json').then(response => {
          if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
          return response.json();
        }),
        fetch(tagsEndpoint)
          .then(response => response.ok ? response.json() : { success: true, tagsMap: {} })
          .catch(() => ({ success: true, tagsMap: {} }))
      ])
        .then(([postData, tagsData]) => {
          tagsMap = tagsData && tagsData.success && tagsData.tagsMap ? tagsData.tagsMap : {};
          loadPosts(postData);
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

    document.addEventListener('DOMContentLoaded', () => {
      ensureTagStyles();
      activeTag = normalizeTag(new URLSearchParams(window.location.search).get('tag') || '');
      fetchAndLoadPosts();
    });
  </script>

  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js" crossorigin></script>
</body>

</html>