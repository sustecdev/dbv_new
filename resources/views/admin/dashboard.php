<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DBV Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@stellar/stellar-sdk@11.2.0/dist/stellar-sdk.min.js" crossorigin="anonymous"></script>
    <style>
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-slow {
            animation: pulse-slow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #1f2937;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 3px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
        .modal-backdrop { background: rgba(0,0,0,0.75); }
    </style>
</head>
<body class="bg-black text-white min-h-screen">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">Admin Dashboard</h1>
                        <p class="text-xs text-gray-400">DBV Bridge Monitoring</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium">Admin</p>
                        <p class="text-xs text-gray-400">UID: <?= $_SESSION['uid'] ?? 'N/A' ?></p>
                    </div>
                    <a href="<?= htmlspecialchars(PathHelper::url('dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm transition-colors">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
            <!-- Total Users -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">USERS</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1" id="stat-users">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400">Total registered</p>
            </div>

            <!-- Total Deposits -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">DEPOSITS</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1" id="stat-deposits">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400" id="stat-deposits-volume">Volume: -- DBV</p>
            </div>

            <!-- Total Withdrawals -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">WITHDRAWALS</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1" id="stat-withdrawals">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400" id="stat-withdrawals-volume">Volume: -- DBV</p>
            </div>

            <!-- Total Fees -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-indigo-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">FEES COLLECTED</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1" id="stat-fees">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400">Total USDD Fees</p>
            </div>

            <!-- Commissions Paid -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-teal-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">COMMISSIONS</span>
                </div>
                <div class="text-3xl font-bold text-teal-400 mb-1" id="stat-commissions">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400" id="stat-commissions-count">Paid to referrers</p>
            </div>

            <!-- Pending Withdrawals -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-yellow-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">PENDING</span>
                </div>
                <div class="text-3xl font-bold text-yellow-400 mb-1" id="stat-pending">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400">Awaiting processing</p>
            </div>

            <!-- Failed Transactions -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400">FAILED</span>
                </div>
                <div class="text-3xl font-bold text-red-400 mb-1" id="stat-failed">
                    <span class="pulse-slow">--</span>
                </div>
                <p class="text-xs text-gray-400" id="stat-failed-breakdown">Deposits + Withdrawals</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="border-b border-gray-800">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button onclick="switchTab('transactions')" id="tab-transactions" class="tab-button border-b-2 border-red-600 py-4 px-1 text-sm font-medium text-white">
                        Transactions
                    </button>
                    <button onclick="switchTab('manual')" id="tab-manual" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Manual Withdrawals <span id="manual-pending-badge" class="hidden ml-1 px-1.5 py-0.5 text-xs bg-yellow-600 rounded-full">0</span>
                    </button>
                    <button onclick="switchTab('failed')" id="tab-failed" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Failed Transactions
                    </button>
                    <button onclick="switchTab('commissions')" id="tab-commissions" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Commissions
                    </button>
                    <button onclick="switchTab('audit')" id="tab-audit" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Audit
                    </button>
                    <button onclick="switchTab('logs')" id="tab-logs" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Logs
                    </button>
                    <button onclick="switchTab('errors')" id="tab-errors" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Errors
                    </button>
                    <button onclick="switchTab('network-logs')" id="tab-network-logs" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        Network Logs
                    </button>
                    <button onclick="switchTab('settings')" id="tab-settings" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-300">
                        ⚙️ Settings
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Transactions Tab -->
                <div id="content-transactions" class="tab-content">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">All Transactions</h2>
                        <div class="flex flex-wrap gap-2">
                            <input type="text" id="filter-search" placeholder="Search ID, UID, Hash..." class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-full sm:w-48 min-w-0">
                            <select id="filter-status" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="nonfailed" selected>All (excl. failed)</option>
                                <option value="all">All Status</option>
                                <option value="0">Pending</option>
                                <option value="1">Processing</option>
                                <option value="3">Completed</option>
                                <option value="8">Pre-Complete</option>
                                <option value="9">Cancelled</option>
                                <option value="2">Failed only</option>
                            </select>
                            <select id="filter-network" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Networks</option>
                                <option value="stellar">Stellar</option>
                                <option value="binance">BSC</option>
                                <option value="ethereum">Ethereum</option>
                            </select>
                            <select id="filter-type" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Types</option>
                                <option value="deposit">Deposits</option>
                                <option value="withdrawal">Withdrawals</option>
                            </select>
                            <button onclick="loadTransactions()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div id="transactions-list" class="space-y-3 max-h-[600px] overflow-y-auto scrollbar-thin">
                        <div class="text-center text-gray-400 py-8">Loading...</div>
                    </div>
                </div>

                <!-- Manual Withdrawals Tab -->
                <div id="content-manual" class="tab-content hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">Pending Manual Withdrawals</h2>
                        <div class="flex flex-wrap gap-2 items-center">
                            <span id="manual-wallet-status" class="text-sm text-gray-400">Wallet not connected</span>
                            <button type="button" id="btn-connect-wallet" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-medium transition-colors">
                                Connect Wallet
                            </button>
                            <select id="filter-manual-network" onchange="loadManualWithdrawals()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Networks</option>
                                <option value="stellar">Stellar</option>
                                <option value="binance">BSC</option>
                                <option value="ethereum">Ethereum</option>
                            </select>
                            <button onclick="loadManualWithdrawals()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                            <button type="button" id="bulk-complete-btn" onclick="bulkCompleteWithWallet()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-medium transition-colors hidden" title="Complete selected withdrawals with connected wallet">
                                Bulk Complete (<span id="bulk-complete-count">0</span>)
                            </button>
                            <button type="button" id="bulk-complete-stellar-btn" onclick="openBulkCompleteStellarModal()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-sm font-medium transition-colors hidden" title="Complete selected Stellar withdrawals with secret key">
                                Bulk Complete Stellar (<span id="bulk-complete-stellar-count">0</span>) <span id="bulk-complete-stellar-dbv" class="opacity-90">— DBV</span>
                            </button>
                            <button type="button" id="bulk-complete-evm-pk-btn" onclick="openBulkCompleteEvmPrivateKeyModal()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 rounded-lg text-sm font-medium transition-colors hidden" title="Complete selected BSC/Ethereum withdrawals using vault private key in browser only">
                                Bulk Complete BSC/ETH (key) (<span id="bulk-complete-evm-pk-count">0</span>)
                            </button>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 mb-4">Complete pending withdrawals by pasting the transaction hash or signing with your connected wallet (BSC/Ethereum). Use Bulk Complete BSC/ETH (key) to sign with a vault private key in the browser only (like Stellar secret). For Stellar, use Bulk Complete Stellar with vault + secret key. Select multiple rows for batch processing.</p>
                    <div class="flex flex-wrap gap-2 mb-3 text-xs">
                        <button type="button" onclick="toggleManualSelectAll(true)" class="text-indigo-400 hover:text-indigo-300">Select all</button>
                        <button type="button" onclick="toggleManualSelectAll(true, 'stellar')" class="text-yellow-400 hover:text-yellow-300">Select Stellar only</button>
                        <button type="button" onclick="toggleManualSelectAll(true, 'binance')" class="text-orange-400 hover:text-orange-300">Select BSC only</button>
                        <button type="button" onclick="toggleManualSelectAll(true, 'ethereum')" class="text-blue-400 hover:text-blue-300">Select Ethereum only</button>
                        <button type="button" onclick="toggleManualSelectAll(false)" class="text-gray-400 hover:text-gray-300">Deselect all</button>
                    </div>
                    <div id="manual-withdrawals-list" class="space-y-3 max-h-[600px] overflow-y-auto scrollbar-thin">
                        <div class="text-center text-gray-400 py-8">Loading...</div>
                    </div>
                </div>

                <!-- Failed Transactions Tab -->
                <div id="content-failed" class="tab-content hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">Failed Transactions</h2>
                        <div class="flex flex-wrap gap-2 items-center">
                            <input type="text" id="filter-failed-search" placeholder="Search ID, UID, Address, Hash..." class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-full sm:w-48 min-w-0">
                            <select id="filter-failed-network" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Networks</option>
                                <option value="stellar">Stellar</option>
                                <option value="binance">BSC</option>
                                <option value="ethereum">Ethereum</option>
                            </select>
                            <button onclick="loadFailedTransactions()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                            <label class="flex items-center gap-2 text-sm text-gray-300 whitespace-nowrap cursor-pointer select-none border border-gray-700 rounded-lg px-3 py-2 bg-gray-800/80" title="When unchecked, only DBV is credited back; USDD withdrawal fees are not refunded">
                                <input type="checkbox" id="reverse-refund-usdd-fees" checked class="rounded border-gray-600 bg-gray-800 text-orange-500 focus:ring-orange-500">
                                Refund USDD fees
                            </label>
                            <button id="bulk-reverse-btn" onclick="bulkReverseSelected()" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 rounded-lg text-sm font-medium transition-colors hidden" title="Reverse selected failed transactions">
                                🔄 Reverse Selected (<span id="bulk-reverse-count">0</span>)
                            </button>
                        </div>
                    </div>
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-900/80 text-gray-400 uppercase text-xs">
                                    <tr>
                                        <th class="text-left px-3 py-3 font-medium w-10">
                                            <input type="checkbox" id="failed-select-all" onchange="toggleFailedSelectAll(this)" class="rounded border-gray-600 bg-gray-800 text-orange-500 focus:ring-orange-500">
                                        </th>
                                        <th class="text-left px-4 py-3 font-medium">ID</th>
                                        <th class="text-left px-4 py-3 font-medium">Network</th>
                                        <th class="text-left px-4 py-3 font-medium">UID</th>
                                        <th class="text-right px-4 py-3 font-medium">DBV</th>
                                        <th class="text-right px-4 py-3 font-medium">Fee (USDD)</th>
                                        <th class="text-left px-4 py-3 font-medium max-w-[200px]">Address</th>
                                        <th class="text-left px-4 py-3 font-medium max-w-[220px]">Failure Reason</th>
                                        <th class="text-left px-4 py-3 font-medium">Time</th>
                                        <th class="text-center px-4 py-3 font-medium">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="failed-list-body" class="divide-y divide-gray-700/50">
                                    <tr><td colspan="10" class="text-center text-gray-400 py-8">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="failed-list-footer" class="px-4 py-3 bg-gray-900/50 border-t border-gray-700 text-xs text-gray-400 hidden">
                            <span id="failed-list-count">0</span> failed withdrawal(s)
                        </div>
                    </div>
                    <div id="failed-reasons-summary" class="mt-4 p-4 bg-gray-800/50 border border-gray-700 rounded-xl hidden">
                        <h3 class="text-sm font-semibold text-gray-300 mb-2">Top Failure Reasons</h3>
                        <ul id="failed-reasons-list" class="text-sm text-gray-400 space-y-1"></ul>
                    </div>
                </div>

                <!-- Commissions Tab -->
                <div id="content-commissions" class="tab-content hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">Referral Commissions Paid</h2>
                        <div class="flex flex-wrap gap-2 items-center">
                            <input type="number" id="filter-comm-referrer" placeholder="Referee UID" min="1" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-28">
                            <input type="number" id="filter-comm-referred" placeholder="Referred UID" min="1" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-28">
                            <select id="filter-comm-network" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Networks</option>
                                <option value="stellar">Stellar</option>
                                <option value="binance">BSC</option>
                                <option value="ethereum">Ethereum</option>
                            </select>
                            <button onclick="loadCommissions()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div id="commissions-summary" class="mb-4 p-4 bg-teal-900/20 border border-teal-800/50 rounded-xl text-sm hidden">
                        <span class="text-teal-300 font-medium">Total: </span><span id="comm-summary-usdd" class="font-bold">0</span> USDD across <span id="comm-summary-count" class="font-bold">0</span> commission(s)
                    </div>
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-900/80 text-gray-400 uppercase text-xs">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-medium">Referee UID</th>
                                        <th class="text-left px-4 py-3 font-medium">Referred UID</th>
                                        <th class="text-left px-4 py-3 font-medium">Network</th>
                                        <th class="text-right px-4 py-3 font-medium">Amount (USDD)</th>
                                        <th class="text-left px-4 py-3 font-medium max-w-[200px]">Commission Hash</th>
                                        <th class="text-left px-4 py-3 font-medium">Withdrawal ID</th>
                                        <th class="text-left px-4 py-3 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="commissions-list-body" class="divide-y divide-gray-700/50">
                                    <tr><td colspan="7" class="text-center text-gray-400 py-8">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Audit Tab -->
                <div id="content-audit" class="tab-content hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">Audit Log</h2>
                        <div class="flex flex-wrap gap-2">
                            <select id="filter-audit-action" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="">All Actions</option>
                                <option value="reversal">Reversal</option>
                                <option value="reversal_failed">Reversal Failed</option>
                                <option value="manual_complete">Manual Complete</option>
                                <option value="backup">Backup</option>
                                <option value="clear_sessions">Clear Sessions</option>
                                <option value="admin_added">Admin Added</option>
                                <option value="admin_removed">Admin Removed</option>
                                <option value="setting_updated">Setting Updated</option>
                            </select>
                            <input type="number" id="filter-audit-admin-uid" placeholder="Admin UID" min="1" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-28">
                            <input type="date" id="filter-audit-date-from" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                            <input type="date" id="filter-audit-date-to" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                            <button onclick="loadAuditLog()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div class="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-900/80 text-gray-400 uppercase text-xs">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-medium">Time</th>
                                        <th class="text-left px-4 py-3 font-medium">Admin UID</th>
                                        <th class="text-left px-4 py-3 font-medium">Action</th>
                                        <th class="text-left px-4 py-3 font-medium">Entity</th>
                                        <th class="text-left px-4 py-3 font-medium">Details</th>
                                        <th class="text-left px-4 py-3 font-medium">IP</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-list-body" class="divide-y divide-gray-700/50">
                                    <tr><td colspan="6" class="text-center text-gray-400 py-8">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div id="content-logs" class="tab-content hidden">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold">System Logs</h2>
                        <div class="flex gap-2">
                            <input type="date" id="filter-date" value="<?= date('Y-m-d') ?>" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                            <select id="filter-level" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Levels</option>
                                <option value="INFO">INFO</option>
                                <option value="WARNING">WARNING</option>
                                <option value="ERROR">ERROR</option>
                                <option value="DEBUG">DEBUG</option>
                            </select>
                            <button onclick="loadLogs()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div id="logs-list" class="space-y-2 max-h-[600px] overflow-y-auto scrollbar-thin">
                        <div class="text-center text-gray-400 py-8">Select a date and click Refresh</div>
                    </div>
                </div>

                <!-- Errors Tab -->
                <div id="content-errors" class="tab-content hidden">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold">Error Logs</h2>
                        <button onclick="loadErrors()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                            Refresh
                        </button>
                    </div>
                    <div id="errors-list" class="space-y-2 max-h-[600px] overflow-y-auto scrollbar-thin">
                        <div class="text-center text-gray-400 py-8">Click Refresh to load errors</div>
                    </div>
                </div>

                <!-- Network Logs Tab (Worker PM2 logs - RPC, connection failures, etc.) -->
                <div id="content-network-logs" class="tab-content hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <h2 class="text-lg font-bold">Network & Worker Logs</h2>
                        <div class="flex flex-wrap gap-2 items-center">
                            <select id="filter-worker-logs-worker" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All Workers</option>
                                <option value="stellar">Stellar</option>
                                <option value="binance">Binance (BSC)</option>
                                <option value="ethereum">Ethereum</option>
                            </select>
                            <select id="filter-worker-logs-filter" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="all">All lines</option>
                                <option value="network">Network failures only (RPC, timeout, error, etc.)</option>
                            </select>
                            <select id="filter-worker-logs-source" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm">
                                <option value="both">Error + Output</option>
                                <option value="error">Error only</option>
                                <option value="out">Output only</option>
                            </select>
                            <button onclick="loadNetworkLogs()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 rounded-lg text-sm font-medium transition-colors">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 mb-4">PM2 worker logs (Stellar, Binance, Ethereum). Use "Network failures only" to filter RPC errors, timeouts, connection issues.</p>
                    <div id="network-logs-list" class="space-y-2 max-h-[600px] overflow-y-auto scrollbar-thin font-mono text-xs">
                        <div class="text-center text-gray-400 py-8">Select options and click Refresh</div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="content-settings" class="tab-content hidden">
                    <h2 class="text-lg font-bold mb-6">⚙️ System Settings</h2>

                    <!-- Admin Roles Section -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 mb-6">
                        <h3 class="text-md font-semibold text-white mb-2">👥 Admin Roles</h3>
                        <p class="text-sm text-gray-400 mb-4">Manage which UIDs have admin access. UID 1290033 is always an admin (bootstrap).</p>
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <input type="number" id="admin-uid-input" placeholder="Enter UID to add as admin" min="1" 
                                   class="bg-black border border-gray-700 rounded-lg px-4 py-2 text-sm text-white w-40 focus:outline-none focus:border-gray-500" />
                            <button type="button" onclick="addAdminUid()" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition-colors">
                                Add Admin
                            </button>
                        </div>
                        <div id="admin-uids-list" class="flex flex-wrap gap-2 text-sm">
                            <span class="text-gray-400">Loading...</span>
                        </div>
                        <div id="admin-roles-message" class="text-sm mt-2"></div>
                    </div>
                    
                    <!-- Manual Withdraw Toggle -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-md font-semibold text-white mb-2">🔄 Manual Withdraw Mode</h3>
                                <p class="text-sm text-gray-400">When <strong>ON</strong>, new withdrawals stay pending until an admin processes them manually. When <strong>OFF</strong>, the worker auto-processes withdrawals.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span id="manual-withdraw-status" class="text-sm text-gray-400">Loading...</span>
                                <button type="button" id="manual-withdraw-toggle" role="switch" aria-checked="false" class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-700 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50">
                                    <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-0" id="manual-withdraw-toggle-thumb"></span>
                                </button>
                            </div>
                        </div>
                        <div id="manual-withdraw-message" class="text-sm mt-2"></div>
                    </div>
                    
                    <!-- Reports Download Section -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 mb-6">
                        <h3 class="text-md font-semibold text-white mb-2">📊 Download Reports</h3>
                        <p class="text-sm text-gray-400 mb-4">Export data as CSV. Reports open in a new tab and download automatically.</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=transactions&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-medium transition-colors">
                                📋 Transactions
                            </a>
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=failed&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                ❌ Failed Withdrawals
                            </a>
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=fees&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition-colors">
                                💰 Fee Collection
                            </a>
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=audit&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 rounded-lg text-sm font-medium transition-colors">
                                📜 Audit Log
                            </a>
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=reversals&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 rounded-lg text-sm font-medium transition-colors">
                                ↩️ Reversals
                            </a>
                            <a href="<?= htmlspecialchars($adminBase . '/api/admin/download-report.php?type=commissions&format=csv', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 rounded-lg text-sm font-medium transition-colors">
                                💰 Commissions
                            </a>
                        </div>
                    </div>
                    
                    <!-- Database Backup Section -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-md font-semibold text-white mb-2">💾 Database Backup</h3>
                                <p class="text-sm text-gray-400">Create a backup of the entire database</p>
                            </div>
                            <button onclick="backupDatabase()" id="backup-btn" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium transition-colors">
                                📦 Create Backup
                            </button>
                        </div>
                        <div id="backup-status" class="mt-4 text-sm"></div>
                    </div>

                    <!-- Session Management Section -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-md font-semibold text-white mb-2">🔐 Session Management</h3>
                                <p class="text-sm text-gray-400">Clear all active user sessions (forces re-login)</p>
                            </div>
                            <button onclick="clearAllSessions()" id="clear-sessions-btn" class="px-6 py-3 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors">
                                🗑️ Clear All Sessions
                            </button>
                        </div>
                        <div id="session-status" class="mt-4 text-sm"></div>
                    </div>

                    <!-- System Info Section -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-white mb-4">ℹ️ System Information</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-400">Database:</span>
                                <span class="text-white ml-2"><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400">PHP Version:</span>
                                <span class="text-white ml-2"><?= PHP_VERSION ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400">Server Time:</span>
                                <span class="text-white ml-2"><?= date('Y-m-d H:i:s') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div id="tx-details-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 modal-backdrop" onclick="closeTxDetailsModal(event)">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Transaction Details</h3>
                <button onclick="closeTxDetailsModal()" class="p-2 hover:bg-gray-700 rounded-lg transition-colors">&times;</button>
            </div>
            <div id="tx-details-content" class="p-6 overflow-y-auto max-h-[calc(90vh-80px)] space-y-4 text-sm">
                <!-- Filled by JS -->
            </div>
        </div>
    </div>

    <!-- Connect Wallet Modal -->
    <div id="connect-wallet-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 modal-backdrop" onclick="closeConnectWalletModal(event)">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-sm w-full shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Connect Wallet</h3>
                <button onclick="closeConnectWalletModal()" class="p-2 hover:bg-gray-700 rounded-lg transition-colors">&times;</button>
            </div>
            <div class="p-6 space-y-3">
                <button type="button" id="wc-option-browser" class="w-full flex items-center gap-3 px-4 py-3 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-left transition-colors">
                    <div class="w-10 h-10 bg-orange-600/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-400" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5C13 2.12 11.88 1 10.5 1S8 2.12 8 3.5V5H4c-1.1 0-2 .9-2 2v3.5h1.5c1.5 0 2.5 1.2 2.5 2.5s-1 2.5-2.5 2.5H2V20c0 1.1.9 2 2 2h3.5v-1.5c0-1.5 1.2-2.5 2.5-2.5s2.5 1.2 2.5 2.5V22H20c1.1 0 2-.9 2-2v-4h-1.5c-1.5 0-2.5-1.2-2.5-2.5s1-2.5 2.5-2.5z"/></svg>
                    </div>
                    <div class="text-left">
                        <p class="font-medium text-white">Browser Wallet</p>
                        <p class="text-xs text-gray-400">MetaMask, Brave, etc.</p>
                    </div>
                </button>
                <button type="button" id="wc-option-walletconnect" class="w-full flex items-center gap-3 px-4 py-3 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-left transition-colors">
                    <div class="w-10 h-10 bg-blue-600/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    </div>
                    <div class="text-left">
                        <p class="font-medium text-white">WalletConnect</p>
                        <p class="text-xs text-gray-400">Mobile & desktop wallets</p>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Complete Progress Modal -->
    <div id="bulk-complete-modal" class="fixed inset-0 z-[102] hidden flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-md w-full shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Bulk Complete</h3>
                <button type="button" id="bulk-cancel-btn" onclick="bulkCompleteAbort()" class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
            </div>
            <div id="bulk-complete-content" class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div id="bulk-spinner" class="flex-shrink-0 w-10 h-10 rounded-full border-2 border-indigo-500 border-t-transparent animate-spin"></div>
                    <div>
                        <p id="bulk-progress-text" class="font-medium text-white">Preparing...</p>
                        <p id="bulk-progress-detail" class="text-sm text-gray-400">Switching network if needed</p>
                    </div>
                </div>
                <div class="w-full bg-gray-800 rounded-full h-2">
                    <div id="bulk-progress-bar" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="bulk-progress-summary" class="text-xs text-gray-500">0 of 0 completed</p>
            </div>
        </div>
    </div>

    <!-- Mark Complete Modal (manual withdrawals) -->
    <div id="mark-complete-modal" class="fixed inset-0 z-[101] hidden flex items-center justify-center p-4 modal-backdrop" onclick="closeMarkCompleteModal(event)">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-lg w-full shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Mark Withdrawal Complete</h3>
                <button onclick="closeMarkCompleteModal()" class="p-2 hover:bg-gray-700 rounded-lg transition-colors">&times;</button>
            </div>
            <div id="mark-complete-content" class="p-6 space-y-4">
                <p class="text-sm text-gray-400">Enter the transaction hash from the blockchain explorer after you have sent the tokens.</p>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="text-gray-400">ID</span><div class="font-mono text-white" id="mc-id">—</div></div>
                    <div><span class="text-gray-400">Network</span><div id="mc-network">—</div></div>
                    <div><span class="text-gray-400">Amount</span><div class="text-green-300 font-medium" id="mc-amount">—</div></div>
                    <div><span class="text-gray-400">Address</span><div class="font-mono text-gray-300 truncate max-w-[180px]" id="mc-address" title="">—</div></div>
                </div>
                <div>
                    <label for="mc-txn-hash" class="block text-sm text-gray-300 font-medium mb-2">Transaction Hash</label>
                    <input type="text" id="mc-txn-hash" placeholder="Paste hash from Stellar/BSCScan/Etherscan" class="w-full bg-black border border-gray-700 rounded-lg px-4 py-3 font-mono text-sm text-white focus:outline-none focus:border-gray-500" />
                </div>
                <div id="mc-skip-onchain-row" class="hidden rounded-lg border border-amber-800/60 bg-amber-950/20 p-3">
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-amber-100/95">
                        <input type="checkbox" id="mc-skip-onchain-verify" class="mt-1 rounded border-gray-600 bg-black accent-amber-500" autocomplete="off" />
                        <span>Complete without on-chain verification (BSC/Ethereum only). The transaction hash format is checked, but amount and recipient are not verified against the chain. Requires ADMIN_ALLOW_SKIP_EVM_ONCHAIN_VERIFY enabled on the server.</span>
                    </label>
                </div>
                <div id="mc-message" class="text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" id="mc-submit-btn" onclick="submitMarkComplete()" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg font-medium transition-colors">Submit</button>
                    <button type="button" onclick="closeMarkCompleteModal()" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Complete EVM (private key) Modal -->
    <div id="bulk-complete-evm-pk-modal" class="fixed inset-0 z-[102] hidden flex items-center justify-center p-4 modal-backdrop" onclick="closeBulkCompleteEvmPrivateKeyModal(event)">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-lg w-full shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Bulk Complete BSC / Ethereum (private key)</h3>
                <button onclick="closeBulkCompleteEvmPrivateKeyModal()" class="p-2 hover:bg-gray-700 rounded-lg transition-colors">&times;</button>
            </div>
            <div id="bulk-complete-evm-pk-content" class="p-6 space-y-4">
                <p id="bulk-complete-evm-pk-summary" class="text-sm font-medium text-teal-300">—</p>
                <p class="text-sm text-gray-400">Enter the vault address and private key. They are used only in your browser and never sent to the server. Requires RPC URLs configured for BSC and Ethereum in server config (.env).</p>
                <div>
                    <label for="evm-pk-vault-address" class="block text-sm text-gray-300 font-medium mb-2">Vault address (0x...)</label>
                    <input type="text" id="evm-pk-vault-address" placeholder="0x..." class="w-full bg-black border border-gray-700 rounded-lg px-4 py-3 font-mono text-sm text-white focus:outline-none focus:border-gray-500" autocomplete="off" />
                </div>
                <div>
                    <label for="evm-pk-private-key" class="block text-sm text-gray-300 font-medium mb-2">Private key for vault</label>
                    <input type="password" id="evm-pk-private-key" placeholder="0x... or hex without prefix" class="w-full bg-black border border-gray-700 rounded-lg px-4 py-3 font-mono text-sm text-white focus:outline-none focus:border-gray-500" autocomplete="off" />
                </div>
                <p class="text-xs text-teal-400/90">Use only on a trusted, secure machine. Never share your screen while entering the private key.</p>
                <div id="bulk-evm-pk-message" class="text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" id="bulk-evm-pk-submit-btn" onclick="submitBulkCompleteEvmPrivateKey()" class="flex-1 px-4 py-2 bg-teal-600 hover:bg-teal-700 rounded-lg font-medium transition-colors">Submit</button>
                    <button type="button" onclick="closeBulkCompleteEvmPrivateKeyModal()" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Complete Stellar Modal -->
    <div id="bulk-complete-stellar-modal" class="fixed inset-0 z-[102] hidden flex items-center justify-center p-4 modal-backdrop" onclick="closeBulkCompleteStellarModal(event)">
        <div class="bg-gray-900 border border-gray-700 rounded-xl max-w-lg w-full shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-bold">Bulk Complete Stellar Withdrawals</h3>
                <button onclick="closeBulkCompleteStellarModal()" class="p-2 hover:bg-gray-700 rounded-lg transition-colors">&times;</button>
            </div>
            <div id="bulk-complete-stellar-content" class="p-6 space-y-4">
                <p id="bulk-complete-stellar-summary" class="text-sm font-medium text-amber-300">—</p>
                <p class="text-sm text-gray-400">Enter the vault address and secret key. Vault and secret are used only in your browser and never sent to the server.</p>
                <div>
                    <label for="stellar-vault-address" class="block text-sm text-gray-300 font-medium mb-2">Vault address (public key, e.g. GXXX...)</label>
                    <input type="text" id="stellar-vault-address" placeholder="G..." class="w-full bg-black border border-gray-700 rounded-lg px-4 py-3 font-mono text-sm text-white focus:outline-none focus:border-gray-500" />
                </div>
                <div>
                    <label for="stellar-secret-key" class="block text-sm text-gray-300 font-medium mb-2">Secret key for vault</label>
                    <input type="password" id="stellar-secret-key" placeholder="S..." class="w-full bg-black border border-gray-700 rounded-lg px-4 py-3 font-mono text-sm text-white focus:outline-none focus:border-gray-500" />
                </div>
                <p class="text-xs text-amber-400/90">Use only on a trusted, secure machine. Never share your screen while entering the secret key.</p>
                <div id="bulk-stellar-message" class="text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" id="bulk-stellar-submit-btn" onclick="submitBulkCompleteStellar()" class="flex-1 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-medium transition-colors">Submit</button>
                    <button type="button" onclick="closeBulkCompleteStellarModal()" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Base path for API URLs - use current page URL so fetches always hit the same endpoint
        const ADMIN_BASE = '<?= addslashes($adminBase) ?>';
        const ADMIN_CSRF_TOKEN = <?= json_encode($adminCsrfToken ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const STELLAR_ASSET_CODE = <?= json_encode($stellarConfig['asset_code'] ?? 'DB', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const STELLAR_ISSUER = <?= json_encode($stellarConfig['issuer'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const STELLAR_NETWORK = <?= json_encode($stellarConfig['network'] ?? 'public', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const WALLETCONNECT_PROJECT_ID = <?= json_encode($walletConnectProjectId ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const WALLET_WHITELIST_ENABLED = <?= json_encode($walletWhitelistEnabled ?? false) ?>;
        const WALLET_WHITELIST = <?= json_encode($walletWhitelist ?? []) ?>;
        const ALLOW_SKIP_EVM_ONCHAIN_VERIFY = <?= json_encode($allowSkipEvmOnchainVerify ?? false) ?>;
        const BLOCKED_WITHDRAWAL_ADDRESSES = <?= json_encode($blockedWithdrawalAddresses ?? ['stellar' => [], 'evm' => []]) ?>;
        function isBlockedWithdrawalAddress(address, network) {
            if (!address) return false;
            const a = String(address).trim();
            if (network === 'stellar') {
                return (BLOCKED_WITHDRAWAL_ADDRESSES.stellar || []).some(b => b && a.toUpperCase() === b.toUpperCase());
            }
            if (network === 'binance' || network === 'ethereum') {
                return (BLOCKED_WITHDRAWAL_ADDRESSES.evm || []).some(b => b && a.toLowerCase() === b.toLowerCase());
            }
            return false;
        }
        function adminUrl(params) {
            const base = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
            return base + (base.includes('?') ? '&' : '?') + (params || '');
        }
        function apiUrl(path) {
            const base = (ADMIN_BASE || '').replace(/\/+$/, '');
            const p = path.startsWith('/') ? path : '/' + path;
            return base + p;
        }

        let walletProvider = null;
        let walletSigner = null;
        let walletAddress = null;
        let walletRawProvider = null; // EIP-1193 provider for network switch
        let wcProvider = null; // WalletConnect provider instance (for disconnect)

        function openConnectWalletModal() {
            document.getElementById('connect-wallet-modal').classList.remove('hidden');
            document.getElementById('connect-wallet-modal').classList.add('flex');
        }
        function closeConnectWalletModal(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('connect-wallet-modal').classList.add('hidden');
            document.getElementById('connect-wallet-modal').classList.remove('flex');
        }

        async function connectWithBrowserWallet() {
            if (typeof ethers === 'undefined') {
                alert('Ethers.js not loaded. Please refresh the page.');
                return;
            }
            if (!window.ethereum) {
                alert('No browser wallet detected. Please install MetaMask or use WalletConnect.');
                return;
            }
            closeConnectWalletModal();
            const btn = document.getElementById('btn-connect-wallet');
            const status = document.getElementById('manual-wallet-status');
            try {
                btn.disabled = true;
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (accounts && accounts.length > 0) {
                    walletRawProvider = window.ethereum;
                    walletProvider = new ethers.providers.Web3Provider(window.ethereum);
                    walletSigner = walletProvider.getSigner();
                    walletAddress = accounts[0];
                    status.textContent = walletAddress.slice(0, 6) + '...' + walletAddress.slice(-4);
                    btn.textContent = 'Disconnect';
                    btn.onclick = disconnectWallet;
                }
            } catch (err) {
                console.error('Wallet connect error:', err);
                alert(err.message || 'Failed to connect wallet');
            }
            btn.disabled = false;
        }

        async function connectWithWalletConnect() {
            if (typeof ethers === 'undefined') {
                alert('Ethers.js not loaded. Please refresh the page.');
                return;
            }
            if (!WALLETCONNECT_PROJECT_ID) {
                alert('WalletConnect is not configured. Please set WALLETCONNECT_PROJECT_ID in .env (get it from cloud.walletconnect.com).');
                return;
            }
            closeConnectWalletModal();
            const btn = document.getElementById('btn-connect-wallet');
            const status = document.getElementById('manual-wallet-status');
            try {
                btn.disabled = true;
                const { EthereumProvider } = await import('https://esm.sh/@walletconnect/ethereum-provider@2');
                wcProvider = await EthereumProvider.init({
                    projectId: WALLETCONNECT_PROJECT_ID,
                    metadata: {
                        name: 'DBV Bridge Admin',
                        description: 'Admin panel for manual withdrawals',
                        url: window.location.origin,
                        icons: [window.location.origin + (ADMIN_BASE || '') + '/dbvheader.jpg']
                    },
                    showQrModal: true,
                    chains: [1, 56],
                    optionalChains: [1, 56]
                });
                await wcProvider.connect();
                const accounts = await wcProvider.request({ method: 'eth_accounts' });
                if (accounts && accounts.length > 0) {
                    walletRawProvider = wcProvider;
                    walletProvider = new ethers.providers.Web3Provider(wcProvider);
                    walletSigner = await walletProvider.getSigner();
                    walletAddress = accounts[0];
                    status.textContent = walletAddress.slice(0, 6) + '...' + walletAddress.slice(-4);
                    btn.textContent = 'Disconnect';
                    btn.onclick = disconnectWallet;
                }
            } catch (err) {
                console.error('WalletConnect error:', err);
                alert(err.message || 'Failed to connect via WalletConnect');
            }
            btn.disabled = false;
        }

        function connectWallet() {
            if (walletProvider) {
                disconnectWallet();
                return;
            }
            openConnectWalletModal();
        }

        function disconnectWallet() {
            if (wcProvider) {
                try { wcProvider.disconnect(); } catch (e) {}
                wcProvider = null;
            }
            walletProvider = null;
            walletSigner = null;
            walletAddress = null;
            walletRawProvider = null;
            const btn = document.getElementById('btn-connect-wallet');
            const status = document.getElementById('manual-wallet-status');
            status.textContent = 'Wallet not connected';
            btn.textContent = 'Connect Wallet';
            btn.onclick = connectWallet;
        }

        function updateBulkCompleteButton() {
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const stellarItems = checkboxes.filter(cb => (cb.dataset.network || '') === 'stellar');
            const evmItems = checkboxes.filter(cb => ['binance', 'ethereum'].includes(cb.dataset.network || ''));
            const btn = document.getElementById('bulk-complete-btn');
            const countEl = document.getElementById('bulk-complete-count');
            const stellarBtn = document.getElementById('bulk-complete-stellar-btn');
            const stellarCountEl = document.getElementById('bulk-complete-stellar-count');
            const stellarDbvEl = document.getElementById('bulk-complete-stellar-dbv');
            if (btn && countEl) {
                if (evmItems.length > 0) {
                    btn.classList.remove('hidden');
                    countEl.textContent = evmItems.length;
                } else {
                    btn.classList.add('hidden');
                    countEl.textContent = '0';
                }
            }
            if (stellarBtn && stellarCountEl) {
                if (stellarItems.length > 0) {
                    stellarBtn.classList.remove('hidden');
                    stellarCountEl.textContent = stellarItems.length;
                    const totalDbv = stellarItems.reduce((sum, cb) => {
                        try {
                            const d = JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                            return sum + parseFloat(d.amount || 0);
                        } catch (e) { return sum; }
                    }, 0);
                    if (stellarDbvEl) stellarDbvEl.textContent = totalDbv.toFixed(2) + ' DBV';
                } else {
                    stellarBtn.classList.add('hidden');
                    stellarCountEl.textContent = '0';
                    if (stellarDbvEl) stellarDbvEl.textContent = '— DBV';
                }
            }
            const evmPkBtn = document.getElementById('bulk-complete-evm-pk-btn');
            const evmPkCountEl = document.getElementById('bulk-complete-evm-pk-count');
            if (evmPkBtn && evmPkCountEl) {
                if (evmItems.length > 0) {
                    evmPkBtn.classList.remove('hidden');
                    evmPkCountEl.textContent = evmItems.length;
                } else {
                    evmPkBtn.classList.add('hidden');
                    evmPkCountEl.textContent = '0';
                }
            }
        }
        function toggleManualSelectAll(checked, networkFilter) {
            document.querySelectorAll('.manual-row-checkbox').forEach(cb => {
                const net = cb.dataset.network || '';
                if (!networkFilter || net === networkFilter) cb.checked = checked;
            });
            updateBulkCompleteButton();
        }
        let bulkCompleteAborted = false;
        let bulkEscapeHandler = null;
        function bulkCompleteAbort() {
            bulkCompleteAborted = true;
        }
        function bulkSetFinalState(state) {
            const spinner = document.getElementById('bulk-spinner');
            if (spinner) {
                spinner.classList.remove('animate-spin');
                if (state === 'success') {
                    spinner.className = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-green-500';
                    spinner.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                } else if (state === 'error') {
                    spinner.className = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-red-500';
                    spinner.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                } else if (state === 'cancelled') {
                    spinner.className = 'flex-shrink-0 w-10 h-10 rounded-full border-2 border-gray-500';
                    spinner.innerHTML = '';
                }
            }
            if (bulkEscapeHandler) {
                document.removeEventListener('keydown', bulkEscapeHandler);
                bulkEscapeHandler = null;
            }
        }
        function bulkResetSpinner() {
            const spinner = document.getElementById('bulk-spinner');
            if (spinner) {
                spinner.className = 'flex-shrink-0 w-10 h-10 rounded-full border-2 border-indigo-500 border-t-transparent animate-spin';
                spinner.innerHTML = '';
            }
        }
        function bulkCloseModal(modal, cancelBtn) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = bulkCompleteAbort;
            if (bulkEscapeHandler) {
                document.removeEventListener('keydown', bulkEscapeHandler);
                bulkEscapeHandler = null;
            }
            loadManualWithdrawals();
            if (typeof loadStats === 'function') loadStats();
            updateBulkCompleteButton();
        }

        function normalizeEvmPrivateKey(raw) {
            let s = (raw || '').trim();
            if (!s) return '';
            if (!/^0x/i.test(s)) s = '0x' + s;
            return s;
        }

        async function executeEvmTransferWithParams(signer, tx, paramsData, onProgress, opts) {
            const apiBase = apiUrl('/api/admin/');
            const amountWei = paramsData.amount_wei;
            const toAddress = (paramsData.to_address || '').replace(/^0X/i, '0x').toLowerCase();
            if (!/^0x[0-9a-f]{40}$/i.test(toAddress)) {
                return { success: false, error: 'Invalid Ethereum/BSC address format' };
            }
            const chainId = parseInt(paramsData.chain_id, 10);
            const currentChain = await signer.provider.getNetwork();
            const currentChainId = parseInt(currentChain.chainId, 10);
            if (currentChainId !== chainId) {
                return {
                    success: false,
                    error: 'RPC network mismatch (chain ' + currentChainId + ', expected ' + chainId + '). Check RPC URL in server config for ' + (tx.network || 'network') + '.'
                };
            }
            const abiTransfer = ['function transfer(address to, uint256 amount) returns (bool)', 'function balanceOf(address owner) view returns (uint256)'];
            const contractWithSigner = new ethers.Contract(paramsData.token_contract, abiTransfer, signer);
            const balance = await contractWithSigner.balanceOf(await signer.getAddress());
            if (balance.lt(amountWei)) {
                return { success: false, error: 'Insufficient balance. Need ' + ethers.utils.formatUnits(amountWei, 18) + ' tokens.' };
            }
            const pkFlow = opts && opts.privateKeyFlow;
            if (onProgress) onProgress(pkFlow ? 'Sending transaction...' : 'Confirm in wallet...');
            const txResponse = await contractWithSigner.transfer(toAddress, amountWei, { gasLimit: 100000 });
            if (onProgress) onProgress('Waiting for confirmation...');
            const receipt = await txResponse.wait();
            const hash = receipt.transactionHash;
            const formData = new FormData();
            formData.append('csrf_token', typeof ADMIN_CSRF_TOKEN !== 'undefined' ? ADMIN_CSRF_TOKEN : '');
            formData.append('network', tx.network);
            formData.append('withdrawal_id', tx.id);
            formData.append('txn_hash', hash);
            const completeRes = await fetch(apiBase + 'complete-manual-withdrawal.php', { method: 'POST', body: formData });
            const completeData = await completeRes.json();
            if (completeData.success) {
                return { success: true, hash };
            }
            return { success: false, error: completeData.message || 'Verification failed' };
        }

        async function executeSingleWithdrawal(tx, onProgress) {
            const apiBase = apiUrl('/api/admin/');
            const paramsRes = await fetch(apiBase + 'withdrawal-tx-params.php?network=' + encodeURIComponent(tx.network) + '&withdrawal_id=' + tx.id);
            const paramsData = await paramsRes.json();
            if (!paramsData.success) {
                return { success: false, error: paramsData.message || 'Failed to get transaction parameters' };
            }
            const chainId = parseInt(paramsData.chain_id, 10);
            const currentChain = await walletSigner.provider.getNetwork();
            if (parseInt(currentChain.chainId, 10) !== chainId) {
                if (onProgress) onProgress('Switching network...');
                try {
                    const rawProvider = walletRawProvider || window.ethereum;
                    await rawProvider.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0x' + chainId.toString(16) }] });
                    walletProvider = new ethers.providers.Web3Provider(rawProvider);
                    walletSigner = await walletProvider.getSigner();
                } catch (switchErr) {
                    return { success: false, error: 'Please switch your wallet to the correct network (Chain ID: ' + chainId + ')' };
                }
            }
            return executeEvmTransferWithParams(walletSigner, tx, paramsData, onProgress, { privateKeyFlow: false });
        }

        async function bulkCompleteWithWallet() {
            if (!walletSigner) {
                alert('Please connect your wallet first.');
                return;
            }
            if (WALLET_WHITELIST_ENABLED && WALLET_WHITELIST.length > 0) {
                const addr = (walletAddress || '').toLowerCase();
                if (!WALLET_WHITELIST.includes(addr)) {
                    alert('This wallet address is not authorized to complete withdrawals.');
                    return;
                }
            }
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const items = checkboxes.map(cb => {
                try {
                    return JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                } catch (e) { return null; }
            }).filter(tx => tx && (tx.network === 'binance' || tx.network === 'ethereum'));
            if (items.length === 0) {
                alert('Select at least one BSC or Ethereum withdrawal.');
                return;
            }
            const byNetwork = { binance: [], ethereum: [] };
            items.forEach(tx => { if (byNetwork[tx.network]) byNetwork[tx.network].push(tx); });
            const ordered = [...byNetwork.binance, ...byNetwork.ethereum];
            const totalDbv = ordered.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
            const confirmMsg = 'Complete ' + ordered.length + ' withdrawal(s)?\n\nTotal: ' + totalDbv.toFixed(2) + ' DBV\n\nYou will need to sign ' + ordered.length + ' transaction(s) in your wallet.';
            if (!confirm(confirmMsg)) return;
            bulkCompleteAborted = false;
            const modal = document.getElementById('bulk-complete-modal');
            const progressText = document.getElementById('bulk-progress-text');
            const progressDetail = document.getElementById('bulk-progress-detail');
            const progressBar = document.getElementById('bulk-progress-bar');
            const progressSummary = document.getElementById('bulk-progress-summary');
            const cancelBtn = document.getElementById('bulk-cancel-btn');
            bulkResetSpinner();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = bulkCompleteAbort;
            bulkEscapeHandler = (e) => { if (e.key === 'Escape') bulkCompleteAbort(); };
            document.addEventListener('keydown', bulkEscapeHandler);
            let completed = 0;
            const total = ordered.length;
            for (let i = 0; i < ordered.length; i++) {
                if (bulkCompleteAborted) {
                    progressText.textContent = 'Cancelled';
                    progressDetail.textContent = completed + ' of ' + total + ' completed before cancel.';
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed';
                    bulkSetFinalState('cancelled');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
                const tx = ordered[i];
                progressText.textContent = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + ')';
                progressDetail.textContent = (i + 1) + ' of ' + total + ' • ' + parseFloat(tx.amount || 0).toFixed(2) + ' DBV to ' + (tx.address || '').slice(0, 10) + '...';
                progressBar.style.width = ((completed / total) * 100) + '%';
                progressSummary.textContent = completed + ' of ' + total + ' completed';
                try {
                    const result = await executeSingleWithdrawal(tx, (msg) => { progressDetail.textContent = msg; });
                    if (result.success) {
                        completed++;
                        progressBar.style.width = ((completed / total) * 100) + '%';
                        progressSummary.textContent = completed + ' of ' + total + ' completed';
                        const cb = checkboxes.find(c => {
                            try {
                                const d = JSON.parse(decodeURIComponent(escape(atob(c.dataset.tx))));
                                return d.id === tx.id && d.network === tx.network;
                            } catch (e) { return false; }
                        });
                        if (cb) cb.checked = false;
                    } else {
                        const errCtx = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + '): ' + result.error + (tx.address ? ' Address: ' + (tx.address.slice(0, 10) + '...' + tx.address.slice(-6)) : '');
                        progressText.textContent = 'Error';
                        progressDetail.textContent = errCtx;
                        progressBar.style.width = '100%';
                        progressSummary.textContent = completed + ' of ' + total + ' completed. Failed: ' + result.error;
                        bulkSetFinalState('error');
                        cancelBtn.textContent = 'Close';
                        cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                        return;
                    }
                } catch (err) {
                    const errCtx = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + '): ' + (err.message || 'Transaction failed') + (tx.address ? ' Address: ' + (tx.address.slice(0, 10) + '...' + tx.address.slice(-6)) : '');
                    progressText.textContent = 'Error';
                    progressDetail.textContent = errCtx;
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed. Error: ' + (err.message || 'Unknown');
                    bulkSetFinalState('error');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
            }
            progressBar.style.width = '100%';
            bulkSetFinalState('success');
            progressText.textContent = 'Done';
            progressDetail.textContent = completed + ' withdrawal(s) completed successfully.';
            progressSummary.textContent = completed + ' of ' + total + ' completed';
            cancelBtn.textContent = 'Close';
            cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
            loadManualWithdrawals();
            if (typeof loadStats === 'function') loadStats();
            updateBulkCompleteButton();
        }

        function openBulkCompleteEvmPrivateKeyModal() {
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const evmItems = checkboxes.filter(cb => ['binance', 'ethereum'].includes(cb.dataset.network || ''));
            if (evmItems.length === 0) {
                alert('Select at least one BSC or Ethereum withdrawal.');
                return;
            }
            const totalDbv = evmItems.reduce((sum, cb) => {
                try {
                    const d = JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                    return sum + parseFloat(d.amount || 0);
                } catch (e) { return sum; }
            }, 0);
            const summaryEl = document.getElementById('bulk-complete-evm-pk-summary');
            if (summaryEl) summaryEl.textContent = evmItems.length + ' withdrawal(s) • Total: ' + totalDbv.toFixed(2) + ' DBV';
            document.getElementById('evm-pk-vault-address').value = '';
            document.getElementById('evm-pk-private-key').value = '';
            const msgEl = document.getElementById('bulk-evm-pk-message');
            msgEl.classList.add('hidden');
            msgEl.textContent = '';
            document.getElementById('bulk-complete-evm-pk-modal').classList.remove('hidden');
            document.getElementById('bulk-complete-evm-pk-modal').classList.add('flex');
        }
        function closeBulkCompleteEvmPrivateKeyModal(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('bulk-complete-evm-pk-modal').classList.add('hidden');
            document.getElementById('bulk-complete-evm-pk-modal').classList.remove('flex');
        }
        async function submitBulkCompleteEvmPrivateKey() {
            const vaultInput = document.getElementById('evm-pk-vault-address');
            const pkInput = document.getElementById('evm-pk-private-key');
            const msgEl = document.getElementById('bulk-evm-pk-message');
            const vaultAddress = (vaultInput.value || '').trim();
            const pkNorm = normalizeEvmPrivateKey(pkInput.value);
            msgEl.classList.add('hidden');
            if (!vaultAddress || !pkNorm) {
                msgEl.textContent = 'Please enter both vault address and private key.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            if (typeof ethers === 'undefined') {
                msgEl.textContent = 'ethers.js not loaded. Please refresh the page.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            let baseWallet;
            try {
                baseWallet = new ethers.Wallet(pkNorm);
            } catch (e) {
                msgEl.textContent = 'Invalid private key format.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            const vaultLower = vaultAddress.replace(/^0X/i, '0x').trim().toLowerCase();
            if (baseWallet.address.toLowerCase() !== vaultLower) {
                msgEl.textContent = 'Private key does not match the vault address.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            if (WALLET_WHITELIST_ENABLED && WALLET_WHITELIST.length > 0) {
                const addr = baseWallet.address.toLowerCase();
                if (!WALLET_WHITELIST.includes(addr)) {
                    msgEl.textContent = 'This vault address is not authorized to complete withdrawals.';
                    msgEl.classList.remove('hidden');
                    msgEl.className = 'text-sm text-red-400';
                    return;
                }
            }
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const items = checkboxes.map(cb => {
                try {
                    return JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                } catch (e) { return null; }
            }).filter(tx => tx && (tx.network === 'binance' || tx.network === 'ethereum'));
            if (items.length === 0) {
                msgEl.textContent = 'No BSC or Ethereum withdrawals selected.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            closeBulkCompleteEvmPrivateKeyModal();
            vaultInput.value = '';
            pkInput.value = '';
            await runBulkCompleteEvmPrivateKey(items, baseWallet);
        }
        async function runBulkCompleteEvmPrivateKey(items, baseWallet) {
            const apiBase = apiUrl('/api/admin/');
            const byNetwork = { binance: [], ethereum: [] };
            items.forEach(tx => { if (byNetwork[tx.network]) byNetwork[tx.network].push(tx); });
            const ordered = [...byNetwork.binance, ...byNetwork.ethereum];
            const modal = document.getElementById('bulk-complete-modal');
            const progressText = document.getElementById('bulk-progress-text');
            const progressDetail = document.getElementById('bulk-progress-detail');
            const progressBar = document.getElementById('bulk-progress-bar');
            const progressSummary = document.getElementById('bulk-progress-summary');
            const cancelBtn = document.getElementById('bulk-cancel-btn');
            bulkCompleteAborted = false;
            bulkResetSpinner();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = bulkCompleteAbort;
            bulkEscapeHandler = (e) => { if (e.key === 'Escape') bulkCompleteAbort(); };
            document.addEventListener('keydown', bulkEscapeHandler);
            let completed = 0;
            const total = ordered.length;
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            for (let i = 0; i < ordered.length; i++) {
                if (bulkCompleteAborted) {
                    progressText.textContent = 'Cancelled';
                    progressDetail.textContent = completed + ' of ' + total + ' completed before cancel.';
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed';
                    bulkSetFinalState('cancelled');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
                const tx = ordered[i];
                progressText.textContent = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + ')';
                progressDetail.textContent = (i + 1) + ' of ' + total + ' • Loading parameters...';
                progressBar.style.width = ((completed / total) * 100) + '%';
                progressSummary.textContent = completed + ' of ' + total + ' completed';
                try {
                    const paramsRes = await fetch(apiBase + 'withdrawal-tx-params.php?network=' + encodeURIComponent(tx.network) + '&withdrawal_id=' + tx.id + '&needs_rpc=1');
                    const paramsData = await paramsRes.json();
                    if (!paramsData.success) {
                        progressText.textContent = 'Error';
                        progressDetail.textContent = 'Withdrawal #' + tx.id + ': ' + (paramsData.message || 'Failed to get transaction parameters');
                        progressBar.style.width = '100%';
                        progressSummary.textContent = completed + ' of ' + total + ' completed. Failed.';
                        bulkSetFinalState('error');
                        cancelBtn.textContent = 'Close';
                        cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                        return;
                    }
                    const rpcUrl = String(paramsData.rpc_url || '').trim();
                    if (!rpcUrl) {
                        progressText.textContent = 'Error';
                        progressDetail.textContent = 'Withdrawal #' + tx.id + ': RPC URL missing for ' + (tx.network || '') + '. Configure RPC in .env.';
                        progressBar.style.width = '100%';
                        bulkSetFinalState('error');
                        cancelBtn.textContent = 'Close';
                        cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                        return;
                    }
                    const provider = new ethers.providers.JsonRpcProvider(rpcUrl);
                    const signer = baseWallet.connect(provider);
                    progressDetail.textContent = (i + 1) + ' of ' + total + ' • ' + parseFloat(tx.amount || 0).toFixed(2) + ' DBV to ' + (tx.address || '').slice(0, 10) + '...';
                    const result = await executeEvmTransferWithParams(signer, tx, paramsData, (msg) => { progressDetail.textContent = msg; }, { privateKeyFlow: true });
                    if (result.success) {
                        completed++;
                        progressBar.style.width = ((completed / total) * 100) + '%';
                        progressSummary.textContent = completed + ' of ' + total + ' completed';
                        const cb = checkboxes.find(c => {
                            try {
                                const d = JSON.parse(decodeURIComponent(escape(atob(c.dataset.tx))));
                                return d.id === tx.id && d.network === tx.network;
                            } catch (e) { return false; }
                        });
                        if (cb) cb.checked = false;
                    } else {
                        const errCtx = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + '): ' + result.error + (tx.address ? ' Address: ' + (tx.address.slice(0, 10) + '...' + tx.address.slice(-6)) : '');
                        progressText.textContent = 'Error';
                        progressDetail.textContent = errCtx;
                        progressBar.style.width = '100%';
                        progressSummary.textContent = completed + ' of ' + total + ' completed. Failed: ' + result.error;
                        bulkSetFinalState('error');
                        cancelBtn.textContent = 'Close';
                        cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                        return;
                    }
                } catch (err) {
                    const errCtx = 'Withdrawal #' + tx.id + ' (' + (tx.network || '').toUpperCase() + '): ' + (err.message || 'Transaction failed') + (tx.address ? ' Address: ' + (tx.address.slice(0, 10) + '...' + tx.address.slice(-6)) : '');
                    progressText.textContent = 'Error';
                    progressDetail.textContent = errCtx;
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed. Error: ' + (err.message || 'Unknown');
                    bulkSetFinalState('error');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
            }
            progressBar.style.width = '100%';
            bulkSetFinalState('success');
            progressText.textContent = 'Done';
            progressDetail.textContent = completed + ' withdrawal(s) completed successfully.';
            progressSummary.textContent = completed + ' of ' + total + ' completed';
            cancelBtn.textContent = 'Close';
            cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
            loadManualWithdrawals();
            if (typeof loadStats === 'function') loadStats();
            updateBulkCompleteButton();
        }

        function openBulkCompleteStellarModal() {
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const stellarItems = checkboxes.filter(cb => (cb.dataset.network || '') === 'stellar');
            if (stellarItems.length === 0) {
                alert('Select at least one Stellar withdrawal.');
                return;
            }
            const totalDbv = stellarItems.reduce((sum, cb) => {
                try {
                    const d = JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                    return sum + parseFloat(d.amount || 0);
                } catch (e) { return sum; }
            }, 0);
            const summaryEl = document.getElementById('bulk-complete-stellar-summary');
            if (summaryEl) summaryEl.textContent = stellarItems.length + ' withdrawal(s) • Total: ' + totalDbv.toFixed(2) + ' DBV';
            document.getElementById('stellar-vault-address').value = '';
            document.getElementById('stellar-secret-key').value = '';
            const msgEl = document.getElementById('bulk-stellar-message');
            msgEl.classList.add('hidden');
            msgEl.textContent = '';
            document.getElementById('bulk-complete-stellar-modal').classList.remove('hidden');
            document.getElementById('bulk-complete-stellar-modal').classList.add('flex');
        }
        function closeBulkCompleteStellarModal(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('bulk-complete-stellar-modal').classList.add('hidden');
            document.getElementById('bulk-complete-stellar-modal').classList.remove('flex');
        }
        async function submitBulkCompleteStellar() {
            const vaultInput = document.getElementById('stellar-vault-address');
            const secretInput = document.getElementById('stellar-secret-key');
            const msgEl = document.getElementById('bulk-stellar-message');
            const submitBtn = document.getElementById('bulk-stellar-submit-btn');
            const vaultAddress = (vaultInput.value || '').trim();
            const secretKey = (secretInput.value || '').trim();
            msgEl.classList.add('hidden');
            if (!vaultAddress || !secretKey) {
                msgEl.textContent = 'Please enter both vault address and secret key.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            if (typeof StellarSdk === 'undefined') {
                msgEl.textContent = 'Stellar SDK not loaded. Please refresh the page.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            let keypair;
            try {
                keypair = StellarSdk.Keypair.fromSecret(secretKey);
            } catch (e) {
                msgEl.textContent = 'Invalid secret key format.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            const derivedPublic = keypair.publicKey();
            const vaultNorm = vaultAddress.replace(/\s/g, '');
            if (derivedPublic !== vaultNorm) {
                msgEl.textContent = 'Secret key does not match the vault address.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            const checkboxes = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
            const items = checkboxes.map(cb => {
                try {
                    return JSON.parse(decodeURIComponent(escape(atob(cb.dataset.tx))));
                } catch (e) { return null; }
            }).filter(tx => tx && tx.network === 'stellar');
            if (items.length === 0) {
                msgEl.textContent = 'No Stellar withdrawals selected.';
                msgEl.classList.remove('hidden');
                msgEl.className = 'text-sm text-red-400';
                return;
            }
            closeBulkCompleteStellarModal();
            vaultInput.value = '';
            secretInput.value = '';
            await runBulkCompleteStellar(items, keypair);
        }
        async function runBulkCompleteStellar(items, keypair) {
            const apiBase = apiUrl('/api/admin/');
            const horizonUrl = STELLAR_NETWORK === 'public' ? 'https://horizon.stellar.org' : 'https://horizon-testnet.stellar.org';
            const networkPass = STELLAR_NETWORK === 'public' ? StellarSdk.Networks.PUBLIC : StellarSdk.Networks.TESTNET;
            const assetCode = STELLAR_ASSET_CODE || 'DB';
            const issuer = STELLAR_ISSUER || '';
            if (!issuer) {
                alert('Stellar issuer not configured. Check ASSET_ISSUER in .env');
                return;
            }
            const modal = document.getElementById('bulk-complete-modal');
            const progressText = document.getElementById('bulk-progress-text');
            const progressDetail = document.getElementById('bulk-progress-detail');
            const progressBar = document.getElementById('bulk-progress-bar');
            const progressSummary = document.getElementById('bulk-progress-summary');
            const cancelBtn = document.getElementById('bulk-cancel-btn');
            bulkCompleteAborted = false;
            bulkResetSpinner();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Cancel';
            cancelBtn.onclick = bulkCompleteAbort;
            bulkEscapeHandler = (e) => { if (e.key === 'Escape') bulkCompleteAbort(); };
            document.addEventListener('keydown', bulkEscapeHandler);
            let completed = 0;
            let skipped = 0;
            const total = items.length;
            const server = new StellarSdk.Horizon.Server(horizonUrl);
            const asset = new StellarSdk.Asset(assetCode, issuer);
            function hasTrustline(balances, code, iss) {
                if (!balances || !Array.isArray(balances)) return false;
                const codeU = (code || '').toUpperCase();
                const issU = (iss || '').toUpperCase();
                return balances.some(b => {
                    if (b.asset_type === 'native') return false;
                    const bc = (b.asset_code || '').toUpperCase();
                    const bi = (b.asset_issuer || '').toUpperCase();
                    return bc === codeU && bi === issU;
                });
            }
            for (let i = 0; i < items.length; i++) {
                if (bulkCompleteAborted) {
                    progressText.textContent = 'Cancelled';
                    progressDetail.textContent = completed + ' of ' + total + ' completed before cancel.' + (skipped > 0 ? ' ' + skipped + ' skipped.' : '');
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed' + (skipped > 0 ? ' (' + skipped + ' skipped)' : '');
                    bulkSetFinalState('cancelled');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
                const tx = items[i];
                progressText.textContent = 'Withdrawal #' + tx.id + ' (STELLAR)';
                progressDetail.textContent = (i + 1) + ' of ' + total + ' • Checking trustline...';
                progressBar.style.width = ((completed / total) * 100) + '%';
                progressSummary.textContent = completed + ' of ' + total + ' completed' + (skipped > 0 ? ' (' + skipped + ' skipped)' : '');
                try {
                    let destAccount;
                    try {
                        destAccount = await server.loadAccount(tx.address);
                    } catch (e) {
                        progressDetail.textContent = (i + 1) + ' of ' + total + ' • Skipping: address not found on network';
                        skipped++;
                        continue;
                    }
                    if (!hasTrustline(destAccount.balances, assetCode, issuer)) {
                        progressDetail.textContent = (i + 1) + ' of ' + total + ' • Skipped: no trustline for ' + (tx.address || '').slice(0, 12) + '...';
                        skipped++;
                        continue;
                    }
                    progressDetail.textContent = (i + 1) + ' of ' + total + ' • ' + parseFloat(tx.amount || 0).toFixed(2) + ' DBV to ' + (tx.address || '').slice(0, 12) + '...';
                    const account = await server.loadAccount(keypair.publicKey());
                    const transaction = new StellarSdk.TransactionBuilder(account, {
                        fee: StellarSdk.BASE_FEE * 1000,
                        networkPassphrase: networkPass
                    })
                        .addOperation(StellarSdk.Operation.payment({
                            destination: tx.address,
                            asset: asset,
                            amount: String(parseFloat(tx.amount || 0))
                        }))
                        .setTimeout(300)
                        .build();
                    transaction.sign(keypair);
                    const result = await server.submitTransaction(transaction);
                    const hash = result && result.hash ? result.hash : '';
                    if (hash && hash.length === 64) {
                        progressDetail.textContent = (i + 1) + ' of ' + total + ' • Waiting for Horizon to index...';
                        await new Promise(r => setTimeout(r, 2500));
                        const formData = new FormData();
                        formData.append('csrf_token', ADMIN_CSRF_TOKEN || '');
                        formData.append('network', 'stellar');
                        formData.append('withdrawal_id', tx.id);
                        formData.append('txn_hash', hash);
                        const completeRes = await fetch(apiBase + 'complete-manual-withdrawal.php', { method: 'POST', body: formData });
                        const completeData = await completeRes.json();
                        if (completeData.success) {
                            completed++;
                            progressBar.style.width = ((completed / total) * 100) + '%';
                            progressSummary.textContent = completed + ' of ' + total + ' completed' + (skipped > 0 ? ' (' + skipped + ' skipped)' : '');
                            const allCbs = Array.from(document.querySelectorAll('.manual-row-checkbox:checked'));
                            const matchingCb = allCbs.find(c => {
                                try {
                                    const d = JSON.parse(decodeURIComponent(escape(atob(c.dataset.tx))));
                                    return d.id === tx.id && d.network === 'stellar';
                                } catch (e) { return false; }
                            });
                            if (matchingCb) matchingCb.checked = false;
                            if (i < items.length - 1) await new Promise(r => setTimeout(r, 500));
                        } else {
                            progressText.textContent = 'Error';
                            let errDetail = 'Withdrawal #' + tx.id + ': ' + (completeData.message || 'Verification failed');
                            if (completeData.debug) {
                                const d = completeData.debug;
                                errDetail += '\n\nExpected: vault=' + (d.expected?.vault || '?') + ' to=' + (d.expected?.to || '?') + ' amount=' + (d.expected?.amount ?? '?');
                                if (d.payment_ops?.length) {
                                    errDetail += '\nTransaction has ' + d.payment_ops.length + ' payment(s):';
                                    d.payment_ops.forEach((p, i) => {
                                        errDetail += '\n  #' + (i+1) + ': from=' + (p.from || '?') + ' to=' + (p.to || '?') + ' amount=' + (p.amount ?? '?') + ' (vault✓=' + p.from_match + ' to✓=' + p.to_match + ' amt✓=' + p.amount_match + ')';
                                    });
                                }
                            }
                            progressDetail.textContent = errDetail;
                            if (completeData.debug) console.log('Stellar verification debug:', completeData.debug);
                            progressBar.style.width = '100%';
                            bulkSetFinalState('error');
                            cancelBtn.textContent = 'Close';
                            cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                            return;
                        }
                    } else {
                        throw new Error('No transaction hash returned');
                    }
                } catch (err) {
                    const errMsg = err.response?.data?.extras?.result_codes?.transaction || err.message || String(err);
                    progressText.textContent = 'Error';
                    progressDetail.textContent = 'Withdrawal #' + tx.id + ': ' + errMsg;
                    progressBar.style.width = '100%';
                    progressSummary.textContent = completed + ' of ' + total + ' completed' + (skipped > 0 ? ' (' + skipped + ' skipped)' : '') + '. Failed: ' + errMsg;
                    bulkSetFinalState('error');
                    cancelBtn.textContent = 'Close';
                    cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
                    return;
                }
            }
            progressBar.style.width = '100%';
            bulkSetFinalState('success');
            progressText.textContent = 'Done';
            progressDetail.textContent = completed + ' Stellar withdrawal(s) completed successfully.' + (skipped > 0 ? ' ' + skipped + ' skipped (no trustline).' : '');
            progressSummary.textContent = completed + ' of ' + total + ' completed' + (skipped > 0 ? ' (' + skipped + ' skipped)' : '');
            cancelBtn.textContent = 'Close';
            cancelBtn.onclick = () => bulkCloseModal(modal, cancelBtn);
            loadManualWithdrawals();
            if (typeof loadStats === 'function') loadStats();
            updateBulkCompleteButton();
        }

        async function completeWithWallet(txDataB64) {
            if (!walletSigner) {
                alert('Please connect your wallet first.');
                return;
            }
            if (WALLET_WHITELIST_ENABLED && WALLET_WHITELIST.length > 0) {
                const addr = (walletAddress || '').toLowerCase();
                if (!WALLET_WHITELIST.includes(addr)) {
                    alert('This wallet address is not authorized to complete withdrawals.');
                    return;
                }
            }
            let tx;
            try {
                tx = JSON.parse(decodeURIComponent(escape(atob(txDataB64))));
            } catch (e) {
                console.error('Invalid tx data:', e);
                return;
            }
            if (tx.network !== 'binance' && tx.network !== 'ethereum') {
                alert('Complete with Wallet is only available for BSC and Ethereum.');
                return;
            }
            try {
                const result = await executeSingleWithdrawal(tx);
                if (result.success) {
                    alert('Withdrawal marked complete! Tx: ' + result.hash.slice(0, 18) + '...');
                    loadManualWithdrawals();
                    if (typeof loadStats === 'function') loadStats();
                } else {
                    alert(result.error || 'Failed to complete withdrawal');
                }
            } catch (err) {
                console.error('Complete with wallet error:', err);
                let msg = err.message || 'Failed to complete withdrawal';
                if (msg.includes('UNPREDICTABLE_GAS_LIMIT') || msg.includes('execution reverted')) {
                    msg += '\n\nLikely cause: Insufficient token balance in your connected wallet, or the token contract has transfer restrictions. Ensure your wallet holds enough tokens on the correct network.';
                }
                alert(msg);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btn-connect-wallet');
            if (btn) btn.onclick = connectWallet;
            const optBrowser = document.getElementById('wc-option-browser');
            if (optBrowser) optBrowser.onclick = connectWithBrowserWallet;
            const optWC = document.getElementById('wc-option-walletconnect');
            if (optWC) optWC.onclick = connectWithWalletConnect;
        });

        function showTransactionDetails(tx) {
            const modal = document.getElementById('tx-details-modal');
            const content = document.getElementById('tx-details-content');
            const statusMap = { 0: 'Pending', 1: 'Processing', 2: 'Failed', 3: 'Completed', 8: 'Pre-Complete', 9: 'Cancelled' };
            const status = statusMap[tx.status] ?? 'Unknown';
            const networkHash = tx.txn_hash_stellar || tx.txn_hash_network || tx.txn_hash_yemchain || '—';
            content.innerHTML = `
                <div class="grid gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><span class="text-gray-400">ID</span><div class="font-mono text-white">#${tx.id}</div></div>
                        <div><span class="text-gray-400">Type</span><div class="font-medium">${(tx.type || '').toUpperCase()}</div></div>
                        <div><span class="text-gray-400">Network</span><div>${(tx.network || '').toUpperCase()}</div></div>
                        <div><span class="text-gray-400">Status</span><div>${status}</div></div>
                        <div><span class="text-gray-400">UID</span><div class="font-mono">${tx.uid ?? '—'}</div></div>
                        <div><span class="text-gray-400">Created</span><div>${tx.created_at || tx.formatted_time || '—'}</div></div>
                    </div>
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Amount (DBV)</div>
                        <div class="text-lg font-bold ${tx.type === 'withdrawal' ? 'text-red-300' : 'text-green-300'}">
                            ${tx.type === 'withdrawal' ? '−' : '+'}${parseFloat(tx.amount || 0).toFixed(2)} DBV ${tx.type === 'withdrawal' ? '(deducted)' : '(credited)'}
                        </div>
                    </div>
                    ${(tx.fee_usdd != null && parseFloat(tx.fee_usdd) > 0) ? `
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Fee (USDD)</div>
                        <div class="text-amber-400 font-medium">−${parseFloat(tx.fee_usdd).toFixed(2)} USDD (deducted)</div>
                    </div>
                    ` : ''}
                    ${tx.address ? `
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Address</div>
                        <div class="font-mono text-gray-300 break-all bg-gray-800 p-2 rounded">${tx.address}</div>
                    </div>
                    ` : ''}
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Network Hash</div>
                        <div class="font-mono text-gray-300 break-all bg-gray-800 p-2 rounded text-xs">${networkHash}</div>
                    </div>
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">DBV Hash</div>
                        <div class="font-mono text-gray-300 break-all bg-gray-800 p-2 rounded text-xs">${tx.txn_hash_yemchain || '—'}</div>
                    </div>
                    ${(tx.type === 'withdrawal' && tx.fee_hash_yemchain) ? `
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Fee Hash</div>
                        <div class="font-mono text-gray-300 break-all bg-gray-800 p-2 rounded text-xs">${tx.fee_hash_yemchain}</div>
                    </div>
                    ` : ''}
                    ${(tx.type === 'withdrawal' && tx.processed_by_admin_uid) ? `
                    <div class="border-t border-gray-700 pt-4">
                        <div class="text-gray-400 mb-1">Processed by Admin</div>
                        <div class="text-amber-300 font-medium">UID ${tx.processed_by_admin_uid}</div>
                    </div>
                    ` : ''}
                </div>
            `;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeTxDetailsModal(e) {
            if (e && e.target !== e.currentTarget && e.type === 'click') return;
            document.getElementById('tx-details-modal').classList.add('hidden');
            document.getElementById('tx-details-modal').classList.remove('flex');
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('tx-details-modal') && !document.getElementById('tx-details-modal').classList.contains('hidden')) {
                closeTxDetailsModal();
            }
            if (e.key === 'Escape' && document.getElementById('mark-complete-modal') && !document.getElementById('mark-complete-modal').classList.contains('hidden')) {
                closeMarkCompleteModal();
            }
        });

        let markCompleteCurrentTx = null;
        function openMarkCompleteModal(txDataB64) {
            try {
                const tx = JSON.parse(decodeURIComponent(escape(atob(txDataB64))));
                markCompleteCurrentTx = tx;
                document.getElementById('mc-id').textContent = '#' + tx.id;
                document.getElementById('mc-network').textContent = (tx.network || '').toUpperCase();
                document.getElementById('mc-amount').textContent = parseFloat(tx.amount || 0).toFixed(2) + ' DBV';
                const addrEl = document.getElementById('mc-address');
                addrEl.textContent = tx.address || '—';
                addrEl.title = tx.address || '';
                document.getElementById('mc-txn-hash').value = '';
                const skipRow = document.getElementById('mc-skip-onchain-row');
                const skipCb = document.getElementById('mc-skip-onchain-verify');
                if (skipCb) skipCb.checked = false;
                if (skipRow) {
                    if (ALLOW_SKIP_EVM_ONCHAIN_VERIFY && (tx.network === 'binance' || tx.network === 'ethereum')) {
                        skipRow.classList.remove('hidden');
                    } else {
                        skipRow.classList.add('hidden');
                    }
                }
                document.getElementById('mc-message').classList.add('hidden');
                document.getElementById('mc-message').textContent = '';
                document.getElementById('mark-complete-modal').classList.remove('hidden');
                document.getElementById('mark-complete-modal').classList.add('flex');
            } catch (err) {
                console.error('openMarkCompleteModal:', err);
            }
        }
        function closeMarkCompleteModal(e) {
            if (e && e.target !== e.currentTarget && e.type === 'click') return;
            document.getElementById('mark-complete-modal').classList.add('hidden');
            document.getElementById('mark-complete-modal').classList.remove('flex');
            markCompleteCurrentTx = null;
        }
        async function submitMarkComplete() {
            if (!markCompleteCurrentTx) return;
            const hash = (document.getElementById('mc-txn-hash').value || '').trim();
            if (!hash) {
                const msgEl = document.getElementById('mc-message');
                msgEl.textContent = 'Please enter the transaction hash';
                msgEl.className = 'text-sm text-red-400';
                msgEl.classList.remove('hidden');
                return;
            }
            const skipCb = document.getElementById('mc-skip-onchain-verify');
            const skipOnchain = !!(ALLOW_SKIP_EVM_ONCHAIN_VERIFY && markCompleteCurrentTx &&
                (markCompleteCurrentTx.network === 'binance' || markCompleteCurrentTx.network === 'ethereum') &&
                skipCb && skipCb.checked);
            if (skipOnchain) {
                const ok = confirm(
                    'Complete without on-chain verification?\n\n' +
                    'The withdrawal will be marked complete using only the pasted hash format. Recipient and amount will not be checked on the blockchain.\n\n' +
                    'Click OK only if you are certain this transaction is correct.'
                );
                if (!ok) return;
            }
            const btn = document.getElementById('mc-submit-btn');
            const msgEl = document.getElementById('mc-message');
            btn.disabled = true;
            msgEl.classList.add('hidden');
            try {
                const formData = new FormData();
                formData.append('csrf_token', ADMIN_CSRF_TOKEN || '');
                formData.append('network', markCompleteCurrentTx.network);
                formData.append('withdrawal_id', markCompleteCurrentTx.id);
                formData.append('txn_hash', hash);
                if (skipOnchain) {
                    formData.append('skip_onchain_verify', '1');
                }
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/complete-manual-withdrawal.php', { method: 'POST', body: formData });
                const text = await res.text();
                let data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    msgEl.textContent = 'Server returned invalid response. Check PHP error log or try again.';
                    msgEl.className = 'text-sm text-red-400';
                    msgEl.classList.remove('hidden');
                    btn.disabled = false;
                    return;
                }
                if (data.success) {
                    msgEl.textContent = 'Success! Withdrawal marked complete.';
                    msgEl.className = 'text-sm text-green-400';
                    msgEl.classList.remove('hidden');
                    loadTransactions();
                    if (typeof loadManualWithdrawals === 'function') loadManualWithdrawals();
                    setTimeout(() => closeMarkCompleteModal(), 1500);
                } else {
                    const errMsg = data.message || 'Failed to mark complete';
                    msgEl.textContent = errMsg;
                    msgEl.className = 'text-sm text-red-400';
                    msgEl.classList.remove('hidden');
                }
            } catch (err) {
                msgEl.textContent = 'Network error: ' + (err.message || 'Unknown');
                msgEl.className = 'text-sm text-red-400';
                msgEl.classList.remove('hidden');
            }
            btn.disabled = false;
        }

        // Auto-refresh stats every 30 seconds
        let statsInterval;
        let currentTab = 'transactions';

        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('border-red-600', 'text-white');
                btn.classList.add('border-transparent', 'text-gray-400');
            });
            document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-400');
            document.getElementById(`tab-${tab}`).classList.add('border-red-600', 'text-white');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.getElementById(`content-${tab}`).classList.remove('hidden');
            
            // Load content
            if (tab === 'transactions') loadTransactions();
            else if (tab === 'manual') loadManualWithdrawals();
            else if (tab === 'failed') loadFailedTransactions();
            else if (tab === 'commissions') loadCommissions();
            else if (tab === 'audit') loadAuditLog();
            else if (tab === 'logs') loadLogs();
            else if (tab === 'errors') loadErrors();
            else if (tab === 'network-logs') loadNetworkLogs();
            else if (tab === 'settings') loadManualWithdrawSettings();
        }

        async function loadStats() {
            try {
                const res = await fetch(adminUrl('action=stats'));
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('stat-users').textContent = data.stats.users.total.toLocaleString();
                    document.getElementById('stat-deposits').textContent = data.stats.deposits.total.toLocaleString();
                    document.getElementById('stat-deposits-volume').textContent = `Volume: ${parseFloat(data.stats.deposits.volume).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} DBV`;
                    document.getElementById('stat-withdrawals').textContent = data.stats.withdrawals.total.toLocaleString();
                    document.getElementById('stat-withdrawals-volume').textContent = `Volume: ${parseFloat(data.stats.withdrawals.volume).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} DBV`;
                    document.getElementById('stat-fees').textContent = `${parseFloat(data.stats.fees.total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} USDD`;
                    const comm = data.stats.commissions || { total_usdd: 0, total_count: 0 };
                    document.getElementById('stat-commissions').textContent = `${parseFloat(comm.total_usdd).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} USDD`;
                    document.getElementById('stat-commissions-count').textContent = `${(comm.total_count || 0).toLocaleString()} paid to referrers`;
                    document.getElementById('stat-pending').textContent = data.stats.withdrawals.pending.toLocaleString();
                    document.getElementById('stat-failed').textContent = data.stats.failed.total.toLocaleString();
                    document.getElementById('stat-failed-breakdown').textContent = `${data.stats.failed.deposits} deposits, ${data.stats.failed.withdrawals} withdrawals`;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        async function loadTransactions() {
            const network = document.getElementById('filter-network').value;
            const type = document.getElementById('filter-type').value;
            const status = document.getElementById('filter-status').value;
            const search = document.getElementById('filter-search').value;
            const list = document.getElementById('transactions-list');
            
            list.innerHTML = '<div class="text-center text-gray-400 py-8">Loading...</div>';
            
            try {
                const res = await fetch(adminUrl(`action=transactions&network=${network}&type=${type}&status=${status}&search=${encodeURIComponent(search)}&limit=200`));
                const data = await res.json();
                
                if (data.success && data.transactions.length > 0) {
                    list.innerHTML = data.transactions.map(tx => {
                        // Status mapping and colors
                        const statusMap = {
                            0: { text: 'Pending', color: 'bg-yellow-900/50 text-yellow-300 border-yellow-700/50' },
                            1: { text: 'Processing', color: 'bg-blue-900/50 text-blue-300 border-blue-700/50' },
                            2: { text: 'Failed', color: 'bg-red-900/50 text-red-300 border-red-700/50' },
                            3: { text: 'Completed', color: 'bg-green-900/50 text-green-300 border-green-700/50' },
                            8: { text: 'Pre-Complete', color: 'bg-blue-900/50 text-blue-300 border-blue-700/50' },
                            9: { text: 'Cancelled', color: 'bg-gray-700 text-gray-300 border-gray-600' }
                        };
                        const status = statusMap[tx.status] || { text: 'Unknown', color: 'bg-gray-700 text-gray-300 border-gray-600' };
                        
                        const isBlockedAddr = tx.type === 'withdrawal' && isBlockedWithdrawalAddress(tx.address, tx.network);
                        // Show reverse button for failed withdrawals only
                        const showReverseButton = tx.status === 2 && tx.type === 'withdrawal';
                        // Blocked destination but not failed/cancelled: reverse via blocked-address flow (e.g. completed to vault)
                        const showReverseBlockedButton = tx.type === 'withdrawal' && isBlockedAddr && tx.status !== 2 && tx.status !== 9;
                        // Show Mark Complete for pending withdrawals without network hash (manual mode)
                        const showMarkCompleteButton = tx.status === 0 && tx.type === 'withdrawal' && !(tx.txn_hash_stellar || tx.txn_hash_network);
                        
                        const networkHash = tx.txn_hash_stellar || tx.txn_hash_network || 'Pending';
                        const yemHash = tx.txn_hash_yemchain || 'Pending';
                        const truncate = (s, n) => (s && s.length > n) ? s.slice(0, n) + '…' : (s || '');
                        const feeUsdd = (tx.fee_usdd != null && parseFloat(tx.fee_usdd) > 0) ? parseFloat(tx.fee_usdd).toFixed(2) : null;
                        const txData = btoa(unescape(encodeURIComponent(JSON.stringify(tx))));
                        return `
                        <div class="bg-gray-800/50 border ${isBlockedAddr ? 'border-red-500/80' : 'border-gray-700'} rounded-lg p-4 hover:border-gray-600 transition-colors cursor-pointer"
                             data-tx="${txData}"
                             onclick="showTransactionDetails(JSON.parse(decodeURIComponent(escape(atob(this.dataset.tx)))))">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-1 rounded text-xs font-medium ${tx.type === 'deposit' ? 'bg-green-900/50 text-green-300' : 'bg-purple-900/50 text-purple-300'}">${tx.type.toUpperCase()}</span>
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-700 text-gray-300">${tx.network.toUpperCase()}</span>
                                    <span class="px-2 py-1 rounded text-xs font-medium border ${status.color}">${status.text}</span>
                                    ${isBlockedAddr ? '<span class="px-2 py-1 rounded text-xs font-medium bg-red-900/70 text-red-300 border border-red-600/50" title="Withdrawal to blocked address">Blocked address</span>' : ''}
                                    <span class="text-xs text-gray-400">UID: ${tx.uid}</span>
                                    ${showReverseButton ? `
                                        <button onclick="event.stopPropagation(); reverseTransaction('${tx.network}', ${tx.id}, this)" 
                                                class="px-3 py-1 rounded text-xs font-medium bg-orange-600 hover:bg-orange-700 text-white transition-colors"
                                                title="Reverse this failed transaction">
                                            🔄 Reverse
                                        </button>
                                    ` : ''}
                                    ${showReverseBlockedButton ? `
                                        <button onclick="event.stopPropagation(); reverseTransaction('${tx.network}', ${tx.id}, this, 'blocked')" 
                                                class="px-3 py-1 rounded text-xs font-medium bg-red-700 hover:bg-red-600 text-white transition-colors"
                                                title="Reverse: credit DBV only (USDD fee not refunded) — destination is blocked">
                                            🔄 Reverse blocked
                                        </button>
                                    ` : ''}
                                    ${showMarkCompleteButton ? `
                                        <button data-tx="${txData}" onclick="event.stopPropagation(); openMarkCompleteModal(this.getAttribute('data-tx'))" 
                                                class="px-3 py-1 rounded text-xs font-medium bg-green-600 hover:bg-green-700 text-white transition-colors"
                                                title="Mark withdrawal complete (paste tx hash)">
                                            ✓ Mark Complete
                                        </button>
                                    ` : ''}
                                </div>
                                <div class="flex flex-col items-end gap-0.5 flex-shrink-0">
                                    <span class="text-sm font-bold whitespace-nowrap ${tx.type === 'withdrawal' ? 'text-red-300' : 'text-green-300'}">
                                        ${tx.type === 'withdrawal' ? '−' : '+'}${parseFloat(tx.amount || 0).toFixed(2)} DBV 
                                        <span class="text-xs font-normal opacity-80">${tx.type === 'withdrawal' ? '(deducted)' : '(credited)'}</span>
                                    </span>
                                    ${feeUsdd ? `<span class="text-xs text-amber-400 whitespace-nowrap">−${feeUsdd} USDD (deducted)</span>` : ''}
                                </div>
                            </div>
                            <div class="text-xs text-gray-400 space-y-1 mt-2 pt-2 border-t border-gray-700/50">
                                <div>Network Hash: <span class="font-mono text-gray-300 truncate max-w-[280px] inline-block align-bottom" title="${networkHash}">${truncate(networkHash, 32)}</span></div>
                                <div>DigitalChain: <span class="font-mono text-gray-300 truncate max-w-[280px] inline-block align-bottom" title="${yemHash}">${truncate(yemHash, 32)}</span></div>
                                ${tx.address ? `<div class="truncate" title="${tx.address}">Address: <span class="font-mono text-gray-300">${truncate(tx.address, 36)}</span></div>` : ''}
                                ${tx.processed_by_admin_uid ? `<div class="text-amber-300/90">Processed by Admin UID: ${tx.processed_by_admin_uid}</div>` : ''}
                                <div>Time: ${tx.formatted_time || tx.created_at}</div>
                            </div>
                        </div>
                    `;
                    }).join('');
                } else {
                    list.innerHTML = '<div class="text-center text-gray-400 py-8">No transactions found</div>';
                }
            } catch (error) {
                list.innerHTML = '<div class="text-center text-red-400 py-8">Failed to load transactions</div>';
                console.error('Failed to load transactions:', error);
            }
        }

        function escHtml(s) {
            if (s == null || s === undefined) return '';
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }
        function escAttr(s) {
            if (s == null || s === undefined) return '';
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        async function loadManualWithdrawals() {
            const list = document.getElementById('manual-withdrawals-list');
            const badge = document.getElementById('manual-pending-badge');
            const network = document.getElementById('filter-manual-network')?.value || 'all';
            list.innerHTML = '<div class="text-center text-gray-400 py-8">Loading...</div>';
            try {
                const res = await fetch(adminUrl('action=manual_withdrawals&network=' + encodeURIComponent(network) + '&limit=500'));
                const data = await res.json();
                if (data.success && data.withdrawals && data.withdrawals.length > 0) {
                    const networkBadge = (net) => {
                        const c = { stellar: 'bg-yellow-900/50 text-yellow-300', binance: 'bg-orange-900/50 text-orange-300', ethereum: 'bg-blue-900/50 text-blue-300' }[net] || 'bg-gray-700 text-gray-300';
                        return `<span class="px-2 py-0.5 rounded text-xs font-medium ${c}">${escHtml((net || '').toUpperCase())}</span>`;
                    };
                    const truncate = (s, n) => (s && s.length > n) ? s.slice(0, n) + '…' : (s || '—');
                    const isEVM = (net) => net === 'binance' || net === 'ethereum';
                    list.innerHTML = data.withdrawals.map(w => {
                        const txData = btoa(unescape(encodeURIComponent(JSON.stringify({ id: w.id, network: w.network, amount: w.amount, address: w.address, uid: w.uid }))));
                        const addr = w.address || '';
                        const addrTrunc = truncate(addr, 42);
                        const created = w.formatted_time || w.created_at || '—';
                        const checkbox = `<input type="checkbox" class="manual-row-checkbox rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500" data-tx="${escAttr(txData)}" data-network="${escAttr(w.network)}" onchange="updateBulkCompleteButton()">`;
                        const isBlockedAddr = isBlockedWithdrawalAddress(addr, w.network);
                        return `
                        <div class="bg-gray-800/50 border ${isBlockedAddr ? 'border-red-500/80' : 'border-gray-700'} rounded-lg p-4 hover:border-gray-600 transition-colors flex gap-3">
                            <div class="flex-shrink-0 pt-0.5" onclick="event.stopPropagation()">${checkbox}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        ${networkBadge(w.network)}
                                        <span class="text-xs text-gray-400">#${escHtml(w.id)} • UID: ${escHtml(w.uid)}</span>
                                        <span class="text-sm font-bold text-red-300">−${escHtml(parseFloat(w.amount || 0).toFixed(2))} DBV</span>
                                        ${isBlockedAddr ? '<span class="px-2 py-0.5 rounded text-xs font-medium bg-red-900/70 text-red-300 border border-red-600/50" title="Withdrawal to blocked address">Blocked address</span>' : ''}
                                    </div>
                                    <div class="flex gap-2 flex-wrap">
                                        ${isBlockedAddr ? `<button onclick="reverseTransaction('${escAttr(w.network)}', ${w.id}, this, 'blocked')" class="px-3 py-1.5 rounded text-xs font-medium bg-red-700 hover:bg-red-600 text-white transition-colors" title="Reverse blocked: DBV only, no USDD fee refund">🔄 Reverse blocked</button>` : ''}
                                        ${isEVM(w.network) ? `<button onclick="completeWithWallet('${txData}')" class="px-3 py-1.5 rounded text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">Complete with Wallet</button>` : ''}
                                        <button onclick="openMarkCompleteModal('${txData}')" class="px-3 py-1.5 rounded text-xs font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">Paste Hash</button>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400 mt-2 pt-2 border-t border-gray-700/50">
                                    <div>To: <span class="font-mono text-gray-300" title="${escAttr(addr)}">${escHtml(addrTrunc)}</span></div>
                                    <div>Created: ${escHtml(created)}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    }).join('');
                    updateBulkCompleteButton();
                    if (badge) { badge.textContent = data.count; badge.classList.remove('hidden'); }
                } else {
                    list.innerHTML = '<div class="text-center text-gray-400 py-8">No pending manual withdrawals</div>';
                    if (badge) badge.classList.add('hidden');
                }
            } catch (error) {
                list.innerHTML = '<div class="text-center text-red-400 py-8">Failed to load manual withdrawals</div>';
                console.error('Failed to load manual withdrawals:', error);
                if (badge) badge.classList.add('hidden');
            }
        }

        let failedTransactionsCache = null;

        async function loadFailedTransactions(useCache = false) {
            const tbody = document.getElementById('failed-list-body');
            const footer = document.getElementById('failed-list-footer');
            const countEl = document.getElementById('failed-list-count');
            const networkFilter = document.getElementById('filter-failed-network')?.value || 'all';
            const search = (document.getElementById('filter-failed-search')?.value || '').trim().toLowerCase();

            const renderList = (list) => {
                const count = list.length;
                if (list.length > 0) {
                    const networkBadge = (net) => {
                        const c = { stellar: 'bg-yellow-900/50 text-yellow-300', binance: 'bg-orange-900/50 text-orange-300', ethereum: 'bg-blue-900/50 text-blue-300' }[net] || 'bg-gray-700 text-gray-300';
                        return `<span class="px-2 py-0.5 rounded text-xs font-medium ${c}">${(net || '').toUpperCase()}</span>`;
                    };
                    const truncate = (s, n) => (s && s.length > n) ? s.slice(0, n) + '…' : (s || '—');
                    tbody.innerHTML = list.map(tx => {
                        const txData = btoa(unescape(encodeURIComponent(JSON.stringify(tx))));
                        const isBlockedAddr = isBlockedWithdrawalAddress(tx.address, tx.network);
                        return `
                        <tr class="hover:bg-gray-700/30 transition-colors cursor-pointer ${isBlockedAddr ? 'bg-red-900/20' : ''}"
                            data-tx="${txData}"
                            data-network="${tx.network}" data-id="${tx.id}"
                            onclick="showTransactionDetails(JSON.parse(decodeURIComponent(escape(atob(this.dataset.tx)))))">
                            <td class="px-3 py-3" onclick="event.stopPropagation()">
                                <input type="checkbox" class="failed-row-checkbox rounded border-gray-600 bg-gray-800 text-orange-500 focus:ring-orange-500" 
                                       data-network="${tx.network}" data-id="${tx.id}" onchange="updateBulkReverseButton()">
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-300">#${tx.id}</td>
                            <td class="px-4 py-3">${networkBadge(tx.network)}${isBlockedAddr ? ' <span class="px-1.5 py-0.5 rounded text-xs bg-red-900/70 text-red-300">Blocked</span>' : ''}</td>
                            <td class="px-4 py-3 text-gray-300">${tx.uid}</td>
                            <td class="px-4 py-3 text-right font-medium text-red-300">−${parseFloat(tx.amount || 0).toFixed(2)}</td>
                            <td class="px-4 py-3 text-right text-amber-400">${(tx.fee_usdd && parseFloat(tx.fee_usdd) > 0) ? '−' + parseFloat(tx.fee_usdd).toFixed(2) : '—'}</td>
                            <td class="px-4 py-3 font-mono text-gray-400 max-w-[200px] truncate" title="${tx.address || ''}">${truncate(tx.address, 24)}</td>
                            <td class="px-4 py-3 text-red-300/90 max-w-[220px] truncate" title="${(tx.error_message || '')}">${tx.error_message ? truncate(tx.error_message, 40) : '—'}</td>
                            <td class="px-4 py-3 text-gray-400">${tx.formatted_time || tx.created_at}</td>
                            <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                <button onclick="reverseTransaction('${tx.network}', ${tx.id}, this)" 
                                        class="px-3 py-1.5 rounded text-xs font-medium bg-orange-600 hover:bg-orange-700 text-white transition-colors"
                                        title="Reverse this failed transaction">
                                    Reverse
                                </button>
                            </td>
                        </tr>
                    `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-gray-400 py-8">No failed transactions found</td></tr>';
                }
                countEl.textContent = count;
                footer.classList.remove('hidden');
                const selectAll = document.getElementById('failed-select-all');
                if (selectAll) selectAll.checked = false;
                updateBulkReverseButton();
            };

            function buildFailedReasonsSummary(fullList) {
                const summaryDiv = document.getElementById('failed-reasons-summary');
                const reasonsList = document.getElementById('failed-reasons-list');
                if (!summaryDiv || !reasonsList) return;
                const trunc = (s, n) => (s && s.length > n) ? s.slice(0, n) + '…' : (s || '—');
                if (fullList && fullList.length > 0) {
                    const byReason = {};
                    fullList.forEach(tx => {
                        const r = (tx.error_message || 'Unknown').trim() || 'Unknown';
                        byReason[r] = (byReason[r] || 0) + 1;
                    });
                    const sorted = Object.entries(byReason).sort((a, b) => b[1] - a[1]).slice(0, 8);
                    reasonsList.innerHTML = sorted.map(([msg, n]) => `<li title="${msg.replace(/"/g, '&quot;')}"><span class="text-amber-400">${n}×</span> ${trunc(msg, 60)}</li>`).join('');
                    summaryDiv.classList.remove('hidden');
                } else {
                    summaryDiv.classList.add('hidden');
                }
            }

            const applyFilters = (list) => {
                let filtered = list;
                if (networkFilter !== 'all') filtered = filtered.filter(tx => tx.network === networkFilter);
                if (search) {
                    filtered = filtered.filter(tx => {
                        const id = String(tx.id || '');
                        const uid = String(tx.uid || '');
                        const address = (tx.address || '').toLowerCase();
                        const err = (tx.error_message || '').toLowerCase();
                        const hashNet = (tx.txn_hash_network || tx.txn_hash_stellar || '').toLowerCase();
                        const hashYem = (tx.txn_hash_yemchain || '').toLowerCase();
                        const feeHash = (tx.fee_hash_yemchain || '').toLowerCase();
                        return id.includes(search) || uid.includes(search) || address.includes(search) || err.includes(search) || hashNet.includes(search) || hashYem.includes(search) || feeHash.includes(search);
                    });
                }
                return filtered;
            };

            if (useCache && failedTransactionsCache) {
                let list = applyFilters([...failedTransactionsCache]);
                renderList(list);
                buildFailedReasonsSummary(failedTransactionsCache);
                return;
            }

            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-400 py-8">Loading...</td></tr>';
            footer.classList.add('hidden');

            try {
                const res = await fetch(adminUrl('action=failed_by_network&limit=500'));
                const data = await res.json();

                if (data.success && Array.isArray(data.list)) {
                    failedTransactionsCache = data.list;
                    let list = applyFilters(data.list);
                    renderList(list);
                    buildFailedReasonsSummary(data.list);
                } else {
                    const errMsg = (data && data.error) ? data.error : 'Failed to load';
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-red-400 py-8">${errMsg}</td></tr>`;
                    buildFailedReasonsSummary([]);
                }
            } catch (error) {
                console.error('Failed to load failed transactions:', error);
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-red-400 py-8">Network error. Check console.</td></tr>';
            }
        }

        document.getElementById('filter-failed-network')?.addEventListener('change', () => loadFailedTransactions(true));
        document.getElementById('filter-failed-search')?.addEventListener('input', () => loadFailedTransactions(true));

        async function loadCommissions() {
            const tbody = document.getElementById('commissions-list-body');
            const summaryDiv = document.getElementById('commissions-summary');
            const network = document.getElementById('filter-comm-network')?.value || 'all';
            const referrerUid = document.getElementById('filter-comm-referrer')?.value?.trim() || '';
            const referredUid = document.getElementById('filter-comm-referred')?.value?.trim() || '';

            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-400 py-8">Loading...</td></tr>';
            summaryDiv.classList.add('hidden');

            try {
                let url = adminUrl('action=commissions&limit=200');
                if (network !== 'all') url += `&network=${encodeURIComponent(network)}`;
                if (referrerUid) url += `&referrer_uid=${encodeURIComponent(referrerUid)}`;
                if (referredUid) url += `&referred_uid=${encodeURIComponent(referredUid)}`;

                const res = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    const list = data.commissions || [];
                    const summary = data.summary || { total_usdd: 0, total_count: 0 };

                    if (data.message) {
                        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-amber-400 py-8">${data.message}</td></tr>`;
                    } else if (list.length > 0) {
                        const networkBadge = (net) => {
                            const c = { stellar: 'bg-yellow-900/50 text-yellow-300', binance: 'bg-orange-900/50 text-orange-300', ethereum: 'bg-blue-900/50 text-blue-300' }[net] || 'bg-gray-700 text-gray-300';
                            return `<span class="px-2 py-0.5 rounded text-xs font-medium ${c}">${(net || '').toUpperCase()}</span>`;
                        };
                        const truncate = (s, n) => (s && s.length > n) ? s.slice(0, n) + '…' : (s || '—');
                        tbody.innerHTML = list.map(c => `
                            <tr class="hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-mono text-teal-300">${c.referrer_uid}</td>
                                <td class="px-4 py-3 font-mono text-gray-300">${c.referred_uid}</td>
                                <td class="px-4 py-3">${networkBadge(c.network)}</td>
                                <td class="px-4 py-3 text-right font-medium text-teal-400">${parseFloat(c.amount_usdd || 0).toFixed(2)}</td>
                                <td class="px-4 py-3 font-mono text-gray-400 max-w-[200px] truncate" title="${c.hash || ''}">${truncate(c.hash, 24)}</td>
                                <td class="px-4 py-3 text-gray-400">#${c.withdrawal_id}</td>
                                <td class="px-4 py-3 text-gray-400">${c.formatted_time || c.created_at}</td>
                            </tr>
                        `).join('');
                        document.getElementById('comm-summary-usdd').textContent = parseFloat(summary.total_usdd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                        document.getElementById('comm-summary-count').textContent = (summary.total_count || list.length).toLocaleString();
                        summaryDiv.classList.remove('hidden');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-400 py-8">No commissions found</td></tr>';
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-red-400 py-8">${data.error || 'Failed to load'}</td></tr>`;
                }
            } catch (error) {
                console.error('Failed to load commissions:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-red-400 py-8">Network error</td></tr>';
            }
        }

        document.getElementById('filter-comm-network')?.addEventListener('change', () => loadCommissions());

        async function loadAuditLog() {
            const tbody = document.getElementById('audit-list-body');
            const action = document.getElementById('filter-audit-action')?.value || '';
            const adminUid = document.getElementById('filter-audit-admin-uid')?.value?.trim() || '';
            const dateFrom = document.getElementById('filter-audit-date-from')?.value || '';
            const dateTo = document.getElementById('filter-audit-date-to')?.value || '';

            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-400 py-8">Loading...</td></tr>';

            try {
                let url = adminUrl(`action=audit&limit=200`);
                if (action) url += `&filter_action=${encodeURIComponent(action)}`;
                if (adminUid) url += `&admin_uid=${encodeURIComponent(adminUid)}`;
                if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
                if (dateTo) url += `&date_to=${encodeURIComponent(dateTo)}`;

                const res = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    if (data.entries && data.entries.length > 0) {
                        const actionColors = { reversal: 'bg-green-900/50 text-green-300', reversal_failed: 'bg-red-900/50 text-red-300', manual_complete: 'bg-teal-900/50 text-teal-300', backup: 'bg-blue-900/50 text-blue-300', clear_sessions: 'bg-yellow-900/50 text-yellow-300', admin_added: 'bg-indigo-900/50 text-indigo-300', admin_removed: 'bg-orange-900/50 text-orange-300', setting_updated: 'bg-gray-600 text-gray-200' };
                        tbody.innerHTML = data.entries.map(e => {
                            const entity = e.entity_type && e.entity_id ? `${e.entity_type}#${e.entity_id}` : (e.entity_type || '—');
                            const details = e.details && Object.keys(e.details).length > 0 
                                ? `<pre class="text-xs text-gray-400 max-w-xs overflow-x-auto">${JSON.stringify(e.details)}</pre>` 
                                : '—';
                            const actionClass = actionColors[e.action] || 'bg-gray-700 text-gray-300';
                            const adminDisplay = e.admin_uid ? `<span class="px-2 py-0.5 rounded text-xs bg-amber-900/50 text-amber-300 font-mono">UID ${e.admin_uid}</span>` : '—';
                            return `
                                <tr class="hover:bg-gray-700/30">
                                    <td class="px-4 py-3 text-gray-400">${e.created_at || '—'}</td>
                                    <td class="px-4 py-3">${adminDisplay}</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs ${actionClass}">${e.action}</span></td>
                                    <td class="px-4 py-3 text-gray-300">${entity}</td>
                                    <td class="px-4 py-3">${details}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">${e.ip || '—'}</td>
                                </tr>
                            `;
                        }).join('');
                    } else {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-gray-400 py-8">${data.message || 'No audit entries found'}</td></tr>`;
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-red-400 py-8">Failed to load</td></tr>';
                }
            } catch (error) {
                console.error('Failed to load audit:', error);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-red-400 py-8">Error loading</td></tr>';
            }
        }

        async function loadLogs() {
            const date = (document.getElementById('filter-date')?.value || '<?= date('Y-m-d') ?>');
            const level = (document.getElementById('filter-level')?.value || 'all');
            const list = document.getElementById('logs-list');
            
            list.innerHTML = '<div class="text-center text-gray-400 py-8">Loading...</div>';
            
            try {
                const res = await fetch(adminUrl(`action=logs&date=${date}&level=${level}&limit=100`));
                const data = await res.json();
                
                if (data.success && data.logs && data.logs.length > 0) {
                    list.innerHTML = data.logs.map(log => {
                        const levelColors = {
                            'INFO': 'bg-blue-900/50 text-blue-300',
                            'WARNING': 'bg-yellow-900/50 text-yellow-300',
                            'ERROR': 'bg-red-900/50 text-red-300',
                            'DEBUG': 'bg-gray-700 text-gray-300'
                        };
                        return `
                            <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 rounded text-xs font-medium ${levelColors[log.level] || 'bg-gray-700 text-gray-300'}">${log.level}</span>
                                        <span class="text-xs text-gray-400">${log.timestamp}</span>
                                    </div>
                                    <span class="text-xs text-gray-400">${log.ip}</span>
                                </div>
                                <div class="text-sm text-white mb-2">${log.message}</div>
                                ${log.context && Object.keys(log.context).length > 0 ? `
                                    <details class="text-xs">
                                        <summary class="text-gray-400 cursor-pointer hover:text-white">Context</summary>
                                        <pre class="mt-2 bg-black/50 p-2 rounded overflow-x-auto text-gray-300">${JSON.stringify(log.context, null, 2)}</pre>
                                    </details>
                                ` : ''}
                            </div>
                        `;
                    }).join('');
                } else {
                    list.innerHTML = `<div class="text-center text-gray-400 py-8">${(data && data.message) ? data.message : (data && data.error) ? data.error : 'No logs found'}</div>`;
                }
            } catch (error) {
                list.innerHTML = '<div class="text-center text-red-400 py-8">Failed to load logs. Check console.</div>';
                console.error('Failed to load logs:', error);
            }
        }

        async function loadNetworkLogs() {
            const worker = document.getElementById('filter-worker-logs-worker')?.value || 'all';
            const filter = document.getElementById('filter-worker-logs-filter')?.value || 'all';
            const source = document.getElementById('filter-worker-logs-source')?.value || 'both';
            const list = document.getElementById('network-logs-list');
            list.innerHTML = '<div class="text-center text-gray-400 py-8">Loading...</div>';
            try {
                const qs = new URLSearchParams({ worker, filter, source, limit: 200 });
                const res = await fetch(adminUrl('action=worker_logs&' + qs.toString()));
                const data = await res.json();
                if (data.success && data.logs && data.logs.length > 0) {
                    const workerColors = { stellar: 'text-blue-400', binance: 'text-amber-400', ethereum: 'text-purple-400' };
                    list.innerHTML = data.logs.map(log => {
                        const wc = workerColors[log.worker] || 'text-gray-400';
                        const isError = log.source === 'error' || /error|failed|❌/i.test(log.line);
                        const bg = isError ? 'bg-red-900/20 border-red-800' : 'bg-gray-800/50 border-gray-700';
                        return `<div class="border rounded-lg p-2 ${bg}">
                            <span class="${wc} text-xs mr-2">[${log.worker}]</span>
                            <span class="text-gray-300 break-all">${escapeHtml(log.line)}</span>
                        </div>`;
                    }).join('');
                } else {
                    const msg = (data && data.message) ? data.message : (data && data.error) ? data.error : 'No worker logs found. Ensure PM2 is running and logs exist in ./logs/.';
                    list.innerHTML = `<div class="text-center text-gray-400 py-8">${msg}</div>`;
                }
            } catch (error) {
                list.innerHTML = '<div class="text-center text-red-400 py-8">Failed to load network logs. Check console.</div>';
                console.error('Failed to load network logs:', error);
            }
        }
        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        async function loadErrors() {
            const date = document.getElementById('filter-date')?.value || '<?= date('Y-m-d') ?>';
            const list = document.getElementById('errors-list');
            
            list.innerHTML = '<div class="text-center text-gray-400 py-8">Loading...</div>';
            
            try {
                const res = await fetch(adminUrl(`action=logs&date=${date}&level=ERROR&limit=100`));
                const data = await res.json();
                
                if (data.success && data.logs && data.logs.length > 0) {
                    list.innerHTML = data.logs.map(log => `
                        <div class="bg-red-900/20 border border-red-800 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-gray-400">${log.timestamp}</span>
                                <span class="text-xs text-gray-400">${log.ip}</span>
                            </div>
                            <div class="text-sm text-red-300 font-medium mb-2">${log.message}</div>
                            ${log.context && Object.keys(log.context).length > 0 ? `
                                <details class="text-xs">
                                    <summary class="text-gray-400 cursor-pointer hover:text-white">Details</summary>
                                    <pre class="mt-2 bg-black/50 p-2 rounded overflow-x-auto text-gray-300">${JSON.stringify(log.context, null, 2)}</pre>
                                </details>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div class="text-center text-gray-400 py-8">No errors found</div>';
                }
            } catch (error) {
                list.innerHTML = '<div class="text-center text-red-400 py-8">Failed to load errors</div>';
                console.error('Failed to load errors:', error);
            }
        }
        
        function toggleFailedSelectAll(checkbox) {
            document.querySelectorAll('.failed-row-checkbox').forEach(cb => cb.checked = checkbox.checked);
            updateBulkReverseButton();
        }

        function updateBulkReverseButton() {
            const checked = document.querySelectorAll('.failed-row-checkbox:checked');
            const btn = document.getElementById('bulk-reverse-btn');
            const countEl = document.getElementById('bulk-reverse-count');
            if (countEl) countEl.textContent = checked.length;
            if (btn) btn.classList.toggle('hidden', checked.length === 0);
            const selectAll = document.getElementById('failed-select-all');
            if (selectAll) selectAll.checked = checked.length > 0 && checked.length === document.querySelectorAll('.failed-row-checkbox').length;
        }

        function getReverseRefundUsddFees() {
            const cb = document.getElementById('reverse-refund-usdd-fees');
            return !cb || cb.checked;
        }

        async function bulkReverseSelected() {
            const checked = document.querySelectorAll('.failed-row-checkbox:checked');
            const seen = new Set();
            const items = Array.from(checked)
                .map(cb => ({ network: cb.dataset.network, id: parseInt(cb.dataset.id, 10) }))
                .filter(x => {
                    const key = x.network + ':' + x.id;
                    if (seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });
            if (items.length === 0) {
                alert('No transactions selected. Select at least one failed transaction to reverse.');
                return;
            }
            const refundFees = getReverseRefundUsddFees();
            const feeLine = refundFees
                ? 'This will credit DBV and refund USDD fees for each selected withdrawal.'
                : 'This will credit DBV only. USDD withdrawal fees will NOT be refunded.';
            if (!confirm(`Reverse ${items.length} selected failed withdrawal(s)?\n\n${feeLine}\n\nProcessing runs one at a time (~2s delay between each). This may take a few minutes.`)) {
                return;
            }

            const btn = document.getElementById('bulk-reverse-btn');
            const originalHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }

            let ok = 0, fail = 0;
            for (let i = 0; i < items.length; i++) {
                const { network, id } = items[i];
                if (btn) btn.innerHTML = `🔄 Reversing ${i + 1}/${items.length}...`;
                try {
                    const formData = new FormData();
                    formData.append('network', network);
                    formData.append('withdrawal_id', id);
                    formData.append('refund_usdd_fee', refundFees ? '1' : '0');
                    formData.append('reversal_kind', 'failed');
                    const res = await fetch((ADMIN_BASE || '') + '/api/admin/reverse-transaction.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) ok++; else fail++;
                } catch (e) { fail++; }
                if (i < items.length - 1) await new Promise(r => setTimeout(r, 2000));
            }

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            updateBulkReverseButton();
            loadTransactions();
            loadFailedTransactions();
            loadManualWithdrawals();
            loadStats();
            alert(`Bulk reversal complete.\n✅ Succeeded: ${ok}\n❌ Failed: ${fail}`);
        }

        async function reverseTransaction(network, withdrawalId, button, reversalKind = 'failed') {
            const refundFees = getReverseRefundUsddFees();
            const isBlockedFlow = reversalKind === 'blocked' || reversalKind === 'blocked_address';
            const feeLines = isBlockedFlow
                ? '✅ Credit DBV back to user\n⏭️ USDD withdrawal fee is not refunded (blocked-address reversal)'
                : (refundFees
                    ? '✅ Credit DBV back to user\n✅ Refund USDD fee (if any)'
                    : '✅ Credit DBV back to user\n⏭️ Do NOT refund USDD fee (fee stays with vault)');
            const blockedNote = isBlockedFlow
                ? '\n\n(Blocked-address reversal: DBV only; on-chain funds may already be at this address — reconcile operationally if needed.)'
                : '';
            if (!confirm(`Are you sure you want to reverse this ${network} withdrawal (ID: ${withdrawalId})?\n\n${feeLines}\n✅ Mark transaction as reversed\n\nThis action cannot be undone.${blockedNote}`)) {
                return;
            }
            
            // Disable button and show loading
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '⏳ Reversing...';
            button.classList.add('opacity-50', 'cursor-not-allowed');
            
            try {
                const formData = new FormData();
                formData.append('network', network);
                formData.append('withdrawal_id', withdrawalId);
                formData.append('refund_usdd_fee', isBlockedFlow ? '0' : (refundFees ? '1' : '0'));
                formData.append('reversal_kind', isBlockedFlow ? 'blocked' : 'failed');
                
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/reverse-transaction.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    const usddNote = data.refund_usdd_fee === false
                        ? 'USDD fee: not refunded (DBV only)'
                        : `USDD Reversed: ${data.usdd_amount}`;
                    const kindNote = (data.reversal_kind === 'blocked_address') ? '\nType: blocked-address reversal' : '';
                    alert(`✅ Reversal Successful!${kindNote}\n\nDBV Reversed: ${data.dbv_amount}\n${usddNote}\n\nDBV Hash: ${data.dbv_txn_hash || 'N/A'}\nUSDD Hash: ${data.usdd_txn_hash || 'N/A'}`);
                    
                    // Reload transactions and failed tab to show updated status
                    loadTransactions();
                    loadFailedTransactions(); // Clear from Failed tab
                    loadManualWithdrawals();
                    loadStats(); // Update stats too
                } else {
                    alert(`❌ Reversal Failed\n\n${data.message}`);
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            } catch (error) {
                alert(`❌ Error: ${error.message}`);
                button.disabled = false;
                button.innerHTML = originalText;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        async function backupDatabase() {
            const button = document.getElementById('backup-btn');
            const status = document.getElementById('backup-status');
            
            button.disabled = true;
            button.innerHTML = '⏳ Creating backup...';
            button.classList.add('opacity-50');
            status.innerHTML = '<div class="text-blue-400">📦 Backup in progress...</div>';
            
            try {
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/backup-database.php', { method: 'POST' });
                const data = await res.json();
                
                if (data.success) {
                    status.innerHTML = `<div class="bg-green-900/30 border border-green-700 rounded-lg p-4">
                        <div class="text-green-300 font-semibold mb-2">✅ Backup Created!</div>
                        <div class="text-xs text-gray-300">📁 ${data.filename} (${data.size})</div>
                        ${data.download_url ? `<a href="${data.download_url}" class="text-blue-400 text-xs">⬇️ Download</a>` : ''}
                    </div>`;
                } else {
                    status.innerHTML = `<div class="bg-red-900/30 border border-red-700 rounded-lg p-4 text-red-300">${data.message}</div>`;
                }
            } catch (error) {
                status.innerHTML = `<div class="bg-red-900/30 border border-red-700 rounded-lg p-4 text-red-300">${error.message}</div>`;
            }
            
            button.disabled = false;
            button.innerHTML = '📦 Create Backup';
            button.classList.remove('opacity-50');
        }

        async function loadManualWithdrawSettings() {
            const status = document.getElementById('manual-withdraw-status');
            const toggle = document.getElementById('manual-withdraw-toggle');
            const thumb = document.getElementById('manual-withdraw-toggle-thumb');
            const msg = document.getElementById('manual-withdraw-message');
            const adminList = document.getElementById('admin-uids-list');
            const adminMsg = document.getElementById('admin-roles-message');
            try {
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/settings.php');
                const data = await res.json();
                if (data.success) {
                    const enabled = data.manual_withdraw_enabled === true;
                    status.textContent = enabled ? 'ON (Manual)' : 'OFF (Auto)';
                    toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
                    toggle.classList.toggle('bg-red-600', enabled);
                    toggle.classList.toggle('bg-gray-700', !enabled);
                    thumb.classList.toggle('translate-x-6', enabled);
                    thumb.classList.toggle('translate-x-0', !enabled);
                    msg.textContent = '';
                    if (adminList && Array.isArray(data.admin_uids)) {
                        const bootstrap = 1290033;
                        adminList.innerHTML = data.admin_uids.map(uid => {
                            const isBootstrap = uid === bootstrap;
                            return `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-lg ${isBootstrap ? 'bg-amber-900/50 text-amber-300 border border-amber-700/50' : 'bg-gray-700 text-gray-300'}">
                                UID ${uid}${isBootstrap ? ' (bootstrap)' : ''}
                                ${!isBootstrap ? `<button type="button" onclick="removeAdminUid(${uid})" class="ml-1 text-red-400 hover:text-red-300 text-xs">&times;</button>` : ''}
                            </span>`;
                        }).join('');
                    }
                    if (adminMsg) adminMsg.textContent = '';
                } else {
                    status.textContent = 'Error';
                    msg.innerHTML = '<span class="text-red-400">' + (data.message || 'Failed to load') + '</span>';
                }
            } catch (e) {
                status.textContent = 'Error';
                msg.innerHTML = '<span class="text-red-400">' + (e.message || 'Network error') + '</span>';
            }
        }

        async function addAdminUid() {
            const input = document.getElementById('admin-uid-input');
            const msg = document.getElementById('admin-roles-message');
            const uid = parseInt(input?.value || '0', 10);
            if (!uid || uid < 1) {
                if (msg) { msg.textContent = 'Enter a valid UID'; msg.className = 'text-sm text-red-400 mt-2'; }
                return;
            }
            try {
                const formData = new FormData();
                formData.append('key', 'admin_uids_add');
                formData.append('value', String(uid));
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/settings.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (msg) { msg.textContent = 'Admin UID ' + uid + ' added.'; msg.className = 'text-sm text-green-400 mt-2'; }
                    input.value = '';
                    loadManualWithdrawSettings();
                } else {
                    if (msg) { msg.textContent = data.message || 'Failed'; msg.className = 'text-sm text-red-400 mt-2'; }
                }
            } catch (e) {
                if (msg) { msg.textContent = 'Network error: ' + (e.message || 'Unknown'); msg.className = 'text-sm text-red-400 mt-2'; }
            }
        }

        async function removeAdminUid(uid) {
            if (!confirm('Remove UID ' + uid + ' from admin?')) return;
            const msg = document.getElementById('admin-roles-message');
            try {
                const formData = new FormData();
                formData.append('key', 'admin_uids_remove');
                formData.append('value', String(uid));
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/settings.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (msg) { msg.textContent = 'Admin UID ' + uid + ' removed.'; msg.className = 'text-sm text-green-400 mt-2'; }
                    loadManualWithdrawSettings();
                } else {
                    if (msg) { msg.textContent = data.message || 'Failed'; msg.className = 'text-sm text-red-400 mt-2'; }
                }
            } catch (e) {
                if (msg) { msg.textContent = 'Network error: ' + (e.message || 'Unknown'); msg.className = 'text-sm text-red-400 mt-2'; }
            }
        }

        async function saveManualWithdrawSetting(enabled) {
            const msg = document.getElementById('manual-withdraw-message');
            try {
                const formData = new FormData();
                formData.append('key', 'manual_withdraw_enabled');
                formData.append('value', enabled ? '1' : '0');
                const res = await fetch((ADMIN_BASE || '') + '/api/admin/settings.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    msg.innerHTML = '<span class="text-green-400">✅ Saved. New withdrawals will ' + (enabled ? 'stay pending until you process them manually.' : 'be auto-processed by the worker.') + '</span>';
                } else {
                    msg.innerHTML = '<span class="text-red-400">' + (data.message || 'Failed to save') + '</span>';
                }
            } catch (e) {
                msg.innerHTML = '<span class="text-red-400">' + (e.message || 'Network error') + '</span>';
            }
        }

        document.getElementById('manual-withdraw-toggle')?.addEventListener('click', async function() {
            const current = this.getAttribute('aria-checked') === 'true';
            const next = !current;
            await saveManualWithdrawSetting(next);
            loadManualWithdrawSettings();
        });

        async function clearAllSessions() {
            if (!confirm('Are you sure you want to clear ALL sessions?\n\nThis will log out ALL users (including you) and force them to log in again.')) {
                return;
            }

            const button = document.getElementById('clear-sessions-btn');
            const status = document.getElementById('session-status');
            
            button.disabled = true;
            button.innerHTML = '⏳ Clearing...';
            button.classList.add('opacity-50');
            status.innerHTML = '<div class="text-blue-400">Processing...</div>';
            
            try {
                const res = await fetch(adminUrl('action=clear_sessions'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ csrf_token: ADMIN_CSRF_TOKEN })
                });
                const data = await res.json();
                
                if (data.success) {
                    status.innerHTML = `<div class="bg-green-900/30 border border-green-700 rounded-lg p-4">
                        <div class="text-green-300 font-semibold mb-2">✅ Success!</div>
                        <div class="text-xs text-gray-300">${data.message}</div>
                    </div>`;
                    
                    // Optional: Redirect to login if current user is logged out
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    status.innerHTML = `<div class="bg-red-900/30 border border-red-700 rounded-lg p-4 text-red-300">${data.error || 'Unknown error'}</div>`;
                }
            } catch (error) {
                status.innerHTML = `<div class="bg-red-900/30 border border-red-700 rounded-lg p-4 text-red-300">${error.message}</div>`;
            }
            
            button.disabled = false;
            button.innerHTML = '🗑️ Clear All Sessions';
            button.classList.remove('opacity-50');
        }

        // Initialize
        loadStats();
        loadTransactions();
        
        // Auto-refresh stats every 30 seconds
        statsInterval = setInterval(loadStats, 30000);
        
        // No auto-refresh of tab content - manual Refresh button only
    </script>
</body>
</html>
