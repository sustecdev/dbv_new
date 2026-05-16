<?php
/**
 * Deposit Form Component
 * 
 * @param string $vaultAddress Stellar vault address
 * @param string $csrfToken CSRF token
 */
?>
<div class="bg-black border border-gray-800 rounded-xl p-6 shadow-lg">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-white">Deposit from Stellar</h2>
    </div>
    <?php if (!empty($vaultAddress)): ?>
        <div class="mt-4 p-4 bg-gray-900/50 rounded-lg border border-gray-800">
            <p class="text-xs text-gray-300 mb-2 font-medium">Send DBV to this Stellar address:</p>
            <div class="flex items-center gap-2 group">
                <code class="flex-1 text-xs font-mono text-white break-all bg-black/50 px-3 py-2 rounded border border-gray-800"><?= htmlspecialchars($vaultAddress) ?></code>
                <button onclick="copyToClipboard('<?= htmlspecialchars($vaultAddress) ?>', this)" 
                        class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-2 rounded-lg hover:bg-gray-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </button>
            </div>
            <div class="mt-3 space-y-1">
                <p class="text-xs text-gray-400"><strong class="text-white">Important:</strong> Send a <strong class="text-white">direct payment</strong> (not a claimable balance) to this address</p>
                <p class="text-xs text-gray-400">In your wallet, use "Send Payment" or "Payment" option, NOT "Create Claimable Balance"</p>
            </div>
        </div>
    <?php endif; ?>
    <form id="deposit-form" class="mt-5 space-y-4">
        <input type="hidden" id="deposit-csrf-token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <div>
            <label class="block text-sm text-gray-300 font-medium mb-2">Stellar Transaction Hash</label>
            <input id="dep-hash" type="text" pattern="[a-zA-Z0-9]{64}" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" maxlength="64" placeholder="Enter 64-character transaction hash" required />
        </div>
        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-3 text-sm font-bold transition-colors shadow-lg hover:shadow-red-900/50">Submit Deposit</button>
    </form>
    <pre id="dep-out" class="mt-4 text-xs text-gray-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800 hidden"></pre>
</div>

