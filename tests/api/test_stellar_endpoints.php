<?php
/**
 * Stellar Network Endpoint Tests
 * Comprehensive tests for Stellar deposit and withdrawal endpoints
 */

session_start();
$_SESSION['uid'] = 1290033; // Test user ID

$baseUrl = 'http://localhost/dbnew/public/api/stellar';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stellar Endpoint Tests</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
        .test { margin: 15px 0; padding: 15px; border: 1px solid #333; background: #2a2a2a; border-radius: 5px; }
        .pass { border-left: 4px solid #0f0; background: #1a3a1a; }
        .fail { border-left: 4px solid #f00; background: #3a1a1a; }
        .info { color: #ff0; margin: 5px 0; }
        .code { background: #000; padding: 10px; border-radius: 3px; overflow-x: auto; }
        h1 { color: #0ff; }
        h2 { color: #ff0; margin-top: 30px; }
    </style>
</head>
<body>
    <h1>🌌 Stellar Network Endpoint Tests</h1>
    
    <p>Testing all Stellar-related API endpoints...</p>

<?php
function makeRequest($url, $method = 'GET', $data = []) {
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
    
    return [
        'success' => $httpCode === 200 && !$error,
        'http_code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

$tests = [];

// Test 1: Deposit endpoint - GET (should fail)
$result = makeRequest("$baseUrl/deposit.php", 'GET');
$tests[] = [
    'name' => 'Deposit Endpoint - GET Request',
    'description' => 'Should reject GET requests',
    'result' => $result,
    'expected' => ['success' => false, 'message' => 'Invalid request method']
];

// Test 2: Withdraw endpoint - GET (should fail)
$result = makeRequest("$baseUrl/withdraw.php", 'GET');
$tests[] = [
    'name' => 'Withdraw Endpoint - GET Request',
    'description' => 'Should reject GET requests',
    'result' => $result,
    'expected' => ['success' => false, 'message' => 'Invalid request method']
];

// Test 3: Withdraw endpoint - POST without data (should fail)
$result = makeRequest("$baseUrl/withdraw.php", 'POST', []);
$tests[] = [
    'name' => 'Withdraw Endpoint - POST Without Data',
    'description' => 'Should reject empty POST requests',
    'result' => $result,
    'expected' => ['success' => false]
];

// Test 4: Test endpoint (should work)
$result = makeRequest("$baseUrl/test-withdraw.php", 'GET');
$tests[] = [
    'name' => 'Test Endpoint',
    'description' => 'Simple test endpoint should be accessible',
    'result' => $result,
    'expected' => ['success' => true]
];

foreach ($tests as $test) {
    $result = $test['result'];
    $expected = $test['expected'];
    $passed = false;
    
    if ($result['success'] && isset($result['data'])) {
        if (isset($expected['message'])) {
            $passed = isset($result['data']['message']) && 
                      strpos($result['data']['message'], $expected['message']) !== false;
        } else {
            $passed = ($result['data']['success'] ?? false) === ($expected['success'] ?? true);
        }
    }
    
    echo "<div class='test " . ($passed ? 'pass' : 'fail') . "'>";
    echo "<h3>" . ($passed ? '✓' : '✗') . " {$test['name']}</h3>";
    echo "<p class='info'>{$test['description']}</p>";
    echo "<p><strong>HTTP Code:</strong> {$result['http_code']}</p>";
    
    if ($result['error']) {
        echo "<p><strong>Error:</strong> {$result['error']}</p>";
    }
    
    if ($result['data']) {
        echo "<div class='code'><pre>" . print_r($result['data'], true) . "</pre></div>";
    } else {
        echo "<p>No JSON response</p>";
        if ($result['raw']) {
            echo "<div class='code'><pre>" . htmlspecialchars(substr($result['raw'], 0, 500)) . "</pre></div>";
        }
    }
    
    echo "</div>";
}

echo "<h2>Summary</h2>";
echo "<p>Run time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Note:</strong> These tests verify endpoint accessibility and basic validation.</p>";
echo "<p>Full functionality tests require valid authentication, PIN, and CSRF tokens.</p>";
?>

</body>
</html>

