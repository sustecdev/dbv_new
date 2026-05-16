<?php
/**
 * Comprehensive Test Suite for All App Functions
 * Tests deposits, withdrawals, fees, PIN validation, error handling, etc.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Config/config.php';

// Load database connection
$host = $config['database']['host'] ?? 'localhost';
$dbname = $config['database']['dbname'] ?? 'Digital';
$username = $config['database']['username'] ?? 'root';
$password = $config['database']['password'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

$baseUrl = 'http://localhost/dbnew';
$passed = 0;
$failed = 0;
$warnings = 0;

function testResult($name, $passed, $message = '') {
    global $passed, $failed, $warnings;
    
    if ($passed === true) {
        echo "✅ PASS: $name\n";
        $passed++;
    } elseif ($passed === false) {
        echo "❌ FAIL: $name";
        if ($message) echo " - $message";
        echo "\n";
        $failed++;
    } else {
        echo "⚠️  WARN: $name";
        if ($message) echo " - $message";
        echo "\n";
        $warnings++;
    }
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "           COMPREHENSIVE APPLICATION TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ============================================================================
// 1. DATABASE STRUCTURE TESTS
// ============================================================================
echo "📊 1. DATABASE STRUCTURE TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

$tables = [
    'stellar_withdraw' => ['id', 'uid', 'address', 'amount', 'fee_usdd', 'fee_hash_yemchain', 'txn_hash_yemchain', 'status', 'created_at'],
    'stellar_deposit' => ['id', 'uid', 'txn_hash_stellar', 'txn_hash_yemchain', 'amount', 'status', 'created_at'],
    'binance_withdraw' => ['id', 'uid', 'address', 'amount', 'fee_usdd', 'fee_hash_yemchain', 'txn_hash_bsc', 'txn_hash_yemchain', 'status', 'created_at'],
    'binance_deposit' => ['id', 'uid', 'txn_hash_bsc', 'txn_hash_yemchain', 'amount', 'status', 'created_at'],
    'ethereum_withdraw' => ['id', 'uid', 'address', 'amount', 'fee_usdd', 'fee_hash_yemchain', 'txn_hash_eth', 'txn_hash_yemchain', 'status', 'created_at'],
    'ethereum_deposit' => ['id', 'uid', 'txn_hash_eth', 'txn_hash_yemchain', 'amount', 'status', 'created_at'],
];

foreach ($tables as $table => $expectedColumns) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        
        $missing = array_diff($expectedColumns, $columns);
        $extra = array_diff($columns, $expectedColumns);
        
        if (empty($missing) && empty($extra)) {
            testResult("Table $table structure", true);
        } else {
            $msg = "";
            if (!empty($missing)) $msg .= "Missing: " . implode(', ', $missing) . ". ";
            if (!empty($extra)) $msg .= "Extra: " . implode(', ', $extra) . ".";
            testResult("Table $table structure", false, $msg);
        }
    } catch (PDOException $e) {
        testResult("Table $table exists", false, $e->getMessage());
    }
}

// Check fee columns specifically
foreach (['stellar_withdraw', 'binance_withdraw', 'ethereum_withdraw'] as $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field IN ('fee_usdd', 'fee_hash_yemchain')");
        $feeColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($feeColumns) === 2) {
            testResult("Fee columns in $table", true);
        } else {
            testResult("Fee columns in $table", false, "Expected 2 columns, found " . count($feeColumns));
        }
    } catch (PDOException $e) {
        testResult("Fee columns in $table", false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// 2. API ENDPOINT ACCESSIBILITY TESTS
// ============================================================================
echo "🌐 2. API ENDPOINT ACCESSIBILITY TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

$endpoints = [
    ['url' => "$baseUrl/public/api/stellar/deposit.php", 'method' => 'POST', 'name' => 'Stellar Deposit'],
    ['url' => "$baseUrl/public/api/stellar/withdraw.php", 'method' => 'POST', 'name' => 'Stellar Withdraw'],
    ['url' => "$baseUrl/public/api/binance/deposit.php", 'method' => 'POST', 'name' => 'Binance Deposit'],
    ['url' => "$baseUrl/public/api/binance/withdraw.php", 'method' => 'POST', 'name' => 'Binance Withdraw'],
    ['url' => "$baseUrl/public/api/ethereum/deposit.php", 'method' => 'POST', 'name' => 'Ethereum Deposit'],
    ['url' => "$baseUrl/public/api/ethereum/withdraw.php", 'method' => 'POST', 'name' => 'Ethereum Withdraw'],
];

foreach ($endpoints as $endpoint) {
    $ch = curl_init($endpoint['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 || $httpCode == 405) { // 405 = Method Not Allowed (expected for GET on POST endpoints)
        testResult($endpoint['name'] . " endpoint accessible", true);
    } else {
        testResult($endpoint['name'] . " endpoint accessible", false, "HTTP $httpCode");
    }
}

echo "\n";

// ============================================================================
// 3. API RESPONSE FORMAT TESTS
// ============================================================================
echo "📦 3. API RESPONSE FORMAT TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

foreach ($endpoints as $endpoint) {
    $ch = curl_init($endpoint['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    // Should return JSON (not HTML)
    $isJson = json_decode($response, true) !== null;
    $isJsonContentType = strpos($contentType, 'application/json') !== false || strpos($contentType, 'json') !== false;
    
    if ($httpCode == 200 && ($isJson || $isJsonContentType)) {
        $data = json_decode($response, true);
        if (isset($data['success'])) {
            testResult($endpoint['name'] . " returns valid JSON", true);
        } else {
            testResult($endpoint['name'] . " returns valid JSON", false, "Missing 'success' key");
        }
    } else {
        testResult($endpoint['name'] . " returns valid JSON", false, "HTTP $httpCode, Content-Type: $contentType");
    }
}

echo "\n";

// ============================================================================
// 4. CONFIGURATION TESTS
// ============================================================================
echo "⚙️  4. CONFIGURATION TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

// Test config loading
testResult("Config file loads", isset($config) && is_array($config), "");

// Test database config
$dbConfig = $config['database'] ?? [];
testResult("Database config exists", !empty($dbConfig), "");

// Test Stellar config
$stellarConfig = $config['stellar'] ?? [];
testResult("Stellar config exists", !empty($stellarConfig), "");
if (!empty($stellarConfig)) {
    testResult("Stellar vault address", !empty($stellarConfig['vault'] ?? ''), "");
    testResult("Stellar network", !empty($stellarConfig['network'] ?? ''), "");
}

// Test Binance config
$binanceConfig = $config['binance'] ?? [];
testResult("Binance config exists", !empty($binanceConfig), "");
if (!empty($binanceConfig)) {
    testResult("Binance vault address", !empty($binanceConfig['vault_address'] ?? ''), "");
    testResult("Binance RPC URL", !empty($binanceConfig['rpc_url'] ?? ''), "");
    testResult("Binance token contract", !empty($binanceConfig['token_contract'] ?? ''), "");
}

// Test Ethereum config
$ethereumConfig = $config['ethereum'] ?? [];
testResult("Ethereum config exists", !empty($ethereumConfig), "");
if (!empty($ethereumConfig)) {
    testResult("Ethereum vault address", !empty($ethereumConfig['vault_address'] ?? ''), "");
    testResult("Ethereum RPC URL", !empty($ethereumConfig['rpc_url'] ?? ''), "");
    testResult("Ethereum token contract", !empty($ethereumConfig['token_contract'] ?? ''), "");
}

// Test YEMChain config
$yemConfig = $config['yemchain'] ?? [];
testResult("YEMChain config exists", !empty($yemConfig), "");
if (!empty($yemConfig)) {
    testResult("YEMChain base URL", !empty($yemConfig['base'] ?? ''), "");
    testResult("YEMChain API key", !empty($yemConfig['api_key'] ?? ''), "");
}

// Test SafeZone config
$safezoneConfig = $config['safezone'] ?? [];
testResult("SafeZone config exists", !empty($safezoneConfig), "");
if (!empty($safezoneConfig)) {
    testResult("SafeZone PIN check URL", !empty($safezoneConfig['pin_check_url'] ?? ''), "");
}

// Test withdrawal fee config
$withdrawalConfig = $config['withdrawal'] ?? [];
testResult("Withdrawal config exists", !empty($withdrawalConfig), "");
if (!empty($withdrawalConfig)) {
    testResult("Withdrawal fee enabled setting", isset($withdrawalConfig['fee_enabled']), "");
    testResult("Withdrawal fee USDD", isset($withdrawalConfig['fee_usdd']), "");
}

echo "\n";

// ============================================================================
// 5. SERVICE CLASS AVAILABILITY TESTS
// ============================================================================
echo "🔧 5. SERVICE CLASS AVAILABILITY TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

$serviceClasses = [
    'YEMChainService' => 'app/Services/YEMChainService.php',
    'SafeZoneService' => 'app/Services/SafeZoneService.php',
    'BinanceService' => 'app/Services/BinanceService.php',
    'EthereumService' => 'app/Services/EthereumService.php',
];

foreach ($serviceClasses as $className => $filePath) {
    $fullPath = __DIR__ . '/../' . $filePath;
    if (file_exists($fullPath)) {
        require_once $fullPath;
        if (class_exists($className)) {
            testResult("$className class", true);
        } else {
            testResult("$className class", false, "File exists but class not found");
        }
    } else {
        testResult("$className class", false, "File not found: $filePath");
    }
}

echo "\n";

// ============================================================================
// 6. CONTROLLER CLASS AVAILABILITY TESTS
// ============================================================================
echo "🎮 6. CONTROLLER CLASS AVAILABILITY TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

$controllerClasses = [
    'WithdrawController' => 'app/Controllers/WithdrawController.php',
    'BinanceWithdrawController' => 'app/Controllers/BinanceWithdrawController.php',
    'EthereumWithdrawController' => 'app/Controllers/EthereumWithdrawController.php',
    'BinanceDepositController' => 'app/Controllers/BinanceDepositController.php',
    'EthereumDepositController' => 'app/Controllers/EthereumDepositController.php',
];

foreach ($controllerClasses as $className => $filePath) {
    $fullPath = __DIR__ . '/../' . $filePath;
    if (file_exists($fullPath)) {
        require_once $fullPath;
        if (class_exists($className)) {
            testResult("$className class", true);
        } else {
            testResult("$className class", false, "File exists but class not found");
        }
    } else {
        testResult("$className class", false, "File not found: $filePath");
    }
}

echo "\n";

// ============================================================================
// 7. ERROR HANDLING TESTS
// ============================================================================
echo "⚠️  7. ERROR HANDLING TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

// Test invalid request method (should return JSON error)
foreach ($endpoints as $endpoint) {
    $ch = curl_init($endpoint['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); // Use GET on POST endpoint
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode == 200 && isset($data['success']) && $data['success'] === false) {
        testResult($endpoint['name'] . " handles invalid method", true);
    } else {
        testResult($endpoint['name'] . " handles invalid method", false, "Expected JSON error response");
    }
}

echo "\n";

// ============================================================================
// 8. DATABASE QUERY TESTS
// ============================================================================
echo "🗄️  8. DATABASE QUERY TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

// Test WithdrawalLimits class
require_once __DIR__ . '/../app/Support/WithdrawalLimits.php';
if (class_exists('WithdrawalLimits')) {
    try {
        $todayTotal = WithdrawalLimits::getTodayTotal($pdo, 'stellar');
        testResult("WithdrawalLimits::getTodayTotal (Stellar)", is_numeric($todayTotal), "");
        
        $limitCheck = WithdrawalLimits::checkLimit($pdo, 'stellar', 100.0, 1000.0);
        testResult("WithdrawalLimits::checkLimit", is_array($limitCheck) && isset($limitCheck['allowed']), "");
        
        $todayTotalBinance = WithdrawalLimits::getTodayTotal($pdo, 'binance');
        testResult("WithdrawalLimits::getTodayTotal (Binance)", is_numeric($todayTotalBinance), "");
        
        $todayTotalEth = WithdrawalLimits::getTodayTotal($pdo, 'ethereum');
        testResult("WithdrawalLimits::getTodayTotal (Ethereum)", is_numeric($todayTotalEth), "");
    } catch (Exception $e) {
        testResult("WithdrawalLimits methods", false, $e->getMessage());
    }
} else {
    testResult("WithdrawalLimits class", false, "Class not found");
}

// Test database query for withdrawals with fees
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stellar_withdraw WHERE fee_usdd > 0 LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    testResult("Query withdrawals with fees (Stellar)", true, "");
} catch (PDOException $e) {
    testResult("Query withdrawals with fees (Stellar)", false, $e->getMessage());
}

echo "\n";

// ============================================================================
// 9. FILE STRUCTURE TESTS
// ============================================================================
echo "📁 9. FILE STRUCTURE TESTS\n";
echo "────────────────────────────────────────────────────────────────\n";

$requiredFiles = [
    'public/index.php',
    'public/js/dashboard.js',
    'app/Config/config.php',
    'app/Support/WithdrawalLimits.php',
    'routes/api.php',
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    testResult("File exists: $file", file_exists($fullPath), "");
}

echo "\n";

// ============================================================================
// 10. MIGRATION VERIFICATION
// ============================================================================
echo "🔄 10. MIGRATION VERIFICATION\n";
echo "────────────────────────────────────────────────────────────────\n";

foreach (['stellar_withdraw', 'binance_withdraw', 'ethereum_withdraw'] as $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field IN ('fee_usdd', 'fee_hash_yemchain')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($columns) === 2) {
            $colNames = array_column($columns, 'Field');
            if (in_array('fee_usdd', $colNames) && in_array('fee_hash_yemchain', $colNames)) {
                testResult("Fee migration: $table", true);
            } else {
                testResult("Fee migration: $table", false, "Columns not found");
            }
        } else {
            testResult("Fee migration: $table", false, "Expected 2 columns, found " . count($columns));
        }
    } catch (PDOException $e) {
        testResult("Fee migration: $table", false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "                        TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ Passed:   $passed\n";
echo "❌ Failed:   $failed\n";
echo "⚠️  Warnings: $warnings\n";
echo "═══════════════════════════════════════════════════════════════\n";

$total = $passed + $failed + $warnings;
$successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

echo "\nSuccess Rate: $successRate%\n\n";

if ($failed === 0) {
    echo "🎉 All critical tests passed! The application is ready.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    exit(1);
}

