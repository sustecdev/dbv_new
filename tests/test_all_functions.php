<?php
/**
 * Comprehensive Test Suite for All App Functions
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../app/Config/config.php';

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
$results = ['pass' => 0, 'fail' => 0, 'warn' => 0];

function test($name, $condition, $message = '') {
    global $results;
    if ($condition === true) {
        echo "✅ PASS: $name\n";
        $results['pass']++;
        return true;
    } elseif ($condition === false) {
        echo "❌ FAIL: $name";
        if ($message) echo " - $message";
        echo "\n";
        $results['fail']++;
        return false;
    } else {
        echo "⚠️  WARN: $name";
        if ($message) echo " - $message";
        echo "\n";
        $results['warn']++;
        return null;
    }
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "           COMPREHENSIVE APPLICATION TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. DATABASE STRUCTURE
echo "📊 1. DATABASE STRUCTURE TESTS\n";
echo str_repeat('─', 60) . "\n";

$tables = ['stellar_withdraw', 'stellar_deposit', 'binance_withdraw', 'binance_deposit', 'ethereum_withdraw', 'ethereum_deposit'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        test("Table $table exists", true);
    } catch (PDOException $e) {
        test("Table $table exists", false, $e->getMessage());
    }
}

foreach (['stellar_withdraw', 'binance_withdraw', 'ethereum_withdraw'] as $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field IN ('fee_usdd', 'fee_hash_yemchain')");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        test("Fee columns in $table", count($cols) === 2, count($cols) . " columns found");
    } catch (PDOException $e) {
        test("Fee columns in $table", false, $e->getMessage());
    }
}

echo "\n";

// 2. API ENDPOINTS
echo "🌐 2. API ENDPOINT TESTS\n";
echo str_repeat('─', 60) . "\n";

$endpoints = [
    'stellar/deposit.php',
    'stellar/withdraw.php',
    'binance/deposit.php',
    'binance/withdraw.php',
    'ethereum/deposit.php',
    'ethereum/withdraw.php',
];

foreach ($endpoints as $endpoint) {
    $url = "$baseUrl/public/api/$endpoint";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $accessible = ($code >= 200 && $code < 400);
    test("Endpoint: $endpoint", $accessible, "HTTP $code");
    
    // Test JSON response
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $isJson = json_decode($response, true) !== null;
    test("  → Returns JSON", $isJson, $isJson ? "" : "Response: " . substr($response, 0, 50));
}

echo "\n";

// 3. CONFIGURATION
echo "⚙️  3. CONFIGURATION TESTS\n";
echo str_repeat('─', 60) . "\n";

test("Config loaded", isset($config) && is_array($config) && count($config) > 0);
test("Database config", isset($config['db']) || isset($config['database']));
test("Stellar config", isset($config['stellar']));
test("Binance config", isset($config['binance']));
test("Ethereum config", isset($config['ethereum']));
test("YEMChain config", isset($config['yemchain']));
test("SafeZone config", isset($config['safezone']));

if (!empty($config['binance'])) {
    test("  → Binance RPC URL", !empty($config['binance']['rpc_url'] ?? ''));
    test("  → Binance vault address", !empty($config['binance']['vault_address'] ?? ''));
    test("  → Binance token contract", !empty($config['binance']['token_contract'] ?? ''));
}

if (!empty($config['ethereum'])) {
    test("  → Ethereum RPC URL", !empty($config['ethereum']['rpc_url'] ?? ''));
    test("  → Ethereum vault address", !empty($config['ethereum']['vault_address'] ?? ''));
    test("  → Ethereum token contract", !empty($config['ethereum']['token_contract'] ?? ''));
}

echo "\n";

// 4. SERVICE CLASSES
echo "🔧 4. SERVICE CLASSES\n";
echo str_repeat('─', 60) . "\n";

$services = [
    'YEMChainService' => 'app/Services/YEMChainService.php',
    'SafeZoneService' => 'app/Services/SafeZoneService.php',
    'BinanceService' => 'app/Services/BinanceService.php',
    'EthereumService' => 'app/Services/EthereumService.php',
];

foreach ($services as $class => $path) {
    $fullPath = __DIR__ . '/../' . $path;
    $exists = file_exists($fullPath);
    test("$class file", $exists);
    
    if ($exists) {
        require_once $fullPath;
        test("  → $class class", class_exists($class));
    }
}

echo "\n";

// 5. CONTROLLERS
echo "🎮 5. CONTROLLER CLASSES\n";
echo str_repeat('─', 60) . "\n";

$controllers = [
    'WithdrawController' => 'app/Controllers/WithdrawController.php',
    'BinanceWithdrawController' => 'app/Controllers/BinanceWithdrawController.php',
    'EthereumWithdrawController' => 'app/Controllers/EthereumWithdrawController.php',
    'BinanceDepositController' => 'app/Controllers/BinanceDepositController.php',
    'EthereumDepositController' => 'app/Controllers/EthereumDepositController.php',
];

foreach ($controllers as $class => $path) {
    $fullPath = __DIR__ . '/../' . $path;
    $exists = file_exists($fullPath);
    test("$class file", $exists);
    
    if ($exists) {
        require_once $fullPath;
        test("  → $class class", class_exists($class));
    }
}

echo "\n";

// 6. DATABASE FUNCTIONS
echo "🗄️  6. DATABASE FUNCTIONS\n";
echo str_repeat('─', 60) . "\n";

require_once __DIR__ . '/../app/Support/WithdrawalLimits.php';
if (class_exists('WithdrawalLimits')) {
    try {
        $total = WithdrawalLimits::getTodayTotal($pdo, 'stellar');
        test("WithdrawalLimits::getTodayTotal (Stellar)", is_numeric($total));
        
        $check = WithdrawalLimits::checkLimit($pdo, 'stellar', 100.0, 1000.0);
        test("WithdrawalLimits::checkLimit", is_array($check) && isset($check['allowed']));
    } catch (Exception $e) {
        test("WithdrawalLimits", false, $e->getMessage());
    }
}

// Test fee query
try {
    $stmt = $pdo->query("SELECT fee_usdd FROM stellar_withdraw LIMIT 1");
    test("Query fee_usdd column", true);
} catch (PDOException $e) {
    test("Query fee_usdd column", false, $e->getMessage());
}

echo "\n";

// 7. FILES
echo "📁 7. FILE STRUCTURE\n";
echo str_repeat('─', 60) . "\n";

$files = [
    'public/index.php',
    'public/js/dashboard.js',
    'app/Config/config.php',
    'routes/api.php',
];

foreach ($files as $file) {
    test("File: $file", file_exists(__DIR__ . '/../' . $file));
}

echo "\n";

// SUMMARY
echo "═══════════════════════════════════════════════════════════════\n";
echo "                        TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ Passed:   {$results['pass']}\n";
echo "❌ Failed:   {$results['fail']}\n";
echo "⚠️  Warnings: {$results['warn']}\n";
echo "═══════════════════════════════════════════════════════════════\n";

$total = array_sum($results);
$success = $total > 0 ? round(($results['pass'] / $total) * 100, 1) : 0;

echo "\nSuccess Rate: $success%\n\n";

if ($results['fail'] === 0) {
    echo "🎉 All critical tests passed! The application is ready.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    exit(1);
}

