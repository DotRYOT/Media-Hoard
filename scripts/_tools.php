<?php
/**
 * Shared cross-platform tool (yt-dlp / ffmpeg) detection.
 *
 * Why this exists: the web server process (httpd/nginx + PHP-FPM, or a systemd
 * service with PrivateUsers=/PrivateTmp=) does NOT inherit your interactive
 * shell's PATH and cannot see user-installed binaries such as
 * ~/.local/bin/yt-dlp (pip --user) or a pyenv/conda install. That is why
 * "which yt-dlp" works in your terminal but the app reports
 * "yt-dlp not found". Detection here therefore never relies on `which` alone:
 * it also probes an extended PATH plus the well-known absolute locations,
 * and it can run Python module entry points (`python -m yt_dlp`) directly.
 */

/**
 * Absolute directories that commonly contain yt-dlp/ffmpeg on Arch and friends.
 */
function getToolSearchDirs()
{
  $dirs = [
    '/usr/bin',
    '/usr/local/bin',
    '/bin',                       // usually a symlink to /usr/bin
    '/sbin',
    '/usr/sbin',
    '/snap/bin',
    '/opt/bin',
    '/opt/ytdlp',
    '/var/lib/snapd/snap/bin',
  ];

  // Per-user installs that are invisible to the web server's PATH
  // (pip install --user, pipx, cargo, mise/asdf shims, npm -g ...).
  foreach (getUserHomeCandidates() as $home) {
    $dirs[] = rtrim($home, '/') . '/.local/bin';
    $dirs[] = rtrim($home, '/') . '/bin';
    $dirs[] = rtrim($home, '/') . '/.cargo/bin';
    $dirs[] = rtrim($home, '/') . '/.pyenv/shims';
    $dirs[] = rtrim($home, '/') . '/.npm-global/bin';
    $dirs[] = rtrim($home, '/') . '/go/bin';
    $dirs[] = rtrim($home, '/') . '/.mise/shims';
    $dirs[] = rtrim($home, '/') . '/.asdf/shims';
  }

  // Conda / miniconda style installs
  foreach (['/opt/conda/bin', '/usr/local/anaconda3/bin'] as $extra) {
    $dirs[] = $extra;
  }

  return array_values(array_unique($dirs));
}

/**
 * Home directories worth probing (current process user + any real users).
 */
function getUserHomeCandidates()
{
  $homes   = [];
  $processUser = null;

  if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $info = @posix_getpwuid(@posix_geteuid());
    if (is_array($info) && !empty($info['dir'])) {
      $homes[] = $info['dir'];
      $processUser = $info['name'] ?? null;
    }
  }

  if (!$processUser) {
    if (isset($_SERVER['USER']) && $_SERVER['USER'] !== '') {
      $processUser = $_SERVER['USER'];
    } elseif (getenv('USER')) {
      $processUser = getenv('USER');
    }
  }

  if ($processUser) {
    $homes[] = '/home/' . $processUser;
    if ($processUser === 'root') {
      $homes[] = '/root';
    }
  }

  // Fall back to reading /etc/passwd so we still find /home/<user>/.local/bin
  // when PHP-FPM runs as www-data/http but yt-dlp was installed by a normal user.
  if (is_readable('/etc/passwd')) {
    $lines = @file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
      foreach ($lines as $line) {
        $parts = explode(':', $line);
        if (count($parts) >= 6) {
          $shell = $parts[6] ?? '';
          $home  = $parts[5] ?? '';
          if ($home !== '' && strpos($home, '/home/') === 0
              && (substr($shell, -4) === 'bash' || substr($shell, -4) === 'zsh')) {
            $homes[] = $home;
          }
        }
      }
    }
  }

  $homes[] = '/root';

  return array_values(array_unique(array_filter($homes)));
}

/**
 * Candidate interpreter paths for running `<python> -m yt_dlp`.
 */
