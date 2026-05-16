import { apiEndpoints } from './node_config.js';
import { generateSignature } from './secure_worker_client.js';
import axios from 'axios';
import { Web3 } from 'web3';
import https from 'https';

(async () => {
    const CacheableLookup = (await import('cacheable-lookup')).default;
    const cacheable = new CacheableLookup();
    cacheable.install(https.globalAgent);
})();

/**
 * Load Binance configuration from environment
 */
function getBinanceConfig() {
    const config = {
        rpcUrl: process.env.BSC_RPC_URL || 'https://bsc-dataseed.binance.org/',
        vaultAddress: process.env.BSC_VAULT_ADDRESS || '',
        vaultPrivateKey: process.env.BSC_VAULT_PRIVATE_KEY || '',
        tokenContract: process.env.BSC_TOKEN_CONTRACT || '',
        chainId: parseInt(process.env.BSC_CHAIN_ID || '56'),
        gasPrice: process.env.BSC_GAS_PRICE || '5000000000',
        gasLimit: process.env.BSC_GAS_LIMIT || '100000',
    };
    
    if (!config.vaultAddress || !config.vaultPrivateKey || !config.tokenContract) {
        console.error('❌ Binance configuration missing! Set BSC_VAULT_ADDRESS, BSC_VAULT_PRIVATE_KEY, and BSC_TOKEN_CONTRACT environment variables.');
        process.exit(1);
    }
    
    return config;
}

/**
 * Get pending Binance withdrawals
 */
async function getWithdrawals() {
    try {
        // Include stuck withdrawals (status=8) older than 10 minutes
        const url = apiEndpoints.get_binance_withdrawals + '?status=0&include_stuck=1';
        console.log(new Date().toISOString(), '🔍 Fetching Binance withdrawals:', url);
        const ts = Math.floor(Date.now() / 1000).toString();
        const uri = new URL(url).pathname + new URL(url).search;
        const res = await axios.get(url, {
            headers: { 'X-Worker-Timestamp': ts, 'X-Worker-Signature': generateSignature(uri, ts) },
            timeout: 20000,
        });
        
        const withdrawals = Array.isArray(res.data) ? res.data : [];
        
        // Filter to include status=0 (pending), status=1 (processing), and status=8 (stuck)
        const valid = withdrawals.filter(w => w && w.id && w.address && w.amount && (w.status === 0 || w.status === 1 || w.status === 8));
        
        // Remove duplicates
        const uniqueWithdrawals = Array.from(
            new Map(valid.map(w => [w.id, w])).values()
        );
        
        const stuckCount = valid.filter(w => w.status === 8).length;
        if (stuckCount > 0) {
            console.log(new Date().toISOString(), `⚠️  Found ${stuckCount} stuck Binance withdrawal(s) (status=8), will reprocess`);
        }
        
        console.log(new Date().toISOString(), `📊 Found ${uniqueWithdrawals.length} Binance withdrawal(s) to process`);
        
        return uniqueWithdrawals;
    } catch (e) {
        console.error(new Date().toISOString(), '❌ Error fetching withdrawals:', e.message);
        return [];
    }
}

/**
 * Update withdrawal status
 * @param {number} id - Withdrawal ID
 * @param {number} status - 2=failed, 3=completed, 8=pre-complete
 * @param {string} [hash=''] - Transaction hash when status=3
 * @param {string} [errorMessage=''] - Failure reason when status=2
 * @param {boolean} [hashOnly=false] - Only persist hash, don't change status (prevents double-send)
 */
async function updateStatus(id, status, hash = '', errorMessage = '', hashOnly = false) {
    try {
        const url = apiEndpoints.update_binance_withdrawals;
        const params = new URLSearchParams({ id, status });
        if (hash) params.append('hash', hash);
        if (status === 2 && errorMessage) params.append('error', errorMessage);
        if (hashOnly) params.append('hash_only', '1');
        
        const fullUrl = url + '?' + params.toString();
        const ts = Math.floor(Date.now() / 1000).toString();
        const uri = new URL(fullUrl).pathname + new URL(fullUrl).search;
        const res = await axios.post(fullUrl, {}, {
            headers: { 'X-Worker-Timestamp': ts, 'X-Worker-Signature': generateSignature(uri, ts) },
            timeout: 10000,
        });
        
        // Debug: log the response
        console.log(new Date().toISOString(), `📝 Update status response:`, {
            statusCode: res.status,
            data: res.data,
            success: res.data?.success,
            rows: res.data?.rows
        });
        
        // Check if update was successful (rows > 0 or success === true)
        return res.data?.success === true || (res.data?.rows ?? 0) > 0;
    } catch (e) {
        console.error(new Date().toISOString(), '❌ Error updating status:', {
            message: e.message,
            response: e.response?.data,
            status: e.response?.status
        });
        return false;
    }
}

