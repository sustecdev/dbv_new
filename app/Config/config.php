<?php

// Load .env from project root (single location)
// Primary: config lives in app/Config, so 2 levels up = project root
$rootDir = dirname(__DIR__, 2);
$ds = DIRECTORY_SEPARATOR;
$envPath = $rootDir . $ds . '.env';
$envExamplePath = $rootDir . $ds . 'env.example';

// Fallback: walk up from entry script until we find .env (handles nested public/api/*.php)
if (!file_exists($envPath) && !file_exists($envExamplePath)) {
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($scriptFile !== '' && file_exists($scriptFile)) {
        $dir = dirname(realpath($scriptFile));
        while ($dir !== '' && $dir !== dirname($dir)) {
            $tryEnv = $dir . $ds . '.env';
            $tryEx = $dir . $ds . 'env.example';
            if (file_exists($tryEnv) || file_exists($tryEx)) {
                $rootDir = $dir;
                $envPath = $tryEnv;
                $envExamplePath = $tryEx;
                break;
            }
            $dir = dirname($dir);
        }
    }
}

// Expose project root for other code (e.g. CA bundle, logs)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $rootDir);
}

$env = [];
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[$parts[0]] = trim($parts[1]);
        }
    }
} else if (file_exists($envExamplePath) && is_readable($envExamplePath)) {
    // fall back to env.example for dev convenience
    $lines = file($envExamplePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[$parts[0]] = trim($parts[1]);
        }
    }
}

if (!function_exists('envv')) {
    function envv(string $key, $default = null) {
        global $env;
        return $env[$key] ?? $default;
    }
}

// Parse blocked withdrawal addresses (fail-safe: empty array on any error)
$blockedWithdrawalAddresses = [];
try {
    $raw = envv('BLOCKED_WITHDRAWAL_ADDRESSES', '');
    if (is_string($raw) && $raw !== '') {
        foreach (explode(',', $raw) as $a) {
            $a = trim($a);
            if ($a === '') continue;
            if (preg_match('/^0x[a-fA-F0-9]{40}$/', $a)) {
                $blockedWithdrawalAddresses[] = strtolower($a);
            } elseif (preg_match('/^[A-Za-z0-9]{56}$/', $a)) {
                $blockedWithdrawalAddresses[] = strtoupper($a);
            } else {
                $blockedWithdrawalAddresses[] = $a;
            }
        }
    }
} catch (Throwable $e) {
    $blockedWithdrawalAddresses = [];
}

