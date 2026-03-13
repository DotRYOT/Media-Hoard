# Media Hoard

Media Hoard is a local-first PHP media library for downloading, uploading, organizing, and playing videos/images from your own server environment.

## Features

# Media Hoard

Media Hoard is a local-first PHP media library for downloading, uploading, organizing, and playing videos/images from your own server environment.

## Features

- Download videos with `yt-dlp`
- Upload local videos
- Upload local images
- Auto-generate thumbnails with `ffmpeg`
- Favorite videos and images
- Autoplay next video with 5-second "Up next" countdown UI
- Tag-based playlists (create tags, browse playlists, play by tag)
- Remove a section of a video by `start` and `end` time (intro/sponsor trimming)
- Video player controls (play/pause, seek, volume, fullscreen, time display)

## Requirements

- PHP-capable server environment (e.g. [XAMPP](https://www.apachefriends.org/), WAMP, LAMP)
- PHP `zip` extension enabled (required for in-app ZIP updater)
- `ffmpeg` and `ffprobe` available in system PATH
- `yt-dlp` (app can install/update it)
- Git (optional)

## Installation (Git)

1. Install Git: [Download Git](https://git-scm.com/downloads)
2. Clone into your web root (`htdocs` for XAMPP):

   ```bash
   cd path/to/htdocs
   git clone https://github.com/DotRYOT/videoArchiver.git Media-Hoard
   ```

3. Install FFmpeg (Windows example):

   ```bash
   winget install "FFmpeg (Essentials Build)"
   ```

4. Open in browser:

   - `http://localhost/Media-Hoard/`

5. On first run, setup files are created automatically and you can install/update `yt-dlp` from the app.

## Enable PHP Zip (XAMPP on Windows)

If you see `PHP Zip extension is required for non-git updates`, enable it in XAMPP:

1. Open `XAMPP Control Panel`.
2. Click `Config` next to Apache → open `php.ini`.
3. Find `;extension=zip` and remove the leading `;` so it becomes `extension=zip`.
4. Save `php.ini`.
5. Restart Apache from XAMPP Control Panel.
6. Verify by creating a file with `<?php phpinfo(); ?>` and checking that **zip** appears, or run `php -m` and confirm `zip` is listed.

## Usage Notes

- **Tag Playlists**: add tags in a video's settings popup, then use tag chips or the **Playlists** filter on Home.
- **Remove Video Section**: open a video → settings → "Remove Video Section" → enter start/end (seconds or `HH:MM:SS`) → process.
- **Autoplay Next**: when a video ends, an "Up next" card appears with countdown, thumbnail, cancel, and play-now options.

## Settings

- Open **Settings** in the app to change runtime options saved in `config.json`.
- **Max Image Uploads Per Request** controls how many images can be uploaded in one request.
- The image uploader uses the `maxFiles` value from `config.json`.

### Configuration Keys

| Key | Default | Description |
| --- | --- | --- |
| `frameTime` | `5` | Frame offset (in frames) used for thumbnail extraction. |
| `thumbWidth` | `640` | Generated thumbnail width in pixels. |
| `thumbHeight` | `360` | Generated thumbnail height in pixels. |
| `videoExtension` | `mp4` | File extension used for downloaded/processed videos. |
| `openMediaTab` | `false` | Whether media opens in a new tab by default. |
| `maxFiles` | `20` | Maximum number of images allowed per upload request. |

## Stack

- Frontend: HTML, SCSS/CSS, JavaScript
- Backend: PHP
- Tools: [yt-dlp](https://github.com/yt-dlp/yt-dlp), FFmpeg/FFprobe

## License

Licensed under the [MIT License](LICENSE).
