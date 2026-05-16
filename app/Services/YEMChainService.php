<?php

class YEMChainService
{
    private string $base;
    private string $apiKey;

    public function __construct(string $base, string $apiKey)
    {
        $this->base = rtrim($base, '/');
        $this->apiKey = $apiKey;
    }

    public function getBalance(int $uid, string $asset = 'DBV'): float
    {
        try {
            $url = $this->base . '/get-balance.php';
            $post = http_build_query([
                'apikey' => $this->apiKey,
                'asset' => $asset,
                'uid' => $uid,
            ]);
            
            $ch = curl_init($url);
            if ($ch === false) {
                if (class_exists('Logger')) Logger::error("YEMChainService::getBalance - Failed to initialize cURL", ['uid' => $uid, 'asset' => $asset]);
                else error_log("YEMChainService::getBalance - Failed to initialize cURL for UID: $uid, Asset: $asset");
                return 0.0;
            }
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                if (class_exists('Logger')) Logger::error("YEMChainService::getBalance - cURL error", ['uid' => $uid, 'asset' => $asset, 'error' => $curlError]);
                else error_log("YEMChainService::getBalance - cURL error for UID: $uid, Asset: $asset - $curlError");
                return 0.0;
            }
            
            if (!$response || $httpCode !== 200) {
                if (class_exists('Logger')) Logger::error("YEMChainService::getBalance - HTTP error", ['uid' => $uid, 'asset' => $asset, 'http_code' => $httpCode]);
                else error_log("YEMChainService::getBalance - HTTP error $httpCode for UID: $uid, Asset: $asset");
                return 0.0;
            }
            
            $parts = explode(':', $response);
            if (($parts[0] ?? '') === 'success') {
                return (float)($parts[1] ?? 0);
            }
            
            if (class_exists('Logger')) Logger::error("YEMChainService::getBalance - Unexpected response format", ['uid' => $uid, 'asset' => $asset, 'response_preview' => substr($response, 0, 100)]);
            else error_log("YEMChainService::getBalance - Unexpected response format for UID: $uid, Asset: $asset - " . substr($response, 0, 100));
            return 0.0;
        } catch (Exception $e) {
            if (class_exists('Logger')) Logger::error("YEMChainService::getBalance - Exception", ['uid' => $uid, 'asset' => $asset, 'error' => $e->getMessage()]);
            else error_log("YEMChainService::getBalance - Exception for UID: $uid, Asset: $asset - " . $e->getMessage());
            return 0.0;
        }
    }

    public function createVoucher(array $params): array
    {
        try {
            // Validate required parameters
            $required = ['accountFrom', 'accountTo', 'asset', 'txnAmount', 'valueUSD', 'currencyCodeFrom', 'currencyCodeTo', 'reason'];
            foreach ($required as $key) {
                if (!isset($params[$key])) {
                    if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Missing required parameter", ['key' => $key]);
                    else error_log("YEMChainService::createVoucher - Missing required parameter: $key");
                    return ['status' => 'error', 'message' => "Missing required parameter: $key"];
                }
            }
            
            // params: accountFrom, accountTo, asset, txnAmount, valueUSD, currencyCodeFrom, currencyCodeTo, reason
            $fn = ($params['network'] ?? 'testnet') === 'public' ? 'create_trans_voucher' : 'create_trans_voucher2';
            $url = $this->base . '/' . $fn . '.php';
            $post = [
                'apikey' => $this->apiKey,
                'accountFrom' => $params['accountFrom'],
                'accountTo' => $params['accountTo'],
                'asset' => $params['asset'],
                'txnAmount' => $params['txnAmount'],
                'valueUSD' => $params['valueUSD'],
                'currencyCodeFrom' => $params['currencyCodeFrom'],
                'currencyCodeTo' => $params['currencyCodeTo'],
                'reason' => $params['reason'],
            ];
            
            $ch = curl_init($url);
            if ($ch === false) {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Failed to initialize cURL");
                else error_log("YEMChainService::createVoucher - Failed to initialize cURL for voucher creation");
                return ['status' => 'error', 'message' => 'Failed to initialize request'];
            }
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - cURL error", ['error' => $curlError]);
                else error_log("YEMChainService::createVoucher - cURL error: $curlError");
                return ['status' => 'error', 'message' => 'Network error: ' . $curlError];
            }

            if (!$resp || $httpCode !== 200) {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - HTTP error", ['http_code' => $httpCode, 'response_preview' => substr($resp, 0, 200)]);
                else error_log("YEMChainService::createVoucher - HTTP error $httpCode, Response: " . substr($resp, 0, 200));
                return ['status' => 'error', 'message' => "API request failed with HTTP $httpCode"];
            }

            // Trim whitespace from response
            $resp = trim((string)$resp);
            
            // Log raw response for debugging
            if (class_exists('Logger')) Logger::debug("YEMChainService::createVoucher - Response", ['response_preview' => substr($resp, 0, 500)]);
            else error_log("YEMChainService::createVoucher - Raw response (length: " . strlen($resp) . "): " . substr($resp, 0, 500));
            
            // Check if response is empty
            if (empty($resp)) {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Empty response received");
                else error_log("YEMChainService::createVoucher - Empty response received");
                return ['status' => 'error', 'message' => 'Empty response from API'];
            }
            
            // API sometimes returns JSON, sometimes colon-delimited
            $json = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                // Validate JSON response
                if (isset($json['status']) && $json['status'] === 'success') {
                    return $json;
                }
                // If it's JSON but not in expected format, log and return error
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Unexpected JSON response", ['json' => $json]);
                else error_log("YEMChainService::createVoucher - Unexpected JSON response: " . json_encode($json));
                return ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
            }
            
            // Try colon-delimited format (e.g., "success:txn_hash_here")
            $parts = explode(':', $resp, 2);
            $firstPart = trim(strtolower($parts[0] ?? ''));
            if ($firstPart === 'success' || $firstPart === 'ok') {
                $txnId = trim($parts[1] ?? '');
                if (!empty($txnId)) {
                    return ['status' => 'success', 'txnID' => $txnId];
                }
            }
            
            // Try other common formats
            // Check for simple "success" or "ok" text
            $lowerResp = strtolower($resp);
            if ($lowerResp === 'success' || $lowerResp === 'ok' || $lowerResp === '1' || $lowerResp === 'true') {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Success but no txnID");
                else error_log("YEMChainService::createVoucher - Received success but no transaction ID");
                return ['status' => 'error', 'message' => 'API returned success but no transaction ID'];
            }
            
            // Check for error messages in response
            if (stripos($resp, 'error') !== false || stripos($resp, 'fail') !== false || stripos($resp, 'invalid') !== false) {
                if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - API returned error", ['response_preview' => substr($resp, 0, 200)]);
                else error_log("YEMChainService::createVoucher - Error in response: " . substr($resp, 0, 200));
                return ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
            }
            
            // Unknown format - log full response for debugging
            if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Unexpected response format", ['response_full' => $resp]);
            else error_log("YEMChainService::createVoucher - Unexpected response format. Full response: " . $resp);
            
            return ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
        } catch (Exception $e) {
            if (class_exists('Logger')) Logger::error("YEMChainService::createVoucher - Exception", ['error' => $e->getMessage()]);
            else error_log("YEMChainService::createVoucher - Exception: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
        }
    }
}
