<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$userRole = $currentUser['role'];
$pageTitle = 'Analytics Dashboard - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Analytics Dashboard
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="exportBtn">
                        <i class="fas fa-download me-1"></i>
                        Export
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                        <i class="fas fa-sync-alt me-1"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Frame Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="timeframeForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="timeframe" class="form-label">Time Period</label>
                    <select class="form-select" id="timeframe" name="timeframe">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" id="customDateRange" style="display: none;">
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="start_date">
                </div>
                <div class="col-md-3" id="customDateRangeEnd" style="display: none;">
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="end_date">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row mb-4" id="overviewCards">
        <!-- Cards will be populated dynamically -->
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-area me-2"></i>
                        Activity Trends
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-dollar-sign me-2"></i>
                        Revenue Trends
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        User Statistics
                    </h5>
                </div>
                <div class="card-body" id="userStats">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Transaction Statistics
                    </h5>
                </div>
                <div class="card-body" id="transactionStats">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Activity
                    </h5>
                </div>
                <div class="card-body" id="recentActivity">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Analytics Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="exportForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exportType" class="form-label">Export Type</label>
                        <select class="form-select" id="exportType" name="export_type" required>
                            <option value="summary">Summary Report</option>
                            <option value="tenders">Tenders Data</option>
                            <option value="transport">Transport Requests</option>
                            <option value="payments">Payment Transactions</option>
                            <option value="quality">Quality Reports</option>
                            <option value="users">User Data</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="format" required>
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportStartDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="exportStartDate" name="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="exportEndDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="exportEndDate" name="end_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>
                        Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let charts = {};
let currentTimeframe = 30;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    
    // Event listeners
    document.getElementById('timeframeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        loadDashboardData();
    });
    
    document.getElementById('timeframe').addEventListener('change', function() {
        const customRange = this.value === 'custom';
        document.getElementById('customDateRange').style.display = customRange ? 'block' : 'none';
        document.getElementById('customDateRangeEnd').style.display = customRange ? 'block' : 'none';
    });
    
    document.getElementById('refreshBtn').addEventListener('click', loadDashboardData);
    document.getElementById('exportBtn').addEventListener('click', showExportModal);
    document.getElementById('exportForm').addEventListener('submit', handleExport);
    
    // Set default dates for export
    const endDate = new Date();
    const startDate = new Date(endDate.getTime() - (30 * 24 * 60 * 60 * 1000));
    document.getElementById('exportStartDate').value = startDate.toISOString().split('T')[0];
    document.getElementById('exportEndDate').value = endDate.toISOString().split('T')[0];
});

