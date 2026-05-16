<?php
/**
 * Shared footer component - used by landing page and login page
 * Requires PathHelper and $loginUrl (defaults to safezone.php)
 */
$loginUrl = $loginUrl ?? PathHelper::url('safezone.php');
?>
<footer class="border-t border-gray-800 py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-black/50">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="<?= htmlspecialchars(PathHelper::url('dbvheader.jpg'), ENT_QUOTES, 'UTF-8') ?>" 
                         alt="DBV Bridge" 
                         class="h-12 sm:h-16 w-auto">
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Secure cross-chain transactions for DBV tokens across multiple blockchain networks.
                </p>
            </div>
            <div>
                <h4 class="font-semibold mb-4 text-white">Quick Links</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white transition-colors">Sign In</a></li>
                    <li><a href="<?= htmlspecialchars(PathHelper::url('landing.php') . '#networks', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white transition-colors">Networks</a></li>
                    <li><a href="<?= htmlspecialchars(PathHelper::url('landing.php') . '#features', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white transition-colors">Features</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 text-center">
            <p class="text-sm text-gray-500">
                (c) <?= date('Y') ?> Digital Network Holding, Inc., approved by Digital Assets Foundation
            </p>
        </div>
    </div>
</footer>
