<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$pageTitle = 'Reports - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    System Reports
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="generateReportBtn">
                        <i class="fas fa-plus me-1"></i>
                        Generate Report
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="scheduleReportBtn">
                        <i class="fas fa-clock me-1"></i>
                        Schedule Reports
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Report Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">Daily</h4>
                            <small>Summary Report</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-light btn-sm" onclick="quickReport('daily')">
                            Generate Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">Weekly</h4>
                            <small>Performance Report</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-light btn-sm" onclick="quickReport('weekly')">
                            Generate Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">Monthly</h4>
                            <small>Analytics Report</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-light btn-sm" onclick="quickReport('monthly')">
                            Generate Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">Custom</h4>
                            <small>Custom Report</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cog fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-light btn-sm" onclick="quickReport('custom')">
                            Generate Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report History -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Generated Reports
                </h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" id="refreshReportsBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="filterReportsBtn">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div id="filterSection" class="row mb-3" style="display: none;">
                <div class="col-md-3">
                    <label for="reportTypeFilter" class="form-label">Report Type</label>
                    <select class="form-select" id="reportTypeFilter">
                        <option value="">All Types</option>
                        <option value="summary">Summary</option>
                        <option value="tenders">Tenders</option>
                        <option value="transport">Transport</option>
                        <option value="payments">Payments</option>
                        <option value="quality">Quality</option>
                        <option value="users">Users</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="generating">Generating</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="dateRangeFilter" class="form-label">Date Range</label>
                    <input type="text" class="form-control" id="dateRangeFilter" placeholder="Select date range">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary" id="applyFilterBtn">Apply</button>
                </div>
            </div>

            <!-- Reports Table -->
            <div id="reportsTable">
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

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="generateReportForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reportName" class="form-label">Report Name *</label>
                                <input type="text" class="form-control" id="reportName" name="report_name" required 
                                       placeholder="Enter report name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reportType" class="form-label">Report Type *</label>
                                <select class="form-select" id="reportType" name="report_type" required>
                                    <option value="">Select type...</option>
                                    <option value="summary">Summary Report</option>
                                    <option value="tenders">Tenders Report</option>
                                    <option value="transport">Transport Report</option>
                                    <option value="payments">Payments Report</option>
                                    <option value="quality">Quality Reports</option>
                                    <option value="users">Users Report</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="startDate" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="startDate" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endDate" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="endDate" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="format" class="form-label">Format *</label>
                                <select class="form-select" id="format" name="format" required>
                                    <option value="pdf">PDF</option>
                                    <option value="csv">CSV</option>
                                    <option value="excel">Excel</option>
                                    <option value="json">JSON</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="delivery" class="form-label">Delivery Method</label>
                                <select class="form-select" id="delivery" name="delivery">
                                    <option value="download">Download</option>
                                    <option value="email">Email</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Report Options</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeCharts" name="include_charts" checked>
                                    <label class="form-check-label" for="includeCharts">
                                        Include Charts
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeDetails" name="include_details" checked>
                                    <label class="form-check-label" for="includeDetails">
                                        Include Detailed Data
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeSummary" name="include_summary" checked>
                                    <label class="form-check-label" for="includeSummary">
                                        Include Summary
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeTrends" name="include_trends">
                                    <label class="form-check-label" for="includeTrends">
                                        Include Trends Analysis
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="emailSection" style="display: none;">
                        <label for="emailRecipients" class="form-label">Email Recipients</label>
                        <textarea class="form-control" id="emailRecipients" name="email_recipients" rows="2"
                                  placeholder="Enter email addresses separated by commas"></textarea>
                        <small class="form-text text-muted">Email addresses of recipients who should receive this report</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-export me-1"></i>
                        Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Automated Reports</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleReportForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="scheduleName" class="form-label">Schedule Name *</label>
                                <input type="text" class="form-control" id="scheduleName" name="schedule_name" required 
                                       placeholder="e.g., Weekly Performance Report">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="frequency" class="form-label">Frequency *</label>
                                <select class="form-select" id="frequency" name="frequency" required>
                                    <option value="">Select frequency...</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Bi-weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="scheduleReportType" class="form-label">Report Type *</label>
                                <select class="form-select" id="scheduleReportType" name="report_type" required>
                                    <option value="">Select type...</option>
                                    <option value="summary">Summary Report</option>
                                    <option value="tenders">Tenders Report</option>
                                    <option value="transport">Transport Report</option>
                                    <option value="payments">Payments Report</option>
                                    <option value="quality">Quality Reports</option>
                                    <option value="users">Users Report</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="scheduleTime" class="form-label">Time *</label>
                                <input type="time" class="form-control" id="scheduleTime" name="schedule_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="scheduleEmailRecipients" class="form-label">Email Recipients *</label>
                        <textarea class="form-control" id="scheduleEmailRecipients" name="email_recipients" rows="2" required
                                  placeholder="Enter email addresses separated by commas"></textarea>
                        <small class="form-text text-muted">Email addresses of recipients who should receive scheduled reports</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Schedule Options</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="autoEmail" name="auto_email" checked>
                                    <label class="form-check-label" for="autoEmail">
                                        Auto Email Report
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="saveReport" name="save_report" checked>
                                    <label class="form-check-label" for="saveReport">
                                        Save Report to System
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                                    <label class="form-check-label" for="active">
                                        Active Schedule
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notifyOnFailure" name="notify_on_failure">
                                    <label class="form-check-label" for="notifyOnFailure">
                                        Notify on Failure
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-clock me-1"></i>
                        Create Schedule
                    </button>
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
                <button type="button" class="btn btn-primary" id="downloadReportBtn">
                    <i class="fas fa-download me-1"></i>
                    Download
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentReportId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadReports();
    
    // Event listeners
    document.getElementById('generateReportBtn').addEventListener('click', showGenerateReportModal);
    document.getElementById('scheduleReportBtn').addEventListener('click', showScheduleReportModal);
    document.getElementById('refreshReportsBtn').addEventListener('click', loadReports);
    document.getElementById('filterReportsBtn').addEventListener('click', toggleFilterSection);
    document.getElementById('applyFilterBtn').addEventListener('click', applyFilters);
    
    // Form submissions
    document.getElementById('generateReportForm').addEventListener('submit', handleGenerateReport);
    document.getElementById('scheduleReportForm').addEventListener('submit', handleScheduleReport);
    
    // Delivery method change
    document.getElementById('delivery').addEventListener('change', function() {
        const emailSection = document.getElementById('emailSection');
        emailSection.style.display = this.value === 'email' || this.value === 'both' ? 'block' : 'none';
    });
    
    // Set default dates
    const endDate = new Date();
    const startDate = new Date(endDate.getTime() - (30 * 24 * 60 * 60 * 1000));
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
});

