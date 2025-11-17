<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || getCurrentUser()['role'] !== 'farmer') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$pageTitle = 'Quality Reports - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Quality Reports
                </h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReportModal">
                    <i class="fas fa-plus me-2"></i>
                    New Report
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="totalReports">-</h4>
                            <small>Total Reports</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="openReports">-</h4>
                            <small>Open Reports</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="resolvedReports">-</h4>
                            <small>Resolved</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="criticalReports">-</h4>
                            <small>Critical</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select" id="statusFilter" name="status">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="investigating">Investigating</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="typeFilter" class="form-label">Type</label>
                    <select class="form-select" id="typeFilter" name="type">
                        <option value="">All Types</option>
                        <option value="produce_quality">Produce Quality</option>
                        <option value="delivery_delay">Delivery Delay</option>
                        <option value="service_issue">Service Issue</option>
                        <option value="payment_issue">Payment Issue</option>
                        <option value="safety_concern">Safety Concern</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priorityFilter" class="form-label">Priority</label>
                    <select class="form-select" id="priorityFilter" name="priority">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchFilter" class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchFilter" name="search" placeholder="Search reports...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Apply Filters
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                        <i class="fas fa-times me-1"></i>
                        Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Your Quality Reports
            </h5>
        </div>
        <div class="card-body">
            <div id="reportsList">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading reports...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Report Modal -->
<div class="modal fade" id="createReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Quality Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createReportForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="transportRequestId" class="form-label">Related Transport Request (Optional)</label>
                                <select class="form-select" id="transportRequestId" name="transport_request_id">
                                    <option value="">Select transport request...</option>
                                </select>
                                <small class="form-text text-muted">Select if this report is related to a specific transport</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reportType" class="form-label">Report Type *</label>
                                <select class="form-select" id="reportType" name="report_type" required>
                                    <option value="">Select type...</option>
                                    <option value="produce_quality">Produce Quality</option>
                                    <option value="delivery_delay">Delivery Delay</option>
                                    <option value="service_issue">Service Issue</option>
                                    <option value="payment_issue">Payment Issue</option>
                                    <option value="safety_concern">Safety Concern</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="">Select priority...</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="Brief description of the issue">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required
                                  placeholder="Provide detailed information about the issue..."></textarea>
                        <small class="form-text text-muted">Please be as specific as possible</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let isLoading = false;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadReports();
    loadStats();
    loadTransportRequests();
    
    // Event listeners
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadReports();
    });
    
    document.getElementById('resetFilters').addEventListener('click', function() {
        document.getElementById('filterForm').reset();
        currentPage = 1;
        loadReports();
    });
    
    document.getElementById('createReportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        createReport();
    });
});

// Load reports
function loadReports() {
    if (isLoading) return;
    isLoading = true;
    
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('page', currentPage);
    formData.append('limit', 20);
    
    fetch('<?= BASE_URL ?>/api/quality-reports.php?action=list', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayReports(data.reports, data.pagination);
        } else {
            showError(data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load reports');
    })
    .finally(() => {
        isLoading = false;
    });
}

