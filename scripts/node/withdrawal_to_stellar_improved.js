import { securityToken, publicOrTestnet, accountToWatch, tokenAsset, secKey2, pubKey1 } from './common_config.js';
import { apiEndpoints } from './node_config.js';
import https from 'https';
import axios from 'axios';
import StellarSdk from '@stellar/stellar-sdk';

(async () => {
    const CacheableLookup = (await import('cacheable-lookup')).default;
    const cacheable = new CacheableLookup();
    cacheable.install(https.globalAgent);
})();

/**
 * Get withdrawals with database locking to prevent race conditions
 */
async function getWithdrawals() {
    try {
        // Get all pending withdrawals (status=0) and stuck ones (status=8), including stuck ones
        // The API now returns ALL status=0 when include_stuck=1, regardless of trustline
        let url = apiEndpoints.get_withdrawals + '?status=0&trustline=1&include_stuck=1';
        console.log(new Date().toISOString(), 'Calling URL:', url);
        const { generateSignature } = await import('./secure_worker_client.js');
        const ts = Math.floor(Date.now() / 1000).toString();
        const uri = new URL(url).pathname + new URL(url).search;
        const res1 = await axios.get(url, {
            headers: { 'X-Worker-Timestamp': ts, 'X-Worker-Signature': generateSignature(uri, ts) },
            timeout: 20000,
        });

        let withdrawals = Array.isArray(res1.data) ? res1.data : [];

        // Debug: Log raw response
        if (withdrawals.length > 0) {
            console.log(new Date().toISOString(), '📥 Raw API response:', JSON.stringify(withdrawals[0]));
        }

        // Filter to include status=0 (pending), status=1 (processing), and status=8 (pre-complete)
        // Note: status comes as string from JSON, so we need to check both string and number
        const valid = withdrawals.filter(w => {
            if (!w || !w.id || !w.address || !w.amount) return false;
            const status = parseInt(w.status);
            return status === 0 || status === 1 || status === 8;
        });

        // Remove duplicates based on ID
        const uniqueWithdrawals = Array.from(
            new Map(valid.map(w => [w.id, w])).values()
        );

        // Log if we found stuck withdrawals
        const stuckCount = valid.filter(w => w.status === 8).length;
        if (stuckCount > 0) {
            console.log(new Date().toISOString(), `⚠️  Found ${stuckCount} stuck withdrawal(s) (status=8), will reprocess`);
        }

        console.log(new Date().toISOString(),
            'Response status:', res1.status,
            'total:', withdrawals.length,
            'valid:', valid.length,
            'unique:', uniqueWithdrawals.length,
            stuckCount > 0 ? `stuck: ${stuckCount}` : '');

        return uniqueWithdrawals;
    } catch (e) {
        console.log(new Date().toISOString(), 'getWithdrawals error', e.code || '', e.message || '');
        return [];
    }
}

/**
 * Update withdrawal status atomically
 * @param {number} id - Withdrawal ID
 * @param {number} status - 2=failed, 3=completed, 8=pre-complete
 * @param {string} [hash=''] - Transaction hash when status=3
 * @param {string} [errorMessage=''] - Failure reason when status=2
 * @param {boolean} [hashOnly=false] - Only persist hash (prevents double-send)
 */
async function updateStatus(id, status, hash = '', errorMessage = '', hashOnly = false) {
    try {
        const params = {
            trustlineOrStatus: 'status',
            status,
            ids: String(id),
            hash: hash || ''
        };
        if (status === 2 && errorMessage) params.error = errorMessage;
        if (hashOnly) params.hash_only = '1';

        const { generateSignature } = await import('./secure_worker_client.js');
        const fullUrl = apiEndpoints.update_withdrawals + '?' + new URLSearchParams(params).toString();
        const ts = Math.floor(Date.now() / 1000).toString();
        const uri = new URL(fullUrl).pathname + new URL(fullUrl).search;
        const res = await axios.get(fullUrl, {
            headers: { 'X-Worker-Timestamp': ts, 'X-Worker-Signature': generateSignature(uri, ts) },
            timeout: 20000,
        });

        const success = res.data === 'OK' || res.data?.success === true;
        if (!success) {
            console.log(new Date().toISOString(), 'Update status returned:', res.data);
        }
        return success;
    } catch (e) {
        console.log(new Date().toISOString(), 'updateStatus error', e.code || '', e.message || '');
        return false;
    }
}

