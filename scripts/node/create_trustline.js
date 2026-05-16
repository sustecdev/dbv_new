import StellarSdk from '@stellar/stellar-sdk';
import { publicOrTestnet, pubKey1, tokenAsset } from './common_config.js';

// Configuration
const network = publicOrTestnet; // 'public' or 'testnet'
const assetCode = tokenAsset; // 'XDEF' or 'DB'
const assetIssuer = pubKey1; // Issuer public key
const destinationSecretKey = 'YOUR_DESTINATION_SECRET_KEY_HERE'; // Replace with the secret key for GBM3FNAGXZF57B2JP5VG5C5KKC23TNBVBJ662DLC7XYZ5MDRXMKLCI7S

async function createTrustline() {
    const horizonUrl = network === 'public' ? 'https://horizon.stellar.org' : 'https://horizon-testnet.stellar.org';
    const server = new StellarSdk.Horizon.Server(horizonUrl);
    const networkPassphrase = network === 'public' ? StellarSdk.Networks.PUBLIC : StellarSdk.Networks.TESTNET;

    try {
        const destinationKeypair = StellarSdk.Keypair.fromSecret(destinationSecretKey);
        const destinationAccount = await server.loadAccount(destinationKeypair.publicKey());

        const asset = new StellarSdk.Asset(assetCode, assetIssuer);

        const transaction = new StellarSdk.TransactionBuilder(destinationAccount, {
            fee: StellarSdk.BASE_FEE,
            networkPassphrase: networkPassphrase
        })
            .addOperation(
                StellarSdk.Operation.changeTrust({
                    asset: asset
                })
            )
            .setTimeout(30)
            .build();

        transaction.sign(destinationKeypair);

        const result = await server.submitTransaction(transaction);

        console.log('Trustline created successfully!');
        console.log('Transaction Hash:', result.hash);
        console.log('Account:', destinationKeypair.publicKey());
        console.log('Asset:', assetCode, 'Issuer:', assetIssuer);
        return result.hash;

    } catch (error) {
        console.error('Error creating trustline:', error);
        if (error.response?.data) {
            console.error('Horizon Error:', JSON.stringify(error.response.data, null, 2));
        }
        throw error;
    }
}

// Run if executed directly
if (import.meta.url === `file://${process.argv[1]}`) {
    createTrustline()
        .then(() => process.exit(0))
        .catch(() => process.exit(1));
}

export { createTrustline };

