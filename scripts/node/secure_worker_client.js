import axios from 'axios';
import crypto from 'crypto';
import { apiEndpoints } from './node_config.js';

const WORKER_SECRET = process.env.WORKER_SECRET || (() => {
    throw new Error('WORKER_SECRET environment variable is required. Please set it in your .env file.');
})();

/**
 * Generate HMAC signature for secure worker authentication
 */
function generateSignature(uri, timestamp) {
    const dataToSign = timestamp + uri;
    return crypto.createHmac('sha256', WORKER_SECRET)
        .update(dataToSign)
        .digest('hex');
}

/**
 * Make authenticated request to worker endpoints
 */
async function secureWorkerRequest(url, params = {}) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const urlObj = new URL(url);
    const uri = urlObj.pathname + urlObj.search;
    const signature = generateSignature(uri, timestamp);
    
    try {
        const response = await axios.get(url, {
            params: Object.keys(params).length > 0 ? params : undefined,
            headers: {
                'X-Worker-Signature': signature,
                'X-Worker-Timestamp': timestamp
            },
            timeout: 20000
        });
        
        return response.data;
    } catch (error) {
        throw error;
    }
}

export { secureWorkerRequest, generateSignature };