// Load reports
function loadReports() {
    fetch('<?= BASE_URL ?>/api/analytics.php?action=reports&report_type=summary')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayReports(data.report.details || []);
        } else {
            showError(data.error || 'Failed to load reports');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load reports');
    });
}

// Display reports
function displayReports(reports) {
    const container = document.getElementById('reportsTable');
    
    if (reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No reports generated yet</p>
                <button type="button" class="btn btn-primary" onclick="showGenerateReportModal()">
                    <i class="fas fa-plus me-1"></i>
                    Generate Your First Report
                </button>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += '<thead><tr><th>Name</th><th>Type</th><th>Period</th><th>Format</th><th>Status</th><th>Generated</th><th>Actions</th></tr></thead><tbody>';
    
    // Mock reports data for demonstration
    const mockReports = [
        {
            id: 1,
            name: 'Monthly Performance Report',
            type: 'summary',
            period: '2024-10-01 to 2024-10-31',
            format: 'PDF',
            status: 'completed',
            generated_at: '2024-11-01 09:00:00',
            file_size: '2.4 MB'
        },
        {
            id: 2,
            name: 'Transport Analytics',
            type: 'transport',
            period: '2024-10-01 to 2024-10-31',
            format: 'Excel',
            status: 'completed',
            generated_at: '2024-11-01 10:30:00',
            file_size: '1.8 MB'
        },
        {
            id: 3,
            name: 'Quality Reports Summary',
            type: 'quality',
            period: '2024-10-01 to 2024-10-31',
            format: 'CSV',
            status: 'generating',
            generated_at: '2024-11-01 11:15:00',
            file_size: '-'
        }
    ];
    
    mockReports.forEach(report => {
        const statusBadge = getStatusBadge(report.status);
        const typeBadge = getTypeBadge(report.type);
        
        html += `
            <tr>
                <td>
                    <div class="fw-bold">${report.name}</div>
                    <small class="text-muted">${report.file_size}</small>
                </td>
                <td>${typeBadge}</td>
                <td>${report.period}</td>
                <td>${report.format}</td>
                <td>${statusBadge}</td>
                <td>
                    <div>${formatDateTime(report.generated_at)}</div>
                    <small class="text-muted">${timeAgo(report.generated_at)}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="viewReport(${report.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${report.status === 'completed' ? `
                            <button type="button" class="btn btn-outline-success" onclick="downloadReport(${report.id})">
                                <i class="fas fa-download"></i>
                            </button>
                        ` : ''}
                        <button type="button" class="btn btn-outline-danger" onclick="deleteReport(${report.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Quick report generation
function quickReport(type) {
    const startDate = new Date();
    const endDate = new Date();
    
    switch (type) {
        case 'daily':
            startDate.setDate(startDate.getDate() - 1);
            break;
        case 'weekly':
            startDate.setDate(startDate.getDate() - 7);
            break;
        case 'monthly':
            startDate.setMonth(startDate.getMonth() - 1);
            break;
        case 'custom':
            showGenerateReportModal();
            return;
    }
    
    document.getElementById('reportName').value = `${type.charAt(0).toUpperCase() + type.slice(1)} Report`;
    document.getElementById('reportType').value = 'summary';
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
    
    showGenerateReportModal();
}

// Show generate report modal
function showGenerateReportModal() {
    const modal = new bootstrap.Modal(document.getElementById('generateReportModal'));
    modal.show();
}

// Show schedule report modal
function showScheduleReportModal() {
    const modal = new bootstrap.Modal(document.getElementById('scheduleReportModal'));
    modal.show();
}

// Toggle filter section
function toggleFilterSection() {
    const filterSection = document.getElementById('filterSection');
    filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
}

// Apply filters
function applyFilters() {
    // In a real implementation, this would filter the reports
    loadReports();
}

// Handle generate report
function handleGenerateReport(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('generateReportForm'));
    
    // Simulate report generation
    const reportData = {
        name: formData.get('report_name'),
        type: formData.get('report_type'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        format: formData.get('format'),
        delivery: formData.get('delivery')
    };
    
    // Show progress
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
    submitBtn.disabled = true;
    
    // Simulate generation delay
    setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('generateReportModal')).hide();
        alert('Report generated successfully! Check your reports list.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        loadReports();
    }, 2000);
}

// Handle schedule report
function handleScheduleReport(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('scheduleReportForm'));
    
    // Simulate schedule creation
    const scheduleData = {
        name: formData.get('schedule_name'),
        frequency: formData.get('frequency'),
        report_type: formData.get('report_type'),
        schedule_time: formData.get('schedule_time'),
        email_recipients: formData.get('email_recipients')
    };
    
    // Show progress
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating...';
    submitBtn.disabled = true;
    
    // Simulate creation delay
    setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('scheduleReportModal')).hide();
        alert('Report schedule created successfully!');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 1500);
}

// View report details
function viewReport(reportId) {
    currentReportId = reportId;
    
    // Mock report details
    const reportDetails = {
        name: 'Monthly Performance Report',
        type: 'summary',
        period: '2024-10-01 to 2024-10-31',
        format: 'PDF',
        status: 'completed',
        generated_at: '2024-11-01 09:00:00',
        file_size: '2.4 MB',
        description: 'Comprehensive monthly performance report including user statistics, tender analytics, transport metrics, and financial summaries.',
        metrics: {
            total_users: 1250,
            new_users: 89,
            active_tenders: 45,
            completed_transports: 234,
            total_revenue: '$45,678',
            quality_reports: 12
        }
    };
    
    let html = `
        <div class="row">
            <div class="col-md-8">
                <h6>Report Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td width="25%">Name:</td><td>${reportDetails.name}</td></tr>
                    <tr><td>Type:</td><td>${getTypeBadge(reportDetails.type)}</td></tr>
                    <tr><td>Period:</td><td>${reportDetails.period}</td></tr>
                    <tr><td>Format:</td><td>${reportDetails.format}</td></tr>
                    <tr><td>Status:</td><td>${getStatusBadge(reportDetails.status)}</td></tr>
                    <tr><td>Generated:</td><td>${formatDateTime(reportDetails.generated_at)}</td></tr>
                    <tr><td>File Size:</td><td>${reportDetails.file_size}</td></tr>
                </table>
                
                <h6 class="mt-3">Description</h6>
                <p>${reportDetails.description}</p>
                
                <h6 class="mt-3">Key Metrics</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${reportDetails.metrics.total_users}</h4>
                                <small>Total Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${reportDetails.metrics.active_tenders}</h4>
                                <small>Active Tenders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${reportDetails.metrics.total_revenue}</h4>
                                <small>Total Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <h6>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" onclick="downloadReport(${reportId})">
                        <i class="fas fa-download me-1"></i>
                        Download Report
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="shareReport(${reportId})">
                        <i class="fas fa-share me-1"></i>
                        Share Report
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="regenerateReport(${reportId})">
                        <i class="fas fa-redo me-1"></i>
                        Regenerate
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('reportDetailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('reportDetailsModal')).show();
}

// Download report
function downloadReport(reportId) {
    // Simulate download
    alert('Downloading report...');
    // In a real implementation, this would trigger a file download
}

// Share report
function shareReport(reportId) {
    // Simulate share functionality
    const shareUrl = `${window.location.origin}/reports/${reportId}`;
    if (navigator.share) {
        navigator.share({
            title: 'Report Share',
            text: 'Check out this report',
            url: shareUrl
        });
    } else {
        navigator.clipboard.writeText(shareUrl);
        alert('Report link copied to clipboard!');
    }
}

// Regenerate report
function regenerateReport(reportId) {
    if (confirm('Regenerate this report? This may take a few minutes.')) {
        alert('Report regeneration started...');
        loadReports();
    }
}

// Delete report
function deleteReport(reportId) {
    if (confirm('Delete this report? This action cannot be undone.')) {
        alert('Report deleted successfully!');
        loadReports();
    }
}

// Helper functions
function getStatusBadge(status) {
    const badges = {
        'generating': '<span class="badge bg-warning">Generating</span>',
        'completed': '<span class="badge bg-success">Completed</span>',
        'failed': '<span class="badge bg-danger">Failed</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getTypeBadge(type) {
    const badges = {
        'summary': '<span class="badge bg-primary">Summary</span>',
        'tenders': '<span class="badge bg-info">Tenders</span>',
        'transport': '<span class="badge bg-success">Transport</span>',
        'payments': '<span class="badge bg-warning">Payments</span>',
        'quality': '<span class="badge bg-danger">Quality</span>',
        'users': '<span class="badge bg-secondary">Users</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
}

function formatDateTime(dateTime) {
    const date = new Date(dateTime);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function timeAgo(dateTime) {
    const date = new Date(dateTime);
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

function showError(message) {
    const container = document.getElementById('reportsTable');
    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
