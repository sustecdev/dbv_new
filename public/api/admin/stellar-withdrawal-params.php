<?php
/**
 * Returns Stellar transaction parameters for building payments.
 * Used by admin "Bulk Complete Stellar" to construct payment transactions client-side.
 */

ob_start();
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
require_once __DIR__ . '/../../../app/Support/Security.php';

$config = require __DIR__ . '/../../../app/Config/config.php';
ob_clean();

$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rateLimitKey = 'admin_stellar_params_' . ((int)($_SESSION['uid'] ?? 0));
if (!Security::checkRateLimit($rateLimitKey, 60, 60)) {
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait a moment.']);
    exit;
}

$stellar = $config['stellar'] ?? [];
$network = $stellar['network'] ?? 'public';
$horizonUrl = $network === 'public'
    ? 'https://horizon.stellar.org'
    : 'https://horizon-testnet.stellar.org';

echo json_encode([
    'success' => true,
    'asset_code' => $stellar['asset_code'] ?? 'DB',
    'asset_issuer' => $stellar['issuer'] ?? '',
    'network' => $network,
    'horizon_url' => $horizonUrl,
]);
