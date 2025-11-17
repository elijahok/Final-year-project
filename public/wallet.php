<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();

// Get wallet information
$wallet = $db->fetch("
    SELECT * FROM wallets
    WHERE user_id = ?
", [$currentUser['id']]);

if (!$wallet) {
    // Create wallet if it doesn't exist
    $walletId = $db->insert('wallets', [
        'user_id' => $currentUser['id'],
        'balance' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $wallet = [
        'id' => $walletId,
        'balance' => 0
    ];
}

// Get recent transactions
$recentTransactions = $db->fetchAll("
    SELECT wt.*, 
           CASE 
               WHEN wt.transaction_type = 'credit' THEN '+'
               ELSE '-'
           END as sign,
           u.full_name as related_user_name
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.related_user_id = u.id
    WHERE wt.user_id = ?
    ORDER BY wt.created_at DESC
    LIMIT 10
", [$currentUser['id']]);

// Get wallet statistics
$stats = [
    'total_credits' => $db->fetch("
        SELECT COALESCE(SUM(amount), 0) as total FROM wallet_transactions
        WHERE user_id = ? AND transaction_type = 'credit'
    ", [$currentUser['id']])['total'] ?? 0,
    
    'total_debits' => $db->fetch("
        SELECT COALESCE(SUM(amount), 0) as total FROM wallet_transactions
        WHERE user_id = ? AND transaction_type = 'debit'
    ", [$currentUser['id']])['total'] ?? 0,
    
    'transactions_this_month' => $db->fetch("
        SELECT COUNT(*) as count FROM wallet_transactions
        WHERE user_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE)
    ", [$currentUser['id']])['count'] ?? 0
];

$pageTitle = 'My Wallet - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-wallet me-2"></i>
                    My Wallet
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#topupModal">
                        <i class="fas fa-plus me-1"></i>
                        Top Up
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                        <i class="fas fa-minus me-1"></i>
                        Withdraw
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Balance Card -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-uppercase text-white-50 mb-2">Current Balance</h6>
                            <h1 class="mb-0">KES <?= number_format($wallet['balance'], 2) ?></h1>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-wallet fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-white-50">Last updated: <?= formatDateTime($wallet['updated_at']) ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1">Total Credits</h6>
                                    <h4 class="mb-0 text-success">+KES <?= number_format($stats['total_credits'], 2) ?></h4>
                                </div>
                                <i class="fas fa-arrow-down fa-2x text-success opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1">Total Debits</h6>
                                    <h4 class="mb-0 text-danger">-KES <?= number_format($stats['total_debits'], 2) ?></h4>
                                </div>
                                <i class="fas fa-arrow-up fa-2x text-danger opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1">This Month</h6>
                                    <h4 class="mb-0 text-primary"><?= $stats['transactions_this_month'] ?></h4>
                                </div>
                                <i class="fas fa-calendar fa-2x text-primary opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Recent Transactions
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="exportTransactions">
                                <i class="fas fa-download me-1"></i>
                                Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTransactions">
                                <i class="fas fa-sync me-1"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No transactions yet</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#topupModal">
                                <i class="fas fa-plus me-1"></i>
                                Top Up Wallet
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Related User</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <small><?= formatDate($transaction['created_at']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= formatTime($transaction['created_at']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($transaction['description']) ?></div>
                                                <small class="text-muted">ID: #<?= $transaction['id'] ?></small>
                                            </td>
                                            <td>
                                                <?php if ($transaction['related_user_name']): ?>
                                                    <?= htmlspecialchars($transaction['related_user_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold <?= $transaction['transaction_type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                                                    <?= $transaction['sign'] ?>KES <?= number_format($transaction['amount'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success">Completed</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary" id="loadMoreTransactions">
                                <i class="fas fa-arrow-down me-1"></i>
                                Load More
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#topupModal">
                            <i class="fas fa-plus me-2"></i>
                            Top Up Wallet
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            <i class="fas fa-minus me-2"></i>
                            Withdraw Funds
                        </button>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#transactionModal">
                            <i class="fas fa-search me-2"></i>
                            Transaction History
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-mobile-alt me-2"></i>
                        Supported Payment Methods
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone fa-2x text-success me-3"></i>
                                <div>
                                    <div class="fw-bold">M-Pesa</div>
                                    <small class="text-muted">Safaricom mobile money</small>
                                </div>
                            </div>
                            <span class="badge bg-success">Active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone fa-2x text-primary me-3"></i>
                                <div>
                                    <div class="fw-bold">Airtel Money</div>
                                    <small class="text-muted">Airtel mobile money</small>
                                </div>
                            </div>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet Tips -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Wallet Tips
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Did you know?</h6>
                        <ul class="mb-0">
                            <li>You can use your wallet balance to pay for transport services</li>
                            <li>Top-ups are processed instantly via mobile money</li>
                            <li>Withdrawals are processed within 24 hours</li>
                            <li>All transactions are secure and encrypted</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Up Modal -->
<div class="modal fade" id="topupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Top Up Wallet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="topupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="topupAmount" class="form-label">Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" id="topupAmount" name="amount" 
                                   min="10" max="50000" step="10" required>
                        </div>
                        <small class="form-text text-muted">Minimum: KES 10, Maximum: KES 50,000</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="topupProvider" class="form-label">Payment Method</label>
                        <select class="form-select" id="topupProvider" name="provider" required>
                            <option value="">Select payment method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="airtel">Airtel Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="topupPhone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="topupPhone" name="phone_number" 
                               placeholder="07XX XXX XXX" required>
                        <small class="form-text text-muted">Enter your mobile money number</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You will receive a prompt on your phone to confirm the payment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Top Up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-minus me-2"></i>
                    Withdraw Funds
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="withdrawForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="withdrawAmount" class="form-label">Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" id="withdrawAmount" name="amount" 
                                   min="10" max="<?= $wallet['balance'] ?>" step="10" required>
                        </div>
                        <small class="form-text text-muted">
                            Available: KES <?= number_format($wallet['balance'], 2) ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="withdrawProvider" class="form-label">Payment Method</label>
                        <select class="form-select" id="withdrawProvider" name="provider" required>
                            <option value="">Select payment method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="airtel">Airtel Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="withdrawPhone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="withdrawPhone" name="phone_number" 
                               placeholder="07XX XXX XXX" required>
                        <small class="form-text text-muted">Enter the mobile money number to receive funds</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Withdrawals are processed within 24 hours. A small transaction fee may apply.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-minus me-2"></i>
                        Withdraw
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transaction History Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history me-2"></i>
                    Transaction History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="filterDateFrom" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="filterDateFrom">
                        </div>
                        <div class="col-md-6">
                            <label for="filterDateTo" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="filterDateTo">
                        </div>
                    </div>
                </div>
                
                <div id="transactionHistory">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Loading transactions...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportHistory">
                    <i class="fas fa-download me-2"></i>
                    Export
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentBalance = <?= $wallet['balance'] ?>;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupFormHandlers();
});

// Setup event listeners
function setupEventListeners() {
    // Refresh transactions
    document.getElementById('refreshTransactions').addEventListener('click', function() {
        location.reload();
    });
    
    // Export transactions
    document.getElementById('exportTransactions').addEventListener('click', function() {
        const url = '<?= BASE_URL ?>/api/export-wallet-transactions.php';
        window.open(url, '_blank');
    });
    
    // Load more transactions
    document.getElementById('loadMoreTransactions').addEventListener('click', function() {
        // Implement pagination
        alert('Load more functionality would be implemented here');
    });
    
    // Transaction history modal
    document.getElementById('transactionModal').addEventListener('show.bs.modal', function() {
        loadTransactionHistory();
    });
    
    // Export history
    document.getElementById('exportHistory').addEventListener('click', function() {
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        let url = '<?= BASE_URL ?>/api/export-wallet-transactions.php';
        
        if (dateFrom && dateTo) {
            url += `?date_from=${dateFrom}&date_to=${dateTo}`;
        }
        
        window.open(url, '_blank');
    });
}

// Setup form handlers
function setupFormHandlers() {
    // Top up form
    document.getElementById('topupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'topup_wallet');
        formData.append('csrf_token', '<?= generateCSRFToken() ?>');
        
        fetch('<?= BASE_URL ?>/api/mobile-money.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Top up initiated successfully! Please check your phone for confirmation.');
                bootstrap.Modal.getInstance(document.getElementById('topupModal')).hide();
                this.reset();
                
                // Check payment status periodically
                checkPaymentStatus(data.transaction_id);
            } else {
                alert(data.error || 'Failed to initiate top up');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to initiate top up');
        });
    });
    
    // Withdraw form
    document.getElementById('withdrawForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const amount = parseFloat(this.amount.value);
        if (amount > currentBalance) {
            alert('Insufficient balance');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'withdraw_wallet');
        formData.append('csrf_token', '<?= generateCSRFToken() ?>');
        
        fetch('<?= BASE_URL ?>/api/mobile-money.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Withdrawal processed successfully!');
                bootstrap.Modal.getInstance(document.getElementById('withdrawModal')).hide();
                this.reset();
                location.reload();
            } else {
                alert(data.error || 'Failed to process withdrawal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to process withdrawal');
        });
    });
}

// Check payment status
function checkPaymentStatus(transactionId) {
    const checkInterval = setInterval(function() {
        fetch('<?= BASE_URL ?>/api/mobile-money.php?action=check_status&transaction_id=' + transactionId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'completed') {
                clearInterval(checkInterval);
                alert('Payment completed successfully!');
                location.reload();
            } else if (data.status === 'failed') {
                clearInterval(checkInterval);
                alert('Payment failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error checking status:', error);
        });
    }, 5000);
    
    // Stop checking after 5 minutes
    setTimeout(function() {
        clearInterval(checkInterval);
    }, 300000);
}

// Load transaction history
function loadTransactionHistory() {
    const container = document.getElementById('transactionHistory');
    
    fetch('<?= BASE_URL ?>/api/mobile-money.php?action=wallet_transactions&limit=50')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayTransactionHistory(data.transactions);
        } else {
            container.innerHTML = '<p class="text-muted text-center">Failed to load transactions</p>';
        }
    })
    .catch(error => {
        console.error('Error loading transactions:', error);
        container.innerHTML = '<p class="text-muted text-center">Failed to load transactions</p>';
    });
}

// Display transaction history
function displayTransactionHistory(transactions) {
    const container = document.getElementById('transactionHistory');
    
    if (transactions.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No transactions found</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Date</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>';
    
    transactions.forEach(transaction => {
        const sign = transaction.transaction_type === 'credit' ? '+' : '-';
        const colorClass = transaction.transaction_type === 'credit' ? 'text-success' : 'text-danger';
        
        html += `<tr>
            <td><small>${formatDate(transaction.created_at)}</small></td>
            <td><small>${transaction.description}</small></td>
            <td class="text-end"><small class="${colorClass}">${sign}KES ${transaction.amount.toFixed(2)}</small></td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
