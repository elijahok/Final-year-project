<?php
// Include configuration and functions
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is farmer
if (!isLoggedIn() || !hasRole('farmer')) {
    redirect(BASE_URL . '/public/login.php');
}

// Initialize database
$db = Database::getInstance();

// Get farmer profile
$farmer = $db->fetch("
    SELECT fp.*, u.full_name, u.email, u.phone
    FROM farmer_profiles fp
    JOIN users u ON fp.user_id = u.id
    WHERE fp.user_id = ?
", [getCurrentUser()['id']]);

if (!$farmer) {
    setFlashMessage('Farmer profile not found', 'danger');
    redirect(BASE_URL . '/public/profile.php');
}

// Get farmer statistics
$stats = [
    'active_requests' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE farmer_id = ? AND status IN ('pending', 'accepted')
    ", [$farmer['id']])['count'],
    
    'completed_today' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE farmer_id = ? AND status = 'completed' 
        AND DATE(completed_at) = CURDATE()
    ", [$farmer['id']])['count'],
    
    'total_completed' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE farmer_id = ? AND status = 'completed'
    ", [$farmer['id']])['count'],
    
    'total_spent' => $db->fetch("
        SELECT COALESCE(SUM(fee), 0) as total
        FROM transport_requests
        WHERE farmer_id = ? AND status = 'completed'
    ", [$farmer['id']])['total'],
    
    'pending_bids' => $db->fetch("
        SELECT COUNT(*) as count
        FROM bids b
        JOIN tenders t ON b.tender_id = t.id
        WHERE b.vendor_id = ? AND b.status = 'submitted' AND t.status = 'open'
    ", [$farmer['id']])['count']
];

// Get active transport requests
$activeRequests = $db->fetchAll("
    SELECT tr.*, u.full_name as transporter_name, u.phone as transporter_phone,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN users u ON tr.transporter_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.farmer_id = ? AND tr.status IN ('pending', 'accepted')
    ORDER BY tr.created_at DESC
    LIMIT 5
", [$farmer['id']]);

// Get recent completed requests
$recentCompleted = $db->fetchAll("
    SELECT tr.*, u.full_name as transporter_name,
           pc.name as produce_category, tr.rating as transporter_rating
    FROM transport_requests tr
    LEFT JOIN users u ON tr.transporter_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.farmer_id = ? AND tr.status = 'completed'
    ORDER BY tr.completed_at DESC
    LIMIT 5
", [$farmer['id']]);

// Get available tenders for farmers
$availableTenders = $db->fetchAll("
    SELECT t.*, pc.name as produce_category
    FROM tenders t
    LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
    WHERE t.status = 'open' AND t.deadline > NOW()
    ORDER BY t.created_at DESC
    LIMIT 5
");

// Get farmer's recent bids
$recentBids = $db->fetchAll("
    SELECT b.*, t.title as tender_title, t.tender_number, t.status as tender_status
    FROM bids b
    JOIN tenders t ON b.tender_id = t.id
    WHERE b.vendor_id = ?
    ORDER BY b.submitted_at DESC
    LIMIT 5
", [$farmer['id']]);

// Get wallet balance
$wallet = $db->fetch("
    SELECT balance, last_updated
    FROM user_wallets
    WHERE user_id = ?
", [getCurrentUser()['id']]);

// Get quality reports
$qualityReports = $db->fetchAll("
    SELECT qr.*, pc.name as produce_category
    FROM quality_reports qr
    LEFT JOIN produce_categories pc ON qr.produce_category_id = pc.id
    WHERE qr.farmer_id = ?
    ORDER BY qr.created_at DESC
    LIMIT 3
", [$farmer['id']]);

$pageTitle = 'Farmer Dashboard';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Farmer Dashboard</h1>
                <div>
                    <a href="<?= BASE_URL ?>/public/farmer/request-transport.php" class="btn btn-primary me-2">
                        <i class="fas fa-truck me-2"></i>Request Transport
                    </a>
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">Welcome back, <?= htmlspecialchars($farmer['full_name']) ?>!</h4>
                            <p class="mb-0">
                                <i class="fas fa-seedling me-2"></i>
                                <?= htmlspecialchars($farmer['farm_name']) ?> • <?= htmlspecialchars($farmer['farm_location']) ?>
                                <?php if ($farmer['organic_certified']): ?>
                                • <span class="badge bg-light text-success">Organic Certified</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="wallet-display">
                                <i class="fas fa-wallet"></i>
                                <span class="h5"><?= formatCurrency($wallet['balance'] ?? 0) ?></span>
                                <small class="ms-1">Balance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['active_requests'] ?></h4>
                            <p class="mb-0">Active Requests</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-truck-loading fa-2x"></i>
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
                            <h4 class="mb-0"><?= $stats['completed_today'] ?></h4>
                            <p class="mb-0">Completed Today</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['total_completed'] ?></h4>
                            <p class="mb-0">Total Completed</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-history fa-2x"></i>
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
                            <h4 class="mb-0"><?= formatCurrency($stats['total_spent']) ?></h4>
                            <p class="mb-0">Total Spent</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="<?= BASE_URL ?>/public/farmer/request-transport.php" class="btn btn-success w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-truck fa-2x mb-2"></i>
                                Request Transport
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= BASE_URL ?>/public/farmer/quality-report.php" class="btn btn-info w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                                Quality Report
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= BASE_URL ?>/public/farmer/emergency-report.php" class="btn btn-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                Emergency Report
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= BASE_URL ?>/public/farmer/wallet.php" class="btn btn-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-wallet fa-2x mb-2"></i>
                                My Wallet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Requests and Available Tenders -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Active Transport Requests</h5>
                    <a href="<?= BASE_URL ?>/public/farmer/transport-requests.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeRequests)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-truck fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No active transport requests</p>
                        <a href="<?= BASE_URL ?>/public/farmer/request-transport.php" class="btn btn-primary btn-sm mt-2">
                            Create Request
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activeRequests as $request): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($request['pickup_location']) ?>
                                        → <?= htmlspecialchars($request['delivery_location']) ?>
                                        <?php if ($request['transporter_name']): ?>
                                        <br><i class="fas fa-user me-1"></i><?= htmlspecialchars($request['transporter_name']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-<?= getStatusColor($request['status']) ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                        <span class="badge bg-info">
                                            <?= formatCurrency($request['fee']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= formatDate($request['created_at']) ?></small>
                                    <div class="btn-group btn-group-sm mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="viewRequest(<?= $request['id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($request['status'] === 'completed'): ?>
                                        <button class="btn btn-success btn-sm" onclick="rateTransporter(<?= $request['id'] ?>)">
                                            <i class="fas fa-star"></i> Rate
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Available Tenders</h5>
                    <a href="<?= BASE_URL ?>/public/vendor/tenders.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($availableTenders)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-file-contract fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No available tenders</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($availableTenders as $tender): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($tender['title']) ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <?= htmlspecialchars($tender['produce_category'] ?? 'N/A') ?>
                                        <br><?= htmlspecialchars($tender['tender_number']) ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-primary">
                                            <?= formatCurrency($tender['budget_min']) ?> - <?= formatCurrency($tender['budget_max']) ?>
                                        </span>
                                        <span class="badge bg-warning">
                                            <?= formatDate($tender['deadline']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= formatDate($tender['created_at']) ?></small>
                                    <div class="mt-2">
                                        <a href="<?= BASE_URL ?>/public/vendor/bid-submission.php?tender=<?= $tender['id'] ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-bid"></i> Bid
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Deliveries</h5>
                    <a href="<?= BASE_URL ?>/public/farmer/transport-history.php" class="btn btn-sm btn-outline-primary">
                        View History
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentCompleted)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-history fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No completed deliveries yet</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transporter</th>
                                    <th>Category</th>
                                    <th>Fee</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCompleted as $request): ?>
                                <tr>
                                    <td>
                                        <div><?= formatDate($request['completed_at']) ?></div>
                                        <small class="text-muted"><?= formatTime($request['completed_at']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($request['transporter_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($request['produce_category'] ?? 'N/A') ?></td>
                                    <td><?= formatCurrency($request['fee']) ?></td>
                                    <td>
                                        <?php if ($request['transporter_rating']): ?>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <?= number_format($request['transporter_rating'], 1) ?>
                                        </div>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="rateTransporter(<?= $request['id'] ?>)">
                                            Rate
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quality Reports</h5>
                    <a href="<?= BASE_URL ?>/public/farmer/quality-reports.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($qualityReports)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clipboard-check fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No quality reports</p>
                        <a href="<?= BASE_URL ?>/public/farmer/quality-report.php" class="btn btn-info btn-sm mt-2">
                            Create Report
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($qualityReports as $report): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= ucfirst(str_replace('_', ' ', $report['report_type'])) ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <?= htmlspecialchars($report['produce_category'] ?? 'N/A') ?>
                                        <?php if ($report['description']): ?>
                                        <br><?= htmlspecialchars(substr($report['description'], 0, 50)) ?>...
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-<?= getSeverityColor($report['severity']) ?>">
                                            <?= ucfirst($report['severity']) ?>
                                        </span>
                                        <span class="badge bg-<?= getStatusColor($report['status']) ?>">
                                            <?= ucfirst($report['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= formatDate($report['created_at']) ?></small>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?= $report['id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Notifications</h5>
                </div>
                <div class="card-body">
                    <?php
                    $notifications = $db->fetchAll("
                        SELECT * FROM notifications
                        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY created_at DESC
                        LIMIT 5
                    ", [getCurrentUser()['id']]);
                    ?>
                    
                    <?php if (empty($notifications)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-bell fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No recent notifications</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($notification['message']) ?></div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= timeAgo($notification['created_at']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Transport Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transport Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestDetailsBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="rateTransporterBtn" onclick="rateTransporterFromModal()">
                    <i class="fas fa-star me-2"></i>Rate Transporter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transporter Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Transporter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ratingForm">
                <div class="modal-body">
                    <input type="hidden" id="ratingRequestId" name="request_id">
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star fa-2x text-muted" data-rating="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" id="ratingValue" name="rating" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comment (optional)</label>
                        <textarea class="form-control" id="ratingComment" name="comment" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-star me-2"></i>Submit Rating
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.wallet-display {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

.rating-stars {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.rating-stars i {
    cursor: pointer;
    transition: color 0.2s;
}

.rating-stars i:hover {
    color: #ffc107 !important;
}

.rating-stars i.active {
    color: #ffc107 !important;
}

.list-group-item {
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    border-left-color: #28a745;
    background-color: #f8f9fa;
}
</style>

<script>
let currentRequestId = null;
let selectedRating = 0;

function viewRequest(requestId) {
    currentRequestId = requestId;
    const modal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
    const modalBody = document.getElementById('requestDetailsBody');
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch(`<?= BASE_URL ?>/api/transport-request-details.php?id=${requestId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = data.html;
            
            // Show/hide rate button based on request status
            const rateBtn = document.getElementById('rateTransporterBtn');
            if (data.request_status === 'completed' && !data.rating_given) {
                rateBtn.style.display = 'inline-block';
            } else {
                rateBtn.style.display = 'none';
            }
            
            modal.show();
        } else {
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load request details</div>';
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading request details</div>';
    });
}

function rateTransporter(requestId) {
    currentRequestId = requestId;
    document.getElementById('ratingRequestId').value = requestId;
    document.getElementById('ratingValue').value = '';
    document.getElementById('ratingComment').value = '';
    selectedRating = 0;
    
    // Reset rating stars
    document.querySelectorAll('.rating-stars i').forEach(star => {
        star.classList.remove('active');
    });
    
    const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
    modal.show();
}

function rateTransporterFromModal() {
    if (currentRequestId) {
        rateTransporter(currentRequestId);
        
        // Close details modal
        const detailsModal = bootstrap.Modal.getInstance(document.getElementById('requestDetailsModal'));
        if (detailsModal) {
            detailsModal.hide();
        }
    }
}

function setRating(rating) {
    selectedRating = rating;
    document.getElementById('ratingValue').value = rating;
    
    // Update star display
    document.querySelectorAll('.rating-stars i').forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

// Handle rating form submission
document.getElementById('ratingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/rate-transporter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Rating submitted successfully!', 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('ratingModal'));
            modal.hide();
            
            // Refresh dashboard
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.error || 'Failed to submit rating', 'danger');
        }
    })
    .catch(error => {
        showNotification('Error submitting rating', 'danger');
    });
});

function viewReport(reportId) {
    window.open(`<?= BASE_URL ?>/public/farmer/quality-report-details.php?id=${reportId}`, '_blank');
}

function refreshDashboard() {
    location.reload();
}

// Auto-refresh dashboard every 2 minutes
setInterval(() => {
    if (!document.hidden) {
        fetch('<?= BASE_URL ?>/api/farmer-dashboard-stats.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update statistics
                updateStats(data.stats);
            }
        })
        .catch(error => {
            console.error('Error refreshing dashboard:', error);
        });
    }
}, 120000);

function updateStats(stats) {
    // Update statistics cards
    document.querySelectorAll('.card-body h4').forEach((element, index) => {
        if (index < 4) {
            const values = [
                stats.active_requests,
                stats.completed_today,
                stats.total_completed,
                formatCurrency(stats.total_spent)
            ];
            element.textContent = values[index];
        }
    });
    
    // Update wallet balance
    const walletDisplay = document.querySelector('.wallet-display .h5');
    if (walletDisplay && stats.wallet_balance !== undefined) {
        walletDisplay.textContent = formatCurrency(stats.wallet_balance);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
