/**
 * Dashboard JavaScript Functionality
 * Handles all dashboard interactions including transactions, deposits, and withdrawals
 */

/** Escape for safe HTML text content (prevents XSS) */
function esc(s) {
    if (s == null || s === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}
/** Escape for HTML attribute (e.g. in onclick) */
function escAttr(s) {
    if (s == null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Transaction Management
function loadTransactions() {
    const timestamp = new Date().getTime();
    const apiBase = window.APP_API_BASE || '/public/api';
    fetch(`${apiBase}/get-transaction-history.php?limit=50&_=${timestamp}`, {
        method: 'GET',
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            // Check if response is actually JSON
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return res.text().then(text => {
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned non-JSON response');
                });
            }
            return res.json();
        })
        .then(data => {
            console.log('Transaction data received:', data);
            if (data && data.success && Array.isArray(data.transactions)) {
                updateTransactionDisplay(data.transactions);
            } else {
                console.error('Transaction API error:', data?.message || 'Unknown error', data);
                const transactionsList = document.getElementById('transactions-list');
                if (transactionsList) {
                    transactionsList.innerHTML = `<div class="text-gray-400 text-sm py-4 text-center">Error: ${esc(data?.message || 'Unknown error')}</div>`;
                }
            }
        })
        .catch(err => {
            console.error('Failed to load transactions:', err);
            const transactionsList = document.getElementById('transactions-list');
            if (transactionsList) {
                transactionsList.innerHTML = `<div class="text-gray-400 text-sm py-4 text-center">Failed to load transactions: ${esc(err.message)}. Check console for details.</div>`;
            }
        });
}

function updateTransactionDisplay(transactions) {
    console.log('Updating transactions:', transactions);
    console.log('Transaction count:', transactions ? transactions.length : 0);
    
    if (!transactions || !Array.isArray(transactions)) {
        console.error('Invalid transactions data:', transactions);
        const transactionsList = document.getElementById('transactions-list');
        if (transactionsList) {
            transactionsList.innerHTML = '<div class="text-gray-400 text-sm py-4 text-center">Error: Invalid transaction data received</div>';
        }
        return;
    }
    
    // Sort transactions by date (newest first)
    const sortedTransactions = [...transactions].sort((a, b) => {
        const dateA = a.created_date ? new Date(a.created_date) : (a.formatted_time ? new Date(a.formatted_time) : new Date(0));
        const dateB = b.created_date ? new Date(b.created_date) : (b.formatted_time ? new Date(b.formatted_time) : new Date(0));
        return dateB - dateA;
    });
    
    const transactionsList = document.getElementById('transactions-list');
    
    if (!transactionsList) {
        console.error('transactions-list element not found!');
        return;
    }
    
    if (sortedTransactions.length === 0) {
        transactionsList.innerHTML = '<div class="text-gray-300 text-sm py-4 text-center">No transactions found.</div>';
        return;
    }
    
    console.log('Rendering', sortedTransactions.length, 'transactions');
    
    try {
        transactionsList.innerHTML = sortedTransactions.map(t => {
                const isDeposit = t.type === 'deposit';
                const isRefund = t.type === 'refund';
                const isIncoming = isDeposit || isRefund;
                const statusBadge = getStatusBadge(t.status_text, t.status_color);
                const networkBadge = getNetworkBadge(t.network || 'Stellar');
                const typeBadge = isRefund 
                    ? '<span class="text-xs px-2 py-1 rounded bg-amber-900/50 text-amber-300 border border-amber-700/50 font-medium">Refund</span>'
                    : isDeposit 
                        ? '<span class="text-xs px-2 py-1 rounded bg-gray-900 text-white border border-gray-700 font-medium">Deposit</span>'
                        : '<span class="text-xs px-2 py-1 rounded bg-gray-900 text-gray-300 border border-gray-700 font-medium">Withdrawal</span>';
                
                return `
                    <div class="group p-4 rounded-lg bg-black border border-gray-800 hover:border-gray-700 transition-all cursor-pointer">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                ${isIncoming ? `
                                    <svg class="w-5 h-5 ${isRefund ? 'text-amber-400' : 'text-gray-400'} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                ` : `
                                    <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                `}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                        <span class="text-xs text-gray-300">${esc(t.formatted_time)}</span>
                                        ${networkBadge}
                                        ${typeBadge}
                                        ${statusBadge}
                                    </div>
                                    <div class="text-lg font-bold text-white mt-2">
                                        <span class="text-${isIncoming ? 'gray' : 'gray'}-300">${isIncoming ? '+' : '-'}${esc(t.formatted_amount)}</span> <span class="text-sm font-normal text-gray-400">DBV</span>
                                        ${isRefund && t.usdd_amount != null && t.usdd_amount > 0 ? `<div class="text-sm font-normal text-amber-400 mt-0.5">+${Number(t.usdd_amount).toFixed(2)} USDD (fee refund)</div>` : ''}
                                        ${!isIncoming && (t.fee_usdd != null && parseFloat(t.fee_usdd) > 0) ? `<div class="text-sm font-normal text-red-400/90 mt-0.5">-${parseFloat(t.fee_usdd).toFixed(2)} USDD (fee)</div>` : ''}
                                    </div>
                                    <div class="flex flex-col gap-2 mt-3 text-xs">
                                        ${getNetworkTransactionHash(t)}
                                        ${t.txn_hash_yemchain ? `
                                            <div class="flex items-center gap-2 group/hash bg-gray-900/30 p-2 rounded border border-gray-800">
                                                <span class="text-gray-400 w-20 flex-shrink-0 font-medium">DigitalChain:</span>
                                                <a href="https://digitalchain.center/transaction_details.php?hash=${encodeURIComponent(t.txn_hash_yemchain)}" target="_blank" 
                                                   class="text-gray-300 hover:text-white font-mono truncate flex-1">
                                                    ${esc(t.txn_hash_yemchain.length > 20 ? t.txn_hash_yemchain.substring(0, 12) + '...' + t.txn_hash_yemchain.substring(t.txn_hash_yemchain.length - 8) : t.txn_hash_yemchain)}
                                                </a>
                                                <button onclick="copyToClipboard('${escAttr(t.txn_hash_yemchain)}', this)" 
                                                        class="opacity-0 group-hover/hash:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-1 rounded hover:bg-gray-800">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        ` : '<div class="text-gray-300 text-xs bg-gray-900/30 p-2 rounded border border-gray-800">DigitalChain: <span class="text-gray-400">Pending</span></div>'}
                                        ${!isIncoming && t.address ? `
                                            <div class="flex items-center gap-2 group/hash bg-gray-900/30 p-2 rounded border border-gray-800">
                                                <span class="text-gray-400 w-20 flex-shrink-0 font-medium">Address:</span>
                                                <span class="text-gray-300 font-mono truncate flex-1">${esc(t.address && t.address.length > 20 ? t.address.substring(0, 10) + '...' + t.address.substring(t.address.length - 10) : t.address)}</span>
                                                <button onclick="copyToClipboard('${escAttr(t.address || '')}', this)" 
                                                        class="opacity-0 group-hover/hash:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-1 rounded hover:bg-gray-800">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } catch (error) {
            console.error('Error rendering transactions:', error);
            transactionsList.innerHTML = '<div class="text-gray-400 text-sm py-4 text-center">Error rendering transactions. Check console for details.</div>';
        }
}

function getStatusBadge(text, color) {
    const baseClasses = 'px-2 py-0.5 rounded text-xs font-medium';
    const safe = esc(text || '');
    switch(color) {
        case 'green': return `<span class="${baseClasses} bg-green-900/50 text-green-300 border border-green-700/50">${safe}</span>`;
        case 'yellow': return `<span class="${baseClasses} bg-yellow-900/50 text-yellow-300 border border-yellow-700/50">${safe}</span>`;
        case 'red': return `<span class="${baseClasses} bg-red-900/50 text-red-300 border border-red-700/50">${safe}</span>`;
        case 'blue': return `<span class="${baseClasses} bg-blue-900/50 text-blue-300 border border-blue-700/50">${safe}</span>`;
        default: return `<span class="${baseClasses} bg-gray-900 text-gray-300 border border-gray-700">${safe}</span>`;
    }
}

function getNetworkBadge(network) {
    const networkColors = {
        'Stellar': 'bg-purple-900/50 text-purple-300 border-purple-700/50',
        'Binance': 'bg-yellow-900/50 text-yellow-300 border-yellow-700/50',
        'Ethereum': 'bg-blue-900/50 text-blue-300 border-blue-700/50'
    };
    const color = networkColors[network] || 'bg-gray-900 text-gray-300 border-gray-700';
    return `<span class="px-2 py-0.5 rounded text-xs font-medium ${color} border">${esc(network || '')}</span>`;
}

function getNetworkTransactionHash(txn) {
    const network = txn.network || 'Stellar';
    const hash = txn.txn_hash_stellar || null;

    if (!hash) {
        return `<div class="text-gray-300 text-xs bg-gray-900/30 p-2 rounded border border-gray-800">${esc(network)}: <span class="text-gray-400">Pending</span></div>`;
    }

    // Get explorer URL based on network
    let explorerUrl = '';
    let displayHash = hash;

    if (network === 'Stellar') {
        explorerUrl = `https://lumenscan.io/txns/${encodeURIComponent(hash)}`;
        displayHash = hash.length > 60 ? hash.substring(0, 12) + '...' + hash.substring(48) : hash;
    } else if (network === 'Binance') {
        explorerUrl = `https://bscscan.com/tx/${encodeURIComponent(hash)}`;
        displayHash = hash.length > 20 ? hash.substring(0, 10) + '...' + hash.substring(hash.length - 10) : hash;
    } else if (network === 'Ethereum') {
        explorerUrl = `https://etherscan.io/tx/${encodeURIComponent(hash)}`;
        displayHash = hash.length > 20 ? hash.substring(0, 10) + '...' + hash.substring(hash.length - 10) : hash;
    } else {
        explorerUrl = '#';
    }

    return `
        <div class="flex items-center gap-2 group/hash bg-gray-900/30 p-2 rounded border border-gray-800">
            <span class="text-gray-400 w-20 flex-shrink-0 font-medium">${esc(network)}:</span>
            <a href="${explorerUrl}" target="_blank"
               class="text-gray-300 hover:text-white font-mono truncate flex-1">
                ${esc(displayHash)}
            </a>
            <button onclick="copyToClipboard('${escAttr(hash)}', this)"
                    class="opacity-0 group-hover/hash:opacity-100 transition-opacity text-gray-400 hover:text-white bg-gray-900 p-1 rounded hover:bg-gray-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            </button>
        </div>
    `;
}

function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = button.innerHTML;
        button.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        setTimeout(() => {
            button.innerHTML = originalHTML;
        }, 1000);
    });
}

function triggerWithdrawalProcessing(id) {
    const apiBase = window.APP_API_BASE || '/public/api';
    // NOTE: Worker secret should not be exposed to client-side JavaScript
    // This should be handled server-side or use a different authentication mechanism
    // For now, using a placeholder - this needs to be fixed properly
    const workerToken = window.WORKER_SECRET || '';
    fetch(`${apiBase}/trigger-withdrawal-worker.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: workerToken, id: id })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Processing triggered');
                loadTransactions();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
}

// Network Selection Handler
let currentActionType = null;
let selectedNetwork = null;

function initNetworkSelection() {
    const openDepositBtn = document.getElementById('open-deposit-network-modal');
    const openWithdrawBtn = document.getElementById('open-withdraw-network-modal');
    const closeNetworkModalBtn = document.getElementById('close-network-selection-modal');
    const networkModal = document.getElementById('network-selection-modal');
    const networkOptions = document.querySelectorAll('.network-option');
    
    if (openDepositBtn) {
        openDepositBtn.addEventListener('click', () => {
            currentActionType = 'deposit';
            const title = document.getElementById('network-selection-title');
            if (title) title.textContent = 'Select Network for Deposit';
            openModal('network-selection-modal');
        });
    }
    
    if (openWithdrawBtn) {
        openWithdrawBtn.addEventListener('click', () => {
            currentActionType = 'withdraw';
            const title = document.getElementById('network-selection-title');
            if (title) title.textContent = 'Select Network for Withdrawal';
            openModal('network-selection-modal');
        });
    }
    
    if (closeNetworkModalBtn) {
        closeNetworkModalBtn.addEventListener('click', () => closeModal('network-selection-modal'));
    }
    
    if (networkModal) {
        networkModal.addEventListener('click', (e) => {
            if (e.target === networkModal) closeModal('network-selection-modal');
        });
    }
    
    networkOptions.forEach(option => {
        option.addEventListener('click', () => {
            const network = option.dataset.network;
            if (network && currentActionType) {
                selectedNetwork = network;
                closeModal('network-selection-modal');
                
                if (currentActionType === 'deposit') {
                    openDepositModalForNetwork(network);
                } else if (currentActionType === 'withdraw') {
                    openWithdrawalModalForNetwork(network);
                }
            }
        });
    });
}

function openDepositModalForNetwork(network) {
    console.log('Opening deposit modal for network:', network);
    console.log('Available config:', window.networkConfig);
    
    if (!window.networkConfig || !window.networkConfig[network]) {
        console.error('Network config not found:', network, 'Available:', Object.keys(window.networkConfig || {}));
        return;
    }
    
    const config = window.networkConfig[network];
    const depositModal = document.getElementById('deposit-modal');
    if (!depositModal) return;
    
    const networkNames = { 
        stellar: 'Stellar', 
        binance: 'Binance Smart Chain', 
        ethereum: 'Ethereum' 
    };
    const networkName = networkNames[network] || network;
    
    // Update modal title
    const modalTitle = depositModal.querySelector('h2');
    if (modalTitle) {
        modalTitle.textContent = `Deposit DBV via ${networkName}`;
    }
    
    // Update vault address container
    const vaultInfo = document.getElementById('vault-address-container') || depositModal.querySelector('.vault-address-container');
    if (vaultInfo && config.vault_address) {
        // Update vault address text
        const addressCode = document.getElementById('vault-address-code') || vaultInfo.querySelector('code');
        if (addressCode) {
            addressCode.textContent = config.vault_address;
        }
        
        // Update the instruction text
        const addressText = vaultInfo.querySelector('.vault-address-text') || vaultInfo.querySelector('p');
        if (addressText) {
            if (network === 'stellar') {
                addressText.textContent = 'Step 1: Send your DBV tokens to this Stellar address:';
            } else if (network === 'binance') {
                addressText.textContent = 'Step 1: Send your DBV tokens (ERC-20) to this BSC address:';
            } else {
                addressText.textContent = 'Step 1: Send your DBV tokens (ERC-20) to this Ethereum address:';
            }
        }
        
        // Update copy button
        const copyButton = document.getElementById('vault-copy-button') || vaultInfo.querySelector('button');
        if (copyButton) {
            copyButton.onclick = function() {
                copyToClipboard(config.vault_address, this);
            };
        }
        
        // Update important notice
        const noticeText = vaultInfo.querySelector('.vault-notice-text');
        const noticeDiv = vaultInfo.querySelector('.vault-notice');
        if (noticeDiv) {
            if (network === 'stellar') {
                noticeDiv.innerHTML = `
                    <p class="text-xs text-gray-400"><strong class="text-white">⚠️ Important:</strong> You must send a <strong class="text-white">direct payment</strong>, NOT a claimable balance.</p>
                    <p class="text-xs text-gray-400">In your Stellar wallet, choose <strong class="text-white">"Send Payment"</strong> or <strong class="text-white">"Payment"</strong>. Do NOT use "Create Claimable Balance".</p>
                `;
            } else {
                noticeDiv.innerHTML = `
                    <p class="text-xs text-gray-400"><strong class="text-white">⚠️ Important:</strong> Send DBV tokens using your ${networkName} compatible wallet.</p>
                    <p class="text-xs text-gray-400">Make sure you're sending an ERC-20 token transfer, not a native currency transfer.</p>
                `;
            }
        }
        
        vaultInfo.style.display = 'block';
    } else if (vaultInfo) {
        vaultInfo.style.display = 'none';
    }
    
    // Update transaction hash input
    const hashInput = document.getElementById('dep-hash');
    if (hashInput) {
        hashInput.pattern = network === 'stellar' ? '[a-zA-Z0-9]{64}' : '0x[a-fA-F0-9]{64}';
        hashInput.maxLength = network === 'stellar' ? 64 : 66;
        hashInput.placeholder = config.hash_placeholder || 'Transaction hash';
        hashInput.value = '';
        hashInput.removeAttribute('required');
        hashInput.setAttribute('required', 'required');
    }
    
    // Update transaction hash label and helper text
    const hashLabel = depositModal.querySelector('label');
    if (hashLabel) {
        hashLabel.textContent = 'Step 2: Enter Transaction Hash';
        
        // Update first helper text
        const helperText = depositModal.querySelector('.deposit-hash-helper') || hashLabel.nextElementSibling;
        if (helperText) {
            if (network === 'stellar') {
                helperText.textContent = 'After sending DBV, copy the transaction hash from your Stellar wallet';
            } else {
                helperText.textContent = `After sending DBV, copy the transaction hash from your ${networkName} wallet`;
            }
        }
        
        // Update second helper text
        const hashHelper2 = depositModal.querySelector('.deposit-hash-helper-2') || hashInput?.nextElementSibling;
        if (hashHelper2) {
            if (network === 'stellar') {
                hashHelper2.textContent = 'The transaction hash is a 64-character alphanumeric code';
            } else {
                hashHelper2.textContent = `The transaction hash is a 66-character hex string starting with 0x`;
            }
        }
    }
    
    // Store network for form submission - MUST be done BEFORE opening modal
    const depositForm = document.getElementById('deposit-form');
    if (depositForm) {
        depositForm.dataset.network = network;
        console.log('Deposit form network set to:', network, 'Verified:', depositForm.dataset.network);
    } else {
        console.error('Deposit form not found!');
    }
    
    // Clear any previous output
    const depOut = document.getElementById('dep-out');
    if (depOut) {
        depOut.classList.add('hidden');
        depOut.textContent = '';
    }
    
    // Open modal
    openModal('deposit-modal');
    if (hashInput) setTimeout(() => hashInput.focus(), 100);
}

function openWithdrawalModalForNetwork(network) {
    console.log('Opening withdrawal modal for network:', network);
    console.log('Available config:', window.networkConfig);
    
    if (!window.networkConfig || !window.networkConfig[network]) {
        console.error('Network config not found:', network, 'Available:', Object.keys(window.networkConfig || {}));
        alert(`Network configuration not found for ${network}. Please refresh the page.`);
        return;
    }
    
    const config = window.networkConfig[network];
    // Use network name from config, fallback to hardcoded map
    const networkName = config.name || (network === 'stellar' ? 'Stellar' : network === 'binance' ? 'Binance Smart Chain' : network === 'ethereum' ? 'Ethereum' : network);
    const shortNames = {
        stellar: 'Stellar',
        binance: 'BSC',
        ethereum: 'Ethereum'
    };
    const shortName = shortNames[network] || network;
    const withdrawalModal = document.getElementById('withdrawal-modal');
    if (!withdrawalModal) return;
    
    // Update modal title
    const modalTitle = withdrawalModal.querySelector('h2');
    if (modalTitle) {
        modalTitle.textContent = `Withdraw DBV to ${networkName}`;
    }
    
    // Update address input
    const addressInput = document.getElementById('wdl-address');
    if (addressInput) {
        addressInput.pattern = network === 'stellar' ? '[G][A-Z0-9]{55}' : '0x[a-fA-F0-9]{40}';
        addressInput.maxLength = network === 'stellar' ? 56 : 42;
        addressInput.placeholder = network === 'stellar' 
            ? 'GXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
            : '0x...';
        addressInput.value = '';
        addressInput.removeAttribute('required');
        addressInput.setAttribute('required', 'required');
    }
    
    // Update address label - find the label that's followed by the address input
    const addressLabel = withdrawalModal.querySelector('label[for="wdl-address"]');
    if (addressLabel) {
        addressLabel.textContent = `Recipient ${shortName} Wallet Address`;
        
        // Update helper text - find by class first, then try next sibling
        let helperText = withdrawalModal.querySelector('.withdrawal-address-helper');
        if (!helperText) {
            // Try to find the P tag that comes after the address input
            helperText = addressInput?.nextElementSibling;
            while (helperText && helperText.tagName !== 'P') {
                helperText = helperText.nextElementSibling;
            }
        }
        
        if (helperText && (helperText.tagName === 'P' || helperText.classList.contains('withdrawal-address-helper'))) {
            helperText.textContent = `Enter the ${shortName} wallet address where you want to receive your DBV tokens`;
        }
    }
    
    // Update fee information based on network
    const feeInfoDiv = withdrawalModal.querySelector('.fee-info-container') || withdrawalModal.querySelector('.p-4.bg-gray-900');
    if (feeInfoDiv && config) {
        const feeEnabled = config.withdrawal_fee_enabled ?? true;
        const networkFee = config.withdrawal_fee_usdd ?? 2.0;
        
        // Update USDD balance display
        const usddBalanceEl = feeInfoDiv.querySelector('.usdd-balance-display');
        if (usddBalanceEl) {
            // Keep existing USDD balance display
        }
        
        // Update fee display
        const feeDisplayEl = feeInfoDiv.querySelector('.withdrawal-fee-display');
        if (feeDisplayEl) {
            if (feeEnabled) {
                feeDisplayEl.innerHTML = `
                    <div class="flex justify-between items-center pt-1 border-t border-gray-700">
                        <span class="text-gray-300">Withdrawal Fee:</span>
                        <span class="text-red-400 font-bold">${networkFee.toFixed(2)} USDD</span>
                    </div>
                `;
            } else {
                feeDisplayEl.innerHTML = '<p class="text-xs text-gray-400">No withdrawal fee is applied</p>';
            }
        } else {
            // Create fee display if it doesn't exist
            const feeSection = feeInfoDiv.querySelector('.space-y-2');
            if (feeSection) {
                const existingFee = feeSection.querySelector('.withdrawal-fee-display');
                if (existingFee) {
                    existingFee.remove();
                }
                if (feeEnabled) {
                    const feeDiv = document.createElement('div');
                    feeDiv.className = 'withdrawal-fee-display flex justify-between items-center pt-1 border-t border-gray-700';
                    feeDiv.innerHTML = `
                        <span class="text-gray-300">Withdrawal Fee:</span>
                        <span class="text-red-400 font-bold">${networkFee.toFixed(2)} USDD</span>
                    `;
                    feeSection.appendChild(feeDiv);
                }
            }
        }
    }
    
    // Store network for form submission - MUST be done BEFORE opening modal
    const withdrawalForm = document.getElementById('withdraw-form');
    if (withdrawalForm) {
        withdrawalForm.dataset.network = network;
        // Store network-specific fee info for validation
        withdrawalForm.dataset.feeEnabled = (config.withdrawal_fee_enabled ?? true) ? 'true' : 'false';
        withdrawalForm.dataset.feeAmount = (config.withdrawal_fee_usdd ?? 2.0).toString();
        console.log('Withdrawal form network set to:', network, 'Fee:', config.withdrawal_fee_usdd, 'Enabled:', config.withdrawal_fee_enabled);
    } else {
        console.error('Withdrawal form not found!');
    }
    
    // Clear any previous output and hide PIN section and confirmation
    const wdlOut = document.getElementById('wdl-out');
    if (wdlOut) {
        wdlOut.classList.add('hidden');
        wdlOut.textContent = '';
    }
    const pinSection = document.getElementById('pin-section');
    if (pinSection) {
        pinSection.classList.add('hidden');
    }
    const confirmationDiv = document.getElementById('withdrawal-confirmation');
    if (confirmationDiv) {
        confirmationDiv.classList.add('hidden');
    }
    
    // CRITICAL: Always ensure PIN input is enabled and responsive when modal opens
    const pinInput = document.getElementById('wdl-pin');
    if (pinInput) {
        pinInput.disabled = false;
        pinInput.readOnly = false;
        pinInput.value = '';
        pinInput.style.pointerEvents = 'auto';
        pinInput.style.opacity = '1';
        pinInput.removeAttribute('disabled');
        pinInput.removeAttribute('readonly');
    }
    
    // Clear and prepare amount input
    const amountInput = document.getElementById('wdl-amount');
    if (amountInput) {
        amountInput.value = '';
        amountInput.focus(); // Try immediate focus first
    }
    
    // Open modal
    openModal('withdrawal-modal');
    
    // Focus amount field after modal animation completes (increased delay for better reliability)
    if (amountInput) {
        // Try multiple times to ensure focus works after modal animation
        setTimeout(() => {
            amountInput.focus();
            amountInput.select(); // Select any existing text for easy replacement
        }, 150);
        
        // Backup focus attempt
        setTimeout(() => {
            if (document.activeElement !== amountInput) {
                amountInput.focus();
            }
        }, 300);
    }
}

// Deposit Form Handler
function initDepositForm() {
    const depForm = document.getElementById('deposit-form');
    const depOut = document.getElementById('dep-out');
    
    if (!depForm || !depOut) {
        console.error('Deposit form elements not found');
        return;
    }
    
    depForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const network = depForm.dataset.network;
        console.log('Deposit form submitted. Network from dataset:', network);
        console.log('Available window.networkConfig:', window.networkConfig);
        
        if (!network) {
            depOut.textContent = '✗ Error: Network not selected. Please select a network first.';
            depOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            depOut.classList.remove('hidden');
            return;
        }
        
        const config = window.networkConfig?.[network];
        
        if (!config) {
            depOut.textContent = `✗ Error: Network configuration not found for "${network}". Available networks: ${Object.keys(window.networkConfig || {}).join(', ')}`;
            depOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            depOut.classList.remove('hidden');
            return;
        }
        
        const hash = document.getElementById('dep-hash').value.trim();
        const csrfToken = document.getElementById('deposit-csrf-token')?.value || '';
        
        // Validate hash based on network
        const hashPattern = network === 'stellar' ? /^[a-zA-Z0-9]{64}$/ : /^0x[a-fA-F0-9]{64}$/i;
        if (!hash || !hashPattern.test(hash)) {
            depOut.textContent = `✗ Error: Invalid transaction hash format for ${network}.`;
            depOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            depOut.classList.remove('hidden');
            return;
        }
        
        depOut.textContent = 'Submitting deposit...';
        depOut.className = 'mt-4 text-xs text-red-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-red-800/50';
        
        try {
            const res = await fetch(config.deposit_api, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: new URLSearchParams({ txn_hash: hash, csrf_token: csrfToken }) 
            });
            
            const data = await res.json();
            
            if (data.success) {
                const amount = parseFloat(data.amount) || 0;
                depOut.textContent = `✓ ${data.message}\nTransaction Hash: ${data.txn_hash}\nAmount: ${amount.toFixed(2)} DBV\nDigitalChain Hash: ${data.yemchain_hash || 'N/A'}`;
                depOut.className = 'text-xs text-green-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                depOut.classList.remove('hidden');
                document.getElementById('dep-hash').value = '';
                
                loadTransactions();
                setTimeout(loadTransactions, 2000);
                setTimeout(loadTransactions, 5000);
                
                // Close modal after 3 seconds on success
                setTimeout(() => {
                    closeModal('deposit-modal');
                    depForm.reset();
                    depOut.classList.add('hidden');
                }, 3000);
            } else {
                depOut.textContent = `✗ Error: ${data.message || 'Unknown error occurred'}`;
                depOut.className = 'text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                depOut.classList.remove('hidden');
            }
        } catch (error) {
            depOut.textContent = `✗ Error: Failed to submit deposit. ${error.message}`;
            depOut.className = 'text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            depOut.classList.remove('hidden');
        }
    });
}

// Withdrawal Form Handler
function initWithdrawalForm(config) {
    const { 
        dbvBalance, 
        usddBalance, 
        feeEnabled, 
        withdrawalFee, 
        pinCheckUrl, 
        userId,
        networkConfig: netConfig
    } = config;
    
    // Make network config available globally
    window.networkConfig = netConfig;
    
    const wdlForm = document.getElementById('withdraw-form');
    const wdlOut = document.getElementById('wdl-out');
    const wdlAmountInput = document.getElementById('wdl-amount');
    const wdlAddressInput = document.getElementById('wdl-address');
    
    if (!wdlForm || !wdlOut || !wdlAmountInput || !wdlAddressInput) {
        console.error('Withdrawal form elements not found!');
        return;
    }
    
    console.log('Withdrawal form initialized');
    
    // Real-time validation
    wdlAmountInput.addEventListener('input', function() {
        const amount = parseFloat(this.value) || 0;
        
        if (amount > dbvBalance) {
            this.setCustomValidity(`Amount exceeds available balance (${dbvBalance.toFixed(2)} DBV)`);
            this.classList.add('border-red-500');
        } else if (amount <= 0) {
            this.setCustomValidity('Amount must be greater than 0');
            this.classList.add('border-red-500');
        } else {
            this.setCustomValidity('');
            this.classList.remove('border-red-500');
        }
    });
    
    wdlAddressInput.addEventListener('input', function() {
        const address = this.value.trim();
        const withdrawalForm = document.getElementById('withdraw-form');
        const network = withdrawalForm?.dataset.network || 'stellar';
        const netConfig = window.networkConfig?.[network];
        
        if (netConfig && address.length > 0) {
            // Extract pattern - handle both formats: "/pattern/flags" or "pattern" with separate flags
            let patternStr = netConfig.address_pattern || '';
            let flags = netConfig.address_pattern_flags || 'i'; // Use flags from config or default to 'i'
            
            // Handle PHP regex format: /pattern/flags (legacy support)
            if (patternStr.startsWith('/') && patternStr.lastIndexOf('/') > 0) {
                const lastSlash = patternStr.lastIndexOf('/');
                const patternPart = patternStr.substring(1, lastSlash);
                const flagsPart = patternStr.substring(lastSlash + 1);
                patternStr = patternPart;
                if (flagsPart) flags = flagsPart;
            }
            
            const addressPattern = new RegExp(patternStr, flags);
            const trimmedAddress = address.trim();
            
            if (!addressPattern.test(trimmedAddress)) {
                const networkNames = { stellar: 'Stellar', binance: 'BSC', ethereum: 'Ethereum' };
                this.setCustomValidity(`Invalid ${networkNames[network] || network} address format`);
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        }
    });
    
    function generateRandomPositions() {
        const positions = [];
        while (positions.length < 3) {
            const pos = Math.floor(Math.random() * 6) + 1;
            if (!positions.includes(pos)) {
                positions.push(pos);
            }
        }
        return positions;
    }
    
    let withdrawalData = null;
    
    wdlForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation(); // Prevent any form submission
        
        console.log('Withdrawal form submit event triggered');
        
        const pinSection = document.getElementById('pin-section');
        const pinInput = document.getElementById('wdl-pin');
        const keyInput = document.getElementById('wdl-key');
        
        if (!pinSection.classList.contains('hidden')) {
            const pin = pinInput.value.trim();
            if (pin.length !== 3) {
                wdlOut.textContent = '✗ Please enter exactly 3 digits from your PIN';
                wdlOut.className = 'text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                wdlOut.classList.remove('hidden');
                pinInput.focus();
                return;
            }
            await validateAndSubmitWithdrawal(pin, keyInput.value);
            return;
        }
        
        const amount = parseFloat(wdlAmountInput.value.trim());
        const address = wdlAddressInput.value.trim().toUpperCase();
        
        if (!amount || amount <= 0) {
            wdlOut.textContent = '✗ Please enter a valid amount greater than 0';
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap';
            return;
        }
        
        const withdrawalForm = document.getElementById('withdraw-form');
        const network = withdrawalForm?.dataset.network;
        console.log('Withdrawal form submitted. Network from dataset:', network);
        console.log('Available window.networkConfig:', window.networkConfig);
        
        if (!network) {
            wdlOut.textContent = '✗ Error: Network not selected. Please select a network first.';
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        const netConfig = window.networkConfig?.[network];
        
        if (!netConfig) {
            wdlOut.textContent = `✗ Error: Network configuration not found for "${network}". Available networks: ${Object.keys(window.networkConfig || {}).join(', ')}`;
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        // Get network-specific fee settings
        const networkFeeEnabled = netConfig.withdrawal_fee_enabled !== undefined 
            ? netConfig.withdrawal_fee_enabled 
            : (withdrawalForm?.dataset.feeEnabled === 'true');
        const networkFeeAmount = netConfig.withdrawal_fee_usdd !== undefined
            ? parseFloat(netConfig.withdrawal_fee_usdd)
            : parseFloat(withdrawalForm?.dataset.feeAmount ?? withdrawalFee);
        
        console.log('Network fee settings:', { network, networkFeeEnabled, networkFeeAmount });
        
        if (amount > dbvBalance) {
            wdlOut.textContent = `✗ Insufficient DBV balance. Available: ${dbvBalance.toFixed(2)} DBV`;
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        if (networkFeeEnabled && usddBalance < networkFeeAmount) {
            wdlOut.textContent = `✗ Insufficient USDD balance for withdrawal fee. Required: ${networkFeeAmount.toFixed(2)} USDD, Available: ${usddBalance.toFixed(2)} USDD`;
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        // Extract pattern - handle both formats: "/pattern/flags" or "pattern" with separate flags
        let patternStr = netConfig.address_pattern || '';
        let flags = netConfig.address_pattern_flags || 'i'; // Use flags from config or default to 'i'
        
        // Handle PHP regex format: /pattern/flags (legacy support)
        if (patternStr.startsWith('/') && patternStr.lastIndexOf('/') > 0) {
            const lastSlash = patternStr.lastIndexOf('/');
            const patternPart = patternStr.substring(1, lastSlash);
            const flagsPart = patternStr.substring(lastSlash + 1);
            patternStr = patternPart;
            if (flagsPart) flags = flagsPart;
        }
        
        const addressPattern = new RegExp(patternStr, flags);
        const trimmedAddress = address.trim();
        console.log('Validating address:', trimmedAddress, 'against pattern:', patternStr, 'flags:', flags);
        
        if (!trimmedAddress || !addressPattern.test(trimmedAddress)) {
            console.error('Address validation failed:', trimmedAddress, 'does not match', patternStr);
            const networkNames = { stellar: 'Stellar (56 characters starting with G)', binance: 'BSC (0x... 42 characters)', ethereum: 'Ethereum (0x... 42 characters)' };
            wdlOut.textContent = `✗ Please enter a valid ${networkNames[network] || network} address`;
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        withdrawalData = { amount, address };
        
        // Show in-modal confirmation instead of browser popup
        const confirmationDiv = document.getElementById('withdrawal-confirmation');
        const confirmAmount = document.getElementById('confirm-amount');
        const confirmAddress = document.getElementById('confirm-address');
        const confirmNetwork = document.getElementById('confirm-network');
        const confirmFee = document.getElementById('confirm-fee');
        const confirmFeeSpan = confirmFee?.querySelector('span:last-child');
        const confirmProceedBtn = document.getElementById('confirm-proceed-btn');
        const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
        
        if (confirmationDiv && confirmAmount && confirmAddress && confirmNetwork) {
            // Hide PIN section if visible
            pinSection.classList.add('hidden');
            wdlOut.classList.add('hidden');
            
            // Show confirmation details
            confirmAmount.textContent = `${amount.toFixed(2)} DBV`;
            confirmAddress.textContent = `${address.substring(0, 10)}...${address.substring(address.length - 6)}`;
            confirmNetwork.textContent = netConfig.name || network.toUpperCase();
            
            // Show/hide fee
            if (networkFeeEnabled && confirmFee && confirmFeeSpan) {
                confirmFee.classList.remove('hidden');
                confirmFeeSpan.textContent = `${networkFeeAmount.toFixed(2)} USDD`;
            } else if (confirmFee) {
                confirmFee.classList.add('hidden');
            }
            
            // Show confirmation section
            confirmationDiv.classList.remove('hidden');
            confirmationDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Handle proceed button - use one-time event listener
            if (confirmProceedBtn) {
                const proceedHandler = function(e) {
                    e.preventDefault();
                    confirmProceedBtn.removeEventListener('click', proceedHandler);
                    proceedToPin();
                };
                confirmProceedBtn.addEventListener('click', proceedHandler);
            }
            
            // Handle cancel button - use one-time event listener
            if (confirmCancelBtn) {
                const cancelHandler = function(e) {
                    e.preventDefault();
                    confirmCancelBtn.removeEventListener('click', cancelHandler);
                    confirmationDiv.classList.add('hidden');
                    wdlOut.classList.add('hidden');
                };
                confirmCancelBtn.addEventListener('click', cancelHandler);
            }
        } else {
            // Fallback: If confirmation elements not found, proceed directly to PIN
            proceedToPin();
        }
        
        function proceedToPin() {
            // Hide confirmation
            const confirmationDiv = document.getElementById('withdrawal-confirmation');
            if (confirmationDiv) {
                confirmationDiv.classList.add('hidden');
            }
            
            const pinPositions = generateRandomPositions();
            const key = pinPositions.join('');
            
            const pinPositionsDiv = document.getElementById('pin-positions');
            pinPositionsDiv.innerHTML = `Enter digits at positions: <span class="text-yellow-400 font-bold">${pinPositions.join(', ')}</span>`;
            keyInput.value = key;
            pinInput.value = '';
            
            // CRITICAL: Always enable PIN input when showing PIN section
            pinInput.disabled = false;
            pinInput.readOnly = false;
            pinInput.style.pointerEvents = 'auto';
            pinInput.style.opacity = '1';
            
            pinSection.classList.remove('hidden');
            
            // Auto-focus on PIN input when section appears
            setTimeout(() => {
                pinInput.focus();
                // Scroll PIN section into view if needed
                pinSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 150);
            
            wdlOut.textContent = 'Please enter the 3 digits from your 6-digit PIN as shown above.';
            wdlOut.className = 'text-xs text-yellow-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            
            // Clone the input to remove all existing event listeners
            const oldPinInput = document.getElementById('wdl-pin');
            if (oldPinInput) {
                const newPinInput = oldPinInput.cloneNode(true);
                oldPinInput.parentNode.replaceChild(newPinInput, oldPinInput);
                newPinInput.value = '';
                
                // CRITICAL: Always ensure the fresh input is enabled
                newPinInput.disabled = false;
                newPinInput.readOnly = false;
                newPinInput.style.pointerEvents = 'auto';
                newPinInput.style.opacity = '1';
                
                // Add event handler that uses the fresh element (not closure)
                newPinInput.addEventListener('keypress', function(e) {
                    const currentPin = document.getElementById('wdl-pin'); // Always get fresh reference
                    if (e.key === 'Enter' && currentPin && currentPin.value.length === 3 && !currentPin.disabled) {
                        e.preventDefault();
                        validateAndSubmitWithdrawal(currentPin.value, keyInput.value);
                    }
                });
                
                // Monitor for disabled state and auto-fix
                newPinInput.addEventListener('input', function() {
                    const currentPin = document.getElementById('wdl-pin');
                    if (currentPin && currentPin.disabled) {
                        currentPin.disabled = false;
                        currentPin.readOnly = false;
                        currentPin.style.pointerEvents = 'auto';
                        currentPin.style.opacity = '1';
                    }
                });
                
                // Monitor on focus to ensure it's enabled
                newPinInput.addEventListener('focus', function() {
                    const currentPin = document.getElementById('wdl-pin');
                    if (currentPin && currentPin.disabled) {
                        currentPin.disabled = false;
                        currentPin.readOnly = false;
                        currentPin.style.pointerEvents = 'auto';
                        currentPin.style.opacity = '1';
                    }
                });
            }
        }
    });
    
    async function validateAndSubmitWithdrawal(pin, key) {
        if (!withdrawalData) return;
        
        const { amount, address } = withdrawalData;
        const pinInput = document.getElementById('wdl-pin');
        const submitBtn = wdlForm.querySelector('button[type="submit"]');
        
        // Get network configuration from form dataset
        const withdrawalForm = document.getElementById('withdraw-form');
        const network = withdrawalForm?.dataset.network;
        
        if (!network) {
            wdlOut.textContent = '✗ Error: Network not found. Please select a network first.';
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            return;
        }
        
        // Get network config from window.networkConfig
        const netConfig = window.networkConfig?.[network];
        
        if (!netConfig) {
            wdlOut.textContent = `✗ Error: Network configuration not found for "${network}". Available: ${Object.keys(window.networkConfig || {}).join(', ')}`;
            wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            console.error('Network config missing:', { network, available: Object.keys(window.networkConfig || {}) });
            return;
        }
        
        wdlOut.textContent = 'Validating PIN...';
        wdlOut.className = 'mt-4 text-xs text-red-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-red-800/50';
        
        pinInput.disabled = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Validating...';
        
        try {
            console.log('PIN Validation - Sending:', { uid: userId, pin: '***', key, pinLength: pin.length });
            
            const pinResponse = await fetch(pinCheckUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ uid: userId, pin, key })
            });
            
            if (!pinResponse.ok) {
                console.error('PIN check HTTP error:', pinResponse.status, pinResponse.statusText);
                throw new Error(`PIN validation failed: HTTP ${pinResponse.status}`);
            }
            
            const pinResult = await pinResponse.text();
            const trimmedResult = pinResult.trim();
            const lowerResult = trimmedResult.toLowerCase();
            
            console.log('PIN Validation - Response:', { 
                status: pinResponse.status, 
                response: trimmedResult.substring(0, 50),
                trimmed: trimmedResult,
                lowerCase: lowerResult
            });
            
            // Check for valid responses (handle various formats)
            const isValid = lowerResult === 'valid' || 
                          lowerResult === 'success' || 
                          lowerResult === 'ok' ||
                          trimmedResult === '1' ||
                          trimmedResult === 'true';
            
            if (!isValid) {
                // CRITICAL: Ensure PIN input is ALWAYS re-enabled on validation failure
                pinInput.disabled = false;
                pinInput.readOnly = false;
                pinInput.style.pointerEvents = 'auto';
                pinInput.style.opacity = '1';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Initiate Withdrawal';
                pinInput.value = '';
                
                // Force focus with multiple attempts
                setTimeout(() => {
                    pinInput.focus();
                }, 50);
                setTimeout(() => {
                    if (document.activeElement !== pinInput) {
                        pinInput.focus();
                    }
                }, 200);
                
                // Better error message with network info
                const errorMsg = trimmedResult || 'Unknown error';
                const networkName = netConfig?.name || network || 'selected network';
                
                console.error('PIN Validation Failed:', {
                    network: networkName,
                    response: trimmedResult,
                    key: key,
                    pinLength: pin.length
                });
                
                if (errorMsg.includes('error') || errorMsg.includes('fail')) {
                    wdlOut.textContent = `✗ PIN validation failed (${networkName}). Response: ${errorMsg.substring(0, 100)}. Please check the digits at positions: ${key.split('').join(', ')}`;
                } else {
                    wdlOut.textContent = `✗ Invalid PIN for ${networkName}. Please check the digits at positions: ${key.split('').join(', ')}. Server response: ${errorMsg.substring(0, 50)}`;
                }
                wdlOut.className = 'mt-3 text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                wdlOut.classList.remove('hidden');
                return;
            }
            
            const networkName = netConfig?.name || network || 'selected network';
            console.log('PIN Validation PASSED - Proceeding with withdrawal for', networkName);
            
            wdlOut.textContent = 'PIN confirmed. Submitting withdrawal request...';
            wdlOut.className = 'mt-4 text-xs text-red-300 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-red-800/50';
            submitBtn.textContent = 'Processing...';
            
            const csrfToken = document.getElementById('csrf-token')?.value || '';
            // Support both withdraw_api (from PHP) and withdrawUrl (from local config)
            const withdrawUrl = netConfig.withdraw_api || netConfig.withdrawUrl;
            console.log('PIN confirmed. Submitting withdrawal request to', withdrawUrl, 'for', networkName);
            
            if (!withdrawUrl) {
                console.error('Network config:', netConfig);
                throw new Error(`Withdraw API URL not found in network configuration for ${network}. Available keys: ${Object.keys(netConfig).join(', ')}`);
            }
            
            // Debug: Log exact values being sent (for debugging only)
            const pinValue = String(pin).trim(); // Ensure it's a string and trimmed
            const keyValue = String(key).trim(); // Ensure it's a string and trimmed
            
            console.log('=== WITHDRAWAL SUBMISSION DEBUG ===');
            console.log('Submitting withdrawal to:', withdrawUrl);
            console.log('Request method: POST');
            console.log('PIN Value:', {
                original: pin,
                processed: pinValue,
                length: pinValue.length,
                type: typeof pinValue,
                firstChar: pinValue.charAt(0),
                lastChar: pinValue.charAt(pinValue.length - 1)
            });
            console.log('KEY Value:', {
                original: key,
                processed: keyValue,
                length: keyValue.length,
                type: typeof keyValue
            });
            console.log('Request data:', { 
                amount, 
                address, 
                pin: pinValue.substring(0, 1) + '**' + pinValue.substring(2), // Show first and last digit
                pinLength: pinValue.length,
                key: keyValue,
                keyLength: keyValue.length,
                csrf_token: csrfToken ? 'present' : 'missing' 
            });
            
            // Create URLSearchParams and log what it creates
            const params = new URLSearchParams({ 
                amount: amount.toString(), 
                address: String(address), 
                pin: pinValue, 
                key: keyValue, 
                csrf_token: csrfToken 
            });
            const paramsString = params.toString();
            console.log('URLSearchParams created');
            console.log('URLSearchParams string (masked):', paramsString.replace(/pin=[^&]*/, 'pin=***').replace(/key=[^&]*/, 'key=***'));
            console.log('URLSearchParams - pin param exists:', params.has('pin'));
            console.log('URLSearchParams - key param exists:', params.has('key'));
            console.log('URLSearchParams - pin value from params:', params.get('pin')?.substring(0, 1) + '**');
            console.log('URLSearchParams - key value from params:', params.get('key'));
            console.log('===================================');
            
            const res = await fetch(withdrawUrl, { 
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: params
            });
            
            // Check if response is actually JSON
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await res.text();
                console.error('Non-JSON response received:', text.substring(0, 500));
                throw new Error(`Server returned ${res.status} ${res.statusText}. Expected JSON but got ${contentType || 'unknown type'}. This usually means the endpoint doesn't exist, there's a server error, or you've been redirected.`);
            }
            
            const data = await res.json();
        
            if (data.success) {
                // Trustline is ONLY for Stellar - EVM networks (Binance/Ethereum) don't use trustlines
                const trustlineInfo = (network === 'stellar' && data.trustline !== undefined)
                    ? (data.trustline === 1 
                        ? '\n✓ Trustline verified - processing immediately' 
                        : '\n⚠ Trustline not found - will process once trustline is established')
                    : ''; // No trustline message for Binance/Ethereum
                
                const withdrawalAmount = parseFloat(data.amount) || 0;
                const feeInfo = (data.fee_enabled && parseFloat(data.fee_usdd) > 0) ? `\nFee: ${parseFloat(data.fee_usdd).toFixed(2)} USDD` : '';
                wdlOut.textContent = `✓ ${data.message}${trustlineInfo}\n\nDigitalChain Hash: ${data.txn_hash}\nAmount: ${withdrawalAmount.toFixed(2)} DBV${feeInfo}\nAddress: ${data.address}\nWithdrawal ID: ${data.id}`;
                wdlOut.className = 'text-xs text-green-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                wdlOut.classList.remove('hidden');
                
                wdlForm.reset();
                document.getElementById('pin-section').classList.add('hidden');
                withdrawalData = null;
                
                loadTransactions();
                setTimeout(loadTransactions, 2000);
                setTimeout(loadTransactions, 5000);
                setTimeout(loadTransactions, 10000);
                
                // Close modal after 5 seconds on success (longer for withdrawal to see details)
                setTimeout(() => {
                    closeModal('withdrawal-modal');
                    wdlForm.reset();
                    wdlOut.classList.add('hidden');
                }, 5000);
            } else {
                wdlOut.textContent = `✗ Error: ${data.message || 'Withdrawal failed. Please try again.'}`;
                wdlOut.className = 'text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
                wdlOut.classList.remove('hidden');
                
                // CRITICAL: Always re-enable PIN input on withdrawal failure
                pinInput.disabled = false;
                pinInput.readOnly = false;
                pinInput.style.pointerEvents = 'auto';
                pinInput.style.opacity = '1';
                
                // Force focus back to PIN input
                setTimeout(() => {
                    pinInput.focus();
                }, 100);
            }
        } catch (err) {
            wdlOut.textContent = `✗ Network Error: ${err.message}\nPlease check your connection and try again.`;
            wdlOut.className = 'text-xs text-red-400 whitespace-pre-wrap bg-black/50 p-3 rounded-lg border border-gray-800';
            wdlOut.classList.remove('hidden');
            
            // CRITICAL: Always re-enable PIN input on any error
            pinInput.disabled = false;
            pinInput.readOnly = false;
            pinInput.style.pointerEvents = 'auto';
            pinInput.style.opacity = '1';
            
            // Force focus back to PIN input
            setTimeout(() => {
                pinInput.focus();
            }, 100);
        } finally {
            // CRITICAL: Always ensure PIN input is enabled in finally block
            pinInput.disabled = false;
            pinInput.readOnly = false;
            pinInput.style.pointerEvents = 'auto';
            pinInput.style.opacity = '1';
            
            submitBtn.disabled = false;
            submitBtn.textContent = 'Initiate Withdrawal';
        }
    }
}

