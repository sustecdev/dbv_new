import StellarSdk from '@stellar/stellar-sdk';
import axios from 'axios';
import { tokenAsset, pubKey1 } from './common_config.js';

function sleep(ms){ return new Promise(r=>setTimeout(r, ms)); }

async function main() {
    const kp = StellarSdk.Keypair.random();
    const pub = kp.publicKey();
    const sec = kp.secret();

    // fund on testnet
    await axios.get('https://friendbot.stellar.org', { params: { addr: pub }, timeout: 20000 });
    await sleep(3000);

    // change trust
    const server = new StellarSdk.Server('https://horizon-testnet.stellar.org');
    const networkPass = StellarSdk.Networks.TESTNET;

    // wait until account is available with a sequence
    let account;
    for (let i=0;i<5;i++) {
        try { account = await server.loadAccount(pub); break; } catch { await sleep(1500); }
    }
    if (!account) throw new Error('Account not found after funding');

    const tx = new StellarSdk.TransactionBuilder(account, { fee: StellarSdk.BASE_FEE * 100, networkPassphrase: networkPass })
        .addOperation(StellarSdk.Operation.changeTrust({ asset: new StellarSdk.Asset(tokenAsset, pubKey1), limit: '1000000000' }))
        .setTimeout(180)
        .build();
    tx.sign(kp);
    await server.submitTransaction(tx);

    console.log(JSON.stringify({ public: pub, secret: sec }, null, 2));
}

main().catch((e) => { console.error(e?.response?.data || e.message); process.exit(1); });
