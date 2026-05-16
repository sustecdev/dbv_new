<?php

class SafeZoneService
{
    private string $pinCheckUrl;

    public function __construct(string $pinCheckUrl)
    {
        $this->pinCheckUrl = $pinCheckUrl;
    }

    public function validatePin(int $uid, string $pin, string $key): bool
    {
        // Debug: Log exactly what we're sending to SafeZone API
        error_log("SafeZoneService validatePin - UID: $uid, PIN: '" . str_repeat('*', strlen($pin)) . "' (length: " . strlen($pin) . "), Key: '$key' (length: " . strlen($key) . ")");
        
        $post = http_build_query([
            'uid' => $uid,
            'pin' => $pin,
            'key' => $key,
        ]);
        
        error_log("SafeZoneService POST query: " . str_replace(['pin=' . $pin, 'key=' . $key], ['pin=***', 'key=***'], $post));
        
        $ch = curl_init($this->pinCheckUrl);
        if ($ch === false) {
            error_log("SafeZoneService::validatePin - Failed to initialize cURL for UID: $uid");
            return false;
        }
        
        curl_setopt($ch, CURLOPT_URL, $this->pinCheckUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log for debugging (can be removed in production)
        if ($curlError || $httpCode !== 200) {
            error_log("PIN validation error - UID: $uid, HTTP: $httpCode, Error: $curlError, Response: " . substr($resp, 0, 100));
        }
        
        // Handle errors
        if ($curlError || $httpCode !== 200 || !$resp) {
            error_log("SafeZoneService::validatePin - Request failed for UID: $uid, HTTP: $httpCode, Error: " . ($curlError ?: 'No response'));
            return false;
        }
        
        $trimmedResponse = trim((string)$resp);
        
        // Debug: Log the actual response from SafeZone API
        error_log("SafeZoneService API Response - UID: $uid, Raw response: '$resp', Trimmed: '$trimmedResponse', Length: " . strlen($trimmedResponse) . ", HTTP Code: $httpCode");
        
        // Check for valid responses (case-insensitive, handle variations)
        $lowerResponse = strtolower($trimmedResponse);
        
        // Valid responses
        if ($lowerResponse === 'valid' || 
            $lowerResponse === 'success' || 
            $lowerResponse === 'ok' ||
            $trimmedResponse === '1' ||
            $trimmedResponse === 'true') {
            return true;
        }
        
        // Invalid responses (explicitly check for invalid)
        if ($lowerResponse === 'invalid' ||
            $lowerResponse === 'pin does not match' ||
            $lowerResponse === 'wrong pin' ||
            $lowerResponse === 'incorrect' ||
            $trimmedResponse === '0' ||
            $trimmedResponse === 'false') {
            // Explicitly invalid - log but return false
            error_log("PIN validation failed - UID: $uid, Response: $trimmedResponse");
            return false;
        }
        
        // Log unexpected response format for debugging
        error_log("PIN validation unexpected response format - UID: $uid, Response: " . substr($trimmedResponse, 0, 100) . " (Length: " . strlen($trimmedResponse) . ")");
        
        // Default to false for unknown responses
        return false;
    }
}