$config = [
    'app' => [
        'debug' => envv('APP_DEBUG', 'false') === 'true',
        'name' => envv('APP_NAME', 'DBV Bridge'),
        'allow_dev_set_uid' => envv('ALLOW_DEV_SET_UID', 'false') === 'true', // Only enable for local dev
    ],
    'db' => [
        'host' => envv('DB_HOST', 'localhost'),
        'name' => envv('DB_NAME', 'digital'),
        'user' => envv('DB_USER', 'root'),
        'pass' => envv('DB_PASS', ''),
        'charset' => envv('DB_CHARSET', 'utf8mb4'),
    ],
    // Optional: full path to mysqldump (e.g. C:\xampp\mysql\bin\mysqldump.exe) if not in PATH
    'mysqldump_path' => envv('MYSQLDUMP_PATH', null) ?: null,
    'yemchain' => [
        'base' => rtrim(envv('YEMCHAIN_API_BASE', 'http://91.98.180.218/api'), '/'),
        'key' => envv('YEMCHAIN_API_KEY', ''),
        'vault_account_id' => envv('VAULT_ACCOUNT_ID', ''),
    ],
    'stellar' => [
        'asset_code' => envv('ASSET_CODE', 'XDEF'),
        'network' => (function () {
            $n = strtolower(trim(envv('HORIZON_NETWORK', 'testnet')));
            return ($n === 'mainnet' || $n === 'public') ? 'public' : 'testnet';
        })(),
        'issuer' => envv('ASSET_ISSUER', ''),
        'owner' => envv('ACCOUNT_OWNER', ''),
        'vault' => envv('ACCOUNT_VAULT', ''),
        'explorer' => envv('EXPLORER_URL', 'https://testnet.lumenscan.io'),
        'curl_cainfo' => (function () use ($rootDir, $ds) {
            $p = trim(envv('CURL_CA_BUNDLE', ''));
            if ($p !== '') {
                $resolved = realpath($p) ?: (file_exists($p) ? $p : null);
                if ($resolved) return $resolved;
            }
            $fallback = $rootDir . $ds . 'dbv-admin-panel' . $ds . 'libraries' . $ds . 'certs' . $ds . 'cacert.pem';
            if (file_exists($fallback)) return realpath($fallback) ?: $fallback;
            $iniCainfo = ini_get('curl.cainfo');
            if ($iniCainfo && file_exists($iniCainfo)) return $iniCainfo;
            return null;
        })(),
        'curl_ssl_verify' => envv('CURL_SSL_VERIFY', 'true') !== 'false',
    ],
    'safezone' => [
        'pin_check' => envv('SAFEZONE_PIN_CHECK', 'https://safe.zone/signup/check_pin_api.php'),
        'tax_country_api_key' => envv('SAFEZONE_TAX_COUNTRY_API_KEY', ''), // For auth.php tax country lookup
        'getrefby_url' => envv('SAFEZONE_GETREFBY_URL', 'https://safe.zone/api/getrefby.php'),
        'getrefby_api_key' => envv('SAFEZONE_GETREFBY_API_KEY', ''), // API key for getrefby.php - referrer lookup
        'getrefby_curl_cainfo' => ($p = trim(envv('CURL_CA_BUNDLE', ''))) !== '' ? $p : null, // Path to cacert.pem if SSL fails (e.g. from curl.se/ca/cacert.pem)
        'api_key' => envv('SAFEZONE_API_KEY', ''), // Step 1 login API key - REQUIRED, set in .env
        'api_key_step2' => envv('SAFEZONE_API_KEY_STEP2', '') ?: envv('SAFEZONE_API_KEY', ''), // Step 2 PIN API key
        'login_api' => envv('SAFEZONE_LOGIN_API', 'https://safe.zone/signup/login_api.php'),
        'pin_api_v2' => envv('SAFEZONE_PIN_API_V2', 'https://safe.zone/signup/check_pin_api_v2.php'),
        // Pernum whitelist - restrict login to specific pernums
        'pernum_whitelist_enabled' => envv('SAFEZONE_PERNUM_WHITELIST_ENABLED', 'false') === 'true',
        'allowed_pernums' => array_filter(array_map('trim', explode(',', envv('SAFEZONE_ALLOWED_PERNUMS', '')))),
        // UID whitelist - restrict login to specific UIDs (checked after SafeZone authentication)
        // HOW TO USE: Set 'uid_whitelist_enabled' to true to BLOCK everyone except the UIDs listed in 'allowed_uids'.
        'uid_whitelist_enabled' => envv('SAFEZONE_UID_WHITELIST_ENABLED', 'false') === 'true',
        
        // Allowed UIDs when uid_whitelist_enabled - from .env, always include bootstrap admin
        'allowed_uids' => array_unique(array_merge(
            [(int)(envv('BOOTSTRAP_ADMIN_UID', '1290033'))],
            array_map('intval', array_filter(array_map('trim', explode(',', envv('SAFEZONE_ALLOWED_UIDS', '')))))
        )),
    ],
    
    // SafeIdent Verification Configuration
    'safeident' => [
        'api_url' => envv('SAFEIDENT_API_URL', 'https://safeident.com/verify_status_api.php'),
        'api_key' => envv('SAFEIDENT_API_KEY', ''), // REQUIRED, set in .env
        'cache_duration' => (int)envv('SAFEIDENT_CACHE_DURATION', 300), // 5 minutes
        'verification_required' => filter_var(envv('SAFEIDENT_VERIFICATION_REQUIRED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'whitelist_enabled' => filter_var(envv('SAFEIDENT_WHITELIST_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'whitelisted_uids' => array_unique(array_merge(
            // No default whitelisted UIDs - all users must verify through SafeIdent
            [],
            // Additional UIDs from environment variable (if needed)
            array_map('intval', array_filter(array_map('trim', explode(',', envv('SAFEIDENT_WHITELISTED_UIDS', '')))))
        ))
    ],
    
    'worker' => [
        'secret' => envv('WORKER_SECRET', ''), // Change in production!
        'trigger_base_url' => rtrim(envv('TRIGGER_BASE_URL', 'http://127.0.0.1'), '/'), // SECURITY: Fixed base to prevent HTTP_HOST SSRF
    ],
    'withdrawal' => [
        'fee_enabled' => envv('WITHDRAWAL_FEE_ENABLED', 'true') === 'true', // Enable/disable withdrawal fee (global)
        'fee_usdd' => (float)envv('WITHDRAWAL_FEE_USDD', '2.50'), // Default withdrawal fee in USDD (for Stellar)
        'referral_commission_usdd' => (float)envv('REFERRAL_COMMISSION_USDD', '0.50'), // Commission to referrer from fee when referred user withdraws
        'daily_limit_stellar' => (float)envv('STELLAR_DAILY_WITHDRAWAL_LIMIT', '0'), // Daily withdrawal limit for Stellar (0 = unlimited)
        // UIDs exempt from daily per-network withdrawal limits (Stellar/BSC/Ethereum). Comma-separated, e.g. "1001,1290033"
        'daily_limit_bypass_uids' => array_values(array_filter(array_map('intval', array_map('trim', explode(',', envv('WITHDRAWAL_DAILY_LIMIT_BYPASS_UIDS', ''))))), function ($id) {
            return $id > 0;
        }),
        'per_user_cap' => (float)envv('WITHDRAWAL_PER_USER_CAP', '0'), // Maximum total withdrawals per user across all networks (0 = unlimited)
        'manual_withdraw_enabled' => envv('MANUAL_WITHDRAW_ENABLED', 'false') === 'true', // Overridden by app_settings if table exists; when true, withdrawals stay pending for admin to process
        'blocked_addresses' => $blockedWithdrawalAddresses,
    ],
    'binance' => [
        'network' => envv('BINANCE_NETWORK', 'mainnet'), // mainnet|testnet
        'rpc_url' => envv('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
        'vault_address' => envv('BSC_VAULT_ADDRESS', ''),
        'vault_private_key' => envv('BSC_VAULT_PRIVATE_KEY', ''),
        'token_contract' => envv('BSC_TOKEN_CONTRACT', ''),
        'chain_id' => envv('BSC_CHAIN_ID', '56'), // 56 for mainnet, 97 for testnet
        'explorer' => envv('BSC_EXPLORER', 'https://bscscan.com'),
        'gas_price' => envv('BSC_GAS_PRICE', '5000000000'), // 5 Gwei in Wei
        'gas_limit' => envv('BSC_GAS_LIMIT', '100000'),
        'withdrawal_fee_enabled' => envv('BSC_WITHDRAWAL_FEE_ENABLED', 'true') === 'true', // Enable/disable withdrawal fee for BSC
        'withdrawal_fee_usdd' => (float)envv('BSC_WITHDRAWAL_FEE_USDD', '2.50'), // Withdrawal fee in USDD for BSC
        'daily_withdrawal_limit' => (float)envv('BSC_DAILY_WITHDRAWAL_LIMIT', '0'), // Daily withdrawal limit for BSC (0 = unlimited)
    ],
    'ethereum' => [
        'network' => envv('ETHEREUM_NETWORK', 'mainnet'), // mainnet|goerli|sepolia
        'rpc_url' => envv('ETH_RPC_URL', 'https://cloudflare-eth.com'),
        'vault_address' => envv('ETH_VAULT_ADDRESS', ''),
        'vault_private_key' => envv('ETH_VAULT_PRIVATE_KEY', ''),
        'token_contract' => envv('ETH_TOKEN_CONTRACT', ''),
        'chain_id' => envv('ETH_CHAIN_ID', '1'), // 1 for mainnet, 5 for Goerli, 11155111 for Sepolia
        'explorer' => envv('ETH_EXPLORER', 'https://etherscan.io'),
        'gas_price' => envv('ETH_GAS_PRICE', '20000000000'), // 20 Gwei in Wei
        'gas_limit' => envv('ETH_GAS_LIMIT', '100000'),
        'withdrawal_fee_enabled' => envv('ETH_WITHDRAWAL_FEE_ENABLED', 'true') === 'true', // Enable/disable withdrawal fee for Ethereum
        'withdrawal_fee_usdd' => (float)envv('ETH_WITHDRAWAL_FEE_USDD', '2.50'), // Withdrawal fee in USDD for Ethereum
        'daily_withdrawal_limit' => (float)envv('ETH_DAILY_WITHDRAWAL_LIMIT', '0'), // Daily withdrawal limit for Ethereum (0 = unlimited)
    ],
    
    'walletconnect' => [
        'project_id' => envv('WALLETCONNECT_PROJECT_ID', ''), // From cloud.walletconnect.com
    ],
    'admin' => [
        'wallet_whitelist_enabled' => envv('ADMIN_WALLET_WHITELIST_ENABLED', 'false') === 'true',
        'wallet_whitelist' => array_filter(array_map(function ($a) {
            return strtolower(trim($a));
        }, explode(',', envv('ADMIN_WALLET_WHITELIST', '')))),
        // When true, admin may mark manual EVM withdrawals complete from the dashboard without RPC verification (see complete-manual-withdrawal.php)
        'allow_skip_evm_onchain_verify' => envv('ADMIN_ALLOW_SKIP_EVM_ONCHAIN_VERIFY', 'false') === 'true',
    ],

    // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
    // ⚠️ WARNING: Set to true to bypass YEMChain API calls - TESTING ONLY!
    'yemchain_bypass' => envv('YEMCHAIN_BYPASS', 'false') === 'true',
];

// Merge DB overrides (admin toggle for manual withdraw takes precedence over .env)
try {
    require_once __DIR__ . '/../Support/Database.php';
    $pdo = Database::pdo($config['db']);
    $stmt = $pdo->query("SELECT k, v FROM app_settings WHERE k = 'manual_withdraw_enabled'");
    if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config['withdrawal']['manual_withdraw_enabled'] = ($row['v'] === '1' || $row['v'] === 'true');
    }
} catch (Throwable $e) {
    /* keep env/file default */
}

// SECURITY: Fail fast if YEMChain bypass enabled in production
if (($config['yemchain_bypass'] ?? false) && !($config['app']['debug'] ?? false)) {
    throw new RuntimeException('YEMCHAIN_BYPASS must not be true when APP_DEBUG is false. Production safety check.');
}

return $config;
