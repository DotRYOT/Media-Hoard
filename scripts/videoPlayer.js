document.addEventListener("DOMContentLoaded", () => {
  const video = document.querySelector("video");
  const playButton = document.getElementById("play-pause");
  const progressBar = document.querySelector(".progress-bar");
  const volumeButton = document.getElementById("volume");
  const fullscreenButton = document.getElementById("fullscreen");
  const timeDisplay = document.getElementById("time-display");
  const volumeControl = document.querySelector(".volume-control");
  const volumeSlider = document.querySelector(".volume-slider input");
  const volumeControlContainer = document.querySelector(".volume-control");
  let previousVolume = parseFloat(localStorage.getItem("previousVolume")) || 1;
  let hideCursorTimeout;
  let hideControlsTimeout;
  let autoplayCountdownTimeout;
  let autoplayCountdownInterval;
  let autoplayOverlayEl;
  let fullscreenResumeOverlayEl;
  let fullscreenRetryBound = false;
  const fullscreenIntentKey = "mh_keep_fullscreen";

  // Load saved volume
  const savedVolume = parseFloat(localStorage.getItem("volume")) || 1;
  video.volume = savedVolume;
  volumeSlider.value = savedVolume;
  updateVolumeIcon(video.volume);

  video.addEventListener("volumechange", () => {
    localStorage.setItem("volume", video.volume);
  });

  // Play/Pause functionality
  playButton.addEventListener("click", togglePlayPause);
  video.addEventListener("click", togglePlayPause);

  function togglePlayPause() {
    if (video.paused) {
      video
        .play()
        .then(() => {
          playButton.innerHTML = '<ion-icon name="pause"></ion-icon>';
        })
        .catch((e) => {
          console.warn("Playback failed:", e);
        });
    } else {
      video.pause();
      playButton.innerHTML = '<ion-icon name="play"></ion-icon>';
    }
  }

  // Progress bar
  video.addEventListener("timeupdate", () => {
    const progress = (video.currentTime / video.duration) * 100;
    progressBar.style.setProperty("--progress", `${progress}%`);
    updateTimeDisplay();
  });

  function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
      return "0:00";
    }

    const totalSeconds = Math.floor(seconds);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const secs = totalSeconds % 60;

    if (hours > 0) {
      return `${hours}:${String(minutes).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
    }

    return `${minutes}:${String(secs).padStart(2, "0")}`;
  }

  function updateTimeDisplay() {
    if (!timeDisplay) return;
    const current = formatTime(video.currentTime || 0);
    const total = formatTime(video.duration || 0);
    timeDisplay.textContent = `${current} / ${total}`;
  }

  video.addEventListener("loadedmetadata", updateTimeDisplay);
  video.addEventListener("durationchange", updateTimeDisplay);

  progressBar.addEventListener("click", (e) => {
    const rect = progressBar.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    video.currentTime = pos * video.duration;
    updateTimeDisplay();
  });

  // Volume controls
  volumeButton.addEventListener("click", (e) => {
    if (video.volume > 0) {
      previousVolume = video.volume;
      localStorage.setItem("previousVolume", previousVolume);
      video.volume = 0;
    } else {
      video.volume = previousVolume;
    }
    updateVolumeIcon(video.volume);
  });

  volumeSlider.addEventListener("input", (e) => {
    const volume = parseFloat(e.target.value);
    video.volume = volume;
    updateVolumeIcon(volume);
  });

  function updateVolumeIcon(volume) {
    if (volume === 0) {
      volumeButton.innerHTML = '<ion-icon name="volume-mute"></ion-icon>';
    } else if (volume < 0.5) {
      volumeButton.innerHTML = '<ion-icon name="volume-low"></ion-icon>';
    } else {
      volumeButton.innerHTML = '<ion-icon name="volume-high"></ion-icon>';
    }
  }

  // Cursor & Controls visibility logic
  const wrapper = document.querySelector(".video-wrapper");
  const controls = wrapper.querySelector(".controls");

  function resetCursorAndControls() {
    // Show cursor
    wrapper.classList.add("show-cursor");

    // Show controls
    controls.style.opacity = "1";
    controls.style.transform = "translateY(0)";

    // Clear timeouts
    clearTimeout(hideCursorTimeout);
    clearTimeout(hideControlsTimeout);

    // Schedule hiding after inactivity
    hideCursorTimeout = setTimeout(() => {
      wrapper.classList.remove("show-cursor");
    }, 2000);

    hideControlsTimeout = setTimeout(() => {
      controls.style.opacity = "0";
      controls.style.transform = "translateY(10px)";
    }, 2000);
  }

  function hideControlsAndCursor() {
    wrapper.classList.remove("show-cursor");
    controls.style.opacity = "0";
    controls.style.transform = "translateY(10px)";
    clearTimeout(hideCursorTimeout);
    clearTimeout(hideControlsTimeout);
  }

  // Mouse move -> show cursor and controls
  wrapper.addEventListener("mousemove", () => {
    resetCursorAndControls();
  });

  wrapper.addEventListener("click", () => {
    resetCursorAndControls();
  });

  wrapper.addEventListener("mouseleave", () => {
    resetCursorAndControls(); // will trigger hide after timeout
  });

  // Keep controls visible while hovering over them
  controls.addEventListener("mouseenter", () => {
    clearTimeout(hideCursorTimeout);
    clearTimeout(hideControlsTimeout);
  });

  controls.addEventListener("mouseleave", () => {
    resetCursorAndControls();
  });

  // Hide controls when mouse leaves the browser window
  document.addEventListener("mouseleave", (e) => {
    if (
      !document.fullscreenElement ||
      !document.body.classList.contains("fullscreen")
    ) {
      hideControlsAndCursor();
    }
  });

  // Also hide on tab/window blur
  window.addEventListener("blur", () => {
    hideControlsAndCursor();
  });

  // Fullscreen handling
  fullscreenButton.addEventListener("click", toggleFullscreen);

  function navigateToVideo(url) {
    if (!url) return;
    if (document.fullscreenElement || document.body.classList.contains("fullscreen")) {
      sessionStorage.setItem(fullscreenIntentKey, "1");
    } else {
      sessionStorage.removeItem(fullscreenIntentKey);
    }
    window.location.href = url;
  }

  function showFullscreenResumePrompt() {
    if (fullscreenResumeOverlayEl || document.fullscreenElement) return;
    ensureAutoplayStyles();
    const targetWrapper = wrapper || document.body;

    const overlay = document.createElement("div");
    overlay.className = "up-next-overlay";
    overlay.innerHTML = `
      <div class="up-next-top">
        <span>Fullscreen paused by browser</span>
      </div>
      <div class="up-next-actions">
        <button type="button" class="up-next-play">Resume Fullscreen</button>
        <button type="button" class="up-next-cancel">Dismiss</button>
      </div>
    `;

    const resumeBtn = overlay.querySelector(".up-next-play");
    const dismissBtn = overlay.querySelector(".up-next-cancel");

    resumeBtn.addEventListener("click", () => {
      const target = wrapper || document.documentElement;
      const request = target.requestFullscreen || document.documentElement.requestFullscreen;
      if (typeof request !== "function") return;

      request
        .call(target)
        .then(() => {
          document.body.classList.add("fullscreen");
          fullscreenButton.innerHTML = '<ion-icon name="contract"></ion-icon>';
          sessionStorage.removeItem(fullscreenIntentKey);
          overlay.remove();
          fullscreenResumeOverlayEl = null;
        })
        .catch(() => {
          document.body.classList.remove("fullscreen");
          fullscreenButton.innerHTML = '<ion-icon name="expand"></ion-icon>';
        });
    });

    dismissBtn.addEventListener("click", () => {
      sessionStorage.removeItem(fullscreenIntentKey);
      overlay.remove();
      fullscreenResumeOverlayEl = null;
    });

    targetWrapper.appendChild(overlay);
    fullscreenResumeOverlayEl = overlay;
  }

  function restoreFullscreenIfNeeded() {
    const shouldRestore = sessionStorage.getItem(fullscreenIntentKey) === "1";
    if (!shouldRestore) return;

    const tryRestore = () => {
      if (document.fullscreenElement) {
        sessionStorage.removeItem(fullscreenIntentKey);
        return;
      }

      const target = wrapper || document.documentElement;
      const request = target.requestFullscreen || document.documentElement.requestFullscreen;
      if (typeof request === "function") {
        request
          .call(target)
          .then(() => {
            document.body.classList.add("fullscreen");
            fullscreenButton.innerHTML = '<ion-icon name="contract"></ion-icon>';
            sessionStorage.removeItem(fullscreenIntentKey);
          })
          .catch(() => {
            document.body.classList.remove("fullscreen");
            fullscreenButton.innerHTML = '<ion-icon name="expand"></ion-icon>';
            showFullscreenResumePrompt();
          });
      } else {
        showFullscreenResumePrompt();
      }
    };

    tryRestore();
    setTimeout(tryRestore, 300);
    setTimeout(tryRestore, 1200);

    if (!fullscreenRetryBound) {
      const retryOnUserGesture = () => {
        const stillNeeded = sessionStorage.getItem(fullscreenIntentKey) === "1";
        if (stillNeeded && !document.fullscreenElement) {
          tryRestore();
        }
        document.removeEventListener("click", retryOnUserGesture, true);
        document.removeEventListener("keydown", retryOnUserGesture, true);
        fullscreenRetryBound = false;
      };

      document.addEventListener("click", retryOnUserGesture, true);
      document.addEventListener("keydown", retryOnUserGesture, true);
      fullscreenRetryBound = true;
    }
  }

  function toggleFullscreen() {
    const elem = document.documentElement;
    if (!document.fullscreenElement) {
      elem.requestFullscreen().catch(console.error);
      document.body.classList.add("fullscreen");
      fullscreenButton.innerHTML = '<ion-icon name="contract"></ion-icon>'; // Exit icon
    } else {
      document.exitFullscreen().catch(console.error);
      document.body.classList.remove("fullscreen");
      fullscreenButton.innerHTML = '<ion-icon name="expand"></ion-icon>'; // Enter icon
    }
  }

  // Exit fullscreen with ESC key
  document.addEventListener("keydown", (e) => {
    const isTyping =
      e.target &&
      (e.target.tagName === "INPUT" ||
        e.target.tagName === "TEXTAREA" ||
        e.target.isContentEditable);

    if (!isTyping && e.key.toLowerCase() === "f") {
      e.preventDefault();
      toggleFullscreen();
      return;
    }

    if (e.key === "Escape" && document.fullscreenElement) {
      document.exitFullscreen();
      document.body.classList.remove("fullscreen");
    }
  });

  // Optional: Listen for system fullscreen change events
  document.addEventListener("fullscreenchange", () => {
    if (!document.fullscreenElement) {
      document.body.classList.remove("fullscreen");
      fullscreenButton.innerHTML = '<ion-icon name="expand"></ion-icon>';
    }
  });

  // Double click to toggle fullscreen
  wrapper.addEventListener("dblclick", () => {
    if (!document.fullscreenElement) {
      wrapper.requestFullscreen().catch(console.error);
      document.body.classList.add("fullscreen");
      fullscreenButton.innerHTML = '<ion-icon name="contract"></ion-icon>';
    } else {
      document.exitFullscreen().catch(console.error);
      document.body.classList.remove("fullscreen");
      fullscreenButton.innerHTML = '<ion-icon name="expand"></ion-icon>';
    }
  });

  // Volume hover behavior
  volumeControlContainer.addEventListener("mouseenter", () => {
    volumeControl.classList.add("visible");
  });

  volumeControlContainer.addEventListener("mouseleave", () => {
    setTimeout(() => {
      if (!volumeSlider.matches(":focus")) {
        volumeControl.classList.remove("visible");
      }
    }, 3000);
  });

  // Buffer bar
  function updateBufferBar() {
    const duration = video.duration;
    if (isNaN(duration)) return;

    const bufferBar = document.querySelector(".progress-bar .buffer-bar");
    if (!bufferBar) {
      console.error("Buffer bar element not found!");
      return;
    }

    bufferBar.innerHTML = "";

    const ranges = video.buffered;
    for (let i = 0; i < ranges.length; i++) {
      const start = ranges.start(i);
      const end = ranges.end(i);
      const percentStart = (start / duration) * 100;
      const percentEnd = (end / duration) * 100;

      const bufferSegment = document.createElement("div");
      bufferSegment.style.left = `${percentStart}%`;
      bufferSegment.style.width = `${percentEnd - percentStart}%`;

      bufferBar.appendChild(bufferSegment);
    }
  }

  video.addEventListener("loadedmetadata", updateBufferBar);
  setInterval(updateBufferBar, 1000); // Update every second

  // Autoplay when enough has buffered
  video.muted = true;

  video.addEventListener("canplaythrough", () => {
    if (!video.paused) return;
    togglePlayPause(); // Uses same logic as user click
  });

  // Optional: Unmute after play starts
  video.addEventListener("play", () => {
    video.muted = false;
    clearAutoplayCountdown();
  });

  function clearAutoplayCountdown(removeOverlay = true) {
    clearTimeout(autoplayCountdownTimeout);
    clearInterval(autoplayCountdownInterval);
    autoplayCountdownTimeout = null;
    autoplayCountdownInterval = null;

    if (removeOverlay && autoplayOverlayEl) {
      autoplayOverlayEl.remove();
      autoplayOverlayEl = null;
    }
  }

  function ensureAutoplayStyles() {
    if (document.getElementById("up-next-style")) return;
    const style = document.createElement("style");
    style.id = "up-next-style";
    style.textContent = `
      .up-next-overlay {
        position: absolute;
        right: 16px;
        bottom: 16px;
        z-index: 50;
        width: min(320px, calc(100% - 32px));
        background: rgba(0, 0, 0, 0.82);
        color: #fff;
        border-radius: 12px;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        backdrop-filter: blur(4px);
      }

      .up-next-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
      }

      .up-next-thumb {
        width: 100%;
        height: 140px;
        object-fit: cover;
        border-radius: 8px;
      }

      .up-next-count {
        font-size: 20px;
        font-weight: 700;
      }

      .up-next-progress {
        width: 100%;
        height: 4px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        overflow: hidden;
      }

      .up-next-progress > div {
        height: 100%;
        width: 0%;
        background: #fff;
        transition: width 1s linear;
      }

      .up-next-actions {
        display: flex;
        gap: 8px;
      }

      .up-next-actions button {
        border: none;
        border-radius: 999px;
        padding: 8px 12px;
        cursor: pointer;
        font-weight: 600;
      }

      .up-next-cancel {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
      }

      .up-next-play {
        background: #fff;
        color: #000;
      }
    `;
    document.head.appendChild(style);
  }

  function showAutoplayOverlay(nextVideoUrl, nextVideoThumbnail) {
    const wrapper = document.querySelector(".video-wrapper");
    if (!wrapper) return;

    clearAutoplayCountdown();
    ensureAutoplayStyles();

    const overlay = document.createElement("div");
    overlay.className = "up-next-overlay";
    const thumbnailMarkup = nextVideoThumbnail
      ? `<img class="up-next-thumb" src="${nextVideoThumbnail}" alt="Next video thumbnail">`
      : "";

    overlay.innerHTML = `
      <div class="up-next-top">
        <span>Up next</span>
        <span class="up-next-count">5</span>
      </div>
      ${thumbnailMarkup}
      <div class="up-next-progress"><div></div></div>
      <div class="up-next-actions">
        <button type="button" class="up-next-cancel">Cancel</button>
        <button type="button" class="up-next-play">Play now</button>
      </div>
    `;

    const countdownEl = overlay.querySelector(".up-next-count");
    const progressEl = overlay.querySelector(".up-next-progress > div");
    const cancelBtn = overlay.querySelector(".up-next-cancel");
    const playNowBtn = overlay.querySelector(".up-next-play");

    let remainingSeconds = 5;
    progressEl.style.width = "0%";

    autoplayCountdownInterval = setInterval(() => {
      remainingSeconds -= 1;
      if (remainingSeconds > 0) {
        countdownEl.textContent = String(remainingSeconds);
      }

      const elapsed = Math.min(5, 5 - Math.max(remainingSeconds, 0));
      progressEl.style.width = `${(elapsed / 5) * 100}%`;
    }, 1000);

    autoplayCountdownTimeout = setTimeout(() => {
      navigateToVideo(nextVideoUrl);
    }, 5000);

    cancelBtn.addEventListener("click", () => {
      clearAutoplayCountdown(false);
      countdownEl.textContent = "Canceled";
      progressEl.style.width = "0%";
      cancelBtn.disabled = true;
      playNowBtn.disabled = false;
    });

    playNowBtn.addEventListener("click", () => {
      clearAutoplayCountdown();
      navigateToVideo(nextVideoUrl);
    });

    wrapper.appendChild(overlay);
    autoplayOverlayEl = overlay;
  }

  video.addEventListener("ended", () => {
    const nextVideoUrl = document.body?.dataset?.nextVideoUrl;
    const nextVideoThumbnail = document.body?.dataset?.nextVideoThumbnail || "";
    if (!nextVideoUrl) return;
    showAutoplayOverlay(nextVideoUrl, nextVideoThumbnail);
  });

  updateTimeDisplay();
  restoreFullscreenIfNeeded();
  window.addEventListener("load", restoreFullscreenIfNeeded);
});
