<?php
/**
 * Deposit Modal Component
 * 
 * @param string $vaultAddress Stellar vault address
 * @param string $csrfToken CSRF token
 */
?>
<!-- Deposit Modal -->
<div id="deposit-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-black border border-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-black border-b border-gray-800 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white">Deposit DBV Tokens</h2>
            </div>
            <button id="close-deposit-modal" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-6">
            <div id="vault-address-container" class="vault-address-container p-4 bg-gray-900/50 rounded-lg border border-gray-800" style="display: none;">
                <p class="vault-address-text text-xs text-gray-300 mb-2 font-medium">Step 1: Send your DBV tokens to this address:</p>
                <div class="flex items-center gap-2 group">
                    <code id="vault-address-code" class="flex-1 text-xs font-mono text-white break-all bg-black/50 px-3 py-2 rounded border border-gray-800"></code>
                    <button id="vault-copy-button" 
                            class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-2 rounded-lg hover:bg-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                </div>
                <div class="mt-3 space-y-1 vault-notice">
                    <p class="text-xs text-gray-400"><strong class="text-white">⚠️ Important:</strong> <span class="vault-notice-text">Send tokens to the address above.</span></p>
                </div>
            </div>
            
            <form id="deposit-form" class="space-y-4">
                <input type="hidden" id="deposit-csrf-token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <div>
                    <label class="block text-sm text-gray-300 font-medium mb-2">Step 2: Enter Transaction Hash</label>
                    <p class="text-xs text-gray-400 mb-2 deposit-hash-helper">After sending DBV, copy the transaction hash from your Stellar wallet</p>
                    <input id="dep-hash" type="text" pattern="[a-zA-Z0-9]{64}" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" maxlength="64" placeholder="Paste your 64-character transaction hash here" required />
                    <p class="mt-1 text-xs text-gray-400 deposit-hash-helper-2">The transaction hash is a 64-character alphanumeric code</p>
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-3 text-sm font-bold transition-colors shadow-lg hover:shadow-red-900/50">Complete Deposit</button>
            </form>
            <pre id="dep-out" class="text-xs sm:text-sm text-gray-300 whitespace-pre-wrap break-words overflow-x-auto bg-black/50 p-3 rounded-lg border border-gray-800 hidden max-w-full"></pre>
        </div>
    </div>
</div>

