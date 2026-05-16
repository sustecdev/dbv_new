<?php
/**
 * Dashboard Main View
 * Uses component-based architecture for better maintainability
 */

// Debug output - only when app debug mode is enabled (passed from controller)
if (!empty($showDebug)) {
    echo '<pre>DBV: ' . ($dbv ?? 0) . ', USDD: ' . ($usdd ?? 0) . ', Deposits: ' . count($deposits ?? []) . ', Withdrawals: ' . count($withdrawals ?? []) . '</pre>';
}

// Define component path helper
$componentsPath = __DIR__ . '/components';

// Load PathHelper before including components (needed by header)
require_once __DIR__ . '/../../app/Support/PathHelper.php';
$isAdmin = $isAdmin ?? false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - Digital Benefits Exchange</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen">
    <?php include $componentsPath . '/header.php'; ?>

    <main class="max-w-6xl mx-auto px-6 py-8 space-y-8">
        <?php 
        // Ensure balance variables are set with defaults
        $dbv = $dbv ?? 0;
        $usdd = $usdd ?? 0;
        include $componentsPath . '/balance_cards.php'; 
        ?>

        <?php include $componentsPath . '/action_buttons.php'; ?>

        <?php include $componentsPath . '/transaction_list.php'; ?>
    </main>

    <!-- Modals -->
    <?php 
    include $componentsPath . '/modal_network_selection.php';
    include $componentsPath . '/modal_deposit.php';
    include $componentsPath . '/modal_withdrawal.php';
    ?>

    <?php
    // Get base path for dynamic asset loading
    require_once __DIR__ . '/../../app/Support/PathHelper.php';
    $basePath = PathHelper::getBasePath();
    $apiBasePath = PathHelper::getApiBasePath();
    $jsPath = PathHelper::publicAsset('js/dashboard.js');
    $jsPath .= '?v=' . (filemtime(__DIR__ . '/../../public/js/dashboard.js') ?: time());
    ?>
    <script src="<?= htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
    // Base path for API calls - dynamically set based on deployment
    window.APP_BASE_PATH = <?= json_encode($basePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.APP_API_BASE = <?= json_encode($apiBasePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    
    // Network configuration from PHP - MUST be on window object for global access
    window.networkConfig = <?= json_encode($networkConfig ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    console.log('Network config loaded:', window.networkConfig);
    console.log('App base path:', window.APP_BASE_PATH);
    
    // Initialize dashboard with configuration
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Dashboard initializing...');
        console.log('Network config available:', window.networkConfig);
        
        // Load transactions immediately
        loadTransactions();
        
        // Initialize network selection modal
        initNetworkSelection();
        
        // Initialize deposit form (will be updated based on selected network)
        initDepositForm();
        
        // Initialize withdrawal form with configuration
        initWithdrawalForm({
            dbvBalance: <?= $dbv ?? 0 ?>,
            usddBalance: <?= $usdd ?? 0 ?>,
            feeEnabled: <?= ($feeEnabled ?? true) ? 'true' : 'false' ?>,
            withdrawalFee: <?= $withdrawalFee ?? 2.0 ?>,
            pinCheckUrl: '<?= htmlspecialchars($safezonePinCheck ?? 'https://safe.zone/signup/check_pin_api.php', ENT_QUOTES, 'UTF-8') ?>',
            userId: <?= $_SESSION['uid'] ?? 0 ?>,
            networkConfig: window.networkConfig
        });
        
        // Refresh transactions every 10 seconds
        setInterval(loadTransactions, 10000);
    });
    </script>
</body>
</html>
