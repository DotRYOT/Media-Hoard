<?php
require_once '../version.php';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = preg_replace('#/(video|scripts)$#', '', $scriptDir);
$basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
$videoUID = $_GET['id'];
$videoTime = $_GET['time'];
$videoTitle = $_GET['title'];
$videoPath = $_GET['video_path'];
$activeTag = isset($_GET['tag']) ? $_GET['tag'] : '';

$videoFileSystemPath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $videoPath);
$videoCacheVersion = file_exists($videoFileSystemPath) ? filemtime($videoFileSystemPath) : time();
$videoSrc = '..' . $videoPath . '?v=' . $videoCacheVersion;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Video</title>
  <link rel="shortcut icon" href="../favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="../css/videoPage.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
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
          <span class="gicon">home</span>
        </button>
        <button type="button" onclick="window.location.href='../settings/'">
          <span class="gicon">settings</span>
        </button>
        <div class="hLine"></div>
      </div>
    </div>
  </nav>
  <div class="leftVideoSection">
    <div class="video-wrapper">
      <video preload="auto">
        <source src="<?= htmlspecialchars($videoSrc, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" type="video/mp4">
        Your browser does not support the video tag.
      </video>
      <div class="controls">
        <button id="play-pause"><span class="gicon">play_arrow</span></button>

        <!-- Volume control group -->
        <div class="volume-control">
          <button id="volume"><span class="gicon">volume_up</span></button>
          <div class="volume-slider">
            <input type="range" min="0" max="1" step="0.05" value="1">
          </div>
        </div>

        <div class="progress-bar">
          <div class="buffer-bar"></div>
        </div>
        <div id="time-display">0:00 / 0:00</div>
        <button id="fullscreen">
          <span class="gicon">fullscreen</span>
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
        <div class="timeStamp" id="activePlaylistLabel"></div>
        <div class="currentVideoTags" id="currentVideoTags"></div>
      </div>
      <div class="videoSettings">
        <button type="button" id="favoriteBtn" name="favorite" onclick="favoriteVideo()" aria-pressed="false">
          <span id="favoriteIcon" class="gicon">star_border</span>
        </button>
        <button type="button" name="settings" onclick="toggleSettingsTab()">
          <span class="gicon">settings</span>
        </button>
      </div>
    </div>
  </div>
  <div class="settingsPopup" id="settingsPopup" style="display: none;">
    <div class="container">
      <div class="VideoSettings">
        <h3>Video Settings</h3>
        <button type="button" name="closeMenu" onclick="toggleSettingsTab()">
          <span class="gicon">close</span>
        </button>
      </div>
      <div class="deleteVideoContainer">
        <p>
          <strong>Delete Video</strong>
        </p>
        <button type="button" name="Delete Video" id="DeleteVideoStageOne" style="display: flex;"
          onclick="document.getElementById('DeleteVideoStageTwo').style.display = 'flex'; document.getElementById('DeleteVideoStageOne').style.display = 'none';">
          <span class="gicon">delete</span>
          <p>Delete Video</p>
        </button>
        <button type="button" name="Delete Video" id="DeleteVideoStageTwo" style="display: none;"
          onclick="document.getElementById('DeleteVideoStageFinal').style.display = 'flex'; document.getElementById('DeleteVideoStageTwo').style.display = 'none';">
          <span class="gicon">delete</span>
          <p>Are you sure?</p>
        </button>
        <button type="button" name="Delete Video" class="deleteAllVideosButtonFinal" id="DeleteVideoStageFinal"
          style="display: none;"
          onclick="window.location.href='../scripts/utility/_deleteVideo.php?puid=<?= $videoUID; ?>'">
          <span class="gicon">delete_forever</span>
          <p>Delete Video!</p>
        </button>
      </div>
      <div class="deleteVideoContainer">
        <p>
          <strong>Tags (Playlist)</strong>
        </p>
        <input type="text" id="videoTagsInput" placeholder="chill, music, tutorial"
          style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #3a3a3a; background: #1a1a1a; color: #fff; margin-bottom: 8px;">
        <button type="button" id="saveTagsBtn" onclick="saveCurrentVideoTags()" style="display: flex;">
          <span class="gicon">sell</span>
          <p>Save Tags</p>
        </button>
      </div>
      <div class="deleteVideoContainer">
        <p>
          <strong>Remove Video Section</strong>
        </p>
        <input type="text" id="removeSectionStart" placeholder="Start (e.g. 00:00:00 or 0)"
          style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #3a3a3a; background: #1a1a1a; color: #fff; margin-bottom: 8px;">
        <input type="text" id="removeSectionEnd" placeholder="End (e.g. 00:00:30 or 30)"
          style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #3a3a3a; background: #1a1a1a; color: #fff; margin-bottom: 8px;">
        <button type="button" id="removeSectionBtn" onclick="removeVideoSection()" style="display: flex;">
          <span class="gicon">content_cut</span>
          <p>Remove Section</p>
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
      let tagsMap = {};
      let nextVideoUrl = '';
      let nextVideoThumbnail = '';
      let currentTag = String(<?= json_encode($activeTag); ?> || '').trim().toLowerCase();
      const currentVideoPuid = <?= json_encode($videoUID); ?>;
      const tagsEndpoint = <?= json_encode($basePath . '/scripts/utility/_videoTags.php'); ?>;

      function normalizeTag(tag) {
        return String(tag || '').trim().toLowerCase();
      }

      function ensureTagStyles() {
        if (document.getElementById('video-tag-styles')) return;
        const style = document.createElement('style');
        style.id = 'video-tag-styles';
        style.textContent = `
          .post-tags, .currentVideoTags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
          }

          .tag-chip {
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            color: inherit;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
          }
        `;
        document.head.appendChild(style);
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

      function getPostTags(post) {
        if (!post || !post.PUID) return [];
        const tags = tagsMap[post.PUID];
        return Array.isArray(tags) ? tags : [];
      }

      function renderActivePlaylistLabel() {
        const label = document.getElementById('activePlaylistLabel');
        if (!label) return;

        if (currentTag) {
          label.textContent = `Playlist: #${currentTag}`;
        } else {
          label.textContent = '';
        }
      }

      function buildVideoUrl(post) {
        const tagParam = currentTag ? `&tag=${encodeURIComponent(currentTag)}` : '';
        return `../video/_video.php?id=${post.PUID}&time=${post.Time}&title=${encodeURIComponent(post.title)}&video_path=${encodeURIComponent(post.video_path)}&thumbnail_path=${encodeURIComponent(post.thumbnail_path)}${tagParam}`;
      }

      function setNextVideoUrl(posts) {
        if (!Array.isArray(posts) || posts.length < 2) {
          nextVideoUrl = '';
          nextVideoThumbnail = '';
          document.body.dataset.nextVideoUrl = '';
          document.body.dataset.nextVideoThumbnail = '';
          return;
        }

        let candidatePosts = [...posts];
        if (currentTag) {
          const tagged = candidatePosts.filter(post => getPostTags(post).map(normalizeTag).includes(currentTag));
          if (tagged.length > 0) {
            candidatePosts = tagged;
          }
        }

        const sortedPosts = [...candidatePosts].sort((a, b) => b.Time - a.Time);
        const currentIndex = sortedPosts.findIndex(post => String(post.PUID) === String(currentVideoPuid));

        if (currentIndex === -1) {
          const fallback = sortedPosts.find(post => String(post.PUID) !== String(currentVideoPuid));
          nextVideoUrl = fallback ? buildVideoUrl(fallback) : '';
          nextVideoThumbnail = fallback && fallback.thumbnail_path ? `../${fallback.thumbnail_path}` : '';
        } else {
          const nextIndex = (currentIndex + 1) % sortedPosts.length;
          nextVideoUrl = buildVideoUrl(sortedPosts[nextIndex]);
          nextVideoThumbnail = sortedPosts[nextIndex] && sortedPosts[nextIndex].thumbnail_path
            ? `../${sortedPosts[nextIndex].thumbnail_path}`
            : '';
        }

        document.body.dataset.nextVideoUrl = nextVideoUrl;
        document.body.dataset.nextVideoThumbnail = nextVideoThumbnail;
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
          <img src="../${thumbnailPath}" alt="${decodedTitle} thumbnail" loading="lazy" class="post-thumbnail">
          <h3 class="post-title">${decodedTitle}</h3>
        </a>
        <p class="post-date">Posted: ${date}</p>
        ${tagsHtml}
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
        setNextVideoUrl(data);
        if (currentTag) {
          applyTagFilter(currentTag);
        } else {
          renderPosts(sortByRandom(data));
        }
      }

      function renderCurrentVideoTags(tags) {
        const container = document.getElementById('currentVideoTags');
        if (!container) return;

        if (!Array.isArray(tags) || tags.length === 0) {
          container.innerHTML = '';
          return;
        }

        container.innerHTML = tags
          .map(tag => `<button type="button" class="tag-chip" data-tag="${encodeURIComponent(tag)}">#${tag}</button>`)
          .join('');

        const input = document.getElementById('videoTagsInput');
        if (input) {
          input.value = tags.join(', ');
        }
      }

      function applyTagFilter(tag) {
        const normalized = normalizeTag(tag);
        const container = document.querySelector('.PostLoadedAreaVideoPage');

        if (!normalized) {
          currentTag = '';
          setTagInUrl('');
          renderActivePlaylistLabel();
          setNextVideoUrl(allPosts);
          renderPosts(sortByRandom(allPosts));
          return;
        }

        currentTag = normalized;
        setTagInUrl(normalized);
        renderActivePlaylistLabel();

        const filtered = allPosts.filter(post => {
          const tags = getPostTags(post).map(normalizeTag);
          return tags.includes(normalized);
        });

        setNextVideoUrl(allPosts);

        if (filtered.length === 0) {
          container.innerHTML = `<div class="noPosts">No related videos for #${normalized}.</div>`;
          return;
        }

        renderPosts(sortByRandom(filtered));
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
        Promise.all([
          fetch('../video/posts.json').then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
          }),
          fetch(tagsEndpoint)
            .then(response => response.ok ? response.json() : { success: true, tagsMap: {} })
            .catch(() => ({ success: true, tagsMap: {} }))
        ])
          .then(([data, tagData]) => {
            tagsMap = tagData && tagData.success && tagData.tagsMap ? tagData.tagsMap : {};

            const currentVideoTags = getPostTags({ PUID: currentVideoPuid });
            if (!currentTag && currentVideoTags.length > 0) {
              currentTag = normalizeTag(currentVideoTags[0]);
              setTagInUrl(currentTag);
            }

            renderActivePlaylistLabel();
            loadPosts(data);
            renderCurrentVideoTags(getPostTags({ PUID: currentVideoPuid }));
          })
          .catch(error => {
            console.error("Fetch error:", error.message);
            const container = document.querySelector('.PostLoadedAreaVideoPage');
            if (container) {
              container.innerHTML = `<div class="noPosts">Error loading posts. Please try again later.</div>`;
            }
          });
      }

      function saveCurrentVideoTags() {
        const input = document.getElementById('videoTagsInput');
        if (!input) return;

        const tags = input.value
          .split(',')
          .map(tag => normalizeTag(tag))
          .filter(Boolean);

        fetch(tagsEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ puid: currentVideoPuid, tags })
        })
          .then(response => response.json())
          .then(result => {
            if (!result || !result.success) {
              console.error('Unable to save tags', result);
              return;
            }

            tagsMap[currentVideoPuid] = Array.isArray(result.tags) ? result.tags : [];
            renderCurrentVideoTags(tagsMap[currentVideoPuid]);

            if (!currentTag && tagsMap[currentVideoPuid].length > 0) {
              currentTag = normalizeTag(tagsMap[currentVideoPuid][0]);
              setTagInUrl(currentTag);
              renderActivePlaylistLabel();
            }

            if (currentTag) {
              applyTagFilter(currentTag);
            } else {
              setNextVideoUrl(allPosts);
            }
          })
          .catch(error => {
            console.error('Tag save error:', error);
          });
      }

      function removeVideoSection() {
        const startInput = document.getElementById('removeSectionStart');
        const endInput = document.getElementById('removeSectionEnd');
        const button = document.getElementById('removeSectionBtn');

        if (!startInput || !endInput || !button) return;

        const startTime = startInput.value.trim();
        const endTime = endInput.value.trim();

        if (!startTime || !endTime) {
          alert('Please provide both start and end time.');
          return;
        }

        const originalButtonHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="gicon">hourglass_top</span><p>Processing...</p>';

        const endpoint = <?= json_encode($basePath . '/scripts/utility/_removeVideoSection.php'); ?>;
        fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            puid: currentVideoPuid,
            startTime,
            endTime
          })
        })
          .then(response => response.json())
          .then(result => {
            if (!result || !result.success) {
              const details = result && result.details ? `\n\n${result.details}` : '';
              alert(`${(result && result.error) ? result.error : 'Unable to remove section.'}${details}`);
              return;
            }

            window.location.reload();
          })
          .catch(error => {
            console.error('Remove section error:', error);
            alert('Failed to process video section removal.');
          })
          .finally(() => {
            button.disabled = false;
            button.innerHTML = originalButtonHtml;
          });
      }

      function favoriteVideo() {
        const puid = '<?= $videoUID; ?>';
        const favoriteEndpoint = <?= json_encode($basePath . '/scripts/utility/_favoriteVideo.php'); ?>;
        fetch(favoriteEndpoint, {
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
        const puid = '<?= $videoUID; ?>';
        const favoritesFile = <?= json_encode($basePath . '/video/favoriteVideos.json'); ?>;
        fetch(favoritesFile)
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
            // console.error('Error loading favorites:', err);
          });
      }

      document.addEventListener('DOMContentLoaded', () => {
        ensureTagStyles();
        fetchAndLoadPosts();
        setInitialFavoriteState();

        const relatedContainer = document.querySelector('.PostLoadedAreaVideoPage');
        if (relatedContainer) {
          relatedContainer.addEventListener('click', (event) => {
            const target = event.target.closest('.tag-chip');
            if (!target) return;
            event.preventDefault();
            const rawTag = decodeURIComponent(target.dataset.tag || '');
            applyTagFilter(rawTag);
          });
        }

        const currentTagContainer = document.getElementById('currentVideoTags');
        if (currentTagContainer) {
          currentTagContainer.addEventListener('click', (event) => {
            const target = event.target.closest('.tag-chip');
            if (!target) return;
            event.preventDefault();
            const rawTag = decodeURIComponent(target.dataset.tag || '');
            applyTagFilter(rawTag);
          });
        }
      });
    </script>
  </div>
  <script src="../scripts/videoPlayer.js"></script>
</body>

</html>