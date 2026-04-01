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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
  <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/zoomies.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body id="imagesPage">
  <?php
  displayMessage();
  ?>
  <div id="spinner" style="display: none;">
    <l-zoomies size="150" stroke="5" bg-opacity="0.1" speed="1.4" color="#ff4500"></l-zoomies>
  </div>
  <nav class="mediaTopNav imageTopNav">
    <div class="navLeft">
      <h3>MediaHoard <span><?= $version; ?></span></h3>
    </div>
    <div class="navRight">
      <div class="videoPostForm mediaNavActions imageNavActions">
        <h3>Images</h3>
        <button type="button" name="uploadMenu" onclick="toggleUploadtab()" class="navAction" aria-label="Upload images">
          <span class="gicon">upload</span>
          <p>Upload</p>
        </button>
        <button type="button" name="videoPage" onclick="window.location.href='../'" class="navAction" aria-label="Go to videos">
          <span class="gicon">videocam</span>
          <p>Videos</p>
        </button>
        <button type="button" onclick="togglePageFiltertab()" class="navAction" aria-label="Open filters">
          <span class="gicon">filter_alt</span>
          <p>Filter</p>
        </button>
        <button type="button" onclick="window.location.href='../settings/'" class="navAction" aria-label="Open settings">
          <span class="gicon">settings</span>
          <p>Settings</p>
        </button>
      </div>
    </div>
  </nav>
  <div class="uploadMenu" id="uploadMenu" style="display: none;">
    <div class="uploadContainer">
      <div class="topUploadTitle">
        <h3>Choose an option</h3>
        <button type="button" onclick="toggleUploadtab()">
          <span class="gicon">close</span>
        </button>
      </div>
      <form id="localImageUpload" method="post" enctype="multipart/form-data">
        <div style="display: flex; align-items: center; gap: 10px;">
          <button type="button" onclick="document.getElementById('fileUpload').click();">
            <span class="gicon">upload</span>
            <p>Upload Images</p>
          </button>
          <span id="fileNameDisplay" style="font-size: 14px; color: #888;">No file selected</span>
          <div id="status"></div>
        </div>
        <input type="file" name="images[]" id="fileUpload" accept="image/*" multiple required style="display: none;">
        <button type="submit" name="upload">Upload</button>
        <div id="imageUploadProgressWrap">
          <progress id="imageUploadProgress" value="0" max="100"></progress>
          <span id="imageUploadProgressText">0%</span>
        </div>
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
  <div class="imageFeedStatus" id="imageFeedStatus">Loading images...</div>
  <div id="imageLoadSentinel" aria-hidden="true"></div>
  <script>
    function setImageUploadProgress(percent) {
      const normalized = Math.max(0, Math.min(100, percent || 0));
      $('#imageUploadProgress').val(normalized);
      $('#imageUploadProgressText').text(`${normalized}%`);
    }

    function resetImageUploadProgress() {
      $('#imageUploadProgressWrap').hide();
      setImageUploadProgress(0);
    }

    $(function () {
      $('#fileUpload').on('change', function () {
        if (!this.files || this.files.length === 0) {
          $('#fileNameDisplay').text('No file selected');
          return;
        }

        if (this.files.length === 1) {
          $('#fileNameDisplay').text(this.files[0].name);
          return;
        }

        $('#fileNameDisplay').text(`${this.files.length} files selected`);
      });

      $('#localImageUpload').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        $.ajax({
          url: '../scripts/_imgUploader.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          xhr: function () {
            const xhr = $.ajaxSettings.xhr();
            if (xhr.upload) {
              xhr.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                setImageUploadProgress(percent);
              });
            }
            return xhr;
          },
          beforeSend: function () {
            $('#status').text('Uploading...');
            $('#imageUploadProgressWrap').show();
            setImageUploadProgress(0);
          },
          success: function (data) {
            $('#status').text(data.message || 'Upload complete.');
            setImageUploadProgress(100);

            if (data.success) {
              fetchAndLoadPosts();
              $('#fileUpload').val('');
              $('#fileNameDisplay').text('No file selected');
            }
          },
          error: function (jqXHR) {
            const response = jqXHR.responseJSON;
            const message = response && response.message ? response.message : 'Upload failed.';
            $('#status').text(message);
          },
          complete: function () {
            setTimeout(resetImageUploadProgress, 700);
          }
        });
      });
    });

    const FEED_CHUNK_SIZE = 48;
    let allPosts = [];
    let activePosts = [];
    let renderedCount = 0;
    let isRenderingChunk = false;
    let filterHandlersBound = false;

    const imageGrid = document.querySelector('.ImageGrid');
    const imageFeedStatus = document.getElementById('imageFeedStatus');
    const imageLoadSentinel = document.getElementById('imageLoadSentinel');

    function setFeedStatus(message) {
      if (!imageFeedStatus) return;
      imageFeedStatus.textContent = message;
    }

    function createPostCardElement(post, index) {
      const PUID = post.PUID;
      const imagePath = post.image_path;
      const target = '<?= $config['openMediaTab'] ?>' === 'true' ? '_blank' : '_self';

      const card = document.createElement('div');
      card.className = 'image-card';
      card.classList.add('is-loading');

      const link = document.createElement('a');
      link.href = `./imageFiles/_img.php?puid=${encodeURIComponent(PUID)}&filePath=${encodeURIComponent(imagePath)}`;
      link.target = target;
      link.className = 'image-link';

      const img = document.createElement('img');
      img.src = `..${imagePath}`;
      img.alt = `Image ${PUID}`;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.className = 'image-thumbnail';

      if (index < 8) {
        img.fetchPriority = 'high';
      }

      const markLoaded = () => {
        card.classList.remove('is-loading');
      };

      img.addEventListener('load', markLoaded, { once: true });
      img.addEventListener('error', markLoaded, { once: true });

      if (img.complete) {
        requestAnimationFrame(markLoaded);
      }

      link.appendChild(img);
      card.appendChild(link);
      return card;
    }

    function clearGrid() {
      if (!imageGrid) return;
      imageGrid.innerHTML = '';
      renderedCount = 0;
      isRenderingChunk = false;
    }

    function renderNextChunk() {
      if (!imageGrid || isRenderingChunk) return;
      if (renderedCount >= activePosts.length) return;

      isRenderingChunk = true;
      const start = renderedCount;
      const end = Math.min(renderedCount + FEED_CHUNK_SIZE, activePosts.length);
      const fragment = document.createDocumentFragment();

      for (let i = start; i < end; i++) {
        fragment.appendChild(createPostCardElement(activePosts[i], i));
      }

      imageGrid.appendChild(fragment);
      renderedCount = end;
      isRenderingChunk = false;

      if (renderedCount >= activePosts.length) {
        setFeedStatus(`Showing ${activePosts.length} images`);
      } else {
        setFeedStatus(`Loaded ${renderedCount} of ${activePosts.length} images`);
      }
    }

    function renderPostsOptimized(posts) {
      if (!imageGrid) return;
      if (!Array.isArray(posts) || posts.length === 0) {
        clearGrid();
        imageGrid.innerHTML = `<div class="noPosts">No posts available.</div>`;
        setFeedStatus('No images to show');
        return;
      }

      activePosts = posts;
      clearGrid();
      renderNextChunk();
    }

    function loadPosts(data) {
      if (!imageGrid) return;
      if (!Array.isArray(data)) {
        imageGrid.innerHTML = `<div class="noPosts">Invalid data format.</div>`;
        setFeedStatus('Failed to parse images');
        return;
      }
      if (data.length === 0) {
        imageGrid.innerHTML = `<div class="noPosts">No posts available.</div>`;
        setFeedStatus('No images found');
        return;
      }
      allPosts = data;
      renderPostsOptimized(sortByNewest(data));
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
      if (filterHandlersBound) return;
      filterHandlersBound = true;

      document.querySelector('.filterTab button:nth-child(1)').addEventListener('click', () => {
        renderPostsOptimized(sortByRandom(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(2)').addEventListener('click', () => {
        renderPostsOptimized(sortByNewest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(3)').addEventListener('click', () => {
        renderPostsOptimized(sortByOldest(allPosts));
      });

      document.querySelector('.filterTab button:nth-child(4)').addEventListener('click', async () => {
        try {
          const resp = await fetch('./favoriteImages.json');
          if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
          const favs = await resp.json();
          if (!Array.isArray(favs) || favs.length === 0) {
            renderPostsOptimized([]);
            imageGrid.innerHTML = `<div class="noPosts">No favorite images yet.</div>`;
            setFeedStatus('No favorites found');
            return;
          }
          const favSet = new Set(favs);
          const filtered = allPosts.filter(p => favSet.has(p.PUID));
          if (filtered.length === 0) {
            renderPostsOptimized([]);
            imageGrid.innerHTML = `<div class="noPosts">No favorite images found.</div>`;
            setFeedStatus('No favorites found');
            return;
          }
          renderPostsOptimized(sortByNewest(filtered));
        } catch (error) {
          console.error('Favorites fetch error:', error.message || error);
          imageGrid.innerHTML = `<div class="noPosts">Error loading favorites. Please try again later.</div>`;
          setFeedStatus('Favorites failed to load');
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
      setFeedStatus('Loading images...');
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
            setFeedStatus('Image feed failed to load');
          }
        });
    }

    if (imageLoadSentinel && 'IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            renderNextChunk();
          }
        }
      }, {
        root: null,
        rootMargin: '500px 0px',
        threshold: 0
      });

      observer.observe(imageLoadSentinel);
    }

    document.addEventListener('DOMContentLoaded', fetchAndLoadPosts);
  </script>

</body>

</html>