import { apiEndpoints } from './node_config.js';
import { generateSignature } from './secure_worker_client.js';
import axios from 'axios';
import { ethers } from 'ethers';
import https from 'https';

(async () => {
    const CacheableLookup = (await import('cacheable-lookup')).default;
    const cacheable = new CacheableLookup();
    cacheable.install(https.globalAgent);
})();

/**
 * Load Ethereum configuration from environment
 */
function getEthereumConfig() {
    // Primary RPC URL
    const primaryRpc = process.env.ETH_RPC_URL || 'https://cloudflare-eth.com';

    // Fallback RPC URLs - only the most reliable free public endpoints
    const fallbackRpcs = process.env.ETH_RPC_FALLBACK_URLS
        ? process.env.ETH_RPC_FALLBACK_URLS.split(',').map(url => url.trim())
        : [
            'https://rpc.ankr.com/eth',           // Ankr - high uptime, generous rate limits
            'https://ethereum.publicnode.com'     // PublicNode - community-trusted, reliable
        ];

    const config = {
        rpcUrl: primaryRpc,
        rpcFallbacks: fallbackRpcs,
        vaultAddress: process.env.ETH_VAULT_ADDRESS || '',
        vaultPrivateKey: process.env.ETH_VAULT_PRIVATE_KEY || '',
        tokenContract: process.env.ETH_TOKEN_CONTRACT || '',
        chainId: parseInt(process.env.ETH_CHAIN_ID || '1'),
        gasPrice: process.env.ETH_GAS_PRICE || '20000000000',
        gasLimit: process.env.ETH_GAS_LIMIT || '100000',
    };

    if (!config.vaultAddress || !config.vaultPrivateKey || !config.tokenContract) {
        console.error('❌ Ethereum configuration missing! Set ETH_VAULT_ADDRESS, ETH_VAULT_PRIVATE_KEY, and ETH_TOKEN_CONTRACT environment variables.');
        process.exit(1);
    }

    return config;
}

/**
 * Create provider with fallback support
 * Tries multiple RPC endpoints until one works
 */
async function createProviderWithFallback(config) {
    const allRpcUrls = [config.rpcUrl, ...config.rpcFallbacks];

    for (let i = 0; i < allRpcUrls.length; i++) {
        const rpcUrl = allRpcUrls[i];
        try {
            console.log(new Date().toISOString(), `🔌 Attempting to connect to RPC: ${rpcUrl}`);
            const provider = new ethers.JsonRpcProvider(rpcUrl);

            // Test the connection with a simple call (with timeout)
            const blockNumberPromise = provider.getBlockNumber();
            const timeoutPromise = new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Connection timeout')), 5000)
            );

            await Promise.race([blockNumberPromise, timeoutPromise]);

            console.log(new Date().toISOString(), `✅ Successfully connected to RPC: ${rpcUrl}`);
            return { provider, rpcUrl };
        } catch (error) {
            console.warn(new Date().toISOString(), `⚠️  RPC ${rpcUrl} failed: ${error.message}`);
            if (i === allRpcUrls.length - 1) {
                throw new Error(`All RPC endpoints failed. Last error: ${error.message}`);
            }
            // Continue to next RPC
        }
    }

    throw new Error('No RPC endpoints available');
}

/**
 * Retry function with exponential backoff
 */
async function retryWithBackoff(fn, maxRetries = 3, initialDelay = 1000) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            return await fn();
        } catch (error) {
            if (i === maxRetries - 1) throw error;

            const delay = initialDelay * Math.pow(2, i);
            console.log(new Date().toISOString(), `⏳ Retry ${i + 1}/${maxRetries} after ${delay}ms...`);
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
}

/**
 * Get pending Ethereum withdrawals
 */
