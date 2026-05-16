<?php

/**
 * Ethereum Service
 * Handles interaction with Ethereum network via JSON-RPC
 * Very similar to BinanceService but for Ethereum mainnet/testnets
 */
class EthereumService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Make JSON-RPC call to Ethereum network
     */
    private function rpcCall(string $method, array $params = []): ?array
    {
        try {
            $url = $this->config['rpc_url'] ?? '';
            if (empty($url)) {
                error_log("EthereumService::rpcCall - RPC URL not configured");
                return null;
            }
            
            $payload = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1
            ];
            
            $ch = curl_init($url);
            if ($ch === false) {
                error_log("EthereumService::rpcCall - Failed to initialize cURL");
                return null;
            }
            
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("EthereumService::rpcCall - cURL error: $curlError");
                return null;
            }
            
            if ($httpCode !== 200 || !$response) {
                error_log("EthereumService::rpcCall - HTTP error: $httpCode, Response: " . substr($response, 0, 200));
                return null;
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("EthereumService::rpcCall - JSON decode error: " . json_last_error_msg());
                return null;
            }
            
            // Check for RPC error in response
            if (isset($result['error'])) {
                error_log("EthereumService::rpcCall - RPC error: " . json_encode($result['error']));
                return null;
            }
            
            return $result['result'] ?? null;
        } catch (Exception $e) {
            error_log("EthereumService::rpcCall - Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert hex to decimal number
     */
    private function hexToDecimal(string $hex): float
    {
        return (float)hexdec($hex);
    }

    /**
     * Convert decimal to hex (for amounts)
     */
    private function decimalToHex(float $amount, int $decimals = 18): string
    {
        $multiplier = pow(10, $decimals);
        $hexValue = dechex((int)($amount * $multiplier));
        return '0x' . str_pad($hexValue, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Fetch transaction details from Ethereum (same structure as BinanceService)
     */
    public function fetchTransactionDetails(string $txnHash): ?array
    {
        try {
            if (empty($txnHash) || !$this->isValidTransactionHash($txnHash)) {
                error_log("EthereumService::fetchTransactionDetails - Invalid transaction hash: " . substr($txnHash, 0, 20));
                return null;
            }
            
            $txn = $this->rpcCall('eth_getTransactionByHash', [$txnHash]);
            if (!$txn) {
                error_log("EthereumService::fetchTransactionDetails - Transaction not found: " . substr($txnHash, 0, 20));
                return null;
            }
            
            $receipt = $this->rpcCall('eth_getTransactionReceipt', [$txnHash]);
            if (!$receipt) {
                error_log("EthereumService::fetchTransactionDetails - Receipt not found: " . substr($txnHash, 0, 20));
                return null;
            }
            
            $status = $receipt['status'] ?? '0x0';
            $successful = $status === '0x1';
            
            return [
                'successful' => $successful,
                'blockNumber' => $txn['blockNumber'] ?? null,
                'from' => $txn['from'] ?? '',
                'to' => $txn['to'] ?? '',
                'value' => $txn['value'] ?? '0x0',
                'hash' => $txn['hash'] ?? $txnHash,
                'gasUsed' => $receipt['gasUsed'] ?? '0x0',
                'logs' => $receipt['logs'] ?? [],
            ];
        } catch (Exception $e) {
            error_log("EthereumService::fetchTransactionDetails - Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify transaction is a valid DBV token deposit
     * Checks ERC-20 Transfer event in transaction logs
     */
    public function verifyDeposit(string $txnHash, string $vaultAddress): ?array
    {
        try {
            if (empty($vaultAddress) || !$this->isValidAddress($vaultAddress)) {
                error_log("EthereumService::verifyDeposit - Invalid vault address");
                return null;
            }
            
            $txnDetails = $this->fetchTransactionDetails($txnHash);
            if (!$txnDetails) {
                error_log("EthereumService::verifyDeposit - Failed to fetch transaction details for: " . substr($txnHash, 0, 20));
                return null;
            }
            
            if (!$txnDetails['successful']) {
                error_log("EthereumService::verifyDeposit - Transaction not successful: " . substr($txnHash, 0, 20));
                return null;
            }
            
            $vaultAddressLower = strtolower($vaultAddress);
            $tokenContract = strtolower($this->config['token_contract'] ?? '');
            
            if (empty($tokenContract)) {
                error_log("EthereumService::verifyDeposit - Token contract not configured");
                return null;
            }
            
            // Check transaction logs for ERC-20 Transfer event
            // Transfer(address indexed from, address indexed to, uint256 value)
            // Event signature: 0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef
            $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
            
            $logs = $txnDetails['logs'] ?? [];
            if (empty($logs)) {
                error_log("EthereumService::verifyDeposit - No logs found in transaction: " . substr($txnHash, 0, 20));
                return null;
            }
            
            foreach ($logs as $log) {
                try {
                    $logAddress = strtolower($log['address'] ?? '');
                    
                    // Check if this log is from our token contract
                    if ($logAddress !== $tokenContract) {
                        continue;
                    }
                    
                    // Check if this is a Transfer event
                    $topics = $log['topics'] ?? [];
                    if (count($topics) < 3 || ($topics[0] ?? '') !== $transferEventSignature) {
                        continue;
                    }
                    
                    // Extract to address (topic[2]) and value (data)
                    $toAddress = '0x' . substr($topics[2] ?? '', 26); // Remove padding
                    $toAddressLower = strtolower($toAddress);
                    
                    // Check if transfer is to vault address
                    if ($toAddressLower !== $vaultAddressLower) {
                        continue;
                    }
                    
                    // Extract amount from data (hex)
                    $amountHex = $log['data'] ?? '0x0';
                    $amount = $this->hexToDecimal($amountHex) / pow(10, 18); // ERC-20 usually 18 decimals
                    
                    if ($amount <= 0) {
                        error_log("EthereumService::verifyDeposit - Invalid amount: $amount");
                        continue;
                    }
                    
                    // Extract from address (topic[1])
                    $fromAddress = '0x' . substr($topics[1] ?? '', 26);
                    
                    return [
                        'amount' => $amount,
                        'from' => $fromAddress,
                        'to' => $toAddress,
                        'token' => $tokenContract,
                    ];
                } catch (Exception $e) {
                    error_log("EthereumService::verifyDeposit - Error processing log: " . $e->getMessage());
                    continue;
                }
            }
            
            error_log("EthereumService::verifyDeposit - No valid transfer found to vault address");
            return null;
        } catch (Exception $e) {
            error_log("EthereumService::verifyDeposit - Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify withdrawal tx: ERC-20 Transfer FROM vault TO recipient with matching amount.
     * Used when admin marks a manual withdrawal complete. (Same structure as BinanceService)
     */
    public function verifyWithdrawal(string $txnHash, string $recipientAddress, float $expectedAmount, bool $allowAnySender = false): ?array
    {
        $txnDetails = $this->fetchTransactionDetails($txnHash);
        if (!$txnDetails) {
            return null;
        }
        if (!$txnDetails['successful']) {
            return null;
        }

        $vaultAddress = strtolower(trim($this->config['vault_address'] ?? ''));
        $recipientLower = strtolower(trim($recipientAddress));
        $tokenContract = strtolower($this->config['token_contract'] ?? '');

        if (empty($tokenContract) || !$this->isValidAddress($recipientAddress)) {
            return null;
        }
        if (!$allowAnySender && empty($vaultAddress)) {
            return null;
        }

        $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        $logs = $txnDetails['logs'] ?? [];

        foreach ($logs as $log) {
            if (strtolower($log['address'] ?? '') !== $tokenContract) {
                continue;
            }
            $topics = $log['topics'] ?? [];
            if (count($topics) < 3 || ($topics[0] ?? '') !== $transferEventSignature) {
                continue;
            }

            $fromAddress = '0x' . substr($topics[1] ?? '', 26);
            $toAddress = '0x' . substr($topics[2] ?? '', 26);
            $fromLower = strtolower($fromAddress);
            $toLower = strtolower($toAddress);

            if ($toLower !== $recipientLower) {
                continue;
            }
            if (!$allowAnySender && $fromLower !== $vaultAddress) {
                continue;
            }

            $amountHex = $log['data'] ?? '0x0';
            $amount = $this->hexToDecimal($amountHex) / pow(10, 18);
            if (abs($amount - $expectedAmount) < 0.01) {
                return ['amount' => $amount, 'from' => $fromAddress, 'to' => $toAddress];
            }
        }
        return null;
    }

    /**
     * Check if address is valid Ethereum address format
     */
    public function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/i', $address) === 1;
    }

    /**
     * Check if transaction hash is valid
     */
    public function isValidTransactionHash(string $hash): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{64}$/i', $hash) === 1;
    }

    /**
     * Get token balance for an address
     */
    public function getTokenBalance(string $address, string $tokenContract): float
    {
        // ERC-20 balanceOf(address) function signature: 0x70a08231
        $functionSignature = '0x70a08231';
        $addressParam = '0x' . str_pad(substr(strtolower($address), 2), 64, '0', STR_PAD_LEFT);
        $data = $functionSignature . $addressParam;
        
        $result = $this->rpcCall('eth_call', [
            [
                'to' => $tokenContract,
                'data' => $data
            ],
            'latest'
        ]);
        
        if (!$result || $result === '0x') {
            return 0.0;
        }
        
        $balance = $this->hexToDecimal($result) / pow(10, 18);
        return $balance;
    }

    /**
     * Get ETH balance for an address
     */
    public function getBalance(string $address): float
    {
        $result = $this->rpcCall('eth_getBalance', [$address, 'latest']);
        if (!$result || $result === '0x') {
            return 0.0;
        }
        
        $balance = $this->hexToDecimal($result) / pow(10, 18);
        return $balance;
    }

    /**
     * Get current gas price
     */
    public function getGasPrice(): string
    {
        $result = $this->rpcCall('eth_gasPrice');
        return $result ?? $this->config['gas_price'];
    }

    /**
     * Prepare token transfer data
     * Note: This requires signing, typically done in Node.js worker
     */
    public function prepareTokenTransfer(string $toAddress, float $amount): array
    {
        $tokenContract = $this->config['token_contract'] ?? '';
        if (empty($tokenContract)) {
            return ['error' => 'Token contract not configured'];
        }
        
        // ERC-20 transfer(address to, uint256 amount) function signature: 0xa9059cbb
        $functionSignature = '0xa9059cbb';
        $toParam = '0x' . str_pad(substr(strtolower($toAddress), 2), 64, '0', STR_PAD_LEFT);
        $amountParam = $this->decimalToHex($amount, 18);
        $data = $functionSignature . $toParam . substr($amountParam, 2);
        
        $gasPrice = $this->getGasPrice();
        
        return [
            'to' => $tokenContract,
            'data' => $data,
            'gas' => '0x' . dechex($this->config['gas_limit']),
            'gasPrice' => $gasPrice,
            'value' => '0x0', // ERC-20 transfers have value 0
        ];
    }
}

