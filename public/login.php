<?php
/**
 * Two-Step SafeZone Authentication Handler
 * Step 1: Login with pernum and password
 * Step 2: Validate Master PIN
 */

require_once __DIR__ . '/../app/Support/Security.php';
require_once __DIR__ . '/../app/Support/PathHelper.php';
require_once __DIR__ . '/../app/Support/Database.php';

Security::configureSecureSession();

// Load configuration
$config = require(__DIR__ . '/../app/Config/config.php');

// TLS: reuse resolved CA bundle from config (same as StellarService) — fixes Windows/XAMPP "unable to get local issuer certificate" on SafeZone APIs
$curlCainfo = $config['stellar']['curl_cainfo'] ?? null;
$curlSslVerify = $config['stellar']['curl_ssl_verify'] ?? true;

// Get the SafeZone API key from config (should be in .env)
$apiKey = $config['safezone']['api_key'] ?? '';
// Step 2 uses a different key (apikey parameter) - can be configured separately
$apiKeyStep2 = $config['safezone']['api_key_step2'] ?? $config['safezone']['api_key'] ?? '';

// Log if API key is missing (for debugging)
if (empty($apiKey)) {
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    $debugLog = $logsDir . '/safezone_login_debug.log';
    @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - WARNING: SafeZone API Key is not configured!\n" .
        "Please set SAFEZONE_API_KEY in your .env file.\n\n", FILE_APPEND);
}

// Check if debug mode is enabled
$debugMode = isset($_GET['debug']) || isset($_POST['debug']) || ($config['app']['debug'] ?? false);

