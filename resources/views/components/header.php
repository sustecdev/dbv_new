<?php
/**
 * Dashboard Header Component
 */
// PathHelper should already be loaded by the parent view (dashboard.php)
// But include it if not available as a fallback
if (!class_exists('PathHelper')) {
    require_once __DIR__ . '/../../../app/Support/PathHelper.php';
}
$uid = $_SESSION['uid'] ?? null;
?>
<header class="sticky top-0 z-10 bg-black border-b border-gray-800 shadow-lg">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-800 border border-gray-700 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-lg">DB</span>
            </div>
        </div>
        <nav class="flex items-center gap-6 text-sm">
            <?php if ($uid): ?>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-900 border border-gray-700 rounded-lg">
                    <span class="text-gray-400 text-xs font-medium">pernum:</span>
                    <span class="text-white font-bold"><?= htmlspecialchars((int)$uid + 1000000000, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($isAdmin)): ?>
                <a class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium" href="<?= htmlspecialchars(PathHelper::url('admin.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Admin Panel
                </a>
            <?php endif; ?>
            
            <a class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-medium" href="<?= htmlspecialchars(PathHelper::publicAsset('logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout
            </a>
        </nav>
    </div>
</header>

