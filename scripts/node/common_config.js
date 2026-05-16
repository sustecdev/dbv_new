// Load from environment variables - DO NOT hardcode sensitive values
export const securityToken = process.env.WORKER_SECRET || (() => {
    throw new Error('WORKER_SECRET environment variable is required');
})();
export const publicOrTestnet = process.env.HORIZON_NETWORK || 'public';
export const tokenAsset = process.env.ASSET_CODE || 'DB';
export const accountToWatch = process.env.VAULT_ACCOUNT_ID || (() => {
    throw new Error('VAULT_ACCOUNT_ID environment variable is required');
})();
export const pubKey1 = process.env.ASSET_ISSUER || (() => {
    throw new Error('ASSET_ISSUER environment variable is required');
})();
export const pubKey2 = process.env.ACCOUNT_OWNER || (() => {
    throw new Error('ACCOUNT_OWNER environment variable is required');
})();
export const pubKey3 = process.env.ACCOUNT_VAULT || (() => {
    throw new Error('ACCOUNT_VAULT environment variable is required');
})();
export const secKey2 = process.env.STELLAR_SECRET_KEY || (() => {
    throw new Error('STELLAR_SECRET_KEY environment variable is required');
})();
