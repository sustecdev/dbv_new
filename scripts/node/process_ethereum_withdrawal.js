import { apiEndpoints } from './node_config.js';
import { generateSignature } from './secure_worker_client.js';
import axios from 'axios';

function workerHeaders(url) {
    const u = new URL(url);
    const uri = u.pathname + u.search;
    const ts = Math.floor(Date.now() / 1000).toString();
    return { 'X-Worker-Timestamp': ts, 'X-Worker-Signature': generateSignature(uri, ts) };
}
import { Web3 } from 'web3';
import https from 'https';

(async () => {
    const CacheableLookup = (await import('cacheable-lookup')).default;
    const cacheable = new CacheableLookup();
    cacheable.install(https.globalAgent);
})();

/**
 * Load config from .env file (project root)
 */
async function loadConfigFromEnv() {
    try {
        const fs = await import('fs');
        const path = await import('path');

        let envPath = path.join(process.cwd(), '.env');
        if (!fs.existsSync(envPath)) {
            envPath = path.join(process.cwd(), 'env.example');
        }

        if (fs.existsSync(envPath)) {
            const envContent = fs.readFileSync(envPath, 'utf8');
            envContent.split('\n').forEach(line => {
                // Skip comments and empty lines
                const trimmed = line.trim();
                if (!trimmed || trimmed.startsWith('#')) {
                    return;
                }

                const match = trimmed.match(/^([^#=]+)=(.*)$/);
                if (match) {
                    const key = match[1].trim();
                    const value = match[2].trim().replace(/^["']|["']$/g, '');
                    // Only set if not already set in environment
                    if (!process.env[key] && value) {
                        process.env[key] = value;
                    }
                }
            });
            console.log(new Date().toISOString(), `📄 Loaded configuration from: ${path.relative(process.cwd(), envPath)}`);
        } else {
            console.log(new Date().toISOString(), '⚠️  No .env file found. Using environment variables only.');
        }
    } catch (e) {
        console.log(new Date().toISOString(), `⚠️  Could not load .env file: ${e.message}`);
    }
}

/**
 * Process a single Ethereum withdrawal by ID
 */
async function processWithdrawalById(withdrawalId) {
    // Try to load from .env first
    await loadConfigFromEnv();

    console.log(new Date().toISOString(), `🚀 Processing Ethereum withdrawal ID: ${withdrawalId}`);

    // Get Ethereum config
    const config = {
        rpcUrl: process.env.ETH_RPC_URL || 'https://mainnet.infura.io/v3/YOUR_KEY',
        vaultAddress: process.env.ETH_VAULT_ADDRESS || '',
        vaultPrivateKey: process.env.ETH_VAULT_PRIVATE_KEY || '',
        tokenContract: process.env.ETH_TOKEN_CONTRACT || '',
        chainId: parseInt(process.env.ETH_CHAIN_ID || '1'),
        gasPrice: process.env.ETH_GAS_PRICE || '20000000000',
        gasLimit: process.env.ETH_GAS_LIMIT || '100000',
        explorer: process.env.ETH_EXPLORER || 'https://etherscan.io',
    };

    // Check config
    if (!config.vaultAddress || !config.vaultPrivateKey || !config.tokenContract) {
        console.error('❌ Ethereum configuration missing!');
        console.error('Required environment variables:');
        console.error('  - ETH_VAULT_ADDRESS');
        console.error('  - ETH_VAULT_PRIVATE_KEY');
        console.error('  - ETH_TOKEN_CONTRACT');
        console.error('\nOptional:');
        console.error('  - ETH_RPC_URL (default: https://mainnet.infura.io/v3/YOUR_KEY)');
        console.error('  - ETH_CHAIN_ID (default: 1)');
        console.error('  - ETH_GAS_PRICE (default: 20000000000)');
        console.error('  - ETH_GAS_LIMIT (default: 100000)');
        process.exit(1);
    }

    console.log(new Date().toISOString(), '✅ Configuration loaded');
    console.log(new Date().toISOString(), `📡 RPC: ${config.rpcUrl}`);
    console.log(new Date().toISOString(), `🏦 Vault: ${config.vaultAddress.substring(0, 10)}...`);
    console.log(new Date().toISOString(), `🪙 Token: ${config.tokenContract.substring(0, 10)}...`);

    // Get withdrawal from API
    try {
        // Fetch all pending withdrawals and find by ID
        console.log(new Date().toISOString(), '🔍 Fetching pending withdrawals...');
        const url = apiEndpoints.get_ethereum_withdrawals
            ? `${apiEndpoints.get_ethereum_withdrawals}?status=0`
            : `${apiEndpoints.base}/getEthereumWithdrawals.php?status=0`;

        console.log(new Date().toISOString(), `Fetching from: ${url}`);

        const res = await axios.get(url, { headers: workerHeaders(url), timeout: 20000 });

        const allWithdrawals = Array.isArray(res.data) ? res.data : [];
        const withdrawals = allWithdrawals.filter(w => w && w.id == withdrawalId);

        if (withdrawals.length === 0) {
            console.error('❌ Withdrawal not found or already processed');
            process.exit(1);
        }

        const withdrawal = withdrawals[0];
        console.log(new Date().toISOString(), `📦 Found withdrawal:`);
        console.log(new Date().toISOString(), `   ID: ${withdrawal.id}`);
        console.log(new Date().toISOString(), `   Amount: ${withdrawal.amount} DBV`);
        console.log(new Date().toISOString(), `   Address: ${withdrawal.address}`);
        console.log(new Date().toISOString(), `   Status: ${withdrawal.status}`);

        if (withdrawal.status !== 0 && withdrawal.status !== 8) {
            console.error(`❌ Withdrawal status is ${withdrawal.status}, cannot process`);
            process.exit(1);
        }

        // Update to pre-complete (status 8)
        console.log(new Date().toISOString(), `📝 Updating status to pre-complete (8)...`);
        const updateUrl = apiEndpoints.update_ethereum_withdrawals || `${apiEndpoints.base}/updEthereumWithdrawals.php`;
        const u = updateUrl + '?id=' + withdrawal.id + '&status=8';
        await axios.post(u, {}, { headers: workerHeaders(u) });

        // Initialize Web3
        console.log(new Date().toISOString(), `🔗 Connecting to Ethereum network...`);
        const web3 = new Web3(config.rpcUrl);

        // Create account from private key
        const account = web3.eth.accounts.privateKeyToAccount('0x' + config.vaultPrivateKey.replace('0x', ''));
        web3.eth.accounts.wallet.add(account);

        // Check balance
        const tokenABI = [
            {
                "constant": true,
                "inputs": [{ "name": "_owner", "type": "address" }],
                "name": "balanceOf",
                "outputs": [{ "name": "balance", "type": "uint256" }],
                "type": "function"
            },
            {
                "constant": false,
                "inputs": [
                    { "name": "_to", "type": "address" },
                    { "name": "_value", "type": "uint256" }
                ],
                "name": "transfer",
                "outputs": [{ "name": "", "type": "bool" }],
                "type": "function"
            },
            {
                "constant": true,
                "inputs": [],
                "name": "decimals",
                "outputs": [{ "name": "", "type": "uint256" }],
                "type": "function"
            }
        ];

        const tokenContract = new web3.eth.Contract(tokenABI, config.tokenContract);

        // Get decimals
        const decimals = await tokenContract.methods.decimals().call();
        console.log(new Date().toISOString(), `📊 Token decimals: ${decimals}`);

        // Check vault balance
        const balance = await tokenContract.methods.balanceOf(config.vaultAddress).call();
        const balanceFormatted = web3.utils.fromWei(balance, 'ether');
        console.log(new Date().toISOString(), `💰 Vault balance: ${balanceFormatted} tokens`);

        // Convert amount to wei (assuming 18 decimals)
        const amountWei = web3.utils.toWei(withdrawal.amount.toString(), 'ether');

        // Check if sufficient balance
        if (BigInt(balance) < BigInt(amountWei)) {
            const errMsg = `Insufficient balance: need ${withdrawal.amount}, have ${balanceFormatted}`;
            console.error(`❌ ${errMsg}`);
            const u = updateUrl + '?id=' + withdrawal.id + '&status=2&error=' + encodeURIComponent(errMsg);
            await axios.post(u, {}, { headers: workerHeaders(u) });
            process.exit(1);
        }

        // Get gas price
        const gasPrice = await web3.eth.getGasPrice();
        console.log(new Date().toISOString(), `⛽ Gas price: ${gasPrice} wei`);

        // Estimate gas
        const estimatedGas = await tokenContract.methods.transfer(withdrawal.address, amountWei).estimateGas({
            from: config.vaultAddress
        });
        console.log(new Date().toISOString(), `⛽ Estimated gas: ${estimatedGas}`);

        // Send transaction
        console.log(new Date().toISOString(), `📤 Sending ${withdrawal.amount} DBV to ${withdrawal.address}...`);

        const tx = await tokenContract.methods.transfer(withdrawal.address, amountWei).send({
            from: config.vaultAddress,
            gas: parseInt(config.gasLimit),
            gasPrice: gasPrice
        });

        const txHash = tx.transactionHash;
        console.log(new Date().toISOString(), `✅ Transaction sent!`);
        console.log(new Date().toISOString(), `📝 Transaction hash: ${txHash}`);
        console.log(new Date().toISOString(), `🔗 Etherscan: ${config.explorer}/tx/${txHash}`);

        // Wait for confirmation (poll for receipt)
        console.log(new Date().toISOString(), `⏳ Waiting for confirmation...`);
        let receipt = null;
        let attempts = 0;
        const maxAttempts = 30;

        while (!receipt && attempts < maxAttempts) {
            await new Promise(resolve => setTimeout(resolve, 2000)); // Wait 2 seconds
            try {
                receipt = await web3.eth.getTransactionReceipt(txHash);
                if (receipt) {
                    console.log(new Date().toISOString(), `✅ Transaction confirmed! Block: ${receipt.blockNumber}`);
                    break;
                }
            } catch (e) {
                // Transaction not yet mined, continue waiting
            }
            attempts++;
            if (attempts % 5 === 0) {
                console.log(new Date().toISOString(), `⏳ Still waiting... (${attempts}/${maxAttempts})`);
            }
        }

        if (!receipt) {
            console.log(new Date().toISOString(), `⚠️  Transaction sent but not yet confirmed. Check Etherscan for status.`);
        }

        // Update status to completed (status 3)
        console.log(new Date().toISOString(), `📝 Updating withdrawal status to completed...`);
        const u = updateUrl + '?id=' + withdrawal.id + '&status=3&hash=' + encodeURIComponent(txHash);
        await axios.post(u, {}, { headers: workerHeaders(u) });

        console.log(new Date().toISOString(), `🎉 Withdrawal ${withdrawal.id} completed successfully!`);

    } catch (error) {
        console.error(new Date().toISOString(), `❌ Error processing withdrawal:`, error.message);
        if (error.response) {
            console.error('Response:', error.response.data);
        }

        // Try to mark as failed
        try {
            const updateUrl = apiEndpoints.update_ethereum_withdrawals || `${apiEndpoints.base}/updEthereumWithdrawals.php`;
            const u = updateUrl + '?id=' + withdrawalId + '&status=2&error=' + encodeURIComponent(error.message || 'Exception during processing');
            await axios.post(u, {}, { headers: workerHeaders(u) });
        } catch (e) {
            console.error('Failed to update status:', e.message);
        }

        process.exit(1);
    }
}

// Get withdrawal ID from command line or use 1
const withdrawalId = process.argv[2] || 1;
processWithdrawalById(withdrawalId);