function getPythonCandidates()
{
  $candidates = [];

  foreach (['/usr/bin/python3', '/usr/bin/python', '/usr/local/bin/python3', '/usr/local/bin/python'] as $p) {
    if (is_executable($p)) {
      $candidates[] = $p;
    }
  }

  // Interpreters living next to a discovered yt-dlp binary
  // (venv/conda layouts: <dir>/yt-dlp + <dir>/python), including symlinks.
  foreach (getToolSearchDirs() as $dir) {
    foreach (['python3', 'python'] as $name) {
      $full = rtrim($dir, '/') . '/' . $name;
      if ((is_executable($full) || is_link($full)) && !in_array($full, $candidates, true)) {
        $candidates[] = $full;
      }
    }
  }

  if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $info = @posix_getpwuid(@posix_geteuid());
    if (is_array($info) && !empty($info['dir'])) {
      foreach (['/bin/python3', '/venv/bin/python3', '/miniconda3/bin/python3', '/anaconda3/bin/python3'] as $rel) {
        $full = rtrim($info['dir'], '/') . $rel;
        if (is_executable($full) && !in_array($full, $candidates, true)) {
          $candidates[] = $full;
        }
      }
    }
  }

  return $candidates;
}

/**
 * Best-effort PATH string for shells we spawn (system + user bin dirs).
 */
function buildToolEnvPath($extraDirs = [])
{
  $merged = array_merge($extraDirs, getToolSearchDirs());
  $current = getenv('PATH');
  if (is_string($current) && $current !== '') {
    $merged = array_merge(explode(PATH_SEPARATOR, $current), $merged);
  }

  $clean = [];
  foreach ($merged as $dir) {
    $dir = trim($dir);
    if ($dir !== '' && !in_array($dir, $clean, true)) {
      $clean[] = $dir;
    }
  }

  return implode(PATH_SEPARATOR, $clean);
}

/**
 * Is a candidate path actually usable by us?
 */
function toolPathIsUsable($path, $isWindows)
{
  if (!is_string($path) || $path === '') {
    return false;
  }

  // Windows drive letters / UNC are fine as-is
  if ($isWindows) {
    return file_exists($path);
  }

  // Anything inside someone's home dir must be traversable by the web server,
  // otherwise accept() would succeed but execution would fail with EACCES.
  if (strpos($path, '/root/') === 0 || preg_match('#^/home/[^/]+/#', $path)) {
    if (!@is_readable($path)) {
      return false;
    }
  }

  return @is_executable($path) || @is_link($path);
}

/**
 * Locate a CLI tool. Returns ['path' => string|null, 'source' => string, 'candidates' => array].
 *
 * Search order: bundled copy in scripts/, config override, local copies in the
 * project root, common absolute locations, extended-PATH lookup, then a shell
 * `which`/`where` as last resort.
 */
function findToolExecutable($toolName, $isWindows, $configOverride = null)
{
  $exe        = $isWindows ? $toolName . '.exe' : $toolName;
  $candidates = [];
  $source     = '';

  $addCandidate = function ($candidate) use (&$candidates) {
    if (!is_string($candidate)) {
      return;
    }
    $candidate = trim(str_replace(["\r", "\n"], '', $candidate));
    if ($candidate === '' || stripos($candidate, 'not found') !== false) {
      return;
    }
    if ($candidate[0] !== '"' && substr($candidate, -1) === '"') {
      $candidate = rtrim($candidate, '"');
    }
    if (!in_array($candidate, $candidates, true)) {
      $candidates[] = $candidate;
    }
  };

  // 1. Bundled binary (the app's own updater puts it here)
  $addCandidate(__DIR__ . '/' . $exe);

  // 2. Explicit override from config.json ("ytDlpPath" / "ffmpegPath")
  if (!empty($configOverride)) {
    $addCandidate($configOverride);
  }

  // 3. Local copies dropped into the project root / bin folder
  $projectRoots = [dirname(__DIR__), __DIR__ . '/..'];
  foreach ($projectRoots as $root) {
    $addCandidate($root . '/' . $exe);
    $addCandidate($root . '/bin/' . $exe);
  }

  // 4. Well-known absolute locations (works even without any shell helper)
  if (!$isWindows) {
    foreach (getToolSearchDirs() as $dir) {
      $addCandidate(rtrim($dir, '/') . '/' . $exe);
    }
  }

  // 5. Ask the shell, using an extended PATH
  $devNull = $isWindows ? 'nul' : '/dev/null';
  $finder  = $isWindows ? 'where' : 'which';
  $result  = @shell_exec($finder . ' ' . escapeshellarg($exe) . ' 2>' . $devNull);
  if (is_string($result) && trim($result) !== '') {
    foreach (explode("\n", trim($result)) as $line) {
      $addCandidate($line);
    }
  }

  foreach ($candidates as $candidate) {
    if (toolPathIsUsable($candidate, $isWindows)) {
      return ['path' => $candidate, 'source' => $source, 'candidates' => $candidates];
    }
  }

  return ['path' => null, 'source' => $source, 'candidates' => $candidates];
}

