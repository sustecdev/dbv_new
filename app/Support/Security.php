<?php

/**
 * Security Helper Class
 * Provides authentication, validation, and security utilities
 */
class Security
{
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate HMAC signature for worker authentication
     */
    public static function generateWorkerSignature(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret);
    }
    
    /**
     * Verify worker HMAC signature
     */
    public static function verifyWorkerSignature(string $data, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $data, $secret);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Sanitize output to prevent XSS
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate Stellar address format
     */
    public static function isValidStellarAddress(string $address): bool
    {
        return (bool)preg_match('/^[A-Z0-9]{56}$/', $address);
    }
    
    /**
     * Validate transaction hash format
     */
    public static function isValidTxnHash(string $hash): bool
    {
        return (bool)preg_match('/^[a-fA-F0-9]{64}$/', $hash);
    }
    
    /**
     * Check if a withdrawal address is in the blocklist (contract/issuer addresses).
     * @param string $address The address to check
     * @param array $blocklist Normalized addresses (Stellar uppercase, EVM lowercase)
     * @return bool True if address is blocked
     */
    public static function isBlockedWithdrawalAddress(string $address, array $blocklist): bool
    {
        if (empty($blocklist)) {
            return false;
        }
        $addr = trim($address);
        if ($addr === '') {
            return false;
        }
        // EVM: 0x + 40 hex -> lowercase
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $addr)) {
            $normalized = strtolower($addr);
        } elseif (preg_match('/^[A-Za-z0-9]{56}$/', $addr)) {
            // Stellar: 56 alphanumeric -> uppercase
            $normalized = strtoupper($addr);
        } else {
            $normalized = $addr;
        }
        return in_array($normalized, $blocklist, true);
    }
    
    /**
     * Rate limit check - uses both session and IP for defense in depth.
     * Session-based can be bypassed by clearing cookies; IP-based provides backup.
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 10, int $windowSeconds = 60): bool
    {
        $now = time();
        
        // Session-based rate limit
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $cacheKey = 'rate_limit_' . md5($key);
        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = ['count' => 0, 'reset' => $now + $windowSeconds];
        }
        $limit = &$_SESSION[$cacheKey];
        if ($now > $limit['reset']) {
            $limit = ['count' => 0, 'reset' => $now + $windowSeconds];
        }
        if ($limit['count'] >= $maxAttempts) {
            return false;
        }
        
        // IP-based rate limit (file-based, survives session clear)
        $ipKey = 'rate_ip_' . md5(self::getClientIp() . '_' . $key);
        $rateDir = __DIR__ . '/../../logs';
        if (!is_dir($rateDir)) {
            @mkdir($rateDir, 0755, true);
        }
        $ipFile = $rateDir . '/' . $ipKey . '.ratelimit';
        $ipData = @file_exists($ipFile) ? @json_decode(@file_get_contents($ipFile), true) : null;
        if (!$ipData || $now > ($ipData['reset'] ?? 0)) {
            $ipData = ['count' => 0, 'reset' => $now + $windowSeconds];
        }
        if ($ipData['count'] >= $maxAttempts) {
            return false;
        }
        $ipData['count']++;
        @file_put_contents($ipFile, json_encode($ipData), LOCK_EX);
        
        $limit['count']++;
        return true;
    }
    
    /**
     * Secure session configuration
     */
    public static function configureSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Use secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $logFile = __DIR__ . '/../../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIp(),
            'data' => $data
        ];
        
        file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

