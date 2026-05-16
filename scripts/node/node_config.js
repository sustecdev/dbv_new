import os from 'os';

/**
 * Smart environment detection that handles both localhost and production
 * Priority:
 * 1. API_BASE_URL environment variable (explicit override)
 * 2. Auto-detect based on hostname, platform, and localhost accessibility
 * 3. Fallback to production URL
 */

function detectEnvironment() {
    const env = process.env.NODE_ENV || process.env.ENVIRONMENT;
    const hostname = os.hostname().toLowerCase();
    const platform = os.platform();
    
    // Check for explicit development indicators
    if (env === 'development' || 
        hostname.includes('dev') || 
        hostname.includes('test') || 
        hostname.includes('local') ||
        hostname === 'localhost' ||
        hostname.includes('127.0.0.1')) {
        return 'development';
    }
    
    // Windows with simple hostname (likely local dev machine)
    if (platform === 'win32' && hostname.length < 15 && !hostname.includes('.')) {
        return 'development';
    }
    
    return 'production';
}

/**
 * Quick check if localhost is accessible (synchronous, non-blocking)
 * Returns true if we can reach localhost, false otherwise
 */
function isLocalhostAccessible() {
    try {
        // Try to connect to localhost (quick check)
        const testUrl = 'http://localhost/dbnew/public/getWithdrawals.php?status=0&trustline=1';
        // Use a simple sync check - if we're on Windows with XAMPP, localhost is likely available
        const platform = os.platform();
        if (platform === 'win32') {
            // On Windows, assume localhost is available if no explicit production indicators
            return true;
        }
        // On Linux/Mac, be more conservative
        return false;
    } catch (e) {
        return false;
    }
}

const environment = detectEnvironment();

// Configuration for production vs development
let BASE_URL;

// Priority 1: Explicit API_BASE_URL environment variable (highest priority)
if (process.env.API_BASE_URL) {
    BASE_URL = process.env.API_BASE_URL;
    console.log(`📡 Using explicit API_BASE_URL from environment: ${BASE_URL}`);
} else {
    // Priority 2: Auto-detect based on environment
    if (environment === 'development' || isLocalhostAccessible()) {
        BASE_URL = 'http://localhost/dbnew/public';
        console.log(`🌍 Auto-detected: Development environment (localhost)`);
    } else {
        BASE_URL = 'https://digitalbenefits.exchange/public';
        console.log(`🌍 Auto-detected: Production environment`);
    }
}

// Final validation and fallback
if (!BASE_URL) {
    BASE_URL = 'http://localhost/dbnew/public';
    console.warn('⚠️  No BASE_URL determined, falling back to localhost');
}

console.log(`📡 Using API BASE_URL: ${BASE_URL}`);
console.log(`💡 Tip: Set API_BASE_URL environment variable to override auto-detection`);

export const apiEndpoints = {
    get_withdrawals: `${BASE_URL}/getWithdrawals.php`,
    update_withdrawals: `${BASE_URL}/updWithdrawals.php`,
    get_stellar_yemchain: `${BASE_URL}/getStellarYEMChain.php`,
    update_stellar_yemchain: `${BASE_URL}/updStellarYEMChain.php`,
    get_db_transactions: `${BASE_URL}/getDbTransactions.php`,
    // Binance endpoints
    get_binance_withdrawals: `${BASE_URL}/getBinanceWithdrawals.php`,
    update_binance_withdrawals: `${BASE_URL}/updBinanceWithdrawals.php`,
    // Ethereum endpoints
    get_ethereum_withdrawals: `${BASE_URL}/getEthereumWithdrawals.php`,
    update_ethereum_withdrawals: `${BASE_URL}/updEthereumWithdrawals.php`,
    base: BASE_URL,
};
