<?php
/**
 * Action Buttons Component
 * Simple Deposit and Withdraw buttons that open network selection
 */
?>
<section class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <button id="open-deposit-network-modal" class="bg-black border-2 border-gray-800 hover:border-red-600 rounded-xl p-8 shadow-lg hover:shadow-red-900/20 transition-all group">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-16 h-16 bg-gray-900 group-hover:bg-red-600/20 rounded-xl flex items-center justify-center transition-colors">
                <svg class="w-8 h-8 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <div class="text-left">
                <h3 class="text-xl font-bold text-white mb-1">Deposit DBV</h3>
                <p class="text-sm text-gray-400">Choose network and deposit tokens</p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-red-600 font-medium">
            <span>Click to Deposit</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </div>
    </button>

    <button id="open-withdraw-network-modal" class="bg-black border-2 border-gray-800 hover:border-red-600 rounded-xl p-8 shadow-lg hover:shadow-red-900/20 transition-all group">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-16 h-16 bg-gray-900 group-hover:bg-red-600/20 rounded-xl flex items-center justify-center transition-colors">
                <svg class="w-8 h-8 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <div class="text-left">
                <h3 class="text-xl font-bold text-white mb-1">Withdraw DBV</h3>
                <p class="text-sm text-gray-400">Choose network and withdraw tokens</p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-red-600 font-medium">
            <span>Click to Withdraw</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </div>
    </button>
</section>

