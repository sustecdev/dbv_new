import http from 'http';
import { isManualModeEnabled, areWorkersDisabled } from './check_manual_mode.js';

const CHECK_INTERVAL = 60000; // Fallback polling every 60 seconds
const MANUAL_MODE_SLEEP_MS = 300000; // 5 min when manual mode ON
const HTTP_PORT = 3001; // Port for HTTP trigger endpoint

let isProcessing = false;

/**
 * Process withdrawals with better error handling and retry logic.
 * Skips entirely when manual withdraw mode is enabled.
 */
async function processWithdrawalsSafe() {
    if (areWorkersDisabled()) return;
    if (await isManualModeEnabled()) {
        return; // Workers completely idle when manual mode ON
    }
    if (isProcessing) {
        console.log(new Date().toISOString(), 'Already processing, skipping...');
        return;
    }

    isProcessing = true;
    try {
        const { processWithdrawals } = await import('./withdrawal_to_stellar_improved.js');
        const processed = await processWithdrawals();
        if (processed > 0) {
            console.log(new Date().toISOString(), `✅ Processed ${processed} withdrawal(s)`);
        }
    } catch (error) {
        console.error(new Date().toISOString(), '❌ Processing error:', error.message);
        console.error(error.stack);
    } finally {
        isProcessing = false;
    }
}

/**
 * Start HTTP server to accept immediate triggers
 */
function startHTTPServer() {
    const server = http.createServer(async (req, res) => {
        if (req.method === 'POST' && req.url === '/process') {
            let body = '';
            req.on('data', chunk => { body += chunk; });
            req.on('end', async () => {
                try {
                    const data = JSON.parse(body);
                    const withdrawalId = data.id || null;
                    
                    console.log(new Date().toISOString(), `🚀 Immediate trigger received${withdrawalId ? ` for withdrawal ID: ${withdrawalId}` : ''}`);
                    
                    // Process immediately
                    await processWithdrawalsSafe();
                    
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: true, message: 'Processing triggered' }));
                } catch (error) {
                    res.writeHead(500, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: false, error: error.message }));
                }
            });
        } else {
            res.writeHead(404);
            res.end();
        }
    });

    server.listen(HTTP_PORT, () => {
        console.log(new Date().toISOString(), `📡 HTTP trigger server listening on port ${HTTP_PORT}`);
        console.log(new Date().toISOString(), `   Trigger URL: http://localhost:${HTTP_PORT}/process`);
    });

    server.on('error', (err) => {
        if (err.code === 'EADDRINUSE') {
            console.log(new Date().toISOString(), `⚠️  Port ${HTTP_PORT} already in use, HTTP triggers disabled`);
        } else {
            console.error(new Date().toISOString(), 'HTTP server error:', err.message);
        }
    });
}

/**
 * Start continuous polling (fallback).
 * When manual mode is ON, workers sleep and re-check - no processing.
 */
async function startPolling() {
    console.log(new Date().toISOString(), `🔄 Starting polling loop (checking every ${CHECK_INTERVAL/1000} seconds)...`);

    while (true) {
        try {
            if (areWorkersDisabled()) {
                console.log(new Date().toISOString(), '⏹️  WORKERS_DISABLED - workers completely off');
                await new Promise(resolve => setTimeout(resolve, MANUAL_MODE_SLEEP_MS));
                continue;
            }
            if (await isManualModeEnabled()) {
                console.log(new Date().toISOString(), '⏸️  Manual withdraw mode ON - workers idle');
                await new Promise(resolve => setTimeout(resolve, MANUAL_MODE_SLEEP_MS));
                continue;
            }
            await processWithdrawalsSafe();
        } catch (error) {
            console.error(new Date().toISOString(), 'Polling error:', error.message);
        }

        await new Promise(resolve => setTimeout(resolve, CHECK_INTERVAL));
    }
}

// Main startup
if (areWorkersDisabled()) {
    console.log(new Date().toISOString(), '⏹️  WORKERS_DISABLED=true - Stellar worker will not process. Set WORKERS_DISABLED=false in .env to re-enable.');
}
console.log(new Date().toISOString(), '🚀 Starting improved withdrawal worker...');

// Start HTTP server for immediate triggers
startHTTPServer();

// Start polling as fallback
startPolling().catch(err => {
    console.error('Fatal error in polling loop:', err);
    process.exit(1);
});

// Handle graceful shutdown
process.on('SIGTERM', () => {
    console.log(new Date().toISOString(), 'Received SIGTERM, shutting down gracefully...');
    process.exit(0);
});

process.on('SIGINT', () => {
    console.log(new Date().toISOString(), 'Received SIGINT, shutting down gracefully...');
    process.exit(0);
});

