<?php
require_once __DIR__ . '/../../app/Support/PathHelper.php';
?>
<!doctype html>
<html lang="en" class="h-full bg-gray-900">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>SafeZone Login - Digital Benefits Exchange</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (prefers-color-scheme: dark) {
            body { background-color: #111827; }
        }
        .login-gradient {
            background: linear-gradient(135deg, #1f2937 0%, #111827 50%, #0f172a 100%);
        }
        .card-glow {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.1), 0 0 60px rgba(239, 68, 68, 0.05);
        }
    </style>
</head>
<body class="min-h-screen login-gradient flex flex-col px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex-1 flex items-center justify-center">
    <div class="w-full max-w-md">
        <!-- Logo/Brand Section -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <a href="<?= htmlspecialchars(PathHelper::url('landing.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block">
                    <img src="<?= htmlspecialchars(PathHelper::url('dbvheader.jpg'), ENT_QUOTES, 'UTF-8') ?>" 
                         alt="DBV Bridge" 
                         class="h-16 sm:h-20 w-auto mx-auto">
                </a>
            </div>
            <p class="text-gray-400 text-sm">Secure access with SafeZone</p>
        </div>

        <!-- Login Card -->
        <div class="bg-black border border-gray-800 rounded-2xl p-8 shadow-2xl card-glow">
            <?php
            $step = $_GET['step'] ?? '1';
            $error = $_GET['error'] ?? '';
            $loginUrl = PathHelper::url('login.php');
            ?>

            <?php if ($step === '1'): ?>
                <!-- Step 1: Login with pernum and password -->
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">Welcome Back</h2>
                        <p class="text-gray-400 text-sm">Sign in to access your DBV wallet and manage your cross-chain transactions</p>
                    </div>

                    <!-- Error Messages -->
                    <?php if ($error === 'missing_fields'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">Please fill in all fields.</p>
                        </div>
                    <?php elseif ($error === 'invalid_credentials'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-red-400 text-sm font-medium">These credentials do not match our records.</p>
                                    <p class="text-red-300 text-xs mt-1">Please check your pernum and password and try again.</p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($error === 'api_error'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">Authentication service error. Please try again later.</p>
                        </div>
                    <?php elseif ($error === 'invalid_response'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">Invalid response from authentication service.</p>
                            <?php if (isset($_GET['debug'])): ?>
                                <p class="text-red-300 text-xs mt-2">Debug: <?= htmlspecialchars($_GET['debug'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-red-300 text-xs mt-1">Check logs/safezone_login_debug.log for details.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($error === 'empty_response'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">Empty response from authentication service. Please check your API configuration.</p>
                        </div>
                    <?php elseif ($error === 'api_key_missing'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm font-medium">API Configuration Error</p>
                            <p class="text-red-300 text-xs mt-1">SafeZone API key is not configured. Please set SAFEZONE_API_KEY in your .env file.</p>
                        </div>
                    <?php elseif ($error === 'pernum_restricted'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <div>
                                    <p class="text-red-400 text-sm font-medium">Access Restricted</p>
                                    <p class="text-red-300 text-xs mt-1">This account is not authorized to access the platform. Please contact support if you believe this is an error.</p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($error === 'uid_restricted'): ?>
                        <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-yellow-400 text-sm font-medium">Application Not Launched Yet</p>
                                    <p class="text-yellow-300 text-xs mt-1">The application is currently in development and not available for public access. Please check back later.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" action="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                        <input type="hidden" name="step" value="1">
                        
                        <div>
                            <label for="pernum" class="block text-sm font-medium text-gray-300 mb-2">Pernum</label>
                            <input type="text" 
                                   id="pernum" 
                                   name="pernum" 
                                   required
                                   autocomplete="username"
                                   class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
                                   placeholder="Enter your pernum">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required
                                   autocomplete="current-password"
                                   class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
                                   placeholder="Enter your password">
                        </div>

                        <button type="submit" 
                                class="w-full flex items-center justify-center gap-3 bg-red-600 hover:bg-red-700 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-red-900/50 transform hover:scale-[1.02] active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            <span>Login</span>
                        </button>
                    </form>
                </div>

            <?php elseif ($step === '2'): ?>
                <!-- Step 2: Master PIN Entry -->
                <?php
                // Get the key from URL or session
                $displayKey = $_GET['key'] ?? $_SESSION['login_pin_key'] ?? '';
                if (empty($displayKey) && isset($_SESSION['login_pin_key'])) {
                    $displayKey = $_SESSION['login_pin_key'];
                }
                ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">Enter Master PIN</h2>
                        <p class="text-gray-400 text-sm">Enter the 3 digits from your master PIN as indicated by the key below</p>
                    </div>
                    
                    <?php if (!empty($displayKey)): ?>
                        <!-- Display the generated key prominently -->
                        <div class="bg-blue-600 rounded-xl p-6 text-center">
                            <p class="text-blue-100 text-sm mb-2">Enter your PIN digits in this order:</p>
                            <div class="text-5xl font-bold text-white tracking-widest mb-2">
                                <?= htmlspecialchars($displayKey[0], ENT_QUOTES, 'UTF-8') ?> - 
                                <?= htmlspecialchars($displayKey[1], ENT_QUOTES, 'UTF-8') ?> - 
                                <?= htmlspecialchars($displayKey[2], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <p class="text-blue-200 text-xs">
                                Example: If your master PIN is <code class="bg-blue-700 px-1 rounded">161023</code> and key is <code class="bg-blue-700 px-1 rounded"><?= htmlspecialchars($displayKey, ENT_QUOTES, 'UTF-8') ?></code>,<br>
                                enter digits at positions <?= htmlspecialchars($displayKey[0], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($displayKey[1], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($displayKey[2], ENT_QUOTES, 'UTF-8') ?>: <code class="bg-blue-700 px-1 rounded"><?php
                                    // Show example based on key
                                    $examplePin = '161023';
                                    $pos1 = (int)$displayKey[0] - 1;
                                    $pos2 = (int)$displayKey[1] - 1;
                                    $pos3 = (int)$displayKey[2] - 1;
                                    echo $examplePin[$pos1] . $examplePin[$pos2] . $examplePin[$pos3];
                                ?></code>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Error Messages -->
                    <?php if ($error === 'missing_pin'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">Please enter your PIN and key.</p>
                        </div>
                    <?php elseif ($error === 'invalid_pin'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">PIN does not match. Please try again.</p>
                        </div>
                    <?php elseif ($error === 'api_error'): ?>
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                            <p class="text-red-400 text-sm">PIN validation service error. Please try again.</p>
                        </div>
                    <?php elseif ($error === 'session_expired'): ?>
                        <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4">
                            <p class="text-yellow-400 text-sm">Session expired. Please login again.</p>
                        </div>
                    <?php endif; ?>


                    <!-- PIN Form -->
                    <form method="POST" action="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                        <input type="hidden" name="step" value="2">
                        <?php if (!empty($displayKey)): ?>
                            <input type="hidden" name="key" value="<?= htmlspecialchars($displayKey, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>

                        <div>
                            <label for="pin" class="block text-sm font-medium text-gray-300 mb-2">Enter PIN (3 digits)</label>
                            <input type="text" 
                                   id="pin" 
                                   name="pin" 
                                   required
                                   maxlength="3"
                                   pattern="[0-9]{3}"
                                   class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent text-center text-2xl tracking-widest"
                                   placeholder="000"
                                   autocomplete="off"
                                   inputmode="numeric"
                                   autofocus>
                            <p class="text-xs text-gray-500 mt-1">Enter the 3 digits from your 6-digit master PIN in the order shown above</p>
                        </div>

                        <button type="submit" 
                                class="w-full flex items-center justify-center gap-3 bg-red-600 hover:bg-red-700 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-red-900/50 transform hover:scale-[1.02] active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <span>Verify PIN</span>
                        </button>
                    </form>

                    <!-- Back to Step 1 -->
                    <div class="text-center">
                        <a href="<?= htmlspecialchars(PathHelper::url('safezone.php'), ENT_QUOTES, 'UTF-8') ?>" 
                           class="text-sm text-gray-400 hover:text-gray-300 underline">
                            ← Back to login
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Info Section -->
            <div class="mt-8 pt-6 border-t border-gray-800">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-400 leading-relaxed">
                            SafeZonePass provides secure authentication without storing passwords. Your credentials remain private and protected.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </div>
    <?php $loginUrl = PathHelper::url('safezone.php'); include __DIR__ . '/components/footer.php'; ?>
</body>
</html>