/**
 * Send payment with retry logic for transient errors
 */
async function sendPayment(network, issuer, secret, dest, amount, retries = 2) {
    const horizonUrl = network === 'public'
        ? 'https://horizon.stellar.org'
        : 'https://horizon-testnet.stellar.org';
    const server = new StellarSdk.Horizon.Server(horizonUrl);
    const networkPass = network === 'public' ? StellarSdk.Networks.PUBLIC : StellarSdk.Networks.TESTNET;
    const kp = StellarSdk.Keypair.fromSecret(secret);

    let lastError = null;

    for (let attempt = 0; attempt <= retries; attempt++) {
        if (attempt > 0) {
            const delay = Math.min(1000 * Math.pow(2, attempt - 1), 5000); // Exponential backoff, max 5s
            console.log(new Date().toISOString(), `Retrying payment (attempt ${attempt + 1}/${retries + 1}) after ${delay}ms...`);
            await new Promise(resolve => setTimeout(resolve, delay));
        }

        try {
            const account = await server.loadAccount(kp.publicKey());
            const tx = new StellarSdk.TransactionBuilder(account, {
                fee: StellarSdk.BASE_FEE * 1000,
                networkPassphrase: networkPass
            })
                .addOperation(StellarSdk.Operation.payment({
                    destination: dest,
                    asset: new StellarSdk.Asset(tokenAsset, issuer),
                    amount: amount.toString()
                }))
                .setTimeout(300)
                .build();

            tx.sign(kp);
            const result = await server.submitTransaction(tx);

            if (result && result.hash && result.hash.length === 64) {
                if (attempt > 0) {
                    console.log(new Date().toISOString(), `✅ Payment succeeded on retry ${attempt + 1}`);
                }
                return result.hash;
            }
        } catch (err) {
            lastError = err;

            // Check if error is retriable (network/timeout errors)
            const isRetriable = err.code === 'ECONNRESET' ||
                err.code === 'ETIMEDOUT' ||
                err.code === 'ENOTFOUND' ||
                err.response?.status >= 500 ||
                err.message?.includes('timeout');

            if (isRetriable && attempt < retries) {
                const extras = err?.response?.data?.extras;
                const resultCode = err?.response?.data?.extras?.result_codes;
                console.log(new Date().toISOString(),
                    `⚠️  Retriable error (attempt ${attempt + 1}):`,
                    err?.response?.status || err.code || err.message);
                continue; // Retry
            }

            // Non-retriable error or max retries reached
            try {
                const extras = err?.response?.data?.extras;
                const resultCode = err?.response?.data?.extras?.result_codes;
                console.log(new Date().toISOString(),
                    '❌ Payment failed:',
                    err?.response?.status || '',
                    err?.response?.statusText || '',
                    'result codes:', resultCode || '',
                    'extras:', JSON.stringify(extras).substring(0, 200));
            } catch (_) {
                console.log(new Date().toISOString(), 'Payment failed:', err?.message || String(err));
            }

            // Don't retry non-retriable errors
            if (!isRetriable) {
                break;
            }
        }
    }

    return ''; // Failed after all retries
}

/**
 * Process withdrawals with improved error handling
 */
