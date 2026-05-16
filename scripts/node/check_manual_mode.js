/**
 * Check if manual withdraw mode is enabled.
 * When enabled, workers should not process any withdrawals.
 */
import { apiEndpoints } from './node_config.js';

/** Check if workers are disabled via WORKERS_DISABLED env (true/1/yes/on). Takes precedence over everything. */
export function areWorkersDisabled() {
    const v = (process.env.WORKERS_DISABLED || '').trim().toLowerCase();
    return ['1', 'true', 'yes', 'on'].includes(v);
}

export async function isManualModeEnabled() {
    try {
        const url = `${apiEndpoints.base}/api/check-manual-mode.php`;
        const res = await fetch(url);
        if (!res.ok) return false;
        const data = await res.json();
        return data.manual_withdraw_enabled === true;
    } catch (e) {
        return false; // On error, assume manual OFF so workers continue
    }
}
