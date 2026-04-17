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
        <div style="margin-top: 10px;">
          <input type="text" name="category" id="categoryInput" placeholder="Category/Person name (optional)" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #555; background: #222; color: #fff;">
          <small style="color: #888;">Separate multiple with commas</small>
        </div>
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
      <button>Categories</button>
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
              $('#categoryInput').val('');
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
    let categoriesMap = {};
    let activeCategory = '';
    let isCategoryHubMode = false;
    const categoriesEndpoint = '../scripts/utility/_imageCategories.php';

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
      if (!imageGrid || isRenderingChunk || isCategoryHubMode) return;
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
      isCategoryHubMode = false;
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

    function normalizeCategory(cat) {
      return (cat || '').trim().toLowerCase();
    }

    function getPostCategories(post) {
      if (!post || !post.PUID) return [];
      const cats = categoriesMap[post.PUID];
      return Array.isArray(cats) ? cats : [];
    }

    function buildCategories() {
      const categoryMap = new Map();

      allPosts.forEach(post => {
        const cats = getPostCategories(post);
        cats.forEach(cat => {
          const normalized = normalizeCategory(cat);
          if (!normalized) return;
          if (!categoryMap.has(normalized)) {
            categoryMap.set(normalized, { displayName: cat, posts: [] });
          }
          categoryMap.get(normalized).posts.push(post);
        });
      });

      const categories = Array.from(categoryMap.entries()).map(([key, data]) => ({
        key,
        displayName: data.displayName,
        posts: sortByNewest(data.posts),
        count: data.posts.length
      }));

      categories.sort((a, b) => b.count - a.count || a.displayName.localeCompare(b.displayName));
      return categories;
    }

    function ensureCategoryStyles() {
      if (document.getElementById('image-category-styles')) return;
      const style = document.createElement('style');
      style.id = 'image-category-styles';
      style.textContent = `
        .ImageGrid:has(.category-hub) {
          display: block !important;
        }

        .category-hub {
          display: flex;
          flex-direction: row;
          flex-wrap: wrap;
          gap: 12px;
          padding: 20px;
          width: 100%;
        }

        .category-card {
          position: relative;
          border: 1px solid rgba(255,255,255,0.18);
          border-radius: 12px;
          overflow: hidden;
          width: 220px;
          height: 180px;
          flex: 0 0 220px;
          display: flex;
          flex-direction: column;
          justify-content: flex-end;
        }

        .category-card-bg {
          position: absolute;
          inset: 0;
          background-size: cover;
          background-position: center;
          z-index: 0;
        }

        .category-card-bg::after {
          content: '';
          position: absolute;
          inset: 0;
          background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0.2) 100%);
        }

        .category-card-content {
          position: relative;
          z-index: 1;
          padding: 12px;
        }

        .category-title {
          font-size: 18px;
          margin-bottom: 4px;
          font-weight: 700;
          text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }

        .category-meta {
          font-size: 12px;
          opacity: 0.85;
          margin-bottom: 10px;
        }

        .category-actions {
          display: flex;
          gap: 8px;
        }

        .category-actions button {
          border: none;
          border-radius: 999px;
          padding: 6px 12px;
          cursor: pointer;
          font-weight: 600;
        }

        .category-view {
          background: #fff;
          color: #000;
        }

        @media (max-width: 560px) {
          .category-card {
            width: 100%;
            flex: 1 1 100%;
          }
        }
      `;
      document.head.appendChild(style);
    }

    function renderCategoriesHub() {
      ensureCategoryStyles();
      isCategoryHubMode = true;
      activePosts = [];
      renderedCount = 0;
      const categories = buildCategories();

      if (categories.length === 0) {
        imageGrid.innerHTML = `<div class="noPosts">No categories yet. Add categories when uploading images.</div>`;
        setFeedStatus('No categories found');
        return;
      }

      imageGrid.innerHTML = `
        <div class="category-hub">
          ${categories.map(cat => {
            const coverImage = cat.posts[0]?.image_path ? `..${cat.posts[0].image_path}` : '';
            return `
            <div class="category-card">
              <div class="category-card-bg" style="background-image: url('${coverImage}')"></div>
              <div class="category-card-content">
                <div class="category-title">${cat.displayName}</div>
                <div class="category-meta">${cat.count} image${cat.count === 1 ? '' : 's'}</div>
                <div class="category-actions">
                  <button type="button" class="category-view" data-category="${encodeURIComponent(cat.key)}">View</button>
                </div>
              </div>
            </div>
          `}).join('')}
        </div>
      `;
      setFeedStatus(`${categories.length} categories`);

      // Attach click handlers for category cards
      imageGrid.querySelectorAll('.category-view').forEach(btn => {
        btn.addEventListener('click', () => {
          const cat = decodeURIComponent(btn.dataset.category || '');
          applyCategoryFilter(cat);
        });
      });
    }

    function applyCategoryFilter(cat) {
      const normalized = normalizeCategory(cat);

      if (!normalized) {
        activeCategory = '';
        renderPostsOptimized(sortByNewest(allPosts));
        return;
      }

      activeCategory = normalized;

      const filtered = allPosts.filter(post => {
        const cats = getPostCategories(post).map(normalizeCategory);
        return cats.includes(normalized);
      });

      if (filtered.length === 0) {
        imageGrid.innerHTML = `<div class="noPosts">No images found for "${cat}".</div>`;
        setFeedStatus('No images found');
        return;
      }

      renderPostsOptimized(sortByNewest(filtered));
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

      document.querySelector('.filterTab button:nth-child(5)').addEventListener('click', () => {
        activeCategory = '';
        renderCategoriesHub();
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
      Promise.all([
        fetch('./imageFiles/images.json').then(response => {
          if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
          return response.json();
        }),
        fetch(categoriesEndpoint)
          .then(response => response.ok ? response.json() : { success: true, categoriesMap: {} })
          .catch(() => ({ success: true, categoriesMap: {} }))
      ])
        .then(([postData, categoriesData]) => {
          categoriesMap = categoriesData && categoriesData.success && categoriesData.categoriesMap ? categoriesData.categoriesMap : {};
          loadPosts(postData);
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