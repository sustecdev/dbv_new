<?php
/**
 * Withdrawal Modal Component
 * 
 * @param float $dbv DBV balance
 * @param float $usdd USDD balance
 * @param bool $feeEnabled Whether withdrawal fee is enabled
 * @param float $withdrawalFee Withdrawal fee amount
 * @param string $csrfToken CSRF token
 */
?>
<!-- Withdrawal Modal -->
<div id="withdrawal-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-black border border-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-black border-b border-gray-800 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white">Withdraw DBV Tokens</h2>
            </div>
            <button id="close-withdrawal-modal" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-6">
            <div class="p-4 bg-gray-900/50 border border-gray-800 rounded-lg fee-info-container">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-300">Your DBV Balance:</span>
                        <span class="text-white font-bold"><?= number_format((float)($dbv ?? 0), 2) ?> DBV</span>
                    </div>
                    <div class="flex justify-between items-center usdd-balance-display">
                        <span class="text-gray-300">Your USDD Balance:</span>
                        <span class="text-white font-bold"><?= number_format((float)($usdd ?? 0), 2) ?> USDD</span>
                    </div>
                    <div class="withdrawal-fee-display flex justify-between items-center pt-1 border-t border-gray-700">
                        <span class="text-gray-300">Withdrawal Fee:</span>
                        <span class="text-red-400 font-bold"><?= number_format($withdrawalFee ?? 2.0, 2) ?> USDD</span>
                    </div>
                    <p class="text-xs text-gray-400 pt-1 fee-notice">You need sufficient USDD balance to cover the withdrawal fee</p>
                    <div class="flex justify-between items-center pt-1 border-t border-gray-700">
                        <span class="text-gray-300">Maximum Amount:</span>
                        <span class="text-white font-semibold">5,000,000 DBV</span>
                    </div>
                </div>
            </div>
            
            <form id="withdraw-form" class="space-y-4">
                <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <div>
                    <label class="block text-sm text-gray-300 font-medium mb-2">Withdrawal Amount</label>
                    <input id="wdl-amount" type="number" step="0.01" min="0.01" max="5000000" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-lg text-white focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" placeholder="0.00" />
                    <div class="mt-1 flex justify-between text-xs">
                        <span class="text-gray-400">Minimum: 0.01 DBV</span>
                        <span class="text-gray-400">Maximum: 5,000,000 DBV</span>
                    </div>
                </div>
                <div>
                    <label for="wdl-address" class="block text-sm text-gray-300 font-medium mb-2">Recipient Stellar Wallet Address</label>
                    <input id="wdl-address" maxlength="56" pattern="[G][A-Z0-9]{55}" class="w-full bg-black border border-gray-800 rounded-lg px-4 py-3 text-sm font-mono text-white focus:outline-none focus:border-gray-700 focus:ring-1 focus:ring-gray-700" placeholder="GXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" />
                    <p class="mt-1 text-xs text-gray-400 withdrawal-address-helper">Enter the Stellar wallet address where you want to receive your DBV tokens</p>
                </div>
                <!-- Confirmation Summary (shown before PIN) -->
                <div id="withdrawal-confirmation" class="hidden space-y-3">
                    <div class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg p-4">
                        <h3 class="text-sm font-bold text-yellow-400 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Review Your Withdrawal
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-300">Amount:</span>
                                <span id="confirm-amount" class="text-white font-bold"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-300">To Address:</span>
                                <span id="confirm-address" class="text-gray-300 font-mono text-xs"></span>
                            </div>
                            <div id="confirm-fee" class="hidden flex justify-between">
                                <span class="text-gray-300">Withdrawal Fee:</span>
                                <span class="text-red-400 font-semibold"></span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-yellow-700/30">
                                <span class="text-gray-300">Network:</span>
                                <span id="confirm-network" class="text-white font-semibold"></span>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <button id="confirm-proceed-btn" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-black font-bold py-2.5 px-4 rounded-lg transition-colors">
                                Continue to PIN Verification
                            </button>
                            <button id="confirm-cancel-btn" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors border border-gray-700">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="pin-section" class="hidden">
                    <label class="block text-sm text-gray-300 font-medium mb-2">
                        <span id="pin-instruction" class="font-bold text-red-400">Security Verification Required</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-3">For security, please enter the 3 digits from your 6-digit PIN at the positions shown below:</p>
                    <div id="pin-positions" class="mb-3 text-sm text-gray-300 bg-gray-900/50 p-4 rounded-lg border border-gray-800 font-medium"></div>
                    <input id="wdl-pin" type="password" maxlength="3" class="w-full bg-black border-2 border-gray-700 rounded-lg px-4 py-4 text-lg font-mono text-center tracking-[0.5em] text-white focus:outline-none focus:border-red-600 focus:ring-2 focus:ring-red-600/20" placeholder="000" />
                    <input type="hidden" id="wdl-key" value="" />
                    <p class="mt-2 text-xs text-gray-400">Type only the 3 digits shown in the box above</p>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-3 text-sm font-bold transition-colors shadow-lg hover:shadow-red-900/50">Confirm Withdrawal</button>
                </div>
            </form>
            <pre id="wdl-out" class="text-xs sm:text-sm text-gray-300 whitespace-pre-wrap break-words overflow-x-auto bg-black/50 p-3 rounded-lg border border-gray-800 hidden mt-4 max-w-full"></pre>
        </div>
    </div>
</div>

