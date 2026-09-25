<?php
function randStringGen($length, $type = 'normal')
{
  switch ($type) {
    case 'normal':
    default:
      $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
      }
      return $randomString;

    case 'numbers':
      $characters = '0123456789';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
      }
      return $randomString;

    case 'letters':
      $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
      }
      return $randomString;
  }
}
function getYoutubeVideoTitleScrape($videoId)
{
  $url = "https://www.youtube.com/watch?v={$videoId}";

  // Initialize cURL session
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  // Execute the request and get the HTML content
  $html = curl_exec($ch);
  curl_close($ch);

  // Use a regular expression to extract the title
  if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
    $title = trim($matches[1]);
    // Remove " - YouTube" suffix
    $title = str_replace(' - YouTube', '', $title);
    return $title;
  } else {
    return 'Error: Unable to retrieve video title.';
  }
}
function filter_user_input($input, $type = 'string')
{
  // Ensure input is a string
  $input = $input ?? ''; // Set to empty string if null

  // First, trim whitespace
  $input = trim($input);

  // Apply type-specific filtering
  switch ($type) {
    case 'email':
      $input = filter_var($input, FILTER_SANITIZE_EMAIL);
      if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
        return false;
      }
      break;

    case 'url':
      $input = filter_var($input, FILTER_SANITIZE_URL);
      if (!filter_var($input, FILTER_VALIDATE_URL)) {
        return false;
      }
      break;

    case 'int':
      $input = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
      if (filter_var($input, FILTER_VALIDATE_INT) === false) {
        return false;
      }
      break;

    case 'float':
      $input = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
      if (filter_var($input, FILTER_VALIDATE_FLOAT) === false) {
        return false;
      }
      break;

    case 'string':
    default:
      // Remove any HTML tags
      $input = strip_tags($input);
      // Convert special characters to HTML entities
      $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      break;
  }

  // Additional security measures
  $input = str_replace(array("\r", "\n", "%0a", "%0d"), '', $input); // Remove line breaks
  $input = preg_replace('/[^\p{L}\p{N}\s\-_,\.]/u', '', $input); // Allow only letters, numbers, spaces, and some punctuation

  // Limit the length of the input (adjust as needed)
  $input = substr($input, 0, 255);

  return $input;
}
function redirectTo($url)
{
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
  $domain = $_SERVER['HTTP_HOST'];
  $redirecturl = $protocol . $domain . $url;

  if ($domain == "localhost") {
    $redirecturl = $protocol . "localhost/MediaHoard/" . $url;
  }

  header("Location: $redirecturl");
  exit;
}
function filePath($url)
{
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
  $domain = $_SERVER['HTTP_HOST'];
  $redirecturl = $protocol . $domain . $url;

  if ($domain == "localhost") {
    $redirecturl = $protocol . "localhost/pixlshare.cc/app" . $url;
  }

  return $redirecturl;
}
function generateErrorUrl($errorMessage)
{
  $encodedError = urlencode($errorMessage);

  $fullUrl = "?error={$encodedError}";

  return $fullUrl;
}
function generateMessageUrl($message, $type = 'message')
{
  switch ($type) {
    case 'message':
    default:
      $urlEncode = urlencode($message);
      $fullUrl = "?message={$urlEncode}";
      return $fullUrl;

    case 'error':
      $urlEncode = urlencode($message);
      $fullUrl = "?error={$urlEncode}";
      return $fullUrl;

    case 'success':
      $urlEncode = urlencode($message);
      $fullUrl = "?success={$urlEncode}";
      return $fullUrl;

    case 'warning':
      $urlEncode = urlencode($message);
      $fullUrl = "?warning={$urlEncode}";
      return $fullUrl;
  }
}
function handleError($message)
{
  $Error = generateMessageUrl($message, 'error');
  redirectTo("$Error");
  exit;
}
function displayMessage($type = 'message')
{
  switch ($type) {
    case 'message':
    default:
      if (isset($_GET['message'])) {
        $message = $_GET['message'];
        echo '
  <div class="errorHeader">
    <span class="gicon">help</span>
    <h2>' . $message . '</h2>
    <a href="./">
        <span class="gicon">cancel</span>
    </a>
  </div>';
      }

    case 'error':
      if (isset($_GET['error'])) {
        $error = $_GET['error'];
        echo '
  <div class="errorHeader">
    <span class="gicon">help</span>
    <h2>' . $error . '</h2>
    <a href="./">
        <span class="gicon">cancel</span>
    </a>
  </div>';
      }
    case 'success':
      if (isset($_GET['success'])) {
        $success = $_GET['success'];
        echo '
  <div class="successAlert">
    <h2>' . $success . '</h2>
    <a href="./">
        <span class="gicon">cancel</span>
    </a>
  </div>';
      }
    case 'warning':
      if (isset($_GET['warning'])) {
        $warning = $_GET['warning'];
        echo '
  <div class="warningAlert">
    <span class="gicon">help</span>
    <h2>' . $warning . '</h2>
    <a href="./">
        <span class="gicon">cancel</span>
    </a>
  </div>';
      }
  }
}
function downloadFile($fileUrl, $destinationPath)
{
  ob_start();

  if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
    return ['success' => false, 'message' => 'Invalid URL'];
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $fileUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // Follow redirects
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  // Timeout after 40 seconds
  curl_setopt($ch, CURLOPT_TIMEOUT, 40);
  $fileContents = curl_exec($ch);

  if (curl_errno($ch)) {
    curl_close($ch);
    return ['success' => false, 'message' => 'Error fetching file: ' . curl_error($ch)];
  }

  curl_close($ch);

  if (@file_put_contents($destinationPath, $fileContents) === false) {
    return ['success' => false, 'message' => 'Failed to write file to destination.'];
  }

  return ['success' => true, 'message' => 'File downloaded successfully.', 'path' => $destinationPath];
}
function getYtDlpVersion($outputJson = false)
{
  // Cross-platform yt-dlp binary detection
  $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $ytdlpExe = $isWindows ? 'yt-dlp.exe' : 'yt-dlp';
  
  // Check for yt-dlp in scripts folder or system PATH
  $YTDLP_PATH = null;
  if (file_exists(__DIR__ . '/' . $ytdlpExe)) {
    $YTDLP_PATH = __DIR__ . '/' . $ytdlpExe;
  } else {
    // Search in system PATH
    if ($isWindows) {
      $whereOutput = shell_exec('where ' . escapeshellarg($ytdlpExe) . ' 2>nul');
      if ($whereOutput && trim($whereOutput) !== '') {
        $YTDLP_PATH = trim(explode("\n", trim($whereOutput))[0]);
      }
    } else {
      $whichOutput = shell_exec('which ' . escapeshellarg($ytdlpExe) . ' 2>/dev/null');
      if ($whichOutput && trim($whichOutput) !== '') {
        $YTDLP_PATH = trim($whichOutput);
      }
    }
  }

  // Debug: Check if file exists
  if (!$YTDLP_PATH || !file_exists($YTDLP_PATH)) {
    $response = [
      'version' => 'File not found',
      'binary_path' => $YTDLP_PATH ?? 'not found',
      'success' => false,
      'error' => 'yt-dlp does not exist at the specified path'
    ];

    if ($outputJson) {
      header("Content-Type: application/json");
      echo json_encode($response);
      exit;
    }

    return $response;
  }

  // Run version command with cross-platform escaping
  if ($isWindows) {
    exec("\"$YTDLP_PATH\" --version", $output, $return_var);
  } else {
    exec(escapeshellarg($YTDLP_PATH) . ' --version', $output, $return_var);
  }

  // Build response
  $response = [
    'version' => $output[0] ?? 'Failed to get version',
    'loaded' => ($return_var === 0) ? 'Loaded' : 'Error',
    'binary_path' => $YTDLP_PATH,
    'exec_output' => $output,
    'exec_return_code' => $return_var,
    'success' => ($return_var === 0)
  ];

  if ($outputJson) {
    header("Content-Type: application/json");
    echo json_encode($response);
    exit;
  }

  return $response;
}
function deleteDirectory($dir)
{
  if (!is_dir($dir)) {
    throw new Exception("Not a valid directory: $dir");
  }
  $items = glob($dir . '/*');
  foreach ($items as $item) {
    if (is_dir($item)) {
      deleteDirectory($item);
    } else {
      unlink($item);
    }
  }
  rmdir($dir);
}