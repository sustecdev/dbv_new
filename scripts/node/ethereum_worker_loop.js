import { processWithdrawals } from './withdrawal_to_ethereum.js';
import { isManualModeEnabled, areWorkersDisabled } from './check_manual_mode.js';
import path from 'path';
import fs from 'fs';

/**
 * Load config from .env file (project root)
 */
async function loadConfigFromEnv() {
    try {
        let envPath = path.join(process.cwd(), '.env');
        if (!fs.existsSync(envPath)) {
            envPath = path.join(process.cwd(), 'env.example');
        }

        if (fs.existsSync(envPath)) {
            const envContent = fs.readFileSync(envPath, 'utf8');
            const lines = envContent.split('\n');

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed || trimmed.startsWith('#')) continue;

                const [key, ...valueParts] = trimmed.split('=');
                if (key && valueParts.length > 0) {
                    const value = valueParts.join('=').trim();
                    // Only set if not already in process.env
                    if (!process.env[key] && value) {
                        process.env[key] = value;
                    }
                }
            }

            console.log(new Date().toISOString(), `✅ Loaded config from: ${envPath}`);
        } else {
            console.log(new Date().toISOString(), '⚠️  No .env file found. Using environment variables only.');
        }
    } catch (e) {
        console.log(new Date().toISOString(), `⚠️  Could not load .env file: ${e.message}`);
    }
}

// Load .env before starting
await loadConfigFromEnv();

const CHECK_INTERVAL = 60000; // 60 seconds

if (areWorkersDisabled()) {
    console.log(new Date().toISOString(), '⏹️  WORKERS_DISABLED=true - Ethereum worker will not process. Set WORKERS_DISABLED=false in .env to re-enable.');
}
console.log(new Date().toISOString(), `🚀 Starting Ethereum withdrawal worker (checking every ${CHECK_INTERVAL / 1000} seconds)...`);

async function runCycle() {
    if (areWorkersDisabled()) {
        console.log(new Date().toISOString(), '⏹️  WORKERS_DISABLED - Ethereum worker off');
        return;
    }
    if (await isManualModeEnabled()) {
        console.log(new Date().toISOString(), '⏸️  Manual withdraw mode ON - Ethereum worker idle');
        return;
    }
    try {
        const processed = await processWithdrawals();
        if (processed > 0) {
            console.log(new Date().toISOString(), `✅ Processed ${processed} Ethereum withdrawal(s)`);
        }
    } catch (error) {
        console.error(new Date().toISOString(), '❌ Ethereum worker error:', error.message);
    }
}

setInterval(runCycle, CHECK_INTERVAL);

// Process immediately on start
runCycle().catch(err => {
    console.error(new Date().toISOString(), '❌ Initial Ethereum processing error:', err.message);
});

// Global error handlers to prevent crashes
process.on('unhandledRejection', (reason, promise) => {
    console.error(new Date().toISOString(), '❌ Unhandled Promise Rejection:', reason);
    console.error('Promise:', promise);
});

process.on('uncaughtException', (error) => {
    console.error(new Date().toISOString(), '❌ Uncaught Exception:', error.message);
    console.error(error.stack);
});

console.log(new Date().toISOString(), '✅ Ethereum worker initialized with error handlers');
