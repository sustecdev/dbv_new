<?php

class StellarService
{
    private string $network;
    private string $assetCode;
    private string $issuer;
    private string $owner;
    private string $vault;
    private ?string $curlCainfo;
    private bool $curlSslVerify;

    public function __construct(array $stellar)
    {
        $this->network = $stellar['network'];
        $this->assetCode = $stellar['asset_code'];
        $this->issuer = $stellar['issuer'];
        $this->owner = $stellar['owner'];
        $this->vault = $stellar['vault'];
        $this->curlCainfo = $stellar['curl_cainfo'] ?? null;
        $this->curlSslVerify = $stellar['curl_ssl_verify'] ?? true;
    }

    public function getHorizonBase(): string
    {
        return $this->network === 'public' ? 'https://horizon.stellar.org' : 'https://horizon-testnet.stellar.org';
    }

    public function isValidTxnHash(string $hash): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9]{64}$/', $hash);
    }

    public function isValidAddress(string $address): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9]{56}$/', $address);
    }

    private function setCurlSslOpts($ch): void
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curlSslVerify);
        if ($this->curlCainfo && $this->curlSslVerify) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->curlCainfo);
        }
    }

    public function fetchTransactionDetails(string $txnHash, int $retries = 3, ?string &$lastError = null): ?array
    {
        $url = rtrim($this->getHorizonBase(), '/') . '/transactions/' . $txnHash;
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $this->setCurlSslOpts($ch);
            $response = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($http === 200 && $response) {
                $json = json_decode($response, true);
                if ($json && !isset($json['status'])) {
                    return $json;
                }
            }
            if ($http !== 404 && $http !== 200) {
                error_log("Stellar fetchTransactionDetails failed: HTTP $http, Error: $error");
                $lastError = $error ?: "HTTP $http";
                return null;
            }
            if ($attempt < $retries) {
                usleep(1500000);
            }
        }
        return null;
    }

    public function fetchTxnOperations(string $txnHash): array
    {
        $url = rtrim($this->getHorizonBase(), '/') . '/transactions/' . $txnHash . '/operations';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $this->setCurlSslOpts($ch);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http !== 200 || !$response) {
            error_log("Failed to fetch transaction operations: HTTP $http, Error: $error");
            return [];
        }
        
        $json = json_decode($response, true);
        if (!$json || !isset($json['_embedded'])) {
            return [];
        }
        
        $operations = $json['_embedded']['records'] ?? [];
        
        // For claimable balance operations, fetch the balance details to get asset info
        foreach ($operations as &$op) {
            if (($op['type'] ?? $op['type_i'] ?? '') === 'create_claimable_balance' || 
                (isset($op['type_i']) && $op['type_i'] == 14)) {
                // Try to get claimable balance ID from multiple possible locations
                $balanceId = $op['balance_id'] ?? $op['id'] ?? null;
                
                // If not in operation, try to extract from links
                if (!$balanceId && isset($op['_links']['claimable_balance']['href'])) {
                    $href = $op['_links']['claimable_balance']['href'];
                    // Extract ID from href like: /claimable_balances/00000000...
                    if (preg_match('#/claimable_balances/([^/]+)#', $href, $matches)) {
                        $balanceId = $matches[1];
                    }
                }
                
                if ($balanceId) {
                    // Fetch claimable balance details
                    $cbDetails = $this->fetchClaimableBalance($balanceId);
                    if ($cbDetails && isset($cbDetails['asset'])) {
                        $asset = $cbDetails['asset'];
                        if (is_array($asset)) {
                            $op['asset_code'] = $op['asset_code'] ?? ($asset['code'] ?? null);
                            $op['asset_issuer'] = $op['asset_issuer'] ?? ($asset['issuer'] ?? null);
                            $op['asset_type'] = $op['asset_type'] ?? ($asset['asset_type'] ?? null);
                        }
                    }
                }
            }
        }
        
        return $operations;
    }
    
    public function fetchClaimableBalance(string $balanceId): ?array
    {
        $url = rtrim($this->getHorizonBase(), '/') . '/claimable_balances/' . $balanceId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $this->setCurlSslOpts($ch);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http !== 200 || !$response) {
            return null;
        }
        
        return json_decode($response, true);
    }

    public function findInboundPayment(array $records): ?array
    {
        foreach ($records as $record) {
            $opType = $record['type'] ?? $record['type_i'] ?? '';
            $opTypeInt = is_numeric($opType) ? (int)$opType : null;
            
            // Handle payment operations (type 1)
            if ($opType === 'payment' || $opTypeInt === 1 || $opType === '1') {
                // Handle native XLM - skip it
                $assetType = $record['asset_type'] ?? null;
                if ($assetType === 'native') {
                    continue;
                }
                
                // Get asset information - check both flat and nested structures
                $assetCode = $record['asset_code'] ?? null;
                $assetIssuer = $record['asset_issuer'] ?? null;
                
                // Check if asset info is nested (sometimes in 'asset' object)
                if (!$assetCode && isset($record['asset'])) {
                    $assetObj = $record['asset'];
                    if (is_array($assetObj)) {
                        $assetCode = $assetObj['code'] ?? $assetObj['asset_code'] ?? null;
                        $assetIssuer = $assetObj['issuer'] ?? $assetObj['asset_issuer'] ?? null;
                        $assetType = $assetType ?? ($assetObj['type'] ?? $assetObj['asset_type'] ?? null);
                    }
                }
                
                // Get destination/to address (check multiple field names)
                $to = $record['to'] ?? $record['destination'] ?? null;
                
                // Handle nested account objects (sometimes addresses are nested)
                if (is_array($to)) {
                    $to = $to['account_id'] ?? $to['id'] ?? null;
                }
                
                // Get source/from address (check multiple field names)  
                $from = $record['from'] ?? $record['source_account'] ?? null;
                
                // Handle nested account objects
                if (is_array($from)) {
                    $from = $from['account_id'] ?? $from['id'] ?? null;
                }
                
                // Skip if missing critical fields
                if (!$assetCode || !$assetIssuer || !$to || !$from) {
                    continue;
                }
                
                // Trim whitespace from addresses (just in case)
                $to = trim($to);
                $from = trim($from);
                
                // Check if payment is sent to vault OR owner address with correct asset
                $isVault = trim($to) === trim($this->vault);
                $isOwner = trim($to) === trim($this->owner);
                
                if (strtoupper($assetCode) === strtoupper($this->assetCode) &&
                    trim($assetIssuer) === trim($this->issuer) &&
                    ($isVault || $isOwner) &&
                    trim($from) !== trim($this->vault) &&
                    trim($from) !== trim($this->owner)) {
                    return $record;
                }
            }
            // Handle create_claimable_balance operations (type 14)
            // This allows deposits via claimable balances
            elseif ($opType === 'create_claimable_balance' || $opTypeInt === 14 || $opType === '14') {
                // Get asset information - check multiple possible locations
                $assetCode = $record['asset_code'] ?? null;
                $assetIssuer = $record['asset_issuer'] ?? null;
                $assetType = $record['asset_type'] ?? null;
                
                // Check if asset info is in nested 'asset' object
                if ((!$assetCode || !$assetIssuer) && isset($record['asset'])) {
                    $assetObj = $record['asset'];
                    if (is_array($assetObj)) {
                        $assetCode = $assetCode ?? ($assetObj['code'] ?? $assetObj['asset_code'] ?? null);
                        $assetIssuer = $assetIssuer ?? ($assetObj['issuer'] ?? $assetObj['asset_issuer'] ?? null);
                        $assetType = $assetType ?? ($assetObj['type'] ?? $assetObj['asset_type'] ?? null);
                    }
                }
                
                // Check if asset info is in 'claimable_balance_asset' field (sometimes used)
                if ((!$assetCode || !$assetIssuer) && isset($record['claimable_balance_asset'])) {
                    $cbAsset = $record['claimable_balance_asset'];
                    if (is_array($cbAsset)) {
                        $assetCode = $assetCode ?? ($cbAsset['code'] ?? $cbAsset['asset_code'] ?? null);
                        $assetIssuer = $assetIssuer ?? ($cbAsset['issuer'] ?? $cbAsset['asset_issuer'] ?? null);
                        $assetType = $assetType ?? ($cbAsset['type'] ?? $cbAsset['asset_type'] ?? null);
                    }
                }
                
                // Skip native XLM
                if ($assetType === 'native') {
                    continue;
                }
                
                // If we still don't have asset info, we need to fetch it from the claimable balance ID
                // But for now, let's check if we can proceed with what we have
                if (!$assetCode || !$assetIssuer) {
                    // Log for debugging - might need to fetch claimable balance details
                    error_log("Claimable balance operation missing asset info. Record: " . json_encode($record));
                    continue;
                }
                
                // Check if vault OR owner is in the claimants list
                $claimants = $record['claimants'] ?? [];
                $vaultCanClaim = false;
                $ownerCanClaim = false;
                
                if (is_array($claimants)) {
                    foreach ($claimants as $claimant) {
                        // Handle both object format and string format
                        if (is_array($claimant)) {
                            $claimantAccount = $claimant['destination'] ?? $claimant['account_id'] ?? null;
                        } else {
                            $claimantAccount = $claimant;
                        }
                        
                        if ($claimantAccount) {
                            if (trim($claimantAccount) === trim($this->vault)) {
                                $vaultCanClaim = true;
                                break;
                            }
                            if (trim($claimantAccount) === trim($this->owner)) {
                                $ownerCanClaim = true;
                                break;
                            }
                        }
                    }
                }
                
                // Get source account
                $from = $record['from'] ?? $record['source_account'] ?? null;
                if (is_array($from)) {
                    $from = $from['account_id'] ?? $from['id'] ?? null;
                }
                
                // Validate asset and that vault OR owner can claim
                if ($assetCode && $assetIssuer && ($vaultCanClaim || $ownerCanClaim) && 
                    strtoupper($assetCode) === strtoupper($this->assetCode) &&
                    trim($assetIssuer) === trim($this->issuer) &&
                    trim($from) !== trim($this->vault) &&
                    trim($from) !== trim($this->owner)) {
                    // Mark that this needs to be claimed first
                    $record['_needs_claim'] = true;
                    return $record;
                }
            }
        }
        return null;
    }

    /**
     * Find outbound payment (vault -> recipient) for withdrawal verification.
     * Used when admin marks a manual withdrawal complete - verifies tx sent to correct address with correct amount.
     * @param bool $requireVaultMatch When false, only checks destination+amount+asset (for manual admin complete when vault may differ from config)
     */
    public function findOutboundPayment(array $records, string $expectedTo, float $expectedAmount, bool $requireVaultMatch = true): ?array
    {
        $expectedToNorm = strtoupper(trim($expectedTo));
        $vaultNorm = strtoupper(trim($this->vault));

        foreach ($records as $record) {
            $opType = $record['type'] ?? $record['type_i'] ?? '';
            $opTypeInt = is_numeric($opType) ? (int)$opType : null;

            if ($opType !== 'payment' && $opTypeInt !== 1 && $opType !== '1') {
                continue;
            }

            $assetType = $record['asset_type'] ?? null;
            if ($assetType === 'native') {
                continue;
            }

            $assetCode = $record['asset_code'] ?? null;
            $assetIssuer = $record['asset_issuer'] ?? null;
            if (isset($record['asset']) && is_array($record['asset'])) {
                $a = $record['asset'];
                $assetCode = $assetCode ?? ($a['code'] ?? $a['asset_code'] ?? null);
                $assetIssuer = $assetIssuer ?? ($a['issuer'] ?? $a['asset_issuer'] ?? null);
            }

            $to = $record['to'] ?? $record['destination'] ?? null;
            $from = $record['from'] ?? $record['source_account'] ?? null;
            if (is_array($to)) {
                $to = $to['account_id'] ?? $to['id'] ?? null;
            }
            if (is_array($from)) {
                $from = $from['account_id'] ?? $from['id'] ?? null;
            }
            // Horizon may return account IDs as URLs; extract the G... address
            if (is_string($to) && preg_match('#/accounts/([A-Za-z0-9]{56})$#', $to, $m)) {
                $to = $m[1];
            }
            if (is_string($from) && preg_match('#/accounts/([A-Za-z0-9]{56})$#', $from, $m)) {
                $from = $m[1];
            }

            if (!$assetCode || !$assetIssuer || !$to || !$from) {
                continue;
            }

            $to = strtoupper(trim($to));
            $from = strtoupper(trim($from));

            // Payment with correct DBV asset to expected recipient
            if (strtoupper($assetCode) !== strtoupper($this->assetCode) || trim($assetIssuer) !== trim($this->issuer)) {
                continue;
            }
            if ($to !== $expectedToNorm) {
                continue;
            }
            if ($requireVaultMatch && $from !== $vaultNorm) {
                continue;
            }

            // Amount match (Stellar amounts are strings like "123.45"); 0.01 tolerance for float precision
            $amountStr = $record['amount'] ?? '';
            $amount = is_numeric($amountStr) ? (float)$amountStr : 0;
            if (abs($amount - $expectedAmount) < 0.01) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Return debug info when findOutboundPayment fails - lists payment ops vs expected values.
     */
    public function getVerificationDebugInfo(array $records, string $expectedTo, float $expectedAmount): array
    {
        $expectedToNorm = strtoupper(trim($expectedTo));
        $vaultNorm = strtoupper(trim($this->vault));
        $payments = [];
        foreach ($records as $i => $record) {
            $opType = $record['type'] ?? $record['type_i'] ?? '';
            $opTypeInt = is_numeric($opType) ? (int)$opType : null;
            if ($opType !== 'payment' && $opTypeInt !== 1 && $opType !== '1') {
                continue;
            }
            $assetType = $record['asset_type'] ?? null;
            if ($assetType === 'native') {
                continue;
            }
            $assetCode = $record['asset_code'] ?? null;
            $assetIssuer = $record['asset_issuer'] ?? null;
            if (isset($record['asset']) && is_array($record['asset'])) {
                $a = $record['asset'];
                $assetCode = $assetCode ?? ($a['code'] ?? $a['asset_code'] ?? null);
                $assetIssuer = $assetIssuer ?? ($a['issuer'] ?? $a['asset_issuer'] ?? null);
            }
            $to = $record['to'] ?? $record['destination'] ?? null;
            $from = $record['from'] ?? $record['source_account'] ?? null;
            if (is_array($to)) {
                $to = $to['account_id'] ?? $to['id'] ?? null;
            }
            if (is_array($from)) {
                $from = $from['account_id'] ?? $from['id'] ?? null;
            }
            if (is_string($to) && preg_match('#/accounts/([A-Za-z0-9]{56})$#', $to, $m)) {
                $to = $m[1];
            }
            if (is_string($from) && preg_match('#/accounts/([A-Za-z0-9]{56})$#', $from, $m)) {
                $from = $m[1];
            }
            $amountStr = $record['amount'] ?? '';
            $amount = is_numeric($amountStr) ? (float)$amountStr : 0;
            $payments[] = [
                'from' => $from ? substr($from, 0, 8) . '...' . substr($from, -4) : null,
                'to' => $to ? substr($to, 0, 8) . '...' . substr($to, -4) : null,
                'amount' => $amount,
                'asset' => ($assetCode ?? '') . ':' . substr($assetIssuer ?? '', 0, 8) . '...',
                'from_match' => $from && strtoupper(trim($from)) === $vaultNorm,
                'to_match' => $to && strtoupper(trim($to)) === $expectedToNorm,
                'amount_match' => abs($amount - $expectedAmount) < 0.01,
            ];
        }
        return [
            'expected' => [
                'vault' => $vaultNorm ? substr($vaultNorm, 0, 8) . '...' . substr($vaultNorm, -4) : '(empty)',
                'to' => $expectedToNorm ? substr($expectedToNorm, 0, 8) . '...' . substr($expectedToNorm, -4) : '(empty)',
                'amount' => $expectedAmount,
            ],
            'config_asset' => $this->assetCode . ':' . substr($this->issuer, 0, 8) . '...',
            'payment_ops' => $payments,
        ];
    }

    public function hasTrustline(string $address): bool
    {
        $url = rtrim($this->getHorizonBase(), '/') . '/accounts/' . $address;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $this->setCurlSslOpts($ch);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http !== 200 || !$response) {
            return false;
        }
        
        $json = json_decode($response, true);
        if (!isset($json['balances'])) {
            return false;
        }
        
        foreach ($json['balances'] as $balance) {
            if ($balance['asset_type'] !== 'native' &&
                isset($balance['asset_code'], $balance['asset_issuer']) &&
                $balance['asset_code'] === $this->assetCode &&
                $balance['asset_issuer'] === $this->issuer) {
                return true;
            }
        }
        
        return false;
    }
}
