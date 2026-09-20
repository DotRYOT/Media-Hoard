<?php
// Test script to verify ffmpeg accessibility from PHP

echo "<h2>FFmpeg Test</h2>";

// Test 1: Check if ffmpeg exists in common locations
echo "<h3>1. Checking common locations:</h3>";
$commonPaths = [
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/bin/ffmpeg',
];

foreach ($commonPaths as $path) {
    if (file_exists($path)) {
        echo "✓ Found at: $path<br>";
        if (is_executable($path)) {
            echo "  - Executable: Yes<br>";
        } else {
            echo "  - Executable: No<br>";
        }
    } else {
        echo "✗ Not found: $path<br>";
    }
}

// Test 2: Check via which command
echo "<h3>2. Checking via 'which' command:</h3>";
$whichOutput = shell_exec('/usr/bin/which ffmpeg 2>&1');
if ($whichOutput) {
    echo "✓ which ffmpeg: " . trim($whichOutput) . "<br>";
} else {
    echo "✗ which ffmpeg: not found<br>";
}

// Test 3: Try to execute ffmpeg directly
echo "<h3>3. Testing ffmpeg execution:</h3>";
$testOutput = shell_exec('ffmpeg -version 2>&1 | head -1');
if ($testOutput && strpos($testOutput, 'ffmpeg version') !== false) {
    echo "✓ ffmpeg executed successfully: " . htmlspecialchars($testOutput) . "<br>";
} else {
    echo "✗ ffmpeg execution failed: " . htmlspecialchars($testOutput ?: 'no output') . "<br>";
}

// Test 4: Check with explicit PATH
echo "<h3>4. Testing with explicit PATH:</h3>";
$env = ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'];
$testOutput2 = shell_exec('ffmpeg -version 2>&1 | head -1', null, null, $env);
if ($testOutput2 && strpos($testOutput2, 'ffmpeg version') !== false) {
    echo "✓ ffmpeg with explicit PATH: " . htmlspecialchars($testOutput2) . "<br>";
} else {
    echo "✗ ffmpeg with explicit PATH failed: " . htmlspecialchars($testOutput2 ?: 'no output') . "<br>";
}

// Test 5: Current user
echo "<h3>5. Current process info:</h3>";
echo "PHP OS: " . PHP_OS . "<br>";
echo "Current user: " . exec('whoami') . "<br>";
echo "PATH: " . getenv('PATH') . "<br>";

?>
