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
    <ion-icon name="help-outline"></ion-icon>
    <h2>' . $message . '</h2>
    <a href="./">
        <ion-icon name="close-circle-outline"></ion-icon>
    </a>
  </div>';
      }

    case 'error':
      if (isset($_GET['error'])) {
        $error = $_GET['error'];
        echo '
  <div class="errorHeader">
    <ion-icon name="help-outline"></ion-icon>
    <h2>' . $error . '</h2>
    <a href="./">
        <ion-icon name="close-circle-outline"></ion-icon>
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
        <ion-icon name="close-circle-outline"></ion-icon>
    </a>
  </div>';
      }
    case 'warning':
      if (isset($_GET['warning'])) {
        $warning = $_GET['warning'];
        echo '
  <div class="warningAlert">
    <ion-icon name="help-outline"></ion-icon>
    <h2>' . $warning . '</h2>
    <a href="./">
        <ion-icon name="close-circle-outline"></ion-icon>
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
  $YTDLP_PATH = __DIR__ . "/yt-dlp.exe";

  // Debug: Check if file exists
  if (!file_exists($YTDLP_PATH)) {
    $response = [
      'version' => 'File not found',
      'binary_path' => $YTDLP_PATH,
      'success' => false,
      'error' => 'yt-dlp.exe does not exist at the specified path'
    ];

    if ($outputJson) {
      header("Content-Type: application/json");
      echo json_encode($response);
      exit;
    }

    return $response;
  }

  // Run version command
  exec("\"$YTDLP_PATH\" --version", $output, $return_var);

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
function countVideosWithCache($jsonFilePath, $cacheFilePath, $cacheTTL = 3600)
{
  if (file_exists($cacheFilePath) && (time() - filemtime($cacheFilePath)) < $cacheTTL) {
    return (int) file_get_contents($cacheFilePath);
  }
  if (!file_exists($jsonFilePath)) {
    throw new Exception("JSON file not found at path: " . $jsonFilePath);
  }
  $jsonContent = file_get_contents($jsonFilePath);
  $videos = json_decode($jsonContent, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Error decoding JSON: " . json_last_error_msg());
  }
  $videoCount = count($videos);
  file_put_contents($cacheFilePath, $videoCount);
  return $videoCount;
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