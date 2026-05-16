<?php
/**
 * Balance Cards Component
 * 
 * @param float $dbv DBV balance
 * @param float $usdd USDD balance
 */
?>
<section class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div class="bg-black border border-gray-800 rounded-xl p-6 shadow-lg hover:shadow-gray-900/20 transition-all hover:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <div class="text-gray-400 text-sm font-semibold uppercase tracking-wide">Your DBV Balance</div>
            <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="text-4xl font-bold text-white"><?= number_format((float)($dbv ?? 0), 2) ?> <span class="text-lg font-normal text-gray-400">DBV</span></div>
    </div>
    <div class="bg-black border border-gray-800 rounded-xl p-6 shadow-lg hover:shadow-gray-900/20 transition-all hover:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <div class="text-gray-400 text-sm font-semibold uppercase tracking-wide">Your USDD Balance</div>
            <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="text-4xl font-bold text-white"><?= number_format((float)($usdd ?? 0), 2) ?> <span class="text-lg font-normal text-gray-400">USDD</span></div>
    </div>
</section>

