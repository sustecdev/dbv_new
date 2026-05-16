<?php

require_once __DIR__ . '/../Support/Database.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/Cache.php';
require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/AdminHelper.php';
require_once __DIR__ . '/../Support/PathHelper.php';
require_once __DIR__ . '/../Services/YEMChainService.php';

class DashboardController
{
    private PDO $pdo;
    private YEMChainService $yem;
    private array $cfg;

    public function __construct(array $cfg)
    {
        try {
            Security::configureSecureSession();
            
            $this->cfg = $cfg;
            
            // Validate database config
            if (!isset($cfg['db']) || !is_array($cfg['db'])) {
                throw new Exception('Database configuration is missing or invalid');
            }
            
            $this->pdo = Database::pdo($cfg['db']);
            
            // Validate YEMChain config
            if (!isset($cfg['yemchain']['base']) || !isset($cfg['yemchain']['key'])) {
                throw new Exception('YEMChain configuration is missing');
            }
            
            $this->yem = new YEMChainService($cfg['yemchain']['base'], $cfg['yemchain']['key']);
        } catch (Exception $e) {
            // Use error_log directly to avoid Logger dependency issues
            error_log('DashboardController constructor error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Try to log with Logger if available
            if (class_exists('Logger')) {
                try {
                    Logger::error('DashboardController constructor error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                } catch (Exception $logError) {
                    // Ignore logger errors
                }
            }
            throw $e;
        }
    }

    public function show(): void
    {
        try {
            if (!isset($_SESSION['uid'])) {
                header('Location: ' . PathHelper::url('safezone.php'));
                exit;
            }
            
            $uid = (int)$_SESSION['uid'];
            
            // Cache balances for 30 seconds to reduce API calls
            // Wrap in try-catch to handle API errors gracefully
            try {
                $dbv = Cache::remember("balance_dbv_{$uid}", function() use ($uid) {
                    return $this->yem->getBalance($uid, 'DBV');
                }, 30);
            } catch (Exception $e) {
                Logger::error('Failed to get DBV balance', ['uid' => $uid, 'error' => $e->getMessage()]);
                $dbv = 0.0;
            }
            
            try {
                $usdd = Cache::remember("balance_usdd_{$uid}", function() use ($uid) {
                    return $this->yem->getBalance($uid, 'USDD');
                }, 30);
            } catch (Exception $e) {
                Logger::error('Failed to get USDD balance', ['uid' => $uid, 'error' => $e->getMessage()]);
                $usdd = 0.0;
            }

            // Use indexes: uid + created_at DESC
            $stmt = $this->pdo->prepare('
                SELECT created_at, amount, txn_hash_stellar, txn_hash_yemchain, status 
                FROM stellar_deposit 
                WHERE uid = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ');
            $stmt->execute([$uid]);
            $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $this->pdo->prepare('
                SELECT created_at, amount, address, txn_hash_stellar, txn_hash_yemchain, status 
                FROM stellar_withdraw 
                WHERE uid = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ');
            $stmt->execute([$uid]);
            $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $explorer = $this->cfg['stellar']['explorer'];
            $vaultAddress = $this->cfg['stellar']['vault'];
            $ownerAddress = $this->cfg['stellar']['owner'];
            $feeEnabled = $this->cfg['withdrawal']['fee_enabled'] ?? true;
            $withdrawalFee = $this->cfg['withdrawal']['fee_usdd'] ?? 2.0;
            $safezonePinCheck = $this->cfg['safezone']['pin_check'];
            
            // Network configuration for frontend - dynamic API base (public as doc root vs /public/ prefix)
            $apiBase = PathHelper::getApiBasePath();
            
            $networkConfig = [
                'stellar' => [
                    'name' => 'Stellar',
                    'vault_address' => $this->cfg['stellar']['owner'], // Use ACCOUNT_OWNER for deposits
                    'explorer' => $this->cfg['stellar']['explorer'],
                    'deposit_api' => $apiBase . '/stellar/deposit.php',
                    'withdraw_api' => $apiBase . '/stellar/withdraw.php',
                    'address_pattern' => '/^G[A-Z0-9]{55}$/',
                    'hash_pattern' => '/^[a-zA-Z0-9]{64}$/',
                    'hash_placeholder' => '64-character transaction hash',
                    'withdrawal_fee_enabled' => $feeEnabled,
                    'withdrawal_fee_usdd' => $withdrawalFee,
                ],
                'binance' => [
                    'name' => 'Binance Smart Chain',
                    'vault_address' => $this->cfg['binance']['vault_address'] ?? '',
                    'explorer' => $this->cfg['binance']['explorer'],
                    'deposit_api' => $apiBase . '/binance/deposit.php',
                    'withdraw_api' => $apiBase . '/binance/withdraw.php',
                    'address_pattern' => '/^0x[a-fA-F0-9]{40}$/i',
                    'hash_pattern' => '/^0x[a-fA-F0-9]{64}$/i',
                    'hash_placeholder' => '0x... (66-character hex string)',
                    'withdrawal_fee_enabled' => $this->cfg['binance']['withdrawal_fee_enabled'] ?? $feeEnabled,
                    'withdrawal_fee_usdd' => $this->cfg['binance']['withdrawal_fee_usdd'] ?? $withdrawalFee,
                ],
                'ethereum' => [
                    'name' => 'Ethereum',
                    'vault_address' => $this->cfg['ethereum']['vault_address'] ?? '',
                    'explorer' => $this->cfg['ethereum']['explorer'],
                    'deposit_api' => $apiBase . '/ethereum/deposit.php',
                    'withdraw_api' => $apiBase . '/ethereum/withdraw.php',
                    'address_pattern' => '/^0x[a-fA-F0-9]{40}$/i',
                    'hash_pattern' => '/^0x[a-fA-F0-9]{64}$/i',
                    'hash_placeholder' => '0x... (66-character hex string)',
                    'withdrawal_fee_enabled' => $this->cfg['ethereum']['withdrawal_fee_enabled'] ?? $feeEnabled,
                    'withdrawal_fee_usdd' => $this->cfg['ethereum']['withdrawal_fee_usdd'] ?? $withdrawalFee,
                ],
            ];
            
            // Generate CSRF tokens for the view
            $csrfToken = Security::generateCsrfToken();

            $isAdmin = AdminHelper::isAdmin($uid, $this->pdo);
            $showDebug = ($this->cfg['app']['debug'] ?? false) && isset($_GET['debug']);

            include __DIR__ . '/../../resources/views/dashboard.php';
        } catch (Exception $e) {
            Logger::error('Dashboard error', [
                'uid' => $uid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Show error page or redirect
            http_response_code(500);
            die('An error occurred. Please try again later.');
        }
    }
}
