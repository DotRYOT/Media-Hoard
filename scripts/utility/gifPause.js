window.addEventListener("load", function () {
  function selectAnimatedImages() {
    return Array.from(document.querySelectorAll("img")).filter((img) => {
      const src = img.src.split("?")[0];
      return /\.(gif|webp)$/i.test(src);
    });
  }

  function pauseAnimatedImage(img) {
    if (img.hasAttribute("data-original-src")) return; // Skip already paused
    try {
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

      // Save original styles
      const originalStyles = window.getComputedStyle(img);
      const width = originalStyles.width;
      const height = originalStyles.height;

      img.setAttribute("data-original-src", img.src);
      img.src = canvas.toDataURL("image/png"); // Use PNG to preserve quality

      // Reapply styles
      img.style.width = width;
      img.style.height = height;
    } catch (e) {
      console.log("Cannot pause external animated image:", img.src);
      img.src = img.src; // Restart animation for CORS-restricted images
    }
  }

  function resumeAnimatedImage(img) {
    const originalSrc = img.getAttribute("data-original-src");
    if (originalSrc) {
      // Force animation to restart by appending a timestamp
      const separator = originalSrc.includes("?") ? "&" : "?";
      img.src = originalSrc + separator + "t=" + Date.now();
      img.removeAttribute("data-original-src");
    }
  }

  function processNewAnimatedImages() {
    const animatedImages = selectAnimatedImages();
    animatedImages.forEach((img) => {
      if (!img.hasAttribute("data-processed")) {
        img.setAttribute("data-processed", "true");
        // Do NOT pause here - only mark as processed
      }
    });
  }

  // Event listeners for mouse enter/leave
  function pauseAnimatedImages() {
    selectAnimatedImages().forEach(pauseAnimatedImage);
  }

  function resumeAnimatedImages() {
    const pausedImages = Array.from(
      document.querySelectorAll("img[data-original-src]")
    );
    pausedImages.forEach(resumeAnimatedImage);
  }

  processNewAnimatedImages();

  // Optional: Pause when window loses visibility
  document.addEventListener("visibilitychange", () => {
    document.hidden ? pauseAnimatedImages() : resumeAnimatedImages();
  });

  // Observe DOM changes to detect dynamically added animated images
  const observer = new MutationObserver(() => {
    processNewAnimatedImages();
  });

  // Start observing the entire document for added nodes
  observer.observe(document.body, { childList: true, subtree: true });

  // Mouse events for pausing/resuming animated images
  document.addEventListener("mouseleave", pauseAnimatedImages);
  document.addEventListener("mouseenter", resumeAnimatedImages);
});
