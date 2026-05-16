<?php
/**
 * Binance Deposit Modal Component
 */
$bscVaultAddress = $cfg['binance']['vault_address'] ?? '';
$csrfToken = Security::getCsrfToken();
?>
<!-- Binance Deposit Modal -->
<div id="binance-deposit-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-black border border-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-black border-b border-gray-800 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white">Deposit DBV via Binance Smart Chain</h2>
            </div>
            <button id="close-binance-deposit-modal" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6 space-y-6">
            <?php if (!empty($bscVaultAddress)): ?>
                <div class="p-4 bg-gray-900/50 rounded-lg border border-gray-800">
                    <p class="text-xs text-gray-300 mb-2 font-medium">Step 1: Send your DBV tokens to this BSC address:</p>
                    <div class="flex items-center gap-2 group">
                        <code class="flex-1 text-xs font-mono text-white break-all bg-black/50 px-3 py-2 rounded border border-gray-800"><?= htmlspecialchars($bscVaultAddress) ?></code>
                        <button onclick="copyToClipboard('<?= htmlspecialchars($bscVaultAddress) ?>', this)" class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-2 rounded-lg hover:bg-gray-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Send DBV tokens (ERC-20) to this address using your BSC-compatible wallet</p>
                </div>
            <?php endif; ?>
            <form id="binance-deposit-form" class="space-y-4">
                <input type="hidden" id="binance-deposit-csrf-token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div>
                    <label class="block text-sm text-gray-300 font-medium mb-2">Step 2: Enter Transaction Hash</label>
                    <p class="text-xs text-gray-400 mb-2">After sending DBV, copy the transaction hash from your wallet</p>
                    <input id="binance-dep-hash" type="text" pattern="0x[a-fA-F0-9]{64}" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" maxlength="66" placeholder="0x..." required />
                    <p class="mt-1 text-xs text-gray-400">The transaction hash is a 66-character hex string starting with 0x</p>
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-3 text-sm font-bold transition-colors shadow-lg hover:shadow-red-900/50">Complete Deposit</button>
            </form>
            <pre id="binance-dep-out" class="text-xs text-gray-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800 hidden"></pre>
        </div>
    </div>
</div>