// Modal Management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Auto-focus on first input field (skip withdrawal-modal as it handles its own focus)
        if (modalId !== 'withdrawal-modal') {
            setTimeout(() => {
                if (modalId === 'deposit-modal') {
                    const depositInput = document.getElementById('dep-hash');
                    if (depositInput) {
                        depositInput.focus();
                    }
                }
            }, 100); // Small delay to ensure modal is fully rendered
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Network Selection variables are declared at top of file (lines 179-180)

// Network configuration - URLs for each network (fallback if not set from PHP)
// Will be overridden by window.networkConfig from PHP if available
const getApiBase = () => window.APP_API_BASE || '/public/api';
const networkConfig = {
    stellar: {
        depositUrl: `${getApiBase()}/stellar/deposit.php`,
        withdrawUrl: `${getApiBase()}/stellar/withdraw.php`,
        name: 'Stellar',
        addressPattern: /^[a-zA-Z0-9]{56}$/,
        hashPattern: /^[a-zA-Z0-9]{64}$/,
        hashPlaceholder: 'Paste your 64-character transaction hash here',
        addressPlaceholder: 'GXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
    },
    binance: {
        depositUrl: `${getApiBase()}/binance/deposit.php`,
        withdrawUrl: `${getApiBase()}/binance/withdraw.php`,
        name: 'Binance Smart Chain',
        addressPattern: /^0x[a-fA-F0-9]{40}$/i,
        hashPattern: /^0x[a-fA-F0-9]{64}$/i,
        hashPlaceholder: '0x... (transaction hash)',
        addressPlaceholder: '0x... (BSC wallet address)'
    },
    ethereum: {
        depositUrl: `${getApiBase()}/ethereum/deposit.php`,
        withdrawUrl: `${getApiBase()}/ethereum/withdraw.php`,
        name: 'Ethereum',
        addressPattern: /^0x[a-fA-F0-9]{40}$/i,
        hashPattern: /^0x[a-fA-F0-9]{64}$/i,
        hashPlaceholder: '0x... (transaction hash)',
        addressPlaceholder: '0x... (Ethereum wallet address)'
    }
};

function openNetworkSelection(actionType) {
    currentActionType = actionType;
    const modal = document.getElementById('network-selection-modal');
    const title = document.getElementById('network-selection-title');
    
    if (modal && title) {
        title.textContent = `Select Network for ${actionType === 'deposit' ? 'Deposit' : 'Withdrawal'}`;
        openModal('network-selection-modal');
    }
}

function selectNetwork(network) {
    selectedNetwork = network;
    closeModal('network-selection-modal');
    
    if (currentActionType === 'deposit') {
        openDepositModalForNetwork(network);
    } else if (currentActionType === 'withdraw') {
        openWithdrawalModalForNetwork(network);
    }
}

// Initialize modal handlers
document.addEventListener('DOMContentLoaded', () => {
    // Network selection modal
    const openDepositNetworkBtn = document.getElementById('open-deposit-network-modal');
    const openWithdrawNetworkBtn = document.getElementById('open-withdraw-network-modal');
    const closeNetworkSelectionBtn = document.getElementById('close-network-selection-modal');
    const networkSelectionModal = document.getElementById('network-selection-modal');
    const networkOptions = document.querySelectorAll('.network-option');
    
    if (openDepositNetworkBtn) {
        openDepositNetworkBtn.addEventListener('click', () => {
            openNetworkSelection('deposit');
        });
    }
    
    if (openWithdrawNetworkBtn) {
        openWithdrawNetworkBtn.addEventListener('click', () => {
            openNetworkSelection('withdraw');
        });
    }
    
    if (closeNetworkSelectionBtn) {
        closeNetworkSelectionBtn.addEventListener('click', () => {
            closeModal('network-selection-modal');
        });
    }
    
    if (networkSelectionModal) {
        networkSelectionModal.addEventListener('click', (e) => {
            if (e.target === networkSelectionModal) {
                closeModal('network-selection-modal');
            }
        });
    }
    
    // Network option buttons
    networkOptions.forEach(btn => {
        btn.addEventListener('click', () => {
            const network = btn.dataset.network;
            if (network) {
                selectNetwork(network);
            }
        });
    });
    
    // Deposit modal
    const closeDepositBtn = document.getElementById('close-deposit-modal');
    const depositModal = document.getElementById('deposit-modal');
    
    if (closeDepositBtn) {
        closeDepositBtn.addEventListener('click', () => closeModal('deposit-modal'));
    }
    if (depositModal) {
        depositModal.addEventListener('click', (e) => {
            if (e.target === depositModal) {
                closeModal('deposit-modal');
            }
        });
    }
    
    // Withdrawal modal
    const closeWithdrawalBtn = document.getElementById('close-withdrawal-modal');
    const withdrawalModal = document.getElementById('withdrawal-modal');
    
    if (closeWithdrawalBtn) {
        closeWithdrawalBtn.addEventListener('click', () => closeModal('withdrawal-modal'));
    }
    if (withdrawalModal) {
        withdrawalModal.addEventListener('click', (e) => {
            if (e.target === withdrawalModal) {
                closeModal('withdrawal-modal');
            }
        });
    }
    
    // Close modals on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal('network-selection-modal');
            closeModal('deposit-modal');
            closeModal('withdrawal-modal');
        }
    });
});

