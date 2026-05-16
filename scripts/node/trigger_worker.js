import axios from 'axios';
import { apiEndpoints } from './node_config.js';

/**
 * Triggers immediate processing of a specific withdrawal or all pending
 * Can be called from PHP when withdrawal is created
 */
async function triggerProcessing(withdrawalId = null) {
    try {
        const url = `${apiEndpoints.base}/trigger-withdrawal-worker.php`;
        const params = withdrawalId ? { id: withdrawalId } : {};
        
        const response = await axios.post(url, params, {
            headers: { 'Content-Type': 'application/json' },
            timeout: 5000
        });
        
        return response.data;
    } catch (error) {
        console.error('Failed to trigger worker:', error.message);
        return { success: false, error: error.message };
    }
}

// Allow running from command line for testing
if (import.meta.url === `file://${process.argv[1]}`) {
    const id = process.argv[2] || null;
    triggerProcessing(id).then(result => {
        console.log(JSON.stringify(result, null, 2));
        process.exit(result.success ? 0 : 1);
    });
}

export { triggerProcessing };

