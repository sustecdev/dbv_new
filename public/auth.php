<?php
/**
 * SafeZone Authentication Callback Handler
 * Handles the callback from SafeZone after external authentication
 * Updated to match dbvapp implementation with POST-based authentication and key validation
 */

require_once __DIR__ . '/../app/Support/Security.php';
require_once __DIR__ . '/../app/Support/PathHelper.php';
require_once __DIR__ . '/../app/Support/Database.php';

// Configure secure session
Security::configureSecureSession();

// Load configuration
$config = require(__DIR__ . '/../app/Config/config.php');

// Debug log: only when app debug enabled, never log credentials
$debugMode = $config['app']['debug'] ?? false;
if ($debugMode) {
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    @file_put_contents($logsDir . '/safezone_callback_debug.log',
        date('Y-m-d H:i:s') . " | " . ($_SERVER['REQUEST_METHOD'] ?? '') . " | uid=" . ($_POST['uid'] ?? $_GET['uid'] ?? 'none') . "\n",
        FILE_APPEND
    );
}

// Clear any existing session key
unset($_SESSION["skey"]);

// Handle POST data from SafeZone (primary method)
if ($_POST) {
    
    // Developer/Tester UID whitelist (optional - can be configured in config)
    $developerUID = 789;
    $developer = false;
    if (isset($_POST['uid']) && $_POST['uid'] == $developerUID) {
        $developer = true;
    }

    $testerUIDs = [373764, 234601]; // Add more tester UIDs as needed
    $tester = false;
    foreach ($testerUIDs as $testUID) {
        if (isset($_POST['uid']) && $_POST['uid'] == $testUID) {
            $tester = true;
            break;
        }
    }


    // Check if development mode allows any user (for testing)
    $developmentMode = $config['app']['debug'] ?? false;
    
    // Validate UID
    $uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    
    if (is_numeric($uid) && $uid > 0) {
        
        // Check UID whitelist if enabled (BEFORE key validation)
        $uidWhitelistEnabled = $config['safezone']['uid_whitelist_enabled'] ?? false;
        $allowedUids = $config['safezone']['allowed_uids'] ?? [];
        
        if ($uidWhitelistEnabled && !in_array($uid, $allowedUids, true)) {
            Security::logSecurityEvent('auth_callback_uid_not_whitelisted', [
                'ip' => Security::getClientIp(),
                'uid' => $uid,
                'reason' => 'UID not in whitelist'
            ]);
            header('Location: ' . PathHelper::url('safezone.php?error=invalid'));
            exit;
        }
        
        // Get authentication keys from POST
        $key1 = isset($_POST['key1']) ? $_POST['key1'] : '';
        $key2 = isset($_POST['key2']) ? $_POST['key2'] : '';
        
        // Validate keys with SafeZone. skip_validation only allowed in debug mode.
        $skipValidation = ($config['app']['debug'] ?? false) && isset($_POST['skip_validation']) && $_POST['skip_validation'] == '1';
        
        if (!$skipValidation && (!empty($key1) || !empty($key2))) {
            // Validate keys with SafeZone
            $validateUrl = "https://safe.zone/validate_keys.php?external=1&uid=" . $uid . 
                          "&website=" . urlencode($_SERVER['SERVER_NAME']) . 
                          "&key1=" . urlencode($key1) . 
                          "&key2=" . urlencode($key2);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $validateUrl);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($response != "valid") {
                Security::logSecurityEvent('auth_callback_validation_failed', [
                    'ip' => Security::getClientIp(),
                    'uid' => $uid,
                    'response' => $response,
                    'curl_error' => $curlError
                ]);
                
                header('Location: ' . PathHelper::url('safezone.php?error=invalid'));
                exit;
            }
        }
        
        // Set session UID
        $_SESSION["uid"] = $uid;
        
        // Set session timeout cookie (30 minutes)
        $time = time() + 1800;
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('session_time', $time, [
            'expires' => $time,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => $isSecure,
            'httponly' => true
        ]);
        
        // Store verification status and member type if provided
        $_SESSION["verification_status"] = $_POST["verification_status"] ?? 'verified';
        $_SESSION["member_type"] = $_POST["member_type"] ?? 'premium';
        
        // Fetch tax country from SafeZone API (key from config)
        $countrytax = "United States"; // Default
        $taxApiKey = $config['safezone']['tax_country_api_key'] ?? '';
        $curlURL = !empty($taxApiKey)
            ? "https://safe.zone/api.php?key=" . urlencode($taxApiKey) . "&action=tax_country&uid=" . $uid
            : null;
        
        if ($curlURL) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $curlURL);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resultCurl = curl_exec($ch);
            if (strlen($resultCurl ?? '') > 0 && !curl_error($ch)) {
                $countrytax = trim($resultCurl);
            }
            curl_close($ch);
        }
        
        $_SESSION["countrytax"] = $countrytax;
        
        // Get currency code and exchange rate from database
        $currencyRate = "0.01"; // Default
        $currencyCode = "USD"; // Default currency code
        
        try {
            // Try to connect to database if available
            $dbConfig = $config['db'];
            $pdo2 = Database::pdo($dbConfig);
            
            // Get currency from country
            $stmt = $pdo2->prepare("SELECT currency FROM geo_countries WHERE name = :countrytax LIMIT 1");
            $stmt->execute(['countrytax' => $countrytax]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currencyCode = $row['currency'];
            }
            
            // Get exchange rate for currency
            $stmt = $pdo2->prepare("SELECT dbv_value FROM voucher_rate WHERE currency_code = :currency LIMIT 1");
            $stmt->execute(['currency' => $currencyCode]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $currencyRate = $row['dbv_value'];
            }
        } catch (Exception $e) {
            // Use defaults if database not available
            error_log("Currency lookup failed: " . $e->getMessage());
        }
        
        $_SESSION["currencycode"] = $currencyCode;
        $_SESSION["currencyrate"] = $currencyRate;
        
        // Store SafeZone keys if provided
        if (!empty($key1)) {
            $_SESSION['safezone_key1'] = $key1;
        }
        if (!empty($key2)) {
            $_SESSION['safezone_key2'] = $key2;
        }
        
        // Log successful authentication
        Security::logSecurityEvent('auth_success', [
            'ip' => Security::getClientIp(),
            'uid' => $uid,
            'verification_status' => $_SESSION["verification_status"],
            'member_type' => $_SESSION["member_type"],
            'countrytax' => $countrytax,
            'currencycode' => $currencyCode
        ]);
        
        // Regenerate session ID for security (prevents session fixation)
        session_regenerate_id(true);
        
        // Redirect to dashboard
        header('Location: ' . PathHelper::url('dashboard.php'));
        exit;
        
    } else {
        // Invalid UID
        Security::logSecurityEvent('auth_callback_invalid_uid', [
            'ip' => Security::getClientIp(),
            'post_data' => $_POST
        ]);
        
        header('Location: ' . PathHelper::url('safezone.php?error=invalid'));
        exit;
    }
    
} else {
    // Fallback: Try GET parameters (for backward compatibility or alternative SafeZone implementations)
    $uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    
    // Try alternative parameter names
    if ($uid <= 0) {
        $uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    }
    if ($uid <= 0) {
        $uid = isset($_GET['userid']) ? (int)$_GET['userid'] : 0;
    }
    if ($uid <= 0) {
        $uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    }
    
    $domain = isset($_GET['domain']) ? $_GET['domain'] : '';
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    
    // Validate the callback
    if ($uid <= 0) {
        // No UID provided - redirect back to login
        Security::logSecurityEvent('auth_callback_invalid', [
            'ip' => Security::getClientIp(),
            'params' => $_GET,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        header('Location: ' . PathHelper::url('safezone.php?error=invalid'));
        exit;
    }

    // Check UID whitelist if enabled (GET request)
    $uidWhitelistEnabled = $config['safezone']['uid_whitelist_enabled'] ?? false;
    $allowedUids = $config['safezone']['allowed_uids'] ?? [];
    
    if ($uidWhitelistEnabled && !in_array($uid, $allowedUids, true)) {
        Security::logSecurityEvent('auth_callback_uid_not_whitelisted_get', [
            'ip' => Security::getClientIp(),
            'uid' => $uid,
            'reason' => 'UID not in whitelist (GET)'
        ]);
        header('Location: ' . PathHelper::url('safezone.php?error=invalid'));
        exit;
    }
    
    // Verify domain matches (security check)
    $expectedDomain = $_SERVER['SERVER_NAME'] ?? '';
    if (!empty($domain) && $domain !== $expectedDomain) {
        Security::logSecurityEvent('auth_callback_domain_mismatch', [
            'ip' => Security::getClientIp(),
            'expected' => $expectedDomain,
            'received' => $domain
        ]);
        
        header('Location: ' . PathHelper::url('safezone.php?error=domain'));
        exit;
    }
    
    // Set the session UID (GET fallback - less secure, no key validation)
    $_SESSION['uid'] = $uid;
    
    // Store additional SafeZone data if provided
    if (!empty($token)) {
        $_SESSION['safezone_token'] = $token;
    }
    if (!empty($key)) {
        $_SESSION['safezone_key'] = $key;
    }
    
    // Log successful authentication (GET method)
    Security::logSecurityEvent('auth_success_get', [
        'ip' => Security::getClientIp(),
        'uid' => $uid,
        'domain' => $domain,
        'method' => 'GET'
    ]);
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Redirect to dashboard
    header('Location: ' . PathHelper::url('dashboard.php'));
    exit;
}

exit;
