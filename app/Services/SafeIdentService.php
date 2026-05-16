<?php

class SafeIdentService
{
    private string $apiUrl;
    private string $apiKey;
    private int $cacheDuration;
    private bool $verificationRequired;
    private bool $whitelistEnabled;
    private array $whitelistedUids;
    private static array $cache = [];

    public function __construct(array $config)
    {
        $this->apiUrl = $config['api_url'] ?? 'https://safeident.com/verify_status_api.php';
        $this->apiKey = $config['api_key'] ?? '';
        $this->cacheDuration = $config['cache_duration'] ?? 300; // 5 minutes default
        $this->verificationRequired = $config['verification_required'] ?? true;
        $this->whitelistEnabled = $config['whitelist_enabled'] ?? true;
        $this->whitelistedUids = $config['whitelisted_uids'] ?? [];
    }

    /**
     * Check if user is whitelisted (bypasses verification)
     */
    public function isUserWhitelisted(int $uid): bool
    {
        return in_array($uid, $this->whitelistedUids, true);
    }

    /**
     * Check verification status from SafeIdent API
     * Returns array with 'verified' boolean and 'data' from API
     */
    public function checkVerificationStatus(int $uid): array
    {
        // Check cache first
        $cacheKey = "safeident_verify_{$uid}";
        if (isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheDuration) {
                return $cached['data'];
            }
        }

        // Call SafeIdent API
        try {
            $url = $this->apiUrl . '?' . http_build_query([
                'apikey' => $this->apiKey,
                'uid' => $uid
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                error_log("SafeIdentService: API request failed - HTTP $httpCode, Error: $error");
                return [
                    'verified' => false,
                    'error' => 'API request failed',
                    'data' => null
                ];
            }

            $data = json_decode($response, true);
            
            // Debug logging
            error_log("SafeIdentService: API Response for UID $uid: " . $response);
            error_log("SafeIdentService: Decoded data: " . json_encode($data));
            
            // Check if response indicates verification
            // API returns: {"status":"verified","code":200,"details":{"uid":234601,"message":"User is Verified."}}
            $verified = false;
            if (isset($data['status']) && $data['status'] === 'verified') {
                $verified = true;
                error_log("SafeIdentService: UID $uid is VERIFIED (status=verified)");
            } elseif (isset($data['verified']) && $data['verified'] === true) {
                $verified = true;
                error_log("SafeIdentService: UID $uid is VERIFIED (verified=true)");
            } elseif (isset($data['verification_status']) && $data['verification_status'] === 'approved') {
                $verified = true;
                error_log("SafeIdentService: UID $uid is VERIFIED (verification_status=approved)");
            } else {
                error_log("SafeIdentService: UID $uid is NOT VERIFIED - status: " . ($data['status'] ?? 'not set'));
            }

            $result = [
                'verified' => $verified,
                'data' => $data,
                'error' => null
            ];

            // Cache the result
            self::$cache[$cacheKey] = [
                'timestamp' => time(),
                'data' => $result
            ];

            return $result;

        } catch (Exception $e) {
            error_log("SafeIdentService: Exception - " . $e->getMessage());
            return [
                'verified' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Check if user can transact (verified OR whitelisted if whitelist enabled)
     * Returns array with 'allowed' boolean and 'message' string
     */
    public function canUserTransact(int $uid): array
    {
        // If verification is not required, allow all
        if (!$this->verificationRequired) {
            return [
                'allowed' => true,
                'message' => 'Verification not required',
                'reason' => 'disabled'
            ];
        }

        // Check whitelist first (bypasses verification check) - only if whitelist is enabled
        if ($this->whitelistEnabled) {
            error_log("SafeIdentService: Checking whitelist for UID $uid");
            error_log("SafeIdentService: Whitelisted UIDs: " . json_encode($this->whitelistedUids));
            error_log("SafeIdentService: Is whitelisted: " . ($this->isUserWhitelisted($uid) ? 'YES' : 'NO'));
            
            if ($this->isUserWhitelisted($uid)) {
                error_log("SafeIdentService: UID $uid is whitelisted - allowing transaction");
                return [
                    'allowed' => true,
                    'message' => 'User is whitelisted',
                    'reason' => 'whitelisted'
                ];
            }
        } else {
            error_log("SafeIdentService: Whitelist is DISABLED - all users must verify");
        }

        // Check verification status
        $verificationResult = $this->checkVerificationStatus($uid);

        if (isset($verificationResult['error']) && $verificationResult['error'] !== null) {
            // API error occurred - fail closed (don't allow transaction)
            return [
                'allowed' => false,
                'message' => 'Unable to verify your identity at this time. Please try again later or contact support.',
                'reason' => 'api_error',
                'error' => $verificationResult['error']
            ];
        }

        if ($verificationResult['verified']) {
            return [
                'allowed' => true,
                'message' => 'User is verified',
                'reason' => 'verified'
            ];
        }

        // User is not verified and not whitelisted
        return [
            'allowed' => false,
            'message' => 'Identity verification required. Please complete verification at SafeIdent before making deposits or withdrawals. To complete verification visit https://safeident.com/',
            'reason' => 'not_verified'
        ];
    }

    /**
     * Clear cache for a specific user or all users
     */
    public static function clearCache(?int $uid = null): void
    {
        if ($uid === null) {
            self::$cache = [];
        } else {
            $cacheKey = "safeident_verify_{$uid}";
            unset(self::$cache[$cacheKey]);
        }
    }
}