async function getWithdrawals() {
    try {
        // Include stuck withdrawals (status=8) older than 10 minutes
        const url = apiEndpoints.get_ethereum_withdrawals + '?status=0&include_stuck=1';
        console.log(new Date().toISOString(), '🔍 Fetching Ethereum withdrawals:', url);
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
            console.log(new Date().toISOString(), `⚠️  Found ${stuckCount} stuck Ethereum withdrawal(s) (status=8), will reprocess`);
        }

        console.log(new Date().toISOString(), `📊 Found ${uniqueWithdrawals.length} Ethereum withdrawal(s) to process`);

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
        const url = apiEndpoints.update_ethereum_withdrawals;
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
 * Send ERC-20 tokens on Ethereum
 */
async function sendTokens(toAddress, amount, config) {
    try {
        // Normalize Ethereum address: convert to lowercase, ensure '0x' prefix (not '0X')
        const normalizedAddress = toAddress.toLowerCase().replace(/^0x/i, '0x');

        console.log(new Date().toISOString(), `🚀 Sending ${amount} DBV to ${normalizedAddress.substring(0, 10)}...`);
        console.log(new Date().toISOString(), `   Original address: ${toAddress}, Normalized: ${normalizedAddress}`);

        // Validate address format
        if (!ethers.isAddress(normalizedAddress)) {
            throw new Error(`Invalid Ethereum address format: ${toAddress} (normalized: ${normalizedAddress})`);
        }

        // Create provider with fallback support
        const { provider, rpcUrl } = await createProviderWithFallback(config);
        console.log(new Date().toISOString(), `✅ Using RPC: ${rpcUrl}`);

        const wallet = new ethers.Wallet(config.vaultPrivateKey, provider);

        // ERC-20 ABI (just transfer function)
        const erc20Abi = [
            "function transfer(address to, uint256 amount) returns (bool)"
        ];

        const tokenContract = new ethers.Contract(config.tokenContract, erc20Abi, wallet);

        // Convert amount to token units (assuming 18 decimals)
        const amountInWei = ethers.parseUnits(amount.toString(), 18);

        // Estimate gas with retry
        console.log(new Date().toISOString(), '⛽ Estimating gas...');
        const gasEstimate = await retryWithBackoff(async () => {
            return await tokenContract.transfer.estimateGas(normalizedAddress, amountInWei);
        }, 3, 2000);

        // Get current gas price with retry
        console.log(new Date().toISOString(), '💰 Fetching gas price...');
        const feeData = await retryWithBackoff(async () => {
            return await provider.getFeeData();
        }, 3, 2000);
        const gasPrice = feeData.gasPrice || ethers.parseUnits(config.gasPrice, 'wei');

        console.log(new Date().toISOString(), `📊 Gas estimate: ${gasEstimate.toString()}, Gas price: ${ethers.formatUnits(gasPrice, 'gwei')} gwei`);

        // Send transaction (use normalized address)
        const tx = await tokenContract.transfer(normalizedAddress, amountInWei, {
            gasLimit: gasEstimate,
            gasPrice: gasPrice,
        });

        console.log(new Date().toISOString(), `📤 Transaction sent! Hash: ${tx.hash}`);

        // Wait for confirmation
        const receipt = await tx.wait();

        console.log(new Date().toISOString(), `✅ Transaction confirmed! Hash: ${receipt.hash}`);
        return receipt.hash;

    } catch (error) {
        console.error(new Date().toISOString(), '❌ Error sending tokens:', error.message);

        // Log more details for specific error types
        if (error.code === 'INSUFFICIENT_FUNDS') {
            console.error(new Date().toISOString(), '💸 Insufficient funds in vault wallet');
        } else if (error.code === 'NONCE_EXPIRED' || error.code === 'REPLACEMENT_UNDERPRICED') {
            console.error(new Date().toISOString(), '🔄 Nonce or gas price issue - transaction may need retry');
        } else if (error.message.includes('timeout')) {
            console.error(new Date().toISOString(), '⏱️  Operation timed out - network may be congested');
        } else if (error.message.includes('rate limit') || error.message.includes('429')) {
            console.error(new Date().toISOString(), '🚫 Rate limited by RPC endpoint');
        }

        return null;
    }
}

/**
 * Process Ethereum withdrawals
 */
async function processWithdrawals() {
    const config = getEthereumConfig();
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
                console.log(new Date().toISOString(), '⚠️  Reprocessing in-progress/stuck Ethereum withdrawal (status=', withdrawal.status, ')');
            }

            // Idempotency: if we already have a hash, don't send again
            const existingHash = (withdrawal.txn_hash_eth || '').trim();
            if (existingHash && existingHash.length >= 64) {
                console.log(new Date().toISOString(), `📋 Found existing hash for ID ${withdrawal.id}, retrying status update only (no resend)`);
                const ok = await updateStatus(withdrawal.id, 3, existingHash);
                if (ok) { processed++; statusUpdated = true; }
                if (withdrawals.length > 1) await new Promise(r => setTimeout(r, 1000));
                continue;
            }

            // Send tokens
            txHash = await sendTokens(withdrawal.address, withdrawal.amount, config);

            if (txHash && txHash.length === 66) { // Ethereum hash is 66 chars (0x + 64 hex)
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
                        for (let r = 0; r < 3; r++) {
                            await new Promise(r => setTimeout(r, 2000));
                            if (await updateStatus(withdrawal.id, 3, txHash)) {
                                processed++; statusUpdated = true;
                                break;
                            }
                        }
                        if (!statusUpdated && txHash) {
                            await updateStatus(withdrawal.id, 3, txHash, '', true);
                        }
                        if (!statusUpdated) failed++;
                    }
                }
            } else {
                // Mark as failed
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
                    console.log(new Date().toISOString(), '⚠️ Exception after send (hash exists) - retrying status 3, NOT marking failed');
                    for (let r = 0; r < 5; r++) {
                        await new Promise(res => setTimeout(res, 2000));
                        if (await updateStatus(withdrawal.id, 3, txHash)) {
                            statusUpdated = true;
                            break;
                        }
                    }
                    if (!statusUpdated) await updateStatus(withdrawal.id, 3, txHash, '', true);
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
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1].endsWith('withdrawal_to_ethereum.js')) {
    (async () => {
        console.log(new Date().toISOString(), '🚀 Starting Ethereum withdrawal worker...');
        const processed = await processWithdrawals();
        process.exit(processed > 0 ? 0 : 1);
    })();
}

export { processWithdrawals, getWithdrawals, updateStatus };

