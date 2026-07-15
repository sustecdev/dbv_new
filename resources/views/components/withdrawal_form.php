<?php
/**
 * Withdrawal Form Component
 * 
 * @param float $dbv DBV balance
 * @param float $usdd USDD balance
 * @param bool $feeEnabled Whether withdrawal fee is enabled
 * @param float $withdrawalFee Withdrawal fee amount
 * @param string $csrfToken CSRF token
 */
?>
<div class="bg-black border border-gray-800 rounded-xl p-6 shadow-lg">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-white">Withdraw to Stellar</h2>
    </div>
    <div class="mt-4 mb-5 p-4 bg-gray-900/50 border border-gray-800 rounded-lg">
        <div class="space-y-2 text-xs">
            <p class="text-gray-300">Available Balance: <span class="text-white font-bold text-sm"><?= number_format($dbv, 2) ?> DBV</span></p>
            <?php if ($feeEnabled ?? true): ?>
                <p class="text-gray-300">USDD Balance: <span class="text-white font-bold text-sm"><?= number_format($usdd, 2) ?> USDD</span></p>
                <p class="text-gray-300">Withdrawal Fee: <span class="text-red-400 font-bold text-sm"><?= number_format($withdrawalFee ?? 2.0, 2) ?> USDD</span> per withdrawal</p>
            <?php else: ?>
                <p class="text-gray-300">Withdrawal Fee: <span class="text-white">Disabled</span></p>
            <?php endif; ?>
            <p class="text-gray-300">Max per withdrawal: <span class="text-white font-semibold">5,000,000 DBV</span></p>
        </div>
    </div>
    <form id="withdraw-form" class="mt-5 space-y-4">
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
        <div>
            <label class="block text-sm text-gray-300 font-medium mb-2">Amount (DBV)</label>
            <input id="wdl-amount" type="number" step="0.01" min="0.01" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm text-white focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" placeholder="e.g. 10.00" />
            <p class="mt-1 text-xs text-gray-400">Minimum: 0.01 DBV</p>
        </div>
        <div>
            <label class="block text-sm text-gray-300 font-medium mb-2">Stellar Address</label>
            <input id="wdl-address" maxlength="56" pattern="[G][A-Z0-9]{55}" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm font-mono text-white focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" placeholder="G..." />
            <p class="mt-1 text-xs text-gray-400">Must be 56 characters starting with 'G'</p>
        </div>
        <div id="pin-section" class="hidden">
            <label class="block text-sm text-gray-300 font-medium mb-2">
                <span id="pin-instruction" class="font-bold text-red-400">Enter the following digits of your 6-digit PIN:</span>
            </label>
            <div id="pin-positions" class="mb-3 text-xs text-gray-300 bg-gray-900/50 p-3 rounded-lg border border-gray-800"></div>
            <input id="wdl-pin" type="password" maxlength="3" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm font-mono text-center text-xl tracking-widest text-white focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" placeholder="●●●" />
            <input type="hidden" id="wdl-key" value="" />
            <p class="mt-2 text-xs text-gray-400">Enter the 3 digits shown above from your 6-digit PIN</p>
        </div>
        <div class="pt-2">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-3 text-sm font-bold transition-colors shadow-lg hover:shadow-red-900/50">Initiate Withdrawal</button>
        </div>
    </form>
    <pre id="wdl-out" class="mt-4 text-xs text-gray-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800 hidden"></pre>
</div>