// Handle Step 1: Login with pernum and password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === '1') {
    $pernum = $_POST['pernum'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($pernum) || empty($password)) {
        header('Location: ' . PathHelper::url('safezone.php?error=missing_fields'));
        exit;
    }
    
    // Check pernum whitelist if enabled
    $pernumWhitelistEnabled = $config['safezone']['pernum_whitelist_enabled'] ?? false;
    $allowedPernums = $config['safezone']['allowed_pernums'] ?? [];
    $developmentMode = $config['app']['debug'] ?? false;
    
    if ($pernumWhitelistEnabled && !$developmentMode && !empty($allowedPernums)) {
        // Normalize pernum for comparison (trim whitespace)
        $pernumNormalized = trim($pernum);
        $isAllowed = false;
        
        foreach ($allowedPernums as $allowedPernum) {
            if (trim($allowedPernum) === $pernumNormalized) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            // Log the restricted login attempt
            Security::logSecurityEvent('login_pernum_restricted', [
                'ip' => Security::getClientIp(),
                'pernum' => substr($pernum, 0, 3) . '***', // Partial logging for security
                'reason' => 'Pernum not in whitelist'
            ]);
            
            header('Location: ' . PathHelper::url('safezone.php?error=pernum_restricted'));
            exit;
        }
    }
    
    // Call SafeZone login API
    $loginUrl = $config['safezone']['login_api'] ?? 'https://safe.zone/signup/login_api.php';
    
    // Check if API key is configured
    if (empty($apiKey)) {
        Security::logSecurityEvent('login_api_key_missing', [
            'ip' => Security::getClientIp()
        ]);
        header('Location: ' . PathHelper::url('safezone.php?error=api_key_missing'));
        exit;
    }
    
    // Follow Postman collection exactly: pernum, password, key
    $postData = http_build_query([
        'pernum' => $pernum,
        'password' => $password,
        'key' => $apiKey
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $curlSslVerify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curlSslVerify ? 2 : 0);
    if (!empty($curlCainfo) && is_readable($curlCainfo)) {
        curl_setopt($ch, CURLOPT_CAINFO, $curlCainfo);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the response for debugging (remove in production if needed)
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    $debugLog = $logsDir . '/safezone_login_debug.log';
    @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Step 1 Login API Response:\n" . 
        "HTTP Code: $httpCode\n" .
        "CURL Error: " . ($curlError ?: 'None') . "\n" .
        "Response: " . $response . "\n" .
        "Response Length: " . strlen($response) . "\n" .
        "API Key: " . (empty($apiKey) ? 'NOT SET' : 'configured (length: ' . strlen($apiKey) . ')') . "\n\n", FILE_APPEND);
    
    // Check for errors
    if ($curlError || $httpCode !== 200) {
        Security::logSecurityEvent('login_api_error', [
            'ip' => Security::getClientIp(),
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response' => substr($response, 0, 200)
        ]);
        header('Location: ' . PathHelper::url('safezone.php?error=api_error'));
        exit;
    }
    
    // Trim response
    $response = trim($response);
    
    // Check if response is "Invalid pernum/password."
    if ($response === 'Invalid pernum/password.' || stripos($response, 'invalid') !== false) {
        Security::logSecurityEvent('login_failed', [
            'ip' => Security::getClientIp(),
            'pernum' => substr($pernum, 0, 3) . '***', // Partial logging for security
            'response' => $response
        ]);
        
        // Log debug info to file but don't show to user
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }
        $debugLog = $logsDir . '/safezone_login_debug.log';
        @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Invalid Credentials:\n" . 
            "HTTP Code: $httpCode\n" .
            "Response: " . $response . "\n" .
            "API URL: $loginUrl\n" .
            "API Key Configured: " . (!empty($apiKey) ? 'Yes' : 'No') . "\n\n", FILE_APPEND);
        
        // If debug mode is explicitly enabled, show JSON response
        if ($debugMode && isset($_GET['debug'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid credentials',
                'debug' => [
                    'http_code' => $httpCode,
                    'curl_error' => $curlError ?: 'None',
                    'response' => $response,
                    'response_length' => strlen($response),
                    'api_url' => $loginUrl,
                    'api_key_configured' => !empty($apiKey)
                ]
            ], JSON_PRETTY_PRINT);
            exit;
        }
        
        // Normal flow: redirect with simple error message (no debug)
        header('Location: ' . PathHelper::url('safezone.php?error=invalid_credentials'));
        exit;
    }
    
    // Check if response is empty
    if (empty($response)) {
        Security::logSecurityEvent('login_empty_response', [
            'ip' => Security::getClientIp(),
            'http_code' => $httpCode
        ]);
        header('Location: ' . PathHelper::url('safezone.php?error=empty_response'));
        exit;
    }
    
    // Try to parse JSON response
    $userData = json_decode($response, true);
    $jsonError = json_last_error();
    
    // If JSON decode failed, log the error and response
    if ($jsonError !== JSON_ERROR_NONE) {
        Security::logSecurityEvent('login_json_error', [
            'ip' => Security::getClientIp(),
            'json_error' => json_last_error_msg(),
            'response_preview' => substr($response, 0, 200),
            'response_length' => strlen($response)
        ]);
        
        // Log full response to debug file
        @file_put_contents($debugLog, "JSON Parse Error: " . json_last_error_msg() . "\n" .
            "Full Response: " . $response . "\n\n", FILE_APPEND);
        
        header('Location: ' . PathHelper::url('safezone.php?error=invalid_response&debug=json_error'));
        exit;
    }
    
    // Check if we got valid user data with uid
    if (!$userData || !isset($userData['uid'])) {
        Security::logSecurityEvent('login_invalid_response', [
            'ip' => Security::getClientIp(),
            'response_preview' => substr($response, 0, 200),
            'parsed_data' => $userData
        ]);
        
        // Log to debug file
        @file_put_contents($debugLog, "Invalid Response - No UID found:\n" .
            "Parsed Data: " . print_r($userData, true) . "\n" .
            "Raw Response: " . $response . "\n\n", FILE_APPEND);
        
        // If debug mode, show the response
        if ($debugMode) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid response - No UID found',
                'debug' => [
                    'http_code' => $httpCode,
                    'curl_error' => $curlError ?: 'None',
                    'raw_response' => $response,
                    'response_length' => strlen($response),
                    'json_error' => json_last_error_msg(),
                    'parsed_data' => $userData,
                    'api_url' => $loginUrl,
                    'api_key_configured' => !empty($apiKey),
                    'post_data' => [
                        'pernum' => substr($pernum, 0, 3) . '***',
                        'password' => '***',
                        'key' => empty($apiKey) ? 'NOT SET' : '(configured)'
                    ]
                ]
            ], JSON_PRETTY_PRINT);
            exit;
        }
        
        header('Location: ' . PathHelper::url('safezone.php?error=invalid_response&debug=no_uid'));
        exit;
    }
    
    // Check UID whitelist if enabled (always enforced, regardless of debug mode)
    $uidWhitelistEnabled = $config['safezone']['uid_whitelist_enabled'] ?? false;
    $allowedUIDs = $config['safezone']['allowed_uids'] ?? [];
    
    if ($uidWhitelistEnabled && !empty($allowedUIDs)) {
        $uid = (int)$userData['uid'];
        
        if (!in_array($uid, $allowedUIDs, true)) {
            // Log the restricted login attempt
            Security::logSecurityEvent('login_uid_restricted', [
                'ip' => Security::getClientIp(),
                'uid' => $uid,
                'pernum' => substr($pernum, 0, 3) . '***',
                'reason' => 'UID not in whitelist'
            ]);
            
            header('Location: ' . PathHelper::url('safezone.php?error=uid_restricted'));
            exit;
        }
    }
    
    // Store user data in session for step 2
    $_SESSION['login_step1_uid'] = $userData['uid'];
    $_SESSION['login_step1_data'] = $userData;
    
    // Generate a random 3-digit key for PIN validation
    // This key tells the user which positions from their 6-digit master PIN to enter
    // Example: If key is "321", user enters digits at positions 3, 2, 1 from their master PIN
    // The key contains 3 unique digits from 1-6 (representing positions in a 6-digit PIN)
    $positions = [1, 2, 3, 4, 5, 6];
    shuffle($positions);
    $pinKey = (string)$positions[0] . (string)$positions[1] . (string)$positions[2];
    
    // Store the generated key in session
    $_SESSION['login_pin_key'] = $pinKey;
    
    // Redirect to step 2 (PIN entry) with the key
    header('Location: ' . PathHelper::url('safezone.php?step=2&key=' . $pinKey));
    exit;
}

