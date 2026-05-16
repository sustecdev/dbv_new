<?php
/**
 * Check YEMChain Balance Script
 * Checks balance for a specific UID and asset
 */

require_once __DIR__ . '/app/Services/YEMChainService.php';

// Get config (only require once)
if (!function_exists('envv')) {
    $config = require(__DIR__ . '/app/Config/config.php');
} else {
    $config = require(__DIR__ . '/app/Config/config.php');
}

// Initialize YEMChain service
$yem = new YEMChainService(
    $config['yemchain']['base'],
    $config['yemchain']['key']
);

// Check balance
$uid = 1290033;
$asset = 'DBV';

echo "=== YEMChain Balance Check ===\n\n";
echo "UID: $uid\n";
echo "Asset: $asset\n";
echo "YEMChain API Base: {$config['yemchain']['base']}\n\n";

try {
    $balance = $yem->getBalance($uid, $asset);
    echo "✅ Balance: " . number_format($balance, 2) . " $asset\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

