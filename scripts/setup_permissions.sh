#!/bin/bash
# Media Hoard - Permission Setup Script for Arch/CachyOS Linux
# This script sets up proper file permissions for video and image uploads

# Detect web server user
if id "http" &>/dev/null; then
    WEB_USER="http"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "www-data" &>/dev/null; then
    WEB_USER="www-data"
else
    echo "Warning: Could not detect web server user. Defaulting to 'http' (Apache on Arch/CachyOS)"
    WEB_USER="http"
fi

echo "Detected web server user: $WEB_USER"

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(dirname "$SCRIPT_DIR")"

echo "Setting up permissions for: $BASE_DIR"

# Create necessary directories
echo "Creating required directories..."
mkdir -p "$BASE_DIR/scripts/temp/videos"
mkdir -p "$BASE_DIR/video"
mkdir -p "$BASE_DIR/img/imageFiles"
mkdir -p "$BASE_DIR/cache"

# Set ownership
echo "Setting ownership to $WEB_USER:$WEB_USER..."
chown -R $WEB_USER:$WEB_USER "$BASE_DIR/scripts/temp"
chown -R $WEB_USER:$WEB_USER "$BASE_DIR/video"
chown -R $WEB_USER:$WEB_USER "$BASE_DIR/img/imageFiles"
chown -R $WEB_USER:$WEB_USER "$BASE_DIR/cache"

# Set directory permissions (755 = rwxr-xr-x)
echo "Setting directory permissions to 755..."
find "$BASE_DIR/scripts/temp" -type d -exec chmod 755 {} \;
find "$BASE_DIR/video" -type d -exec chmod 755 {} \;
find "$BASE_DIR/img/imageFiles" -type d -exec chmod 755 {} \;
find "$BASE_DIR/cache" -type d -exec chmod 755 {} \;

# Set file permissions (644 = rw-r--r--)
echo "Setting file permissions to 644..."
find "$BASE_DIR/scripts/temp" -type f -exec chmod 644 {} \;
find "$BASE_DIR/video" -type f -exec chmod 644 {} \;
find "$BASE_DIR/img/imageFiles" -type f -exec chmod 644 {} \;
find "$BASE_DIR/cache" -type f -exec chmod 644 {} \;

# Make JSON files writable by web server
echo "Ensuring JSON files are writable..."
chmod 664 "$BASE_DIR/video/posts.json" 2>/dev/null || true
chmod 664 "$BASE_DIR/video/tags.json" 2>/dev/null || true
chmod 664 "$BASE_DIR/video/favoriteVideos.json" 2>/dev/null || true
chmod 664 "$BASE_DIR/img/imageFiles/images.json" 2>/dev/null || true
chmod 664 "$BASE_DIR/config.json" 2>/dev/null || true

echo ""
echo "Permission setup complete!"
echo ""
echo "Summary:"
echo "  - Web server user: $WEB_USER"
echo "  - Temp directory: $BASE_DIR/scripts/temp (owned by $WEB_USER)"
echo "  - Video directory: $BASE_DIR/video (owned by $WEB_USER)"
echo "  - Image directory: $BASE_DIR/img/imageFiles (owned by $WEB_USER)"
echo "  - Cache directory: $BASE_DIR/cache (owned by $WEB_USER)"
echo ""
echo "If you still experience permission issues, try:"
echo "  sudo systemctl restart httpd    # For Apache"
echo "  sudo systemctl restart nginx    # For nginx"
echo "  sudo systemctl restart php-fpm  # For PHP-FPM"
