<?php

// === CONFIGURATION ===
$repoUrl = "https://github.com/DotRYOT/videoArchiver.git";
$branch = "main"; // Or 'master'
$projectDir = __DIR__ . '/../..';
$updateScriptPath = __DIR__;

// Paths
$localVersionFile = "$projectDir/version.php";
$remoteVersionUrl = "https://raw.githubusercontent.com/DotRYOT/videoArchiver/refs/heads/main/version.php";

// Style for output
echo "<style>
body { background:#1e1e1e; color:#eee; font-family:sans-serif; padding:2rem; }
.success { color: #0f0; }
.error { color: #f44; }
.code { background:#2d2d2d; padding:0.5rem; border-radius:5px; display:inline-block; margin:1rem 0; }
.button { background:#0f0; color:#1e1e1e; padding:0.5rem 1rem; border-radius:5px; display:inline-block; margin:1rem 0; }
</style>";

echo "<h2>🛠️ Github Video Archiver Auto-Updater</h2>";

// Get local version
if (!file_exists($localVersionFile)) {
  die("<div class='error'>❌ Error: Local version.php not found at: <code class='code'>$localVersionFile</code></div>");
}

include $localVersionFile;
if (!isset($version)) {
  die("<div class='error'>❌ Error: Version variable not found in local version.php</div>");
}
$localVersion = trim($version);

// Fetch remote version
$remoteContent = @file_get_contents($remoteVersionUrl);
if ($remoteContent === false) {
  die("<div class='error'>❌ Error: Unable to fetch remote version from GitHub.</div>");
}

preg_match('/\$version\s*=\s*"([^"]+)"/', $remoteContent, $matches);
if (!isset($matches[1])) {
  die("<div class='error'>❌ Error: Version variable not found in remote version.php</div>");
}
$remoteVersion = trim($matches[1]);

echo "<p>Local Version: <strong>$localVersion</strong></p>";
echo "<p>Remote Version: <strong>$remoteVersion</strong></p>";

if (version_compare($localVersion, $remoteVersion, '>=')) {
  echo "<p class='success'>🎉 Already up to date. No action required.</p>";
  echo "<a href='../../setup.php?update=true' class='button'>🔄 Return to Home</a>";
  exit(0);
}

echo "<p class='success'>⚠️ New version available. Starting update...</p>";

// Change working directory
chdir($projectDir);

if (!is_dir('.git')) {
  die("<div class='error'>❌ Error: Not in a git repository. Please ensure this is a git repository.</div>");
}

function runCmd($cmd)
{
  exec($cmd . ' 2>&1', $output, $returnCode);
  echo "<pre class='code'>Running: $cmd\n" . implode("\n", $output) . "</pre>";
  if ($returnCode !== 0) {
    die("<div class='error'>❌ Error executing command: '$cmd' (Code: $returnCode)</div>");
  }
  return implode("\n", $output);
}

runCmd("git config --global --add safe.directory \"$projectDir\"");

$remoteCheck = shell_exec("git remote -v 2>&1");
if (strpos($remoteCheck, 'origin') === false) {
  echo "<p>Adding remote origin...</p>";
  runCmd("git remote add origin $repoUrl");
}

runCmd("git config --global user.email \"updater@localhost\"");
runCmd("git config --global user.name \"Updater\"");
runCmd("git fetch origin");
runCmd("git reset --hard origin/$branch");
runCmd("git pull origin $branch");

echo "<p class='success'>✅ Successfully updated to the latest version!</p>";
echo "<a href='../../setup.php?update=true' class='button'>🔄 Return to Home</a>";
