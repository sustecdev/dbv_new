<?php
/**
 * Enhanced SafeZone Login Page
 * Integrated with existing DBV Bridge authentication system
 * 
 * This is an enhanced version with language support, navigation, and full features
 */

// Load required classes (will be loaded by public/safezone.php, but just in case)
// Use @ to suppress errors if paths are wrong - public/safezone.php should have loaded these
if (!class_exists('PathHelper')) {
    $pathHelperFile = __DIR__ . '/../../app/Support/PathHelper.php';
    if (file_exists($pathHelperFile)) {
        require_once $pathHelperFile;
    }
}

// Initialize language array with defaults
$_lang = array();
$lang = 'EN';

// Try to load language files (optional - won't break if missing)
$langDir = __DIR__ . '/../../lang';
$locationFile = __DIR__ . '/../../location.php';

// Try to include location.php safely
if (file_exists($locationFile)) {
    try {
        include($locationFile);
        if (isset($languageCode) && !empty($languageCode)) {
            $lang = strtoupper($languageCode);
        }
    } catch (Exception $e) {
        // Silently fail - use default
        $lang = 'EN';
    }
}

// Check for language override from GET or COOKIE
if (isset($_GET['lang']) && $_GET['lang'] != '') {
    $lang = strtoupper($_GET['lang']);
    // Set secure cookie (domain parameter can be null/empty for current domain)
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    @setcookie("lang", $lang, [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'domain' => '', // Empty = current domain
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} elseif (isset($_COOKIE['lang']) && $_COOKIE['lang'] != '') {
    $lang = strtoupper($_COOKIE['lang']);
}

// Try to load language file
if (is_dir($langDir)) {
    $langfile = $langDir . '/' . strtolower($lang) . ".inc.php";
    if (file_exists($langfile)) {
        include($langfile);
    } else {
        // Fallback to English
        $langfile = $langDir . '/en.inc.php';
        if (file_exists($langfile)) {
            include($langfile);
        }
    }
}

// Translation function
function TranslateText($text) {
    global $_lang;
    if (isset($_lang[$text]) && !empty($_lang[$text])) {
        return $_lang[$text];
    }
    return $text;
}

// Get reference number from cookie if available
$refNum = "";
if (isset($_COOKIE['ref_pernum'])) {
    $refNum = $_COOKIE['ref_pernum'];
}

// Check development mode
$DEVELOPMENT_MODE = defined('DEVELOPMENT_MODE') ? DEVELOPMENT_MODE : false;

// Domain configuration
$currentDomain = $_SERVER['SERVER_NAME'] ?? 'digitalbenefits.exchange';
$isLocalhost = ($currentDomain === 'localhost' || strpos($currentDomain, '127.0.0.1') !== false);
$safezoneDomain = 'digitalbenefits.exchange'; // Always use production domain for SafeZone

// Error handling
$error = $_GET['error'] ?? '';

// Helper function to safely get URL
function safeUrl($path) {
    if (class_exists('PathHelper') && method_exists('PathHelper', 'url')) {
        try {
            return PathHelper::url($path);
        } catch (Exception $e) {
            // Fallback to relative path
            return $path;
        }
    }
    // Fallback to relative path if PathHelper not available
    return $path;
}

// Image paths (make them optional)
$headerImagePath = __DIR__ . '/../../public/images/dbvheader.jpg';
$sealImagePath = __DIR__ . '/../../public/images/sz_seal-min.png';

$headerImage = file_exists($headerImagePath) ? safeUrl('images/dbvheader.jpg') : '';
$sealImage = file_exists($sealImagePath) ? safeUrl('images/sz_seal-min.png') : '';
?>
<!DOCTYPE html>
<html lang="<?= strtolower($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeZone Login - Digital Benefits Exchange</title>
    <meta name="description" content="<?= TranslateText('Secure login with SafeZone authentication system for Digital Benefits Exchange.') ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#DC2626',
                        'dark-red': '#B91C1C',
                        'light-red': '#FEE2E2',
                        'custom-black': '#0F0F0F',
                        'custom-gray': '#1F1F1F',
                        'custom-light-gray': '#F5F5F5'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                        'display': ['Poppins', 'system-ui', 'sans-serif']
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'slide-down': 'slideDown 0.6s ease-out',
                        'scale-in': 'scaleIn 0.5s ease-out'
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap');
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0F0F0F 0%, #1F1F1F 100%);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(220, 38, 38, 0.2);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .safezone-seal {
            filter: drop-shadow(0 10px 30px rgba(220, 38, 38, 0.3));
        }
    </style>
</head>
<body class="bg-custom-black text-white font-sans">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 glass-effect"> 
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                <?php if ($headerImage): ?>
                    <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block">
                        <img src="<?= htmlspecialchars($headerImage, ENT_QUOTES, 'UTF-8') ?>" alt="DBV Platform" class="h-10 w-auto hover:opacity-80 transition-opacity duration-300">
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-xl font-bold text-white">DBV Bridge</a>
                <?php endif; ?>
                </div>
                
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red px-3 py-2 text-sm font-medium transition-colors duration-200"><?= TranslateText('HOME') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#features'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red px-3 py-2 text-sm font-medium transition-colors duration-200"><?= TranslateText('FEATURES') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#ecosystem'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red px-3 py-2 text-sm font-medium transition-colors duration-200"><?= TranslateText('ECOSYSTEM') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#about'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red px-3 py-2 text-sm font-medium transition-colors duration-200"><?= TranslateText('ABOUT') ?></a>
                    </div>
                </div>
                
                <!-- Language Selector -->
                <div class="hidden md:flex items-center space-x-4">
                    <select id="language-selector" class="bg-custom-gray text-white border border-gray-600 rounded px-3 py-1 text-sm focus:outline-none focus:border-primary-red">
                        <option value="EN" <?= $lang === 'EN' ? 'selected' : '' ?>>English</option>
                        <option value="DE" <?= $lang === 'DE' ? 'selected' : '' ?>>Deutsch</option>
                        <option value="ES" <?= $lang === 'ES' ? 'selected' : '' ?>>Español</option>
                        <option value="FR" <?= $lang === 'FR' ? 'selected' : '' ?>>Français</option>
                        <option value="PT" <?= $lang === 'PT' ? 'selected' : '' ?>>Português</option>
                    </select>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white hover:text-primary-red focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden glass-effect">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                        <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red block px-3 py-2 text-base font-medium"><?= TranslateText('HOME') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#features'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red block px-3 py-2 text-base font-medium"><?= TranslateText('FEATURES') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#ecosystem'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red block px-3 py-2 text-base font-medium"><?= TranslateText('ECOSYSTEM') ?></a>
                        <a href="<?= htmlspecialchars(safeUrl('index.php#about'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-primary-red block px-3 py-2 text-base font-medium"><?= TranslateText('ABOUT') ?></a>
                
                <!-- Mobile Language Selector -->
                <div class="px-3 py-2">
                    <select id="mobile-language-selector" class="w-full bg-custom-gray text-white border border-gray-600 rounded px-3 py-2 text-sm focus:outline-none focus:border-primary-red">
                        <option value="EN" <?= $lang === 'EN' ? 'selected' : '' ?>>English</option>
                        <option value="DE" <?= $lang === 'DE' ? 'selected' : '' ?>>Deutsch</option>
                        <option value="ES" <?= $lang === 'ES' ? 'selected' : '' ?>>Español</option>
                        <option value="FR" <?= $lang === 'FR' ? 'selected' : '' ?>>Français</option>
                        <option value="PT" <?= $lang === 'PT' ? 'selected' : '' ?>>Português</option>
                    </select>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="min-h-screen flex items-center justify-center gradient-bg relative overflow-hidden pt-20 sm:pt-24 lg:pt-0">
        <!-- Animated background elements -->
        <div class="absolute top-20 left-10 w-20 h-20 bg-primary-red/20 rounded-full animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-primary-red/10 rounded-full animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-primary-red/15 rounded-full animate-pulse delay-500"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 bg-primary-red/10 rounded-full animate-pulse delay-1500"></div>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center">
                <!-- SafeZone Seal -->
                <?php if ($sealImage): ?>
                    <div class="mb-8 animate-fade-in">
                        <img src="<?= htmlspecialchars($sealImage, ENT_QUOTES, 'UTF-8') ?>" alt="SafeZone Seal" class="mx-auto h-32 w-auto safezone-seal">
                    </div>
                <?php endif; ?>
                
                <!-- Error Messages -->
                <?php if ($error === 'invalid'): ?>
                    <div class="max-w-md mx-auto mb-6 animate-slide-down">
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-red-400 font-medium text-sm"><?= TranslateText('Authentication Failed') ?></p>
                                    <p class="text-red-300 text-xs mt-1"><?= TranslateText('No valid user ID received from SafeZone.') ?></p>
                                    
                                    <div class="mt-3 pt-3 border-t border-red-800">
                                        <p class="text-red-400 font-medium text-xs mb-2"><?= TranslateText('Possible Causes:') ?></p>
                                        <ul class="text-red-300 text-xs space-y-1.5 list-disc list-inside">
                                            <li><?= TranslateText('SafeZone domain not configured/whitelisted') ?></li>
                                            <li><?= TranslateText('User not authenticated on SafeZone') ?></li>
                                            <li><?= TranslateText('Callback URL mismatch in SafeZone settings') ?></li>
                                        </ul>
                                    </div>
                                    
                                    <?php if ($isLocalhost): ?>
                                        <div class="mt-3 pt-3 border-t border-red-800">
                                            <p class="text-red-300 text-xs"><?= TranslateText('Note: SafeZone doesn\'t support localhost. Use a proper domain for testing.') ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isLocalhost): ?>
                                        <div class="mt-3 pt-3 border-t border-red-800">
                                            <p class="text-red-300 text-xs"><?= TranslateText('Please ensure your domain is whitelisted in SafeZone and the callback URL matches:') ?></p>
                                            <?php 
                                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                                            $callbackPath = safeUrl('auth.php');
                                            // Ensure we have a proper path
                                            if (strpos($callbackPath, 'http') === 0) {
                                                $callbackUrl = $callbackPath;
                                            } else {
                                                $callbackUrl = $protocol . $safezoneDomain . '/' . ltrim($callbackPath, '/');
                                            }
                                            ?>
                                            <p class="text-red-200 text-xs font-mono mt-1 break-all"><?= htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($error === 'domain'): ?>
                    <div class="max-w-md mx-auto mb-6 animate-slide-down">
                        <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4">
                            <p class="text-yellow-400 font-medium text-sm"><?= TranslateText('Domain Mismatch') ?></p>
                            <p class="text-yellow-300 text-xs mt-1"><?= TranslateText('The callback domain does not match the expected domain.') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Main Heading -->
                <div class="mb-8 animate-slide-up">
                    <?php if ($DEVELOPMENT_MODE): ?>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-display font-bold mb-4">
                            <span class="text-white"><?= TranslateText('DEVELOPMENT') ?></span>
                            <span class="text-gradient"> <?= TranslateText('MODE') ?></span>
                        </h1>
                        <p class="text-lg sm:text-xl text-gray-300 max-w-2xl mx-auto leading-relaxed">
                            <?= TranslateText('Coming Soon. Only the Development Team are able to login at the moment.') ?>
                        </p>
                    <?php else: ?>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-display font-bold mb-4">
                            <span class="text-white"><?= TranslateText('SECURE') ?></span>
                            <span class="text-gradient"> <?= TranslateText('LOGIN') ?></span>
                        </h1>
                        <p class="text-lg sm:text-xl text-gray-300 max-w-2xl mx-auto leading-relaxed">
                            <?= TranslateText('This Website is protected by SafeZone') ?>&trade;
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Login Options -->
                <div class="max-w-md mx-auto space-y-4 animate-scale-in">
                    <div class="glass-effect p-6 sm:p-8 rounded-xl">
                        <h2 class="text-xl font-display font-semibold text-white mb-6">
                            <?= TranslateText('Choose Your Login Method') ?>
                        </h2>
                        
                        <div class="space-y-4">
                            <!-- Login with SafeZonePass -->
                            <a href="https://safe.zone/login.php?external=1&domain=<?= htmlspecialchars($safezoneDomain, ENT_QUOTES, 'UTF-8') ?>" 
                               class="block w-full bg-primary-red hover:bg-dark-red text-white px-6 py-4 rounded-lg text-lg font-semibold transition-all duration-300 hover-lift">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><?= TranslateText('LOGIN WITH SAFEZONEPASS') ?></span>
                                </div>
                            </a>
                            
                            <!-- Get Free SafeZonePass -->
                            <a href="https://safe.zone/signup_l.php?external=1<?= !empty($refNum) ? '&ref_pernum=' . htmlspecialchars($refNum, ENT_QUOTES, 'UTF-8') : '' ?>&domain=<?= htmlspecialchars($safezoneDomain, ENT_QUOTES, 'UTF-8') ?>&t=pro" 
                               class="block w-full bg-gray-700 hover:bg-gray-600 text-white px-6 py-4 rounded-lg text-lg font-semibold transition-all duration-300 hover-lift">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    <span><?= TranslateText('GET YOUR FREE SAFEZONEPASS') ?></span>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Development Note (Localhost Only) -->
                        <?php if ($isLocalhost): ?>
                            <div class="mt-6 pt-6 border-t border-gray-700">
                                <div class="bg-blue-900/20 border border-blue-800 rounded-lg p-4">
                                    <p class="text-blue-400 font-medium text-sm mb-2">🧪 <?= TranslateText('Development Mode (Localhost)') ?></p>
                                    <p class="text-blue-300 text-xs"><?= TranslateText('SafeZone doesn\'t support localhost callbacks. Use a proper domain or staging environment for testing.') ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Back to Home -->
                <div class="mt-8 animate-slide-up">
                    <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center text-gray-400 hover:text-white transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <?= TranslateText('Back to Home') ?>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-custom-gray py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <?php if ($headerImage): ?>
                    <a href="<?= htmlspecialchars(safeUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block mb-4">
                        <img src="<?= htmlspecialchars($headerImage, ENT_QUOTES, 'UTF-8') ?>" alt="DBV Platform" class="h-8 w-auto hover:opacity-80 transition-opacity duration-300">
                    </a>
                <?php endif; ?>
                <p class="text-gray-400 text-sm">
                    (c) <?php echo date("Y"); ?> Digital Network Holding, Inc., approved by Digital Assets Foundation
                </p>
                <p class="text-gray-500 text-xs mt-2">
                    <?= TranslateText('Protected by SafeZone Security') ?>&trade;
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Language selector functionality
        const langParam = new URLSearchParams(window.location.search).get('lang');
        const currentUrl = new URL(window.location.href);
        
        document.getElementById('language-selector')?.addEventListener('change', function() {
            currentUrl.searchParams.set('lang', this.value);
            window.location.href = currentUrl.toString();
        });

        document.getElementById('mobile-language-selector')?.addEventListener('change', function() {
            currentUrl.searchParams.set('lang', this.value);
            window.location.href = currentUrl.toString();
        });

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>

