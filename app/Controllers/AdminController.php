<?php

require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/AuditService.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/AdminHelper.php';

/**
 * Admin Dashboard Controller
 * Accessible only to admin users (UIDs from app_settings + bootstrap 1290033)
 */
class AdminController
{
    private PDO $pdo;
    private array $cfg;

    public function __construct(PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /**
     * Check if current user is admin
     */
    private function isAdmin(): bool
    {
        $uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
        return AdminHelper::isAdmin($uid, $this->pdo);
    }
    
    /**
     * Require admin access
     */
    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            Security::logSecurityEvent('admin_access_denied', [
                'ip' => Security::getClientIp(),
                'uid' => $_SESSION['uid'] ?? 0,
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    }
    
    /**
     * Main admin dashboard page
     */
    public function index(): void
    {
        $this->requireAdmin();
        require_once __DIR__ . '/../Support/PathHelper.php';
        $adminBase = rtrim(PathHelper::getBasePath(), '/');
        $dbName = $this->cfg['db']['name'] ?? 'unknown';
        $adminCsrfToken = Security::generateCsrfToken();
        $walletConnectProjectId = $this->cfg['walletconnect']['project_id'] ?? '';
        $walletWhitelistEnabled = $this->cfg['admin']['wallet_whitelist_enabled'] ?? false;
        $walletWhitelist = $this->cfg['admin']['wallet_whitelist'] ?? [];
        $allowSkipEvmOnchainVerify = $this->cfg['admin']['allow_skip_evm_onchain_verify'] ?? false;
        $blockedWithdrawalAddresses = [
            'stellar' => array_filter(array_map('trim', [
                $this->cfg['stellar']['issuer'] ?? '',
                $this->cfg['stellar']['vault'] ?? '',
                $this->cfg['stellar']['owner'] ?? '',
            ])),
            'evm' => array_filter(array_map('trim', [
                strtolower($this->cfg['binance']['vault_address'] ?? ''),
                strtolower($this->cfg['ethereum']['vault_address'] ?? ''),
                '0xaed72bac1da87a9ed09b1de1a54590ba1124c734',
            ])),
        ];
        $stellarConfig = $this->cfg['stellar'] ?? [];
        require __DIR__ . '/../../resources/views/admin/dashboard.php';
    }
    
    /**
     * Get referral commissions summary (total USDD paid, count)
     */
    private function getCommissionsStats(): array
    {
        try {
            $chk = $this->pdo->query("SHOW COLUMNS FROM stellar_withdraw LIKE 'referrer_uid'");
            if (!$chk || $chk->rowCount() === 0) {
                return ['total_usdd' => 0, 'total_count' => 0];
            }
            $commUsdd = (float)(
                ($this->pdo->query('SELECT COALESCE(SUM(referral_commission_usdd), 0) FROM stellar_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0) +
                ($this->pdo->query('SELECT COALESCE(SUM(referral_commission_usdd), 0) FROM binance_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0) +
                ($this->pdo->query('SELECT COALESCE(SUM(referral_commission_usdd), 0) FROM ethereum_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0)
            );
            $commCount = (int)(
                ($this->pdo->query('SELECT COUNT(*) FROM stellar_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0) +
                ($this->pdo->query('SELECT COUNT(*) FROM binance_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0) +
                ($this->pdo->query('SELECT COUNT(*) FROM ethereum_withdraw WHERE referrer_uid IS NOT NULL')->fetchColumn() ?: 0)
            );
            return ['total_usdd' => round($commUsdd, 2), 'total_count' => $commCount];
        } catch (Throwable $e) {
            return ['total_usdd' => 0, 'total_count' => 0];
        }
    }

    /**
     * Get system statistics
     */
    public function getStats(): void
    {
        $this->requireAdmin();
        
        try {
            // Total users - count unique UIDs from all transaction tables
            try {
                $stmt = $this->pdo->query('SELECT COUNT(DISTINCT uid) as total FROM users');
                $totalUsers = $stmt->fetchColumn() ?: 0;
            } catch (Exception $e) {
                // If users table doesn't exist, count from transactions
                $stmt = $this->pdo->query('
                    SELECT COUNT(DISTINCT uid) FROM (
                        SELECT uid FROM stellar_deposit
                        UNION
                        SELECT uid FROM stellar_withdraw
                        UNION
                        SELECT uid FROM binance_deposit
                        UNION
                        SELECT uid FROM binance_withdraw
                        UNION
                        SELECT uid FROM ethereum_deposit
                        UNION
                        SELECT uid FROM ethereum_withdraw
                    ) as all_users
                ');
                $totalUsers = $stmt->fetchColumn() ?: 0;
            }
            
            // Total deposits (all networks)
            $stellarDeposits = $this->pdo->query('SELECT COUNT(*) FROM stellar_deposit')->fetchColumn() ?: 0;
            $binanceDeposits = $this->pdo->query('SELECT COUNT(*) FROM binance_deposit')->fetchColumn() ?: 0;
            $ethereumDeposits = $this->pdo->query('SELECT COUNT(*) FROM ethereum_deposit')->fetchColumn() ?: 0;
            $totalDeposits = $stellarDeposits + $binanceDeposits + $ethereumDeposits;
            
            // Total withdrawals (all networks)
            $stellarWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM stellar_withdraw')->fetchColumn() ?: 0;
            $binanceWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM binance_withdraw')->fetchColumn() ?: 0;
            $ethereumWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM ethereum_withdraw')->fetchColumn() ?: 0;
            $totalWithdrawals = $stellarWithdrawals + $binanceWithdrawals + $ethereumWithdrawals;
            
            // Pending withdrawals
            $pendingStellar = $this->pdo->query('SELECT COUNT(*) FROM stellar_withdraw WHERE status = 0')->fetchColumn() ?: 0;
            $pendingBinance = $this->pdo->query('SELECT COUNT(*) FROM binance_withdraw WHERE status = 0')->fetchColumn() ?: 0;
            $pendingEthereum = $this->pdo->query('SELECT COUNT(*) FROM ethereum_withdraw WHERE status = 0')->fetchColumn() ?: 0;
            $pendingWithdrawals = $pendingStellar + $pendingBinance + $pendingEthereum;
            
            // Failed transactions (deposits + withdrawals with status = 2)
            $failedStellarDeposits = $this->pdo->query('SELECT COUNT(*) FROM stellar_deposit WHERE status = 2')->fetchColumn() ?: 0;
            $failedBinanceDeposits = $this->pdo->query('SELECT COUNT(*) FROM binance_deposit WHERE status = 2')->fetchColumn() ?: 0;
            $failedEthereumDeposits = $this->pdo->query('SELECT COUNT(*) FROM ethereum_deposit WHERE status = 2')->fetchColumn() ?: 0;
            $failedStellarWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM stellar_withdraw WHERE status = 2')->fetchColumn() ?: 0;
            $failedBinanceWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM binance_withdraw WHERE status = 2')->fetchColumn() ?: 0;
            $failedEthereumWithdrawals = $this->pdo->query('SELECT COUNT(*) FROM ethereum_withdraw WHERE status = 2')->fetchColumn() ?: 0;
            $totalFailed = $failedStellarDeposits + $failedBinanceDeposits + $failedEthereumDeposits + 
                           $failedStellarWithdrawals + $failedBinanceWithdrawals + $failedEthereumWithdrawals;
            
            // Total DBV volume
            $stellarDepositVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM stellar_deposit WHERE status IN (1, 3)')->fetchColumn() ?: 0;
            $binanceDepositVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM binance_deposit WHERE status IN (1, 3)')->fetchColumn() ?: 0;
            $ethereumDepositVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM ethereum_deposit WHERE status IN (1, 3)')->fetchColumn() ?: 0;
            $totalDepositVolume = $stellarDepositVolume + $binanceDepositVolume + $ethereumDepositVolume;
            
            $stellarWithdrawVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM stellar_withdraw WHERE status IN (1, 3, 8)')->fetchColumn() ?: 0;
            $binanceWithdrawVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM binance_withdraw WHERE status IN (1, 3, 8)')->fetchColumn() ?: 0;
            $ethereumWithdrawVolume = $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM ethereum_withdraw WHERE status IN (1, 3, 8)')->fetchColumn() ?: 0;
            $totalWithdrawVolume = $stellarWithdrawVolume + $binanceWithdrawVolume + $ethereumWithdrawVolume;
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'users' => [
                        'total' => (int)$totalUsers
                    ],
                    'deposits' => [
                        'total' => (int)$totalDeposits,
                        'stellar' => (int)$stellarDeposits,
                        'binance' => (int)$binanceDeposits,
                        'ethereum' => (int)$ethereumDeposits,
                        'volume' => (float)$totalDepositVolume
                    ],
                    'withdrawals' => [
                        'total' => (int)$totalWithdrawals,
                        'pending' => (int)$pendingWithdrawals,
                        'stellar' => (int)$stellarWithdrawals,
                        'binance' => (int)$binanceWithdrawals,
                        'ethereum' => (int)$ethereumWithdrawals,
                        'volume' => (float)$totalWithdrawVolume
                    ],
                    'fees' => [
                        // Only status=3 (completed): fee actually collected. Failed (2)=refunded, Pending (0,1,8)=not finalized.
                        // Exclude fee_usdd > 25 to filter corrupt data (actual fee is ~2 USDD)
                        'total' => (float)(
                            ($this->pdo->query('SELECT COALESCE(SUM(fee_usdd), 0) FROM stellar_withdraw WHERE status = 3 AND COALESCE(fee_usdd, 0) <= 25')->fetchColumn() ?: 0) +
                            ($this->pdo->query('SELECT COALESCE(SUM(fee_usdd), 0) FROM binance_withdraw WHERE status = 3 AND COALESCE(fee_usdd, 0) <= 25')->fetchColumn() ?: 0) +
                            ($this->pdo->query('SELECT COALESCE(SUM(fee_usdd), 0) FROM ethereum_withdraw WHERE status = 3 AND COALESCE(fee_usdd, 0) <= 25')->fetchColumn() ?: 0)
                        )
                    ],
                    'failed' => [
                        'total' => (int)$totalFailed,
                        'deposits' => (int)($failedStellarDeposits + $failedBinanceDeposits + $failedEthereumDeposits),
                        'withdrawals' => (int)($failedStellarWithdrawals + $failedBinanceWithdrawals + $failedEthereumWithdrawals)
                    ],
                    'commissions' => $this->getCommissionsStats()
                ]
            ]);
        } catch (Exception $e) {
            Logger::error('Admin stats error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch stats']);
        }
    }
    
    /**
     * Get recent transactions
     */
    public function getTransactions(): void
    {
        $this->requireAdmin();
        
        $limit = min((int)($_GET['limit'] ?? 200), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        $type = $_GET['type'] ?? 'all'; // all, deposit, withdrawal
        $network = $_GET['network'] ?? 'all'; // all, stellar, binance, ethereum
        $status = $_GET['status'] ?? 'all'; 
        $search = trim($_GET['search'] ?? '');
        
        try {
            $params = [];
            $queries = [];
            
            // Helper query builder (processedByExpr: NULL for deposits, processed_by_admin_uid for withdrawals)
            $buildQuery = function($net, $typ, $table, $hashCol, $feeExpr = '0', $feeHashExpr = 'NULL', $processedByExpr = 'NULL') use ($status, $search, &$params) {
                $direction = ($typ === 'withdrawal') ? 'out' : 'in';
                $addressCol = ($typ === 'withdrawal') ? 'address' : 'NULL';
                $sql = "SELECT 
                    id,
                    '$net' as network, 
                    '$typ' as type, 
                    uid, 
                    amount, 
                    ($feeExpr) as fee_usdd,
                    ($feeHashExpr) as fee_hash_yemchain,
                    status, 
                    $addressCol as address,
                    $hashCol as txn_hash_network,
                    txn_hash_yemchain,
                    created_at,
                    ($processedByExpr) as processed_by_admin_uid,
                    '$direction' as direction
                FROM $table WHERE 1=1";

                if ($status === 'nonfailed') {
                    $sql .= " AND status != 2";
                } elseif ($status !== 'all') {
                    $sql .= " AND status = ?";
                    $params[] = (int)$status;
                }

                if (!empty($search)) {
                    if (is_numeric($search)) {
                        // Exact match for UID or ID
                        $sql .= " AND (uid = ? OR id = ?)";
                        $params[] = $search;
                        $params[] = $search;
                    } else {
                        // Partial match for hashes or address
                        $sql .= " AND ($hashCol LIKE ? OR txn_hash_yemchain LIKE ?";
                        $params[] = "%$search%";
                        $params[] = "%$search%";
                        
                        if ($typ === 'withdrawal') {
                            $sql .= " OR address LIKE ?";
                            $params[] = "%$search%";
                        }
                        $sql .= ")";
                    }
                }
                
                return $sql;
            };

            // Deposits
            if ($type === 'all' || $type === 'deposit') {
                if ($network === 'all' || $network === 'stellar') {
                    $queries[] = $buildQuery('stellar', 'deposit', 'stellar_deposit', 'txn_hash_stellar');
                }
                if ($network === 'all' || $network === 'binance') {
                    $queries[] = $buildQuery('binance', 'deposit', 'binance_deposit', 'txn_hash_bsc');
                }
                if ($network === 'all' || $network === 'ethereum') {
                    $queries[] = $buildQuery('ethereum', 'deposit', 'ethereum_deposit', 'txn_hash_eth');
                }
            }
            
            // Withdrawals (include fee_usdd, fee_hash_yemchain, processed_by_admin_uid)
            if ($type === 'all' || $type === 'withdrawal') {
                if ($network === 'all' || $network === 'stellar') {
                    $queries[] = $buildQuery('stellar', 'withdrawal', 'stellar_withdraw', 'txn_hash_stellar', 'COALESCE(fee_usdd, 0)', 'fee_hash_yemchain', 'processed_by_admin_uid');
                }
                if ($network === 'all' || $network === 'binance') {
                    $queries[] = $buildQuery('binance', 'withdrawal', 'binance_withdraw', 'txn_hash_bsc', 'COALESCE(fee_usdd, 0)', 'fee_hash_yemchain', 'processed_by_admin_uid');
                }
                if ($network === 'all' || $network === 'ethereum') {
                    $queries[] = $buildQuery('ethereum', 'withdrawal', 'ethereum_withdraw', 'txn_hash_eth', 'COALESCE(fee_usdd, 0)', 'fee_hash_yemchain', 'processed_by_admin_uid');
                }
            }
            
            if (empty($queries)) {
                echo json_encode(['success' => true, 'transactions' => [], 'count' => 0]);
                return;
            }
            
            // Combine queries
            $sql = implode(' UNION ALL ', $queries);
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rawTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format transactions (matching public API style)
            $transactions = array_map(function($txn) {
                $processedBy = isset($txn['processed_by_admin_uid']) && (int)$txn['processed_by_admin_uid'] > 0
                    ? (int)$txn['processed_by_admin_uid'] : null;
                return [
                    'id' => (int)$txn['id'],
                    'type' => $txn['type'],
                    'network' => $txn['network'],
                    'uid' => $txn['uid'],
                    'amount' => (float)$txn['amount'],
                    'fee_usdd' => (float)($txn['fee_usdd'] ?? 0),
                    'fee_hash_yemchain' => $txn['fee_hash_yemchain'] ?? null,
                    'formatted_amount' => number_format((float)$txn['amount'], 2),
                    'status' => (int)$txn['status'],
                    'direction' => $txn['direction'],
                    // Who manually processed this withdrawal (if applicable)
                    'processed_by_admin_uid' => $processedBy,
                    // Legacy key for dashboard compatibility
                    'txn_hash_network' => $txn['txn_hash_network'],
                    'txn_hash_stellar' => $txn['txn_hash_network'],
                    'txn_hash_yemchain' => $txn['txn_hash_yemchain'],
                    'created_at' => $txn['created_at'],
                    'formatted_time' => date('M j, H:i', strtotime($txn['created_at'])),
                    'address' => $txn['address']
                ];
            }, $rawTransactions);
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ]);

        } catch (Exception $e) {
            Logger::error('Admin transactions error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch transactions']);
        }
    }

    /**
     * Get pending withdrawals (status=0) from all networks - matches All Transactions pending list
     */
    public function getManualWithdrawals(): void
    {
        $this->requireAdmin();

        $limit = min((int)($_GET['limit'] ?? 500), 500);
        $network = $_GET['network'] ?? 'all';

        try {
            $manualFilter = ' AND status = 0';
            $queries = [];

            if ($network === 'all' || $network === 'stellar') {
                $queries[] = "SELECT id, 'stellar' as network, uid, address, amount, fee_usdd, txn_hash_stellar as txn_hash_network, txn_hash_yemchain, created_at FROM stellar_withdraw WHERE 1=1" . $manualFilter;
            }
            if ($network === 'all' || $network === 'binance') {
                $queries[] = "SELECT id, 'binance' as network, uid, address, amount, fee_usdd, txn_hash_bsc as txn_hash_network, txn_hash_yemchain, created_at FROM binance_withdraw WHERE 1=1" . $manualFilter;
            }
            if ($network === 'all' || $network === 'ethereum') {
                $queries[] = "SELECT id, 'ethereum' as network, uid, address, amount, fee_usdd, txn_hash_eth as txn_hash_network, txn_hash_yemchain, created_at FROM ethereum_withdraw WHERE 1=1" . $manualFilter;
            }

            if (empty($queries)) {
                echo json_encode(['success' => true, 'withdrawals' => [], 'count' => 0]);
                return;
            }

            $sql = implode(' UNION ALL ', $queries) . ' ORDER BY created_at ASC LIMIT ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $withdrawals = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'network' => $row['network'],
                    'uid' => (int)$row['uid'],
                    'address' => $row['address'],
                    'amount' => (float)$row['amount'],
                    'fee_usdd' => (float)($row['fee_usdd'] ?? 0),
                    'txn_hash_network' => $row['txn_hash_network'],
                    'txn_hash_yemchain' => $row['txn_hash_yemchain'],
                    'created_at' => $row['created_at'],
                    'formatted_time' => date('M j, H:i', strtotime($row['created_at'])),
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'withdrawals' => $withdrawals,
                'count' => count($withdrawals),
            ]);
        } catch (Exception $e) {
            Logger::error('Admin manual withdrawals error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch manual withdrawals']);
        }
    }

    /**
     * Get all referral commissions paid across Stellar, BSC, Ethereum
     * Shows referee UID (who received commission) and referred UID (whose withdrawal triggered it)
     */
    public function getCommissions(): void
    {
        $this->requireAdmin();

        $limit = min((int)($_GET['limit'] ?? 200), 500);
        $network = $_GET['network'] ?? 'all';
        $referrerUid = !empty($_GET['referrer_uid']) ? (int)$_GET['referrer_uid'] : null;
        $referredUid = !empty($_GET['referred_uid']) ? (int)$_GET['referred_uid'] : null;

        try {
            $hasReferralColumns = false;
            try {
                $chk = $this->pdo->query("SHOW COLUMNS FROM stellar_withdraw LIKE 'referrer_uid'");
                $hasReferralColumns = $chk && $chk->rowCount() > 0;
            } catch (Throwable $e) {
                /* ignore */
            }

            if (!$hasReferralColumns) {
                echo json_encode([
                    'success' => true,
                    'commissions' => [],
                    'summary' => ['total_usdd' => 0, 'total_count' => 0],
                    'message' => 'Referral columns not found. Run add_referral_columns migration.'
                ]);
                return;
            }

            $where = 'referrer_uid IS NOT NULL AND COALESCE(referral_commission_usdd, 0) > 0';
            $extraParams = [];
            if ($referrerUid !== null) {
                $where .= ' AND referrer_uid = ?';
                $extraParams[] = $referrerUid;
            }
            if ($referredUid !== null) {
                $where .= ' AND uid = ?';
                $extraParams[] = $referredUid;
            }

            $queries = [];
            if ($network === 'all' || $network === 'stellar') {
                $queries[] = "SELECT 'stellar' as network, id as withdrawal_id, uid as referred_uid, referrer_uid, referral_commission_usdd, referral_commission_hash, created_at FROM stellar_withdraw WHERE $where";
            }
            if ($network === 'all' || $network === 'binance') {
                $queries[] = "SELECT 'binance' as network, id as withdrawal_id, uid as referred_uid, referrer_uid, referral_commission_usdd, referral_commission_hash, created_at FROM binance_withdraw WHERE $where";
            }
            if ($network === 'all' || $network === 'ethereum') {
                $queries[] = "SELECT 'ethereum' as network, id as withdrawal_id, uid as referred_uid, referrer_uid, referral_commission_usdd, referral_commission_hash, created_at FROM ethereum_withdraw WHERE $where";
            }

            if (empty($queries)) {
                echo json_encode(['success' => true, 'commissions' => [], 'summary' => ['total_usdd' => 0, 'total_count' => 0]]);
                return;
            }

            $sql = implode(' UNION ALL ', $queries) . ' ORDER BY created_at DESC LIMIT ?';
            $params = [];
            foreach ($queries as $_) {
                $params = array_merge($params, $extraParams);
            }
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalUsdd = 0;
            foreach ($rows as $r) {
                $totalUsdd += (float)($r['referral_commission_usdd'] ?? 0);
            }

            $commissions = array_map(function ($r) {
                return [
                    'network' => $r['network'],
                    'withdrawal_id' => (int)$r['withdrawal_id'],
                    'referred_uid' => (int)$r['referred_uid'],
                    'referrer_uid' => (int)$r['referrer_uid'],
                    'amount_usdd' => (float)($r['referral_commission_usdd'] ?? 0),
                    'hash' => $r['referral_commission_hash'] ?? null,
                    'created_at' => $r['created_at'],
                    'formatted_time' => date('M j, H:i', strtotime($r['created_at'])),
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'commissions' => $commissions,
                'summary' => [
                    'total_usdd' => round($totalUsdd, 2),
                    'total_count' => count($commissions),
                ],
            ]);
        } catch (Exception $e) {
            Logger::error('Admin commissions error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch commissions']);
        }
    }

    /**
     * Get failed withdrawals grouped by network
     */
    public function getFailedByNetwork(): void
    {
        $this->requireAdmin();

        $limit = min((int)($_GET['limit'] ?? 500), 1000);

        try {
            $networks = [
                'stellar' => ['table' => 'stellar_withdraw', 'hash_col' => 'txn_hash_stellar'],
                'binance' => ['table' => 'binance_withdraw', 'hash_col' => 'txn_hash_bsc'],
                'ethereum' => ['table' => 'ethereum_withdraw', 'hash_col' => 'txn_hash_eth'],
            ];

            $result = [];
            $allFlat = [];

            $hasErrorColumn = false;
            try {
                $chk = $this->pdo->query("SHOW COLUMNS FROM stellar_withdraw LIKE 'error_message'");
                $hasErrorColumn = $chk && $chk->rowCount() > 0;
            } catch (Throwable $e) {
                /* ignore */
            }
            foreach ($networks as $network => $def) {
                $selectList = "id, uid, address, amount, fee_usdd, fee_hash_yemchain, {$def['hash_col']} as txn_hash_network, txn_hash_yemchain, status, created_at";
                if ($hasErrorColumn) {
                    $selectList .= ', error_message';
                }
                $stmt = $this->pdo->prepare("
                    SELECT {$selectList}
                    FROM {$def['table']}
                    WHERE status = 2
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $mapped = array_map(function ($row) use ($network) {
                    return [
                        'id' => (int)$row['id'],
                        'type' => 'withdrawal',
                        'network' => $network,
                        'uid' => (int)$row['uid'],
                        'amount' => (float)$row['amount'],
                        'fee_usdd' => (float)($row['fee_usdd'] ?? 0),
                        'fee_hash_yemchain' => $row['fee_hash_yemchain'] ?? null,
                        'formatted_amount' => number_format((float)$row['amount'], 2),
                        'status' => 2,
                        'direction' => 'out',
                        'txn_hash_stellar' => $row['txn_hash_network'],
                        'txn_hash_network' => $row['txn_hash_network'],
                        'txn_hash_yemchain' => $row['txn_hash_yemchain'],
                        'created_at' => $row['created_at'],
                        'formatted_time' => date('M j, H:i', strtotime($row['created_at'])),
                        'address' => $row['address'],
                        'error_message' => $row['error_message'] ?? null,
                    ];
                }, $rows);

                $result[$network] = $mapped;
                foreach ($mapped as $m) {
                    $allFlat[] = $m;
                }
            }

            usort($allFlat, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

            echo json_encode([
                'success' => true,
                'by_network' => $result,
                'list' => $allFlat,
                'totals' => [
                    'stellar' => count($result['stellar']),
                    'binance' => count($result['binance']),
                    'ethereum' => count($result['ethereum']),
                    'all' => count($allFlat),
                ],
            ]);
        } catch (Exception $e) {
            Logger::error('Admin failed_by_network error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch failed transactions']);
        }
    }

    /**
     * Get audit log entries
     */
    public function getAuditLog(): void
    {
        $this->requireAdmin();

        $limit = min((int)($_GET['limit'] ?? 200), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        $action = $_GET['filter_action'] ?? null;
        $adminUid = !empty($_GET['admin_uid']) ? (int)$_GET['admin_uid'] : null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        try {
            $audit = new AuditService($this->pdo);
            if (!$audit->tableExists()) {
                echo json_encode(['success' => true, 'entries' => [], 'message' => 'Audit table not created. Run setup_audit_log.php']);
                return;
            }
            $entries = $audit->getEntries($limit, $offset, $action ?: null, $adminUid ?: null, $dateFrom ?: null, $dateTo ?: null);
            foreach ($entries as &$e) {
                $e['details'] = isset($e['details']) ? json_decode($e['details'], true) : null;
            }
            echo json_encode(['success' => true, 'entries' => $entries]);
        } catch (Exception $e) {
            Logger::error('Admin audit error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch audit log']);
        }
    }
    
    /**
     * Get recent logs
     */
    public function getLogs(): void
    {
        $this->requireAdmin();
        
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $level = $_GET['level'] ?? 'all'; // all, INFO, WARNING, ERROR, DEBUG
        $date = $_GET['date'] ?? date('Y-m-d');
        
        try {
            $logFile = __DIR__ . '/../../logs/app-' . $date . '.log';
            
            if (!file_exists($logFile)) {
                echo json_encode([
                    'success' => true,
                    'logs' => [],
                    'message' => 'No logs for this date'
                ]);
                return;
            }
            
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs = [];
            
            foreach (array_reverse($lines) as $line) {
                if (count($logs) >= $limit) break;
                
                $log = json_decode($line, true);
                if (!$log) continue;
                
                // Filter by level
                if ($level !== 'all' && ($log['level'] ?? '') !== $level) {
                    continue;
                }
                
                $logs[] = $log;
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'date' => $date
            ]);
        } catch (Exception $e) {
            Logger::error('Admin logs error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch logs']);
        }
    }

    /**
     * Get worker / network logs (PM2 stdout/stderr from Binance, Ethereum, Stellar workers)
     * Useful for debugging RPC failures, connection timeouts, rate limits, etc.
     */
    public function getWorkerLogs(): void
    {
        $this->requireAdmin();

        $worker = $_GET['worker'] ?? 'all'; // stellar, binance, ethereum, all
        $filter = $_GET['filter'] ?? 'all';  // all, network - "network" shows only RPC/connection/failure lines
        $limit = min((int)($_GET['limit'] ?? 200), 500);
        $source = $_GET['source'] ?? 'both'; // error, out, both

        $logDir = realpath(__DIR__ . '/../../logs');
        if (!$logDir || !is_dir($logDir)) {
            echo json_encode([
                'success' => true,
                'logs' => [],
                'message' => 'Logs directory not found'
            ]);
            return;
        }

        $workerFiles = [
            'stellar' => ['pm2-error.log' => 'error', 'pm2-out.log' => 'out'],
            'binance' => ['pm2-binance-error.log' => 'error', 'pm2-binance-out.log' => 'out'],
            'ethereum' => ['pm2-ethereum-error.log' => 'error', 'pm2-ethereum-out.log' => 'out'],
        ];

        $networkKeywords = [
            'rpc', 'failed', 'error', 'timeout', 'etimedout', 'econnrefused', 'rate limit',
            'connection', 'network_error', 'all rpc endpoints', 'insufficient balance',
            'connection timeout', 'payment failed', 'exception', '❌', '⚠️', 'retry'
        ];

        $allLines = [];
        $workersToRead = $worker === 'all' ? array_keys($workerFiles) : [$worker];

        foreach ($workersToRead as $w) {
            $files = $workerFiles[$w] ?? [];
            foreach ($files as $file => $type) {
                if ($source !== 'both' && $source !== $type) continue;
                $path = $logDir . '/' . $file;
                if (!file_exists($path)) continue;
                $content = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($content === false) continue;
                $chunk = array_slice($content, -$limit);
                foreach ($chunk as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $lineLower = strtolower($line);
                    if ($filter === 'network') {
                        $matches = false;
                        foreach ($networkKeywords as $kw) {
                            if (strpos($lineLower, $kw) !== false) {
                                $matches = true;
                                break;
                            }
                        }
                        if (!$matches) continue;
                    }
                    $allLines[] = [
                        'worker' => $w,
                        'source' => $type,
                        'line' => $line,
                    ];
                }
            }
        }

        $allLines = array_slice($allLines, -$limit);
        $allLines = array_reverse($allLines); // newest first

        echo json_encode([
            'success' => true,
            'logs' => $allLines,
            'count' => count($allLines),
            'worker' => $worker,
            'filter' => $filter,
        ]);
    }

    /**
     * Clear all sessions
     */
    public function clearAllSessions(): void
    {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            Security::logSecurityEvent('admin_csrf_failed', ['action' => 'clear_sessions', 'uid' => $_SESSION['uid'] ?? 0]);
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request. Please refresh the page.']);
            exit;
        }
        
        try {
            $sessionPath = session_save_path();
            if (empty($sessionPath)) {
                $sessionPath = sys_get_temp_dir();
            }

            if (!is_dir($sessionPath)) {
                throw new Exception("Session directory not found: $sessionPath");
            }

            $files = glob($sessionPath . DIRECTORY_SEPARATOR . 'sess_*');
            $count = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // Optional: Skip current session if we want to keep admin logged in
                    // if (strpos($file, session_id()) !== false) continue;
                    
                    @unlink($file);
                    $count++;
                }
            }

            Logger::info('Admin cleared all sessions', [
                'admin_uid' => $_SESSION['uid'] ?? 0,
                'count' => $count,
                'path' => $sessionPath
            ]);

            (new AuditService($this->pdo))->log((int)($_SESSION['uid'] ?? 0), 'clear_sessions', null, null, [
                'count' => $count,
                'path' => $sessionPath
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully cleared $count sessions",
                'count' => $count
            ]);
        } catch (Exception $e) {
            Logger::error('Clear sessions error', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => 'Failed to clear sessions. Please try again later.']);
        }
    }
    
    /**
     * Get user details
     */
    public function getUser(): void
    {
        $this->requireAdmin();
        
        $uid = (int)($_GET['uid'] ?? 0);
        
        if ($uid <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid UID']);
            return;
        }
        
        try {
            // Try users table first (may not exist in dbv_now)
            $user = null;
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM users WHERE uid = ?');
                $stmt->execute([$uid]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // users table doesn't exist - build user from transaction data
            }
            
            if (!$user) {
                // Build minimal user info from transactions
                $user = ['uid' => $uid, 'pernum' => (string)(1000000000 + $uid)];
            }
            
            // Get transaction counts across all networks
            $tables = [
                'stellar_deposit' => 'stellar_withdraw',
                'binance_deposit' => 'binance_withdraw',
                'ethereum_deposit' => 'ethereum_withdraw'
            ];
            $deposits = 0;
            $withdrawals = 0;
            foreach ($tables as $depTable => $withTable) {
                try {
                    $s = $this->pdo->prepare("SELECT COUNT(*) FROM $depTable WHERE uid = ?");
                    $s->execute([$uid]);
                    $deposits += (int)$s->fetchColumn();
                    $s = $this->pdo->prepare("SELECT COUNT(*) FROM $withTable WHERE uid = ?");
                    $s->execute([$uid]);
                    $withdrawals += (int)$s->fetchColumn();
                } catch (Exception $e) {
                    // Table may not exist
                }
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'stats' => [
                    'deposits' => $deposits,
                    'withdrawals' => $withdrawals
                ]
            ]);
        } catch (Exception $e) {
            Logger::error('Admin get user error', ['error' => $e->getMessage(), 'uid' => $uid]);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch user']);
        }
    }
}
