<?php
/**
 * Landing Page
 * Modern, professional landing page for DBV Bridge
 */

require_once __DIR__ . '/../../app/Support/PathHelper.php';
$basePath = PathHelper::getBasePath();
$loginUrl = PathHelper::url('safezone.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Digital Benefits Exchange</title>
    <meta name="description" content="Secure cross-chain transactions for DBV tokens. Deposit and withdraw across Stellar, Binance Smart Chain, and Ethereum networks." />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (prefers-color-scheme: dark) {
            body { background-color: #000000; }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1f2937 0%, #111827 50%, #0f172a 100%);
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(17, 24, 39, 0.8) 100%);
        }
        .card-glow {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.1), 0 0 60px rgba(239, 68, 68, 0.05);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(239, 68, 68, 0.2);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.8s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .mobile-menu.open {
            max-height: 500px;
        }
        html {
            scroll-behavior: smooth;
        }
        .network-card {
            position: relative;
            overflow: hidden;
        }
        .network-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .network-card:hover::before {
            left: 100%;
        }
        
        /* Particle Network Animation */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }
        
        #particles-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body class="bg-black text-white min-h-screen gradient-bg">
    <!-- Navigation -->
    <nav class="sticky top-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center gap-3">
                    <a href="<?= htmlspecialchars(PathHelper::url('landing.php'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center">
                        <img src="<?= htmlspecialchars(PathHelper::url('dbvheader.jpg'), ENT_QUOTES, 'UTF-8') ?>" 
                             alt="DBV Bridge" 
                             class="h-12 sm:h-16 w-auto">
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center gap-6">
                    <a href="#networks" 
                       class="text-gray-300 hover:text-white transition-colors font-medium">
                        Networks
                    </a>
                    <a href="#features" 
                       class="text-gray-300 hover:text-white transition-colors font-medium">
                        Features
                    </a>
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                       class="text-gray-300 hover:text-white transition-colors font-medium">
                        Sign In
                    </a>
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                       class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all font-medium transform hover:scale-105">
                        Get Started
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu md:hidden">
                <div class="px-2 pt-2 pb-4 space-y-2 border-t border-gray-800">
                    <a href="#networks" 
                       class="block px-4 py-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                        Networks
                    </a>
                    <a href="#features" 
                       class="block px-4 py-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                        Features
                    </a>
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                       class="block px-4 py-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-colors">
                        Sign In
                    </a>
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                       class="block px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg text-center font-medium transition-colors">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden py-12 sm:py-16 lg:py-24 px-4 sm:px-6 lg:px-8">
        <!-- 3D Animated Background -->
        <div class="absolute inset-0 overflow-hidden">
            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-br from-black via-gray-900 to-black opacity-90 z-10"></div>
            
            <!-- 3D Canvas -->
            <canvas id="hero-3d-canvas" class="absolute inset-0 w-full h-full"></canvas>
            
            <!-- Additional Glow Effects -->
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-green-500/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        </div>
        
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                <div class="text-center lg:text-left animate-fade-in">

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-bold mb-6 leading-tight">
                        <span class="bg-gradient-to-r from-white via-gray-200 to-gray-400 bg-clip-text text-transparent">
                            Multi-Chain Bridge
                        </span>
                        <br>
                        <span class="bg-gradient-to-r from-green-400 to-green-600 bg-clip-text text-transparent">
                            for the Digital&nbsp;Benefits Network
                        </span>
                    </h1>
                    <p class="text-lg sm:text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                        Seamlessly transfer DBV tokens to and from DigitalChain, with native representations across major networks: DB on Stellar, DBE on Ethereum, and DBB on Binance Smart Chain — enabling fast, secure, and reliable cross-chain transactions.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                           class="px-8 py-4 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-lg transition-all transform hover:scale-105 shadow-lg hover:shadow-green-900/50 text-center">
                            Transfer DBV Now
                        </a>
                        <a href="#networks" 
                           class="px-8 py-4 bg-gray-800/50 hover:bg-gray-700/50 text-white rounded-xl font-semibold text-lg transition-all border border-gray-700 hover:border-gray-600 text-center">
                            Explore Networks
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-12 pt-8 border-t border-gray-800">
                        <div class="text-center lg:text-left">
                            <div class="text-2xl sm:text-3xl font-bold text-green-400">4</div>
                            <div class="text-sm text-gray-400 mt-1">Networks</div>
                        </div>
                        <div class="text-center lg:text-left">
                            <div class="text-2xl sm:text-3xl font-bold text-green-400">24/7</div>
                            <div class="text-sm text-gray-400 mt-1">Available</div>
                        </div>
                        <div class="text-center lg:text-left">
                            <div class="text-2xl sm:text-3xl font-bold text-green-400">100%</div>
                            <div class="text-sm text-gray-400 mt-1">Secure</div>
                        </div>
                    </div>
                </div>
                <div class="relative flex items-center justify-center mt-8 lg:mt-0">
                    <div class="animate-float relative">

                        <img src="<?= htmlspecialchars(PathHelper::url('dbvabout.gif'), ENT_QUOTES, 'UTF-8') ?>" 
                             alt="DBV About" 
                             class="relative w-full max-w-lg rounded-2xl">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 sm:py-20 lg:py-24 px-4 sm:px-6 lg:px-8 bg-gray-900/30">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12 lg:mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                    Why Choose <span class="text-green-400">DBV Bridge</span>?
                </h2>
                <p class="text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto">
                    Seamlessly bridge your DBV tokens between Digital Chain and major blockchain networks
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Feature 1 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Secure & Safe</h3>
                    <p class="text-gray-400">Bank-level security with SafeZonePass authentication protecting your Digital Chain assets</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Fast</h3>
                    <p class="text-gray-400">Near-instant transfers between Digital Chain and other networks with optimized routing</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Multi-Network</h3>
                    <p class="text-gray-400">Connect Digital Chain with Stellar, Binance Smart Chain, and Ethereum networks</p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Low Fees</h3>
                    <p class="text-gray-400">Competitive fees for bridging between Digital Chain and other blockchains</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Real-Time Tracking</h3>
                    <p class="text-gray-400">Track your Digital Chain bridge transactions in real-time with detailed status updates</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-gradient-to-br from-gray-900/50 to-black/50 border border-gray-800 rounded-xl p-6 lg:p-8 card-hover">
                    <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">24/7 Support</h3>
                    <p class="text-gray-400">24/7 platform availability for seamless Digital Chain bridging anytime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Networks Section -->
    <section id="networks" class="py-16 sm:py-20 lg:py-24 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12 lg:mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">Supported Networks</h2>
                <p class="text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto">
                    Transfer DBV tokens to and from the Digital Benefits Chain, with native representations across major networks
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                <!-- Digital Chain -->
                <div class="bg-gradient-to-br from-green-900/80 to-black/80 border border-green-800 rounded-xl p-6 lg:p-8 text-center network-card card-hover">
                    <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <img src="<?= htmlspecialchars(PathHelper::url('assets/digitalchain.png'), ENT_QUOTES, 'UTF-8') ?>" 
                             alt="Digital Chain" 
                             class="w-16 h-16 object-contain">
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Digital Chain</h3>
                    <div class="mb-3">
                        <span class="inline-block px-3 py-1 bg-green-600/30 border border-green-600/50 rounded-lg text-sm font-semibold text-green-300 mb-2">DBV Token</span>
                    </div>
                    <p class="text-gray-400 mb-4">The native blockchain for the Digital Benefits ecosystem.</p>
                    <div class="inline-block px-3 py-1 bg-green-600/20 border border-green-600/30 rounded-full text-sm text-green-400">
                        Mainnet & Testnet
                    </div>
                </div>
                
                <!-- Stellar -->
                <div class="bg-gradient-to-br from-gray-900/80 to-black/80 border border-gray-800 rounded-xl p-6 lg:p-8 text-center network-card card-hover">
                    <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-16 h-16" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="50" fill="#000000"/>
                            <path d="M27.5 50L50 37.5L72.5 50L50 62.5L27.5 50Z" fill="#14B6E7"/>
                            <path d="M50 62.5L27.5 50V65L50 77.5L72.5 65V50L50 62.5Z" fill="#14B6E7" opacity="0.6"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Stellar</h3>
                    <div class="mb-3">
                        <span class="inline-block px-3 py-1 bg-blue-600/30 border border-blue-600/50 rounded-lg text-sm font-semibold text-blue-300 mb-2">DB Token</span>
                    </div>
                    <p class="text-gray-400 mb-4">Native representation of DBV on the Stellar network.</p>
                    <div class="inline-block px-3 py-1 bg-blue-600/20 border border-blue-600/30 rounded-full text-sm text-blue-400">
                        Mainnet & Testnet
                    </div>
                </div>

                <!-- Binance Smart Chain -->
                <div class="bg-gradient-to-br from-gray-900/80 to-black/80 border border-gray-800 rounded-xl p-6 lg:p-8 text-center network-card card-hover">
                    <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-16 h-16" viewBox="0 0 126.61 126.61" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="63.305" cy="63.305" r="63.305" fill="#F3BA2F"/>
                            <path d="M38.73 53.2L63.31 28.62L87.88 53.2L77.87 63.21L63.31 48.65L48.74 63.21L38.73 53.2Z" fill="white"/>
                            <path d="M28.62 63.31L38.63 53.3L48.64 63.31L38.63 73.32L28.62 63.31Z" fill="white"/>
                            <path d="M38.73 73.42L63.31 98L87.88 73.42L97.89 83.43L63.31 118L28.72 83.42L38.73 73.42Z" fill="white"/>
                            <path d="M77.98 63.31L87.99 53.3L98 63.31L87.99 73.32L77.98 63.31Z" fill="white"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Binance Smart Chain</h3>
                    <div class="mb-3">
                        <span class="inline-block px-3 py-1 bg-yellow-600/30 border border-yellow-600/50 rounded-lg text-sm font-semibold text-yellow-300 mb-2">DBB Token</span>
                    </div>
                    <p class="text-gray-400 mb-4">Native representation of DBV on Binance Smart Chain.</p>
                    <div class="inline-block px-3 py-1 bg-yellow-600/20 border border-yellow-600/30 rounded-full text-sm text-yellow-400">
                        Mainnet & Testnet
                    </div>
                </div>

                <!-- Ethereum -->
                <div class="bg-gradient-to-br from-gray-900/80 to-black/80 border border-gray-800 rounded-xl p-6 lg:p-8 text-center network-card card-hover">
                    <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-16 h-16" viewBox="0 0 256 417" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M127.961 0L125.164 9.5L125.164 285.168L127.961 287.958L255.922 212.32L127.961 0Z" fill="#8C8C8C"/>
                            <path d="M127.962 0L0 212.32L127.962 287.959L127.962 154.158L127.962 0Z" fill="#C0C0C0"/>
                            <path d="M127.961 312.187L126.386 314.106L126.386 412.306L127.961 416.616L256 237.585L127.961 312.187Z" fill="#8C8C8C"/>
                            <path d="M127.962 416.616L127.962 312.187L0 237.585L127.962 416.616Z" fill="#C0C0C0"/>
                            <path d="M127.961 287.958L255.922 212.32L127.961 154.159L127.961 287.958Z" fill="#5C5C5C"/>
                            <path d="M0 212.32L127.962 287.959L127.962 154.159L0 212.32Z" fill="#8C8C8C"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Ethereum</h3>
                    <div class="mb-3">
                        <span class="inline-block px-3 py-1 bg-purple-600/30 border border-purple-600/50 rounded-lg text-sm font-semibold text-purple-300 mb-2">DBE Token</span>
                    </div>
                    <p class="text-gray-400 mb-4">Native representation of DBV on the Ethereum network.</p>
                    <div class="inline-block px-3 py-1 bg-purple-600/20 border border-purple-600/30 rounded-full text-sm text-purple-400">
                        Mainnet & Testnet
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 sm:py-20 lg:py-24 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-green-600/10 via-red-600/10 to-gray-900/50 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-green-600/5 to-transparent"></div>
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4 sm:mb-6">
                Ready to Get Started?
            </h2>
            <p class="text-lg sm:text-xl text-gray-300 mb-8 sm:mb-10 max-w-2xl mx-auto">
                Join DBV Bridge today and experience seamless cross-chain transactions with enterprise-grade security
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" 
                   class="w-full sm:w-auto px-10 py-4 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-lg transition-all transform hover:scale-105 shadow-lg hover:shadow-green-900/50 text-center">
                    Sign In with SafeZonePass
                </a>
            </div>
            <p class="text-sm text-gray-500 mt-6 flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Secure authentication powered by SafeZone
            </p>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/components/footer.php'; ?>

    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = mobileMenuBtn.querySelector('svg');
            
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('open');
                
                // Toggle hamburger icon
                if (mobileMenu.classList.contains('open')) {
                    menuIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                } else {
                    menuIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
                }
            });

            // Close mobile menu when clicking on a link
            const mobileLinks = mobileMenu.querySelectorAll('a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.remove('open');
                    menuIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
                });
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            
            // 3D Particle Network Animation (like uploaded image)
            const canvas = document.getElementById('hero-3d-canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                
                // Set canvas size
                function resizeCanvas() {
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
                
                // Detect mobile
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
                
                // Mouse interaction
                let mouse = { x: null, y: null, radius: isMobile ? 100 : 150 };
                
                canvas.addEventListener('mousemove', function(event) {
                    const rect = canvas.getBoundingClientRect();
                    mouse.x = event.clientX - rect.left;
                    mouse.y = event.clientY - rect.top;
                });
                
                canvas.addEventListener('mouseleave', function() {
                    mouse.x = null;
                    mouse.y = null;
                });
                
                // 3D Particle class
                class Particle {
                    constructor() {
                        this.x = Math.random() * canvas.width;
                        this.y = Math.random() * canvas.height;
                        this.z = Math.random() * 1000;
                        this.baseVx = (Math.random() - 0.5) * 0.5;
                        this.baseVy = (Math.random() - 0.5) * 0.5;
                        this.baseVz = (Math.random() - 0.5) * 2;
                        this.vx = this.baseVx;
                        this.vy = this.baseVy;
                        this.vz = this.baseVz;
                        this.size = Math.random() * 3 + 2; // Larger particles (2-5px)
                        this.opacity = Math.random() * 0.3 + 0.7; // Brighter (0.7-1.0)
                    }
                    
                    update() {
                        // Mouse interaction - repel particles
                        if (mouse.x != null && mouse.y != null) {
                            const dx = this.x - mouse.x;
                            const dy = this.y - mouse.y;
                            const distance = Math.sqrt(dx * dx + dy * dy);
                            
                            if (distance < mouse.radius) {
                                const force = (mouse.radius - distance) / mouse.radius;
                                this.vx = this.baseVx + (dx / distance) * force * 3;
                                this.vy = this.baseVy + (dy / distance) * force * 3;
                            } else {
                                // Return to base velocity
                                this.vx += (this.baseVx - this.vx) * 0.05;
                                this.vy += (this.baseVy - this.vy) * 0.05;
                            }
                        }
                        
                        this.x += this.vx;
                        this.y += this.vy;
                        this.z += this.vz;
                        
                        // Wrap around edges
                        if (this.x < 0) this.x = canvas.width;
                        if (this.x > canvas.width) this.x = 0;
                        if (this.y < 0) this.y = canvas.height;
                        if (this.y > canvas.height) this.y = 0;
                        if (this.z < 0) this.z = 1000;
                        if (this.z > 1000) this.z = 0;
                    }
                    
                    draw() {
                        // Calculate perspective with stronger 3D effect
                        const scale = 1200 / (1200 + this.z); // Stronger perspective
                        const x2d = (this.x - canvas.width / 2) * scale + canvas.width / 2;
                        const y2d = (this.y - canvas.height / 2) * scale + canvas.height / 2;
                        const size = this.size * scale * 1.5; // Larger visible size
                        
                        // Calculate opacity based on depth - much brighter
                        const depthOpacity = 0.5 + (1 - this.z / 1000) * 0.5;
                        const finalOpacity = this.opacity * depthOpacity;
                        
                        // Draw large outer glow
                        const outerGlow = ctx.createRadialGradient(x2d, y2d, 0, x2d, y2d, size * 8);
                        outerGlow.addColorStop(0, `rgba(34, 197, 94, ${finalOpacity * 0.8})`);
                        outerGlow.addColorStop(0.3, `rgba(34, 197, 94, ${finalOpacity * 0.4})`);
                        outerGlow.addColorStop(1, 'rgba(34, 197, 94, 0)');
                        
                        ctx.fillStyle = outerGlow;
                        ctx.beginPath();
                        ctx.arc(x2d, y2d, size * 8, 0, Math.PI * 2);
                        ctx.fill();
                        
                        // Draw medium glow
                        const mediumGlow = ctx.createRadialGradient(x2d, y2d, 0, x2d, y2d, size * 4);
                        mediumGlow.addColorStop(0, `rgba(255, 255, 255, ${finalOpacity})`);
                        mediumGlow.addColorStop(0.5, `rgba(34, 197, 94, ${finalOpacity * 0.8})`);
                        mediumGlow.addColorStop(1, 'rgba(34, 197, 94, 0)');
                        
                        ctx.fillStyle = mediumGlow;
                        ctx.beginPath();
                        ctx.arc(x2d, y2d, size * 4, 0, Math.PI * 2);
                        ctx.fill();
                        
                        // Draw bright core particle
                        ctx.fillStyle = `rgba(255, 255, 255, ${finalOpacity})`;
                        ctx.shadowBlur = 10;
                        ctx.shadowColor = 'rgba(34, 197, 94, 1)';
                        ctx.beginPath();
                        ctx.arc(x2d, y2d, size, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.shadowBlur = 0;
                        
                        return { x: x2d, y: y2d, z: this.z, opacity: finalOpacity };
                    }
                }
                
                // Create particles
                const particles = [];
                const particleCount = isMobile ? 80 : 150; // More particles
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle());
                }
                
                // Draw connections between nearby particles
                function drawConnections(positions) {
                    const maxDistance = isMobile ? 150 : 200; // Longer connections
                    
                    for (let i = 0; i < positions.length; i++) {
                        for (let j = i + 1; j < positions.length; j++) {
                            const dx = positions[i].x - positions[j].x;
                            const dy = positions[i].y - positions[j].y;
                            const dz = (positions[i].z - positions[j].z) / 10;
                            const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);
                            
                            if (distance < maxDistance) {
                                const opacity = (1 - distance / maxDistance) * 0.6; // Brighter lines
                                const avgOpacity = (positions[i].opacity + positions[j].opacity) / 2;
                                const finalOpacity = opacity * avgOpacity;
                                
                                // Create gradient line
                                const gradient = ctx.createLinearGradient(
                                    positions[i].x, positions[i].y,
                                    positions[j].x, positions[j].y
                                );
                                gradient.addColorStop(0, `rgba(34, 197, 94, ${finalOpacity})`);
                                gradient.addColorStop(0.5, `rgba(59, 130, 246, ${finalOpacity})`);
                                gradient.addColorStop(1, `rgba(34, 197, 94, ${finalOpacity})`);
                                
                                ctx.strokeStyle = gradient;
                                ctx.lineWidth = 1.5; // Thicker lines
                                ctx.shadowBlur = 3;
                                ctx.shadowColor = 'rgba(34, 197, 94, 0.5)';
                                ctx.beginPath();
                                ctx.moveTo(positions[i].x, positions[i].y);
                                ctx.lineTo(positions[j].x, positions[j].y);
                                ctx.stroke();
                                ctx.shadowBlur = 0;
                            }
                        }
                    }
                }
                
                // Animation loop
                function animate() {
                    // Clear canvas with fade effect
                    ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    
                    // Update and collect particle positions
                    const positions = [];
                    particles.forEach(particle => {
                        particle.update();
                        const pos = particle.draw();
                        positions.push(pos);
                    });
                    
                    // Draw connections
                    drawConnections(positions);
                    
                    requestAnimationFrame(animate);
                }
                
                animate();
            }
        });
    </script>
</body>
</html>

