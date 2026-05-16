import { securityToken, publicOrTestnet, accountToWatch, tokenAsset, secKey2, pubKey1 } from './common_config.js';
import { apiEndpoints } from './node_config.js';
import { secureWorkerRequest } from './secure_worker_client.js';
import https from 'https';
import axios from 'axios';
import StellarSdk from '@stellar/stellar-sdk';

(async () => {
    const CacheableLookup = (await import('cacheable-lookup')).default;
    const cacheable = new CacheableLookup();
    cacheable.install(https.globalAgent);
})();

async function getWithdrawals() {
    try {
        const url = apiEndpoints.get_withdrawals + '?status=0&trustline=1';
        console.log(new Date().toISOString(), 'Calling URL:', url);

        // Use secure authenticated request
        const data = await secureWorkerRequest(url);

        console.log(new Date().toISOString(), 'Response data type:', typeof data, 'isArray:', Array.isArray(data), 'length:', Array.isArray(data) ? data.length : 'N/A');
        return Array.isArray(data) ? data : [];
    } catch (e) {
        console.log(new Date().toISOString(), 'getWithdrawals error', e.code || '', e.message || '');
        return [];
    }
}

async function updateStatus(id, status, hash = '') {
    try {
        const result = await secureWorkerRequest(apiEndpoints.update_withdrawals, {
            trustlineOrStatus: 'status',
            status,
            ids: String(id),
            hash: hash || ''
        });
        return result === 'OK';
    } catch (e) {
        console.log(new Date().toISOString(), 'updateStatus error', e.code || '', e.message || '');
        return false;
    }
}

async function sendPayment(network, issuer, secret, dest, amount) {
    const horizonUrl = network === 'public'
        ? 'https://horizon.stellar.org'
        : 'https://horizon-testnet.stellar.org';
    const server = new StellarSdk.Horizon.Server(horizonUrl);
    const networkPass = network === 'public' ? StellarSdk.Networks.PUBLIC : StellarSdk.Networks.TESTNET;
    const kp = StellarSdk.Keypair.fromSecret(secret);
    let txHash = '';
    try {
        const account = await server.loadAccount(kp.publicKey());
        const tx = new StellarSdk.TransactionBuilder(account, { fee: StellarSdk.BASE_FEE * 1000, networkPassphrase: networkPass })
            .addOperation(StellarSdk.Operation.payment({ destination: dest, asset: new StellarSdk.Asset(tokenAsset, issuer), amount: amount.toString() }))
            .setTimeout(300)
            .build();
        tx.sign(kp);
        const result = await server.submitTransaction(tx);
        if (result && result.hash && result.hash.length === 64) {
            txHash = result.hash;
        }
    } catch (err) {
        try {
            const extras = err?.response?.data?.extras;
            const resultCode = err?.response?.data?.extras?.result_codes;
            console.log(new Date().toISOString(), 'Horizon submit error:', err?.response?.status || '', err?.response?.statusText || '', 'result codes:', resultCode || '', 'extras:', JSON.stringify(extras).substring(0, 200));
        } catch (_) {
            console.log(new Date().toISOString(), 'Horizon submit error', err?.message || String(err));
        }
    }
    return txHash;
}

async function processWithdrawals() {
    const items = await getWithdrawals();
    if (!items.length) {
        console.log(new Date().toISOString(), 'No pending withdrawals');
        return 0;
    }
    for (const it of items) {
        console.log(new Date().toISOString(), 'Processing id', it.id, 'amount', it.amount, 'dest', it.address);
        const pre = await updateStatus(it.id, 8, '');
        console.log(new Date().toISOString(), 'Pre-complete', pre ? 'OK' : 'FAIL');
        const hash = await sendPayment(publicOrTestnet, pubKey1, secKey2, it.address, it.amount);
        if (hash) {
            const ok = await updateStatus(it.id, 3, hash);
            console.log(new Date().toISOString(), 'Submitted OK, hash', hash, 'status update', ok ? 'OK' : 'FAIL');
        } else {
            const fail = await updateStatus(it.id, 2, '');
            console.log(new Date().toISOString(), 'Submit failed, status update', fail ? 'OK' : 'FAIL');
        }
    }
    return items.length;
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