/**
 * Can we run `$binary -m yt_dlp --version` (or `python -m yt_dlp`)?
 */
function verifyYtDlpModule($binary)
{
  if (!toolPathIsUsable($binary, false)) {
    return false;
  }

  $output = @shell_exec(escapeshellarg($binary) . ' -m yt_dlp --version 2>&1');

  return is_string($output) && preg_match('/^\s*\d{4}\.\d{2}\.\d{2}/', $output) === 1;
}

/**
 * Resolve yt-dlp for actual use.
 *
 * @return array{type:string, command:string, version:?string, source:string, candidates:array}
 *   type: 'binary' (run the executable) or 'module' (run `python -m yt_dlp`)
 *         or 'missing'. `command` is already shell-escaped.
 */
function resolveYtDlp($isWindows, $configOverride = null)
{
  $fallback = [
    'type'       => 'missing',
    'command'    => '',
    'version'    => null,
    'source'     => 'not found',
    'candidates' => [],
  ];

  if ($isWindows) {
    $found = findToolExecutable('yt-dlp', true, $configOverride);
    if ($found['path']) {
      return [
        'type'       => 'binary',
        'command'    => '"' . str_replace('\\', '/', $found['path']) . '"',
        'version'    => null,
        'source'     => $found['path'],
        'candidates' => $found['candidates'],
      ];
    }
    $fallback['candidates'] = $found['candidates'];
    return $fallback;
  }

  $found = findToolExecutable('yt-dlp', false, $configOverride);
  $candidates = $found['candidates'];

  // Extension-less names on Arch are frequently Python scripts with a shebang;
  // make sure we can really execute them before committing to a path.
  if ($found['path']) {
    $versionOutput = @shell_exec(escapeshellarg($found['path']) . ' --version 2>&1');
    if (is_string($versionOutput) && preg_match('/\d{4}\.\d{2}\.\d{2}|yt-dlp/i', $versionOutput)) {
      return [
        'type'       => 'binary',
        'command'    => escapeshellarg($found['path']),
        'version'    => trim(explode("\n", trim($versionOutput))[0]),
        'source'     => $found['path'],
        'candidates' => $candidates,
      ];
    }
  }

  // No runnable binary: try Python module entry points.
  foreach (getPythonCandidates() as $python) {
    if (verifyYtDlpModule($python)) {
      return [
        'type'       => 'module',
        'command'    => escapeshellarg($python) . ' -m yt_dlp',
        'version'    => null,
        'source'     => $python . ' -m yt_dlp',
        'candidates' => $candidates,
      ];
    }
  }

  $fallback['candidates'] = $candidates;
  return $fallback;
}

/**
 * Resolve ffmpeg for actual use.
 *
 * @return array{command:string, path:?string, candidates:array}
 *   `command` is empty when ffmpeg could not be located.
 */
function resolveFfmpeg($isWindows, $configOverride = null)
{
  $found = findToolExecutable('ffmpeg', $isWindows, $configOverride);

  if (!$found['path']) {
    return ['command' => '', 'path' => null, 'candidates' => $found['candidates']];
  }

  if ($isWindows) {
    return [
      'command'    => '"' . str_replace('\\', '/', $found['path']) . '"',
      'path'       => $found['path'],
      'candidates' => $found['candidates'],
    ];
  }

  return [
    'command'    => escapeshellarg($found['path']),
    'path'       => $found['path'],
    'candidates' => $found['candidates'],
  ];
}

