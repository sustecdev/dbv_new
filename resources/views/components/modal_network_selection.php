<?php
/**
 * Network Selection Modal Component
 * Allows user to choose between Stellar, Binance, or Ethereum
 * 
 * @param string $actionType 'deposit' or 'withdraw'
 */
?>
<!-- Network Selection Modal -->
<div id="network-selection-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-black border border-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-black border-b border-gray-800 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                    </svg>
                </div>
                <h2 id="network-selection-title" class="text-xl font-bold text-white">Select Network</h2>
            </div>
            <button id="close-network-selection-modal" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-6">
            <p class="text-sm text-gray-400 mb-6">Choose the blockchain network you want to use:</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Stellar Option -->
                <button data-network="stellar" class="network-option bg-black border-2 border-gray-800 hover:border-red-600 rounded-xl p-6 shadow-lg hover:shadow-red-900/20 transition-all group text-left">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-gray-900 group-hover:bg-red-600/20 rounded-xl flex items-center justify-center transition-colors">
                            <svg class="w-8 h-8 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-white">Stellar</h3>
                        <p class="text-xs text-gray-400 text-center">Fast and low-cost transactions</p>
                    </div>
                </button>

                <!-- Binance Option -->
                <button data-network="binance" class="network-option bg-black border-2 border-gray-800 hover:border-red-600 rounded-xl p-6 shadow-lg hover:shadow-red-900/20 transition-all group text-left">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-gray-900 group-hover:bg-red-600/20 rounded-xl flex items-center justify-center transition-colors">
                            <svg class="w-8 h-8 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-white">Binance</h3>
                        <p class="text-xs text-gray-400 text-center">BSC network - Low fees</p>
                    </div>
                </button>

                <!-- Ethereum Option -->
                <button data-network="ethereum" class="network-option bg-black border-2 border-gray-800 hover:border-red-600 rounded-xl p-6 shadow-lg hover:shadow-red-900/20 transition-all group text-left">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-gray-900 group-hover:bg-red-600/20 rounded-xl flex items-center justify-center transition-colors">
                            <svg class="w-8 h-8 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-white">Ethereum</h3>
                        <p class="text-xs text-gray-400 text-center">ETH mainnet - Most widely used</p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>