async function processWithdrawals() {
    const items = await getWithdrawals();
    if (!items.length) {
        return 0;
    }

    let processed = 0;
    let failed = 0;

    for (const it of items) {
        let statusUpdated = false;
        let hash = '';
        try {
            console.log(new Date().toISOString(),
                'Processing id', it.id,
                'amount', it.amount,
                'dest', it.address,
                'current_status', it.status);

            // If status is 1 (processing) or 8 (pre-complete), skip pre-complete step
            if (it.status === 0) {
                // Mark as pre-complete
                const pre = await updateStatus(it.id, 8, '');
                if (!pre) {
                    console.log(new Date().toISOString(), '⚠️  Failed to update to pre-complete, skipping');
                    failed++;
                    continue;
                }
                console.log(new Date().toISOString(), 'Pre-complete OK');
            } else {
                console.log(new Date().toISOString(), '⚠️  Reprocessing in-progress/stuck withdrawal (status=', it.status, ')');
            }

            // Idempotency: if we already have a hash, don't send again
            const existingHash = (it.txn_hash_stellar || '').trim();
            if (existingHash && existingHash.length === 64) {
                console.log(new Date().toISOString(), `📋 Found existing hash for ID ${it.id}, retrying status update only (no resend)`);
                const ok = await updateStatus(it.id, 3, existingHash);
                if (ok) { processed++; statusUpdated = true; }
                if (items.length > 1) await new Promise(r => setTimeout(r, 500));
                continue;
            }

            // Send payment with retry logic
            hash = await sendPayment(publicOrTestnet, pubKey1, secKey2, it.address, it.amount, 2);

            if (hash && hash.length === 64) {
                // Payment succeeded - update to completed
                const ok = await updateStatus(it.id, 3, hash);
                if (ok) {
                    console.log(new Date().toISOString(),
                        '✅ Submitted OK, hash', hash.substring(0, 16) + '...',
                        'status update OK');
                    processed++;
                    statusUpdated = true;
                } else {
                    console.log(new Date().toISOString(),
                        '⚠️  Payment succeeded but status update failed, hash:', hash);
                    // Retry status update
                    const retryOk = await updateStatus(it.id, 3, hash);
                    if (retryOk) {
                        console.log(new Date().toISOString(), '✅ Status update retry succeeded');
                        processed++;
                        statusUpdated = true;
                    } else {
                        for (let r = 0; r < 3; r++) {
                            await new Promise(r => setTimeout(r, 2000));
                            if (await updateStatus(it.id, 3, hash)) {
                                processed++; statusUpdated = true;
                                break;
                            }
                        }
                        if (!statusUpdated && hash) await updateStatus(it.id, 3, hash, '', true);
                        if (!statusUpdated) failed++;
                    }
                }
            } else {
                // Payment failed - update to failed status
                console.log(new Date().toISOString(), '❌ Payment failed, updating status to failed (2)');
                const fail = await updateStatus(it.id, 2, '', 'Payment failed or invalid response');
                if (fail) {
                    console.log(new Date().toISOString(), '❌ Status updated to failed');
                    failed++;
                    statusUpdated = true;
                } else {
                    console.log(new Date().toISOString(), '❌ Status update to failed also failed - retrying...');
                    // Retry status update
                    const retryFail = await updateStatus(it.id, 2, '', 'Payment failed or invalid response');
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

            // Small delay between withdrawals to avoid rate limiting
            if (items.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 500));
            }

        } catch (error) {
            console.error(new Date().toISOString(), 'Error processing withdrawal', it.id, ':', error.message);
            console.error(error.stack);

            if (!statusUpdated) {
                if (hash && hash.length === 64) {
                    console.log(new Date().toISOString(), '⚠️ Exception after send (hash exists) - retrying status 3, NOT marking failed');
                    for (let r = 0; r < 5; r++) {
                        await new Promise(res => setTimeout(res, 2000));
                        if (await updateStatus(it.id, 3, hash)) {
                            statusUpdated = true;
                            break;
                        }
                    }
                    if (!statusUpdated) await updateStatus(it.id, 3, hash, '', true);
                } else {
                    try {
                        const fail = await updateStatus(it.id, 2, '', error.message || 'Exception during processing');
                        if (fail) console.log(new Date().toISOString(), '✅ Updated to failed status after exception');
                    } catch (updateError) {
                        console.error(new Date().toISOString(), 'Exception updating status:', updateError.message);
                    }
                }
            }
            failed++;
        }
    }

    if (failed > 0) {
        console.log(new Date().toISOString(), `⚠️  Processed ${processed}, Failed ${failed}`);
    }

    return processed;
}

async function main() {
    const passed = process.argv[2];
    if (passed !== securityToken) {
        console.error('Security token mismatch');
        process.exit(1);
    }
    await processWithdrawals();
}

// Export for use in loop mode
export { processWithdrawals };

// Run main if executed directly
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1].endsWith('withdrawal_to_stellar.js')) {
    main();
}

