<?php
/**
 * Connection Test
 * Quick test to verify Apache and PHP are working
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connection Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        .pass { color: #0f0; }
        .fail { color: #f00; }
        .info { color: #ff0; }
    </style>
</head>
<body>
    <h1>Connection Test</h1>
    
<?php
echo "<h2>Server Status</h2>";
echo "<p>PHP Version: <span class='pass'>" . phpversion() . "</span></p>";
echo "<p>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";

echo "<h2>Local File Check</h2>";
$testFile = __DIR__ . '/../public/api/test-endpoint.php';
if (file_exists($testFile)) {
    echo "<p class='pass'>✓ Test endpoint file exists</p>";
    echo "<p class='info'>Path: $testFile</p>";
} else {
    echo "<p class='fail'>✗ Test endpoint file NOT found</p>";
}

echo "<h2>URL Test</h2>";
$testUrl = 'http://localhost/dbnew/public/api/test-endpoint.php';
echo "<p>Testing: <a href='$testUrl' target='_blank'>$testUrl</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

if ($errno !== 0 || $error) {
    echo "<p class='fail'>✗ Connection failed</p>";
    echo "<p class='fail'>Error: " . ($error ?: curl_strerror($errno)) . "</p>";
    echo "<p class='fail'>Error Code: $errno</p>";
    echo "<p class='info'><strong>Solution:</strong> Make sure Apache is running in XAMPP Control Panel</p>";
} elseif ($httpCode === 0) {
    echo "<p class='fail'>✗ No response from server</p>";
    echo "<p class='info'><strong>Solution:</strong> Check if Apache is running and the URL is correct</p>";
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo "<p class='pass'>✓ Connection successful - HTTP $httpCode</p>";
    $data = json_decode($response, true);
    if ($data) {
        echo "<pre>" . print_r($data, true) . "</pre>";
    } else {
        echo "<p class='info'>Response: " . htmlspecialchars(substr($response, 0, 200)) . "</p>";
    }
} else {
    echo "<p class='fail'>✗ HTTP Error: $httpCode</p>";
    echo "<p class='info'>Response: " . htmlspecialchars(substr($response, 0, 200)) . "</p>";
}

echo "<h2>Quick Diagnostics</h2>";
echo "<ol style='color: #ff0;'>";
echo "<li>Is Apache running? Check XAMPP Control Panel</li>";
echo "<li>Is the URL correct? Should be: http://localhost/dbnew/public/api/...</li>";
echo "<li>Can you access http://localhost/dbnew/public/dashboard.php in browser?</li>";
echo "<li>Check Apache error logs: C:\\xampp\\apache\\logs\\error.log</li>";
echo "</ol>";
?>

</body>
</html>