// Load dashboard data
function loadDashboardData() {
    const timeframe = document.getElementById('timeframe').value;
    let startDate, endDate;
    
    if (timeframe === 'custom') {
        startDate = document.getElementById('startDate').value;
        endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select start and end dates');
            return;
        }
    } else {
        currentTimeframe = parseInt(timeframe);
        startDate = '';
        endDate = '';
    }
    
    const params = new URLSearchParams({
        action: 'dashboard',
        timeframe: currentTimeframe
    });
    
    if (startDate && endDate) {
        params.set('start_date', startDate);
        params.set('end_date', endDate);
    }
    
    fetch(`<?= BASE_URL ?>/api/analytics.php?${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDashboardData(data.data);
            loadTrendsData();
        } else {
            showError(data.error || 'Failed to load dashboard data');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load dashboard data');
    });
}

// Display dashboard data
function displayDashboardData(data) {
    displayOverviewCards(data.overview);
    displayUserStats(data.user_stats || {});
    displayTransactionStats(data.transaction_stats || {});
    displayRecentActivity(data.recent_activity || []);
}

// Display overview cards
function displayOverviewCards(overview) {
    const container = document.getElementById('overviewCards');
    
    const cards = [
        { title: 'Total Users', value: overview.total_users || 0, icon: 'users', color: 'primary' },
        { title: 'Active Tenders', value: overview.active_tenders || 0, icon: 'clipboard-list', color: 'success' },
        { title: 'Total Bids', value: overview.total_bids || 0, icon: 'hand-holding-usd', color: 'info' },
        { title: 'Transport Requests', value: overview.total_transport_requests || 0, icon: 'truck', color: 'warning' },
        { title: 'Total Revenue', value: formatCurrency(overview.total_revenue || 0), icon: 'dollar-sign', color: 'success' },
        { title: 'Quality Reports', value: overview.quality_reports || 0, icon: 'exclamation-triangle', color: 'danger' }
    ];
    
    let html = '';
    cards.forEach(card => {
        html += `
            <div class="col-md-2">
                <div class="card bg-${card.color} text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">${card.value}</h4>
                                <small>${card.title}</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-${card.icon} fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Display user statistics
function displayUserStats(stats) {
    const container = document.getElementById('userStats');
    
    if (Object.keys(stats).length === 0) {
        container.innerHTML = '<p class="text-muted">No user statistics available</p>';
        return;
    }
    
    let html = '<div class="row">';
    
    if (stats.by_role) {
        html += '<div class="col-12 mb-3"><h6>Users by Role</h6>';
        stats.by_role.forEach(role => {
            const percentage = (role.count / stats.total * 100).toFixed(1);
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${role.role}</span>
                    <div>
                        <span class="badge bg-primary me-2">${role.count}</span>
                        <small class="text-muted">${percentage}%</small>
                    </div>
                </div>
                <div class="progress mb-3" style="height: 5px;">
                    <div class="progress-bar bg-primary" style="width: ${percentage}%"></div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Display transaction statistics
function displayTransactionStats(stats) {
    const container = document.getElementById('transactionStats');
    
    if (Object.keys(stats).length === 0) {
        container.innerHTML = '<p class="text-muted">No transaction statistics available</p>';
        return;
    }
    
    let html = '<div class="row">';
    
    if (stats.by_provider) {
        html += '<div class="col-12 mb-3"><h6>Transactions by Provider</h6>';
        stats.by_provider.forEach(provider => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${provider.provider}</span>
                    <div>
                        <span class="badge bg-success me-2">${provider.count}</span>
                        <small class="text-muted">${formatCurrency(provider.total)}</small>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Display recent activity
function displayRecentActivity(activity) {
    const container = document.getElementById('recentActivity');
    
    if (activity.length === 0) {
        container.innerHTML = '<p class="text-muted">No recent activity</p>';
        return;
    }
    
    let html = '<div class="timeline">';
    
    activity.forEach(item => {
        const icon = getActivityIcon(item.action);
        const color = getActivityColor(item.action);
        
        html += `
            <div class="d-flex mb-3">
                <div class="flex-shrink-0">
                    <div class="bg-${color} text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-${icon}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="fw-bold">${item.full_name || 'System'}</div>
                    <div class="text-muted small">${formatAction(item.action)}</div>
                    <div class="text-muted small">${timeAgo(item.created_at)}</div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Load trends data
function loadTrendsData() {
    const params = new URLSearchParams({
        action: 'trends',
        type: 'daily',
        period: currentTimeframe
    });
    
    fetch(`<?= BASE_URL ?>/api/analytics.php?${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayTrendsChart(data.trends);
            loadRevenueTrends();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Load revenue trends
function loadRevenueTrends() {
    const params = new URLSearchParams({
        action: 'trends',
        type: 'revenue',
        period: currentTimeframe
    });
    
    fetch(`<?= BASE_URL ?>/api/analytics.php?${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRevenueChart(data.trends);
            loadDistributionData();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Load distribution data
function loadDistributionData() {
    const params = new URLSearchParams({
        action: 'stats',
        type: 'overview',
        timeframe: currentTimeframe
    });
    
    fetch(`<?= BASE_URL ?>/api/analytics.php?${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDistributionChart(data.stats);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Display trends chart
function displayTrendsChart(trends) {
    const ctx = document.getElementById('trendsChart').getContext('2d');
    
    if (charts.trends) {
        charts.trends.destroy();
    }
    
    charts.trends = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trends.map(t => formatDate(t.date)),
            datasets: [
                {
                    label: 'New Users',
                    data: trends.map(t => t.new_users),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'New Tenders',
                    data: trends.map(t => t.new_tenders),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'New Bids',
                    data: trends.map(t => t.new_bids),
                    borderColor: 'rgb(153, 102, 255)',
                    backgroundColor: 'rgba(153, 102, 255, 0.1)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Display revenue chart
function displayRevenueChart(trends) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    if (charts.revenue) {
        charts.revenue.destroy();
    }
    
    charts.revenue = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trends.map(t => formatDate(t.date)),
            datasets: [
                {
                    label: 'Total Revenue',
                    data: trends.map(t => t.revenue),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'Transport Revenue',
                    data: trends.map(t => t.transport_revenue),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}

// Display distribution chart
function displayDistributionChart(stats) {
    const ctx = document.getElementById('distributionChart').getContext('2d');
    
    if (charts.distribution) {
        charts.distribution.destroy();
    }
    
    // Prepare data for pie chart
    const labels = [];
    const data = [];
    const colors = [
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(255, 205, 86)',
        'rgb(75, 192, 192)',
        'rgb(153, 102, 255)',
        'rgb(255, 159, 64)'
    ];
    
    if (stats.users && stats.users.by_role) {
        stats.users.by_role.forEach((item, index) => {
            labels.push(item.role);
            data.push(item.count);
        });
    }
    
    charts.distribution = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
}

// Show export modal
function showExportModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

// Handle export
function handleExport(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('exportForm'));
    const params = new URLSearchParams({
        action: 'export',
        export_type: formData.get('export_type'),
        format: formData.get('format'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date')
    });
    
    const exportUrl = `<?= BASE_URL ?>/api/analytics.php?${params}`;
    
    if (formData.get('format') === 'csv') {
        // Download CSV file
        window.location.href = exportUrl;
    } else {
        // Download JSON file
        fetch(exportUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                downloadJSON(data.data, `${formData.get('export_type')}_export.json`);
            } else {
                alert(data.error || 'Export failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Export failed');
        });
    }
    
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
}

// Helper functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) {
        return 'Just now';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + ' minutes ago';
    } else if (diff < 86400000) {
        return Math.floor(diff / 3600000) + ' hours ago';
    } else {
        return Math.floor(diff / 86400000) + ' days ago';
    }
}

function getActivityIcon(action) {
    const icons = {
        'login': 'sign-in-alt',
        'register': 'user-plus',
        'create_tender': 'clipboard-list',
        'submit_bid': 'hand-holding-usd',
        'accept_transport_request': 'truck',
        'create_transport_request': 'route',
        'payment': 'credit-card',
        'quality_report': 'exclamation-triangle'
    };
    return icons[action] || 'circle';
}

function getActivityColor(action) {
    const colors = {
        'login': 'primary',
        'register': 'success',
        'create_tender': 'info',
        'submit_bid': 'warning',
        'accept_transport_request': 'success',
        'create_transport_request': 'info',
        'payment': 'success',
        'quality_report': 'danger'
    };
    return colors[action] || 'secondary';
}

function formatAction(action) {
    const actions = {
        'login': 'Logged in',
        'register': 'Registered',
        'create_tender': 'Created tender',
        'submit_bid': 'Submitted bid',
        'accept_transport_request': 'Accepted transport request',
        'create_transport_request': 'Created transport request',
        'payment': 'Processed payment',
        'quality_report': 'Submitted quality report'
    };
    return actions[action] || action.replace(/_/g, ' ');
}

function downloadJSON(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function showError(message) {
    const container = document.getElementById('overviewCards');
    container.innerHTML = `
        <div class="col-12">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        </div>
    `;
}
</script>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