/**
 * Send ERC-20 tokens on Binance Smart Chain
 */
async function sendTokens(toAddress, amount, config) {
    try {
        // Normalize Ethereum/BSC address: convert to lowercase, ensure '0x' prefix (not '0X')
        const normalizedAddress = toAddress.toLowerCase().replace(/^0x/i, '0x');
        
        console.log(new Date().toISOString(), `🚀 Sending ${amount} DBV to ${normalizedAddress.substring(0, 10)}...`);
        console.log(new Date().toISOString(), `   Original address: ${toAddress}, Normalized: ${normalizedAddress}`);
        
        // Validate address format
        if (!Web3.utils.isAddress(normalizedAddress)) {
            throw new Error(`Invalid BSC address format: ${toAddress} (normalized: ${normalizedAddress})`);
        }
        
        const web3 = new Web3(config.rpcUrl);
        
        // Add vault account
        const account = web3.eth.accounts.privateKeyToAccount('0x' + config.vaultPrivateKey.replace('0x', ''));
        web3.eth.accounts.wallet.add(account);
        
        // ERC-20 transfer function ABI
        const transferAbi = [{
            constant: false,
            inputs: [
                { name: '_to', type: 'address' },
                { name: '_value', type: 'uint256' }
            ],
            name: 'transfer',
            outputs: [{ name: '', type: 'bool' }],
            type: 'function'
        }];
        
        const tokenContract = new web3.eth.Contract(transferAbi, config.tokenContract);
        
        // Convert amount to wei (assuming 18 decimals)
        const decimals = 18;
        const amountInWei = web3.utils.toWei(amount.toString(), 'ether');
        
        // Estimate gas (use normalized address)
        const gasEstimate = await tokenContract.methods.transfer(normalizedAddress, amountInWei).estimateGas({
            from: config.vaultAddress
        });
        
        // Send transaction (use normalized address)
        const tx = tokenContract.methods.transfer(normalizedAddress, amountInWei);
        const gasPrice = await web3.eth.getGasPrice();
        
        const receipt = await tx.send({
            from: config.vaultAddress,
            gas: gasEstimate,
            gasPrice: gasPrice,
        });
        
        console.log(new Date().toISOString(), `✅ Transaction sent! Hash: ${receipt.transactionHash}`);
        return receipt.transactionHash;
        
    } catch (error) {
        console.error(new Date().toISOString(), '❌ Error sending tokens:', error.message);
        return null;
    }
}

/**
 * Process Binance withdrawals
 */
