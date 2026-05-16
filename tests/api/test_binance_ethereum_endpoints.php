<?php
/**
 * Test Binance and Ethereum Deposit/Withdraw Endpoints
 * This script tests that all endpoints are accessible and return proper JSON responses
 */

$config = require __DIR__ . '/../../app/Config/config.php';

$baseUrl = 'http://localhost/dbnew/public/api';

// Test endpoints
$endpoints = [
    'Binance Deposit' => $baseUrl . '/binance/deposit.php',
    'Binance Withdraw' => $baseUrl . '/binance/withdraw.php',
    'Ethereum Deposit' => $baseUrl . '/ethereum/deposit.php',
    'Ethereum Withdraw' => $baseUrl . '/ethereum/withdraw.php',
];

echo "=== Testing Binance and Ethereum Endpoints ===\n\n";

$results = [];

foreach ($endpoints as $name => $url) {
    echo "Testing: $name\n";
    echo "URL: $url\n";
    
    // Test with GET (should return error for POST-only endpoints)
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'name' => $name,
        'url' => $url,
        'http_code' => $httpCode ?? 0,
        'content_type' => $contentType ?? 'unknown',
        'curl_error' => $curlError,
        'response' => $response ? substr($response, 0, 200) : 'No response'
    ];
    
    // Check if response is JSON
    if ($response) {
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['is_json'] = true;
            $result['json_data'] = $json;
        } else {
            $result['is_json'] = false;
            $result['json_error'] = json_last_error_msg();
        }
    }
    
    // Validate
    $result['status'] = 'PASS';
    if ($curlError) {
        $result['status'] = 'FAIL';
        $result['reason'] = 'Connection error: ' . $curlError;
    } elseif ($httpCode >= 500) {
        $result['status'] = 'FAIL';
        $result['reason'] = 'Server error (HTTP ' . $httpCode . ')';
    } elseif (!$result['is_json'] && $httpCode === 200) {
        $result['status'] = 'WARN';
        $result['reason'] = 'Response is not JSON';
    } elseif ($httpCode === 405 || $httpCode === 403 || $httpCode === 401) {
        $result['status'] = 'INFO';
        $result['reason'] = 'Expected response for GET request (endpoint requires POST)';
    }
    
    $results[] = $result;
    
    // Display result
    echo "Status: " . $result['status'];
    if (isset($result['reason'])) {
        echo " - " . $result['reason'];
    }
    echo "\n";
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Content-Type: " . $result['content_type'] . "\n";
    if ($result['is_json']) {
        echo "Response (JSON): " . json_encode($result['json_data'], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Response (text): " . $result['response'] . "\n";
    }
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

// Summary
echo "\n=== Summary ===\n";
$passCount = 0;
$failCount = 0;
$warnCount = 0;
$infoCount = 0;

foreach ($results as $result) {
    switch ($result['status']) {
        case 'PASS':
            $passCount++;
            break;
        case 'FAIL':
            $failCount++;
            break;
        case 'WARN':
            $warnCount++;
            break;
        case 'INFO':
            $infoCount++;
            break;
    }
}

echo "✓ Passed: $passCount\n";
echo "✗ Failed: $failCount\n";
echo "⚠ Warnings: $warnCount\n";
echo "ℹ Info: $infoCount\n\n";

// Check configuration
echo "=== Configuration Check ===\n";
$binanceConfig = $config['binance'] ?? [];
$ethereumConfig = $config['ethereum'] ?? [];

echo "Binance:\n";
echo "  RPC URL: " . ($binanceConfig['rpc_url'] ?? 'NOT SET') . "\n";
echo "  Vault Address: " . (isset($binanceConfig['vault_address']) && !empty($binanceConfig['vault_address']) ? substr($binanceConfig['vault_address'], 0, 10) . '...' : 'NOT SET') . "\n";
echo "  Token Contract: " . (isset($binanceConfig['token_contract']) && !empty($binanceConfig['token_contract']) ? substr($binanceConfig['token_contract'], 0, 10) . '...' : 'NOT SET') . "\n";

echo "\nEthereum:\n";
echo "  RPC URL: " . ($ethereumConfig['rpc_url'] ?? 'NOT SET') . "\n";
echo "  Vault Address: " . (isset($ethereumConfig['vault_address']) && !empty($ethereumConfig['vault_address']) ? substr($ethereumConfig['vault_address'], 0, 10) . '...' : 'NOT SET') . "\n";
echo "  Token Contract: " . (isset($ethereumConfig['token_contract']) && !empty($ethereumConfig['token_contract']) ? substr($ethereumConfig['token_contract'], 0, 10) . '...' : 'NOT SET') . "\n";

echo "\n=== Test Complete ===\n";