/**
 * Turn raw yt-dlp log output into a short, human-readable reason.
 * Used by _downloader.php so the UI never shows a bare "Download failed".
 */
function explainYtDlpLog($log, $dlReturn = null)
{
  $clean = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', (string)$log); // strip ANSI colors
  $lines = array_values(array_filter(array_map('trim', explode("\n", $clean)), function ($l) {
    return $l !== '';
  }));

  // Collect every ERROR: line (yt-dlp prints one per failure).
  $errors = [];
  foreach ($lines as $line) {
    if (preg_match('/^ERROR:\s*(.+)$/i', $line, $m)) {
      $errors[] = trim($m[1]);
    }
  }

  $reason = null;
  if (!empty($errors)) {
    $first = $errors[0];
    if (stripos($first, 'sign in to confirm') !== false || stripos($first, 'not a bot') !== false) {
      $reason = 'YouTube blocked this server IP ("Sign in to confirm you\'re not a bot"). This is common on VPS/cloud IPs and VPNs. Fix: use a residential connection, or pass cookies - e.g. export cookies.txt from a logged-in browser and set "cookies" in config.json, or run yt-dlp --cookies-from-browser once as the web server user.';
    } elseif (preg_match('/age restricted|confirm your age/i', $first)) {
      $reason = 'This video is age-restricted and needs YouTube cookies to download. Set "cookies" in config.json to an exported cookies.txt file.';
    } elseif (preg_match('/Private video/i', $first)) {
      $reason = 'The video is private and cannot be downloaded.';
    } elseif (preg_match('/Video unavailable|no longer available/i', $first)) {
      $reason = 'The video is unavailable (removed, region-locked, or a bad link).';
    } elseif (preg_match('/HTTP Error 4\d\d/', $first, $hm)) {
      $reason = 'YouTube rejected the request (' . $hm[0] . '). Try again later or provide cookies via "cookies" in config.json.';
    } elseif (preg_match('/getaddrinfo failed|unable to download webpage|network|timed out|connection/i', $first)) {
      $reason = 'Network error while contacting YouTube from the server: ' . substr($first, 0, 200);
    } else {
      $reason = substr($first, 0, 300);
    }
  }

  if ($reason === null) {
    if ($dlReturn === 127 || preg_match('/command not found/i', $clean)) {
      $reason = 'yt-dlp could not be executed by the web server.';
    } elseif (preg_match('/ffmpeg/i', $clean) && preg_match('/not found|No such file|is not a valid/i', $clean)) {
      $reason = 'yt-dlp ran but ffmpeg is missing or unusable for merging. Install it with "sudo pacman -S ffmpeg" or set "ffmpegPath" in config.json.';
    } elseif ($dlReturn === 143 || preg_match('/Terminated|SIGTERM/i', $clean)) {
      $reason = 'The download was killed before finishing (out of time or memory). Raise max_execution_time/memory_limit and retry.';
    } else {
      $reason = !empty($lines) ? substr((string)end($lines), 0, 300) : 'Unknown yt-dlp failure (exit code ' . var_export($dlReturn, true) . ', empty log).';
    }
  }

  return $reason;
}

/**
 * Human-readable hint for error messages / diagnostics.
 */
function describeToolSearch($candidates, $limit = 6)
{
  if (!is_array($candidates) || count($candidates) === 0) {
    return 'no search locations were checked';
  }
  $shown = array_slice($candidates, 0, $limit);
  $text  = implode(', ', $shown);
  if (count($candidates) > $limit) {
    $text .= ' (and ' . (count($candidates) - $limit) . ' more)';
  }
  return $text;
}

/**
 * Load config.json (shared helper so tool detection can honour overrides).
 */
function loadAppConfig()
{
  $configFile = dirname(__DIR__) . '/config.json';
  if (!file_exists($configFile)) {
    return [];
  }
  $config = json_decode((string)file_get_contents($configFile), true);
  return is_array($config) ? $config : [];
}
