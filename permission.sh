#!/bin/bash

# permission.sh - Fixes ownership and permissions for Media-Hoard uploads
# Run this script with sudo from the root of your Media-Hoard directory.

# Configuration
WEB_USER="mstrcomp"
WEB_GROUP="webdev"
TARGET_DIR="$(pwd)"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script with sudo."
    exit 1
fi

# Check if we are in the right directory
if [ ! -f "index.php" ] || [ ! -d "scripts" ]; then
    echo "Error: This script must be run from the root of the Media-Hoard directory."
    exit 1
fi

echo "Fixing permissions in $TARGET_DIR..."

# 1. Set ownership recursively for the whole project
chown -R "$WEB_USER":"$WEB_GROUP" "$TARGET_DIR"

# 2. Set directory permissions: rwx for owner, rwx for group (with setgid), r-x for others
# The '2' (setgid) ensures new files/dirs inherit the 'webdev' group
find "$TARGET_DIR" -type d -exec chmod 2775 {} +

# 3. Set file permissions: rw- for owner, rw- for group, r-- for others
find "$TARGET_DIR" -type f -exec chmod 664 {} +

# 4. Specifically ensure the critical upload directories are writable by the group
chmod -R g+w "$TARGET_DIR/video"
chmod -R g+w "$TARGET_DIR/scripts/temp"
chmod -R g+w "$TARGET_DIR/img"

echo "Permissions successfully updated!"
