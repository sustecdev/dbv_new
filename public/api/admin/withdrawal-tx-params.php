<?php
/**
 * Returns transaction parameters for building EVM (BSC/Ethereum) ERC-20 transfer.
 * Used by admin "Complete with Wallet" and private-key bulk (needs_rpc=1 requires rpc_url).
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

$rateLimitKey = 'admin_tx_params_' . ((int)($_SESSION['uid'] ?? 0));
if (!Security::checkRateLimit($rateLimitKey, 60, 60)) {
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait a moment.']);
    exit;
}

$network = trim($_GET['network'] ?? '');
if (!in_array($network, ['binance', 'ethereum'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid network. Use binance or ethereum.']);
    exit;
}

$withdrawalId = (int)($_GET['withdrawal_id'] ?? 0);
if ($withdrawalId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
    exit;
}

$needsRpc = isset($_GET['needs_rpc']) && $_GET['needs_rpc'] === '1';

$tables = [
    'binance' => ['table' => 'binance_withdraw', 'cfg' => 'binance'],
    'ethereum' => ['table' => 'ethereum_withdraw', 'cfg' => 'ethereum'],
];
$def = $tables[$network];
$cfg = $config[$def['cfg']] ?? [];

$stmt = $pdo->prepare("SELECT id, address, amount, status FROM {$def['table']} WHERE id = ?");
$stmt->execute([$withdrawalId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
    exit;
}
if ((int)$row['status'] !== 0) {
    echo json_encode(['success' => false, 'message' => 'Withdrawal is not pending']);
    exit;
}

$tokenContract = $cfg['token_contract'] ?? '';
$chainId = (int)($cfg['chain_id'] ?? ($network === 'binance' ? 56 : 1));
$rpcUrl = $cfg['rpc_url'] ?? '';

if ($needsRpc && trim((string)$rpcUrl) === '') {
    echo json_encode([
        'success' => false,
        'message' => 'RPC URL not configured for ' . $network . '. Set the RPC URL in .env (required for private-key bulk complete).',
    ]);
    exit;
}

if (empty($tokenContract)) {
    echo json_encode(['success' => false, 'message' => 'Token contract not configured for ' . $network]);
    exit;
}

// Amount in wei (18 decimals) - use bcmath to avoid overflow for large amounts
$amountFloat = (float)$row['amount'];
$amountWei = function_exists('bcmul')
    ? bcmul((string)$amountFloat, '1000000000000000000', 0)
    : (string)(int)round($amountFloat * 1e18);

echo json_encode([
    'success' => true,
    'token_contract' => $tokenContract,
    'to_address' => $row['address'],
    'amount_wei' => $amountWei,
    'amount' => $amountFloat,
    'chain_id' => $chainId,
    'rpc_url' => $rpcUrl,
    'decimals' => 18,
]);