// Load statistics
function loadStats() {
    fetch('<?= BASE_URL ?>/api/quality-reports.php?action=stats&timeframe=30')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalReports').textContent = data.stats.total_reports;
            document.getElementById('openReports').textContent = data.stats.open_reports;
            document.getElementById('resolvedReports').textContent = data.stats.resolved_reports;
            document.getElementById('criticalReports').textContent = data.stats.critical_reports;
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Load transport requests
function loadTransportRequests() {
    fetch('<?= BASE_URL ?>/api/transport-requests.php?action=list&status=completed')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('transportRequestId');
            select.innerHTML = '<option value="">Select transport request...</option>';
            
            data.requests.forEach(request => {
                const option = document.createElement('option');
                option.value = request.id;
                option.textContent = `#${request.id} - ${request.pickup_location} to ${request.delivery_location}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Display reports
function displayReports(reports, pagination) {
    const container = document.getElementById('reportsList');
    
    if (reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No quality reports found</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += '<thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Priority</th><th>Status</th><th>Transporter</th><th>Created</th><th>Actions</th></tr></thead><tbody>';
    
    reports.forEach(report => {
        html += `
            <tr>
                <td>#${report.id}</td>
                <td>
                    <div class="fw-bold">${report.title}</div>
                    <small class="text-muted">${report.description.substring(0, 100)}...</small>
                </td>
                <td>${report.type_badge}</td>
                <td>${report.priority_badge}</td>
                <td>${report.status_badge}</td>
                <td>${report.related_transporter_name || '-'}</td>
                <td>
                    <div>${report.created_at}</div>
                    <small class="text-muted">${timeAgo(report.created_at)}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="viewReport(${report.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${report.status === 'open' ? `
                            <button type="button" class="btn btn-outline-success" onclick="respondToReport(${report.id})">
                                <i class="fas fa-reply"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    
    // Add pagination
    if (pagination.total_pages > 1) {
        html += '<nav><ul class="pagination justify-content-center">';
        
        if (pagination.has_prev) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Previous</a></li>`;
        }
        
        for (let i = 1; i <= pagination.total_pages; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }
        
        if (pagination.has_next) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">Next</a></li>`;
        }
        
        html += '</ul></nav>';
    }
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadReports();
}

// Create report
function createReport() {
    const formData = new FormData(document.getElementById('createReportForm'));
    formData.append('action', 'create');
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/quality-reports.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Quality report submitted successfully!');
            bootstrap.Modal.getInstance(document.getElementById('createReportModal')).hide();
            document.getElementById('createReportForm').reset();
            loadReports();
            loadStats();
        } else {
            alert(data.error || 'Failed to submit report');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit report');
    });
}

// View report details
function viewReport(reportId) {
    fetch(`<?= BASE_URL ?>/api/quality-reports.php?action=details&id=${reportId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayReportDetails(data.report, data.responses);
        } else {
            alert(data.error || 'Failed to load report details');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load report details');
    });
}

// Display report details
function displayReportDetails(report, responses) {
    let html = `
        <div class="row">
            <div class="col-md-8">
                <h6>Report Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td width="20%">ID:</td><td>#${report.id}</td></tr>
                    <tr><td>Title:</td><td>${report.title}</td></tr>
                    <tr><td>Type:</td><td>${report.type_badge}</td></tr>
                    <tr><td>Priority:</td><td>${report.priority_badge}</td></tr>
                    <tr><td>Status:</td><td>${report.status_badge}</td></tr>
                    <tr><td>Created:</td><td>${report.created_at}</td></tr>
                </table>
                
                <h6 class="mt-3">Description</h6>
                <div class="alert alert-info">${report.description}</div>
                
                ${report.resolution ? `
                    <h6 class="mt-3">Resolution</h6>
                    <div class="alert alert-success">${report.resolution}</div>
                ` : ''}
            </div>
            <div class="col-md-4">
                ${report.related_transporter_name ? `
                    <h6>Related Transporter</h6>
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-bold">${report.related_transporter_name}</div>
                            <small class="text-muted">Transport Request #${report.transport_request_id}</small>
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
        
        <h6 class="mt-3">Responses</h6>
        <div class="responses">
    `;
    
    if (responses.length === 0) {
        html += '<p class="text-muted">No responses yet</p>';
    } else {
        responses.forEach(response => {
            html += `
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-bold">${response.responder_name} ${response.responder_role_badge}</div>
                                <div class="mt-2">${response.response}</div>
                            </div>
                            <small class="text-muted">${response.created_at}</small>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += '</div>';
    
    document.getElementById('reportDetailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('reportDetailsModal')).show();
}

// Respond to report
function respondToReport(reportId) {
    const response = prompt('Enter your response:');
    if (!response) return;
    
    const formData = new FormData();
    formData.append('action', 'respond');
    formData.append('report_id', reportId);
    formData.append('response', response);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/quality-reports.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Response added successfully!');
            viewReport(reportId);
        } else {
            alert(data.error || 'Failed to add response');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add response');
    });
}

// Helper functions
function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const now = Date.now();
    const diff = now - time;
    
    if (diff < 60000) {
        return 'Just now';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + ' minutes ago';
    } else if (diff < 86400000) {
        return Math.floor(diff / 3600000) + ' hours ago';
    } else if (diff < 604800000) {
        return Math.floor(diff / 86400000) + ' days ago';
    } else {
        return new Date(time).toLocaleDateString();
    }
}

function showError(message) {
    const container = document.getElementById('reportsList');
    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