async function processWithdrawals() {
    const config = getBinanceConfig();
    const withdrawals = await getWithdrawals();
    
    if (withdrawals.length === 0) {
        return 0;
    }
    
    let processed = 0;
    let failed = 0;
    
    for (const withdrawal of withdrawals) {
        let statusUpdated = false;
        let txHash = null;
        try {
            console.log(new Date().toISOString(), 
                `📦 Processing withdrawal ID ${withdrawal.id}, amount: ${withdrawal.amount}, address: ${withdrawal.address.substring(0, 10)}..., current_status: ${withdrawal.status}`);
            
            // If status is 1 (processing) or 8 (pre-complete), skip pre-complete step
            if (withdrawal.status === 0) {
                // Mark as pre-complete
                const preUpdate = await updateStatus(withdrawal.id, 8, '');
                if (!preUpdate) {
                    console.log(new Date().toISOString(), '⚠️  Failed to update to pre-complete, skipping');
                    failed++;
                    continue;
                }
                console.log(new Date().toISOString(), 'Pre-complete OK');
            } else {
                console.log(new Date().toISOString(), '⚠️  Reprocessing in-progress/stuck Binance withdrawal (status=', withdrawal.status, ')');
            }
            
            // Idempotency: if we already have a hash (from previous run where status update failed), don't send again
            const existingHash = (withdrawal.txn_hash_bsc || '').trim();
            if (existingHash && existingHash.length >= 64) {
                console.log(new Date().toISOString(), `📋 Found existing hash for ID ${withdrawal.id}, retrying status update only (no resend)`);
                const ok = await updateStatus(withdrawal.id, 3, existingHash);
                if (ok) {
                    processed++;
                    statusUpdated = true;
                }
                if (withdrawals.length > 1) await new Promise(r => setTimeout(r, 1000));
                continue;
            }
            
            // Send tokens
            txHash = await sendTokens(withdrawal.address, withdrawal.amount, config);
            
            if (txHash && txHash.length === 66) { // BSC hash is 66 chars (0x + 64 hex)
                // Update with transaction hash
                const success = await updateStatus(withdrawal.id, 3, txHash);
                if (success) {
                    console.log(new Date().toISOString(), 
                        `✅ Withdrawal ${withdrawal.id} completed! Hash: ${txHash.substring(0, 16)}...`);
                    processed++;
                    statusUpdated = true;
                } else {
                    console.log(new Date().toISOString(), 
                        `⚠️  Tokens sent but status update failed. Hash: ${txHash}`);
                    // Retry status update
                    const retryOk = await updateStatus(withdrawal.id, 3, txHash);
                    if (retryOk) {
                        console.log(new Date().toISOString(), '✅ Status update retry succeeded');
                        processed++;
                        statusUpdated = true;
                } else {
                    // Retry more, then persist hash only to prevent double-send on next run
                    for (let r = 0; r < 3; r++) {
                        await new Promise(r => setTimeout(r, 2000));
                        if (await updateStatus(withdrawal.id, 3, txHash)) {
                            processed++;
                            statusUpdated = true;
                            break;
                        }
                    }
                    if (!statusUpdated && txHash) {
                        const persisted = await updateStatus(withdrawal.id, 3, txHash, '', true);
                        if (persisted) console.log(new Date().toISOString(), '📋 Persisted hash only (status update will retry next run)');
                    }
                    if (!statusUpdated) failed++;
                }
            }
            } else {
                // Mark as failed (sendTokens returns empty/false on failure - no specific error available here)
                console.log(new Date().toISOString(), '❌ Payment failed, updating status to failed (2)');
                const fail = await updateStatus(withdrawal.id, 2, '', 'Payment failed or invalid response');
                if (fail) {
                    console.log(new Date().toISOString(), '❌ Status updated to failed');
                    failed++;
                    statusUpdated = true;
                } else {
                    console.log(new Date().toISOString(), '❌ Status update to failed also failed - retrying...');
                    // Retry status update
                    const retryFail = await updateStatus(withdrawal.id, 2, '', 'Payment failed or invalid response');
                    if (retryFail) {
                        console.log(new Date().toISOString(), '✅ Failed status update retry succeeded');
                        failed++;
                        statusUpdated = true;
                    } else {
                        console.log(new Date().toISOString(), '❌ CRITICAL: Cannot update status to failed!');
                        failed++;
                    }
                }
            }
            
            // Small delay between withdrawals
            if (withdrawals.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
        } catch (error) {
            console.error(new Date().toISOString(), `❌ Error processing withdrawal ${withdrawal.id}:`, error.message);
            console.error(error.stack);
            
            if (!statusUpdated) {
                if (txHash && txHash.length >= 64) {
                    // Crypto was sent - NEVER mark failed; retry status 3 or persist hash only
                    console.log(new Date().toISOString(), '⚠️ Exception after send (hash exists) - retrying status 3, NOT marking failed');
                    for (let r = 0; r < 5; r++) {
                        await new Promise(res => setTimeout(res, 2000));
                        if (await updateStatus(withdrawal.id, 3, txHash)) {
                            statusUpdated = true;
                            break;
                        }
                    }
                    if (!statusUpdated) {
                        await updateStatus(withdrawal.id, 3, txHash, '', true);
                    }
                } else {
                    try {
                        const fail = await updateStatus(withdrawal.id, 2, '', error.message || 'Exception during processing');
                        if (fail) console.log(new Date().toISOString(), '✅ Updated to failed status after exception');
                    } catch (updateError) {
                        console.error(new Date().toISOString(), 'Exception updating status:', updateError.message);
                    }
                }
            }
            failed++;
        }
    }
    
    console.log(new Date().toISOString(), 
        `📊 Summary: ${processed} processed, ${failed} failed`);
    
    return processed;
}

// Run if called directly
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1].endsWith('withdrawal_to_binance.js')) {
    (async () => {
        console.log(new Date().toISOString(), '🚀 Starting Binance withdrawal worker...');
        const processed = await processWithdrawals();
        process.exit(processed > 0 ? 0 : 1);
    })();
}

export { processWithdrawals, getWithdrawals, updateStatus };

