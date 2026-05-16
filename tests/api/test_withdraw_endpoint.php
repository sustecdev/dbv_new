<?php
/**
 * Withdrawal Endpoint Test
 * Tests the withdrawal endpoint with proper POST requests
 */

session_start();
require_once __DIR__ . '/../../app/Config/config.php';

$_SESSION['uid'] = 1290033; // Test user ID

$baseUrl = 'http://localhost/dbnew/public/api/stellar';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Withdrawal Endpoint Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #333; }
        .pass { background: #0a3; color: #fff; }
        .fail { background: #a30; color: #fff; }
        .info { color: #ff0; }
    </style>
</head>
<body>
    <h1>Withdrawal Endpoint Tests</h1>

<?php
function testEndpoint($url, $method, $data = [], $description) {
    global $baseUrl;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<div class='test'>";
    echo "<h3>$description</h3>";
    echo "<p class='info'>URL: $url</p>";
    echo "<p class='info'>Method: $method</p>";
    
    if ($error) {
        echo "<p class='fail'>✗ Error: $error</p>";
    } else {
        echo "<p class='info'>HTTP Code: $httpCode</p>";
        
        $json = json_decode($response, true);
        if ($json) {
            echo "<pre>" . print_r($json, true) . "</pre>";
            if ($json['success'] ?? false) {
                echo "<p class='pass'>✓ SUCCESS</p>";
            } else {
                echo "<p class='fail'>✗ FAILED: " . ($json['message'] ?? 'Unknown error') . "</p>";
            }
        } else {
            echo "<p class='fail'>✗ Invalid JSON response</p>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        }
    }
    
    echo "</div>";
}

// Test 1: GET request (should fail)
testEndpoint(
    "$baseUrl/withdraw.php",
    'GET',
    [],
    'Test 1: GET Request (Should Fail)'
);

// Test 2: POST without authentication (should fail)
testEndpoint(
    "$baseUrl/withdraw.php",
    'POST',
    ['amount' => '10.00', 'address' => 'GXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'],
    'Test 2: POST Without Session (Should Fail)'
);

echo "<p class='info'><strong>Note:</strong> Full withdrawal test requires valid PIN and CSRF token.</p>";
?>

</body>
</html>

