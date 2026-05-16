<?php

/**
 * ReferralService - Resolves referrer UID for a given user via SafeZone getrefby API.
 * Used to distribute withdrawal fee commission to referrers.
 */
class ReferralService
{
    private string $apiUrl;
    private string $apiKey;
    private ?string $cainfoPath;

    public function __construct(string $apiUrl, string $apiKey, ?string $cainfoPath = null)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->cainfoPath = $cainfoPath;
    }

    /**
     * Get the UID of the user who referred the given user.
     * Returns null on API failure, timeout, invalid response, or self-referral.
     *
     * @param int $uid The UID of the referred user (withdrawing user)
     * @return int|null Referrer UID, or null if none/unavailable
     */
    public function getReferrerUid(int $uid): ?int
    {
        if (empty($this->apiKey)) {
            if (class_exists('Logger')) {
                Logger::debug('ReferralService: No API key configured, skipping referrer lookup', ['uid' => $uid]);
            }
            return null;
        }

        try {
            $url = $this->apiUrl . '?' . http_build_query([
                'apikey' => $this->apiKey,
                'uid' => $uid,
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            // Use CA bundle if set (fixes "unable to get local issuer certificate" on Windows/XAMPP)
            $cainfo = $this->cainfoPath ?: getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE') ?: ini_get('curl.cainfo');
            if ($cainfo && is_string($cainfo) && file_exists($cainfo)) {
                curl_setopt($ch, CURLOPT_CAINFO, $cainfo);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                if (class_exists('Logger')) {
                    Logger::warning('ReferralService: API request failed', ['uid' => $uid, 'http_code' => $httpCode, 'error' => $error]);
                }
                return null;
            }

            $response = trim((string)$response);

            // Handle error responses
            if (stripos($response, 'invalid') !== false || stripos($response, 'error') !== false || empty($response)) {
                return null;
            }

            $referrerUid = $this->parseResponse($response);

            if ($referrerUid === null || $referrerUid <= 0) {
                return null;
            }

            // Reject self-referral
            if ($referrerUid === $uid) {
                if (class_exists('Logger')) {
                    Logger::debug('ReferralService: Self-referral detected, ignoring', ['uid' => $uid]);
                }
                return null;
            }

            return $referrerUid;
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::warning('ReferralService: Exception', ['uid' => $uid, 'error' => $e->getMessage()]);
            }
            return null;
        }
    }

    /**
     * Parse API response to extract referrer UID.
     * Supports: JSON {"ref_uid": 12345}, {"referrer": 12345}, colon-delimited "success:12345"
     *
     * @param string $response Raw API response
     * @return int|null Parsed referrer UID or null
     */
    private function parseResponse(string $response): ?int
    {
        // Try JSON
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            $uid = $data['ref_uid'] ?? $data['referrer'] ?? $data['referrer_uid'] ?? $data['ref'] ?? null;
            if ($uid !== null && is_numeric($uid)) {
                return (int)$uid;
            }
        }

        // Try colon-delimited: success:12345 or ref_uid:12345
        $parts = explode(':', $response, 2);
        if (count($parts) === 2) {
            $value = trim($parts[1]);
            if (is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        // Plain numeric
        if (is_numeric($response) && (int)$response > 0) {
            return (int)$response;
        }

        return null;
    }
}