// Handle Step 2: Master PIN validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === '2') {
    // Check if step 1 was completed
    if (!isset($_SESSION['login_step1_uid']) || !isset($_SESSION['login_step1_data'])) {
        header('Location: ' . PathHelper::url('safezone.php?error=session_expired'));
        exit;
    }
    
    $uid = $_SESSION['login_step1_uid'];
    $pin = $_POST['pin'] ?? '';
    
    // Get the key from session (generated in step 1)
    $key = $_SESSION['login_pin_key'] ?? '';
    
    if (empty($pin) || empty($key)) {
        // If key is missing from session, redirect back to step 1
        if (empty($key)) {
            header('Location: ' . PathHelper::url('safezone.php?error=session_expired'));
            exit;
        }
        header('Location: ' . PathHelper::url('safezone.php?step=2&error=missing_pin'));
        exit;
    }
    
    // Call SafeZone PIN validation API v2
    // Follow Postman collection exactly: uid, key, pin, apikey
    $pinUrl = $config['safezone']['pin_api_v2'] ?? 'https://safe.zone/signup/check_pin_api_v2.php';
    $postData = http_build_query([
        'uid' => $uid,
        'key' => $key,  // The 3-digit key (position order)
        'pin' => $pin,  // The 3-digit PIN
        'apikey' => $apiKeyStep2  // API key for Step 2 (different from Step 1)
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pinUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $curlSslVerify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curlSslVerify ? 2 : 0);
    if (!empty($curlCainfo) && is_readable($curlCainfo)) {
        curl_setopt($ch, CURLOPT_CAINFO, $curlCainfo);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Check for errors
    if ($curlError || $httpCode !== 200) {
        Security::logSecurityEvent('pin_api_error', [
            'ip' => Security::getClientIp(),
            'uid' => $uid,
            'http_code' => $httpCode,
            'curl_error' => $curlError
        ]);
        header('Location: ' . PathHelper::url('safezone.php?step=2&error=api_error'));
        exit;
    }
    
    $trimmedResponse = trim($response);
    
    // Check if PIN is valid
    if (strtolower($trimmedResponse) === 'valid') {
        // PIN is valid - complete authentication
        $userData = $_SESSION['login_step1_data'];
        
        // Set session UID
        $_SESSION['uid'] = $uid;
        
        // Store user data
        $_SESSION['verification_status'] = $userData['verification_status'] ?? 'unverified';
        $_SESSION['member_type'] = $userData['member_type'] ?? 'user';
        $_SESSION['email'] = $userData['email'] ?? '';
        $_SESSION['fname'] = $userData['fname'] ?? '';
        $_SESSION['lname'] = $userData['lname'] ?? '';
        
        // Clear step 1 session data
        unset($_SESSION['login_step1_uid']);
        unset($_SESSION['login_step1_data']);
        unset($_SESSION['login_pin_key']); // Clear the PIN key after successful validation
        
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
        
        // Fetch tax country from SafeZone API (if needed)
        $countrytax = "United States"; // Default
        // You can add country fetching logic here if needed
        
        $_SESSION["countrytax"] = $countrytax;
        $_SESSION["currencycode"] = "USD"; // Default
        $_SESSION["currencyrate"] = "0.01"; // Default
        
        // Log successful authentication
        Security::logSecurityEvent('auth_success', [
            'ip' => Security::getClientIp(),
            'uid' => $uid,
            'verification_status' => $_SESSION['verification_status'],
            'member_type' => $_SESSION['member_type']
        ]);
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Redirect to dashboard
        header('Location: ' . PathHelper::url('dashboard.php'));
        exit;
    } else {
        // PIN is invalid
        Security::logSecurityEvent('pin_validation_failed', [
            'ip' => Security::getClientIp(),
            'uid' => $uid,
            'response' => $trimmedResponse
        ]);
        header('Location: ' . PathHelper::url('safezone.php?step=2&error=invalid_pin'));
        exit;
    }
}

// If neither step, redirect to login page
header('Location: ' . PathHelper::url('safezone.php'));
exit;

