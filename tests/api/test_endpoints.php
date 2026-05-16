<?php
/**
 * API Endpoint Tests
 * Tests all API endpoints for accessibility and correct responses
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Endpoint Tests</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #333; }
        .pass { background: #0a3; color: #fff; }
        .fail { background: #a30; color: #fff; }
        .info { color: #ff0; }
    </style>
</head>
<body>
    <h1>API Endpoint Tests</h1>
    
<?php
$baseUrl = 'http://localhost/dbnew/public/api';
$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Simple endpoint test
$tests[] = [
    'name' => 'Test Endpoint (GET)',
    'url' => "$baseUrl/test-endpoint.php",
    'method' => 'GET',
    'expected' => ['success' => true]
];

// Test 2: Stellar withdraw endpoint (should reject GET)
$tests[] = [
    'name' => 'Stellar Withdraw Endpoint (GET - should fail)',
    'url' => "$baseUrl/stellar/withdraw.php",
    'method' => 'GET',
    'expected' => ['success' => false, 'message' => 'Invalid request method']
];

// Test 3: Transaction history (requires session)
session_start();
$_SESSION['uid'] = 1290033; // Test user ID

$tests[] = [
    'name' => 'Transaction History Endpoint',
    'url' => "$baseUrl/get-transaction-history.php?limit=5",
    'method' => 'GET',
    'expected' => ['success' => true]
];

function runTest($test) {
    global $baseUrl;
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $test['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5, // Reduced timeout
        CURLOPT_CONNECTTIMEOUT => 3, // Connection timeout
        CURLOPT_COOKIE => session_name() . '=' . session_id(),
        CURLOPT_SSL_VERIFYPEER => false, // For localhost
        CURLOPT_SSL_VERIFYHOST => false, // For localhost
    ];
    
    if ($test['method'] === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($test['data'] ?? []);
    }
    
    foreach ($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    // Handle connection errors
    if ($errno !== 0 || $error) {
        $errorMsg = $error ?: curl_strerror($errno);
        return [
            'success' => false, 
            'error' => $errorMsg,
            'errno' => $errno,
            'http_code' => 0,
            'data' => null,
            'raw' => null
        ];
    }
    
    // Handle HTTP errors
    if ($httpCode === 0) {
        return [
            'success' => false,
            'error' => 'Connection failed - is Apache running?',
            'http_code' => 0,
            'data' => null,
            'raw' => $response ?: 'No response received'
        ];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300 && $response !== false,
        'http_code' => $httpCode ?: 0,
        'data' => $data,
        'raw' => $response ?: ''
    ];
}

foreach ($tests as $test) {
    echo "<div class='test'>";
    echo "<h3>{$test['name']}</h3>";
    echo "<p class='info'>URL: {$test['url']}</p>";
    echo "<p class='info'>Method: {$test['method']}</p>";
    
    $result = runTest($test);
    
    // Safely get HTTP code with default
    $httpCode = $result['http_code'] ?? 0;
    
    if ($result['success']) {
        echo "<p class='pass'>✓ PASS - HTTP $httpCode</p>";
        $passed++;
    } else {
        echo "<p class='fail'>✗ FAIL - HTTP $httpCode</p>";
        if (isset($result['error']) && $result['error']) {
            echo "<p class='fail'><strong>Error:</strong> {$result['error']}</p>";
            if (isset($result['errno']) && $result['errno'] !== 0) {
                echo "<p class='info'>Error Code: {$result['errno']}</p>";
            }
        }
        if ($httpCode === 0) {
            echo "<p class='fail'><strong>Possible causes:</strong></p>";
            echo "<ul style='color: #ff0;'>";
            echo "<li>Apache is not running</li>";
            echo "<li>URL is incorrect</li>";
            echo "<li>Network connection issue</li>";
            echo "</ul>";
        }
        $failed++;
    }
    
    if (isset($result['data']) && $result['data'] !== null) {
        echo "<pre>" . print_r($result['data'], true) . "</pre>";
    } elseif ($result['raw']) {
        echo "<p class='info'>Raw Response:</p>";
        echo "<pre>" . htmlspecialchars(substr($result['raw'], 0, 500)) . "</pre>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>Passed: <span style='color: #0f0'>$passed</span></p>";
echo "<p>Failed: <span style='color: #f00'>$failed</span></p>";
?>

</body>
</html>

