<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || !hasRole('farmer')) {
    redirect(BASE_URL . '/public/login.php');
    exit;
}

// Get tender ID
$tenderId = (int)($_GET['id'] ?? 0);

if (!$tenderId) {
    $_SESSION['flash_error'] = 'Invalid tender ID';
    redirect(BASE_URL . '/public/farmer/dashboard.php');
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get tender details
$tender = $db->fetch("
    SELECT t.*, 
           u.full_name as created_by_name,
           u.email as created_by_email,
           pc.name as category_name
    FROM tenders t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
    WHERE t.id = ?
", [$tenderId]);

if (!$tender) {
    $_SESSION['flash_error'] = 'Tender not found';
    redirect(BASE_URL . '/public/farmer/dashboard.php');
    exit;
}

// Get all bids for this tender with vendor details
$bids = $db->fetchAll("
    SELECT b.*, 
           vp.company_name,
           vp.contact_person,
           vp.phone,
           vp.email,
           u.full_name as vendor_name,
           bs.total_score,
           bs.price_score,
           bs.delivery_score,
           bs.vendor_rating_score,
           bs.experience_score,
           bs.proposal_score
    FROM bids b
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
    LEFT JOIN users u ON vp.user_id = u.id
    LEFT JOIN bid_scores bs ON b.id = bs.bid_id
    WHERE b.tender_id = ?
    ORDER BY bs.total_score DESC, b.created_at DESC
", [$tenderId]);

// Get farmer profile
$farmerProfile = getFarmerProfile(getCurrentUserId());

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Tender Details</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/farmer/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/index.php">Marketplace</a></li>
                        <li class="breadcrumb-item active">Tender Details</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Tender Information -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-gavel me-2"></i><?= htmlspecialchars($tender['title']) ?>
                    </h5>
                    <span class="badge bg-<?= getTenderStatusColor($tender['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $tender['status'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Category</label>
                                <div><?= htmlspecialchars($tender['category_name'] ?? 'General') ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Budget Range</label>
                                <div class="h5 text-primary"><?= formatCurrency($tender['budget_range_min']) ?> - <?= formatCurrency($tender['budget_range_max']) ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Quantity Required</label>
                                <div><?= number_format($tender['quantity']) ?> <?= htmlspecialchars($tender['unit']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Deadline</label>
                                <div><i class="fas fa-calendar-alt me-2"></i><?= formatDate($tender['deadline']) ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Delivery Location</label>
                                <div><?= htmlspecialchars($tender['delivery_location']) ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Posted By</label>
                                <div><?= htmlspecialchars($tender['created_by_name']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Description</label>
                        <div class="border rounded p-3 bg-light">
                            <?= nl2br(htmlspecialchars($tender['description'])) ?>
                        </div>
                    </div>
                    
                    <?php if ($tender['special_requirements']): ?>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Special Requirements</label>
                        <div class="border rounded p-3 bg-light">
                            <?= nl2br(htmlspecialchars($tender['special_requirements'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row text-muted small">
                        <div class="col-md-6">
                            <i class="fas fa-clock me-2"></i>Created: <?= formatDate($tender['created_at']) ?>
                        </div>
                        <div class="col-md-6">
                            <i class="fas fa-edit me-2"></i>Last Updated: <?= formatDate($tender['updated_at']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Bidding Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="h2 text-primary"><?= count($bids) ?></div>
                        <div class="text-muted">Total Bids</div>
                    </div>
                    
                    <?php if (!empty($bids)): ?>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Price Range</label>
                        <div class="d-flex justify-content-between">
                            <span>Min: <?= formatCurrency(min(array_column($bids, 'total_price'))) ?></span>
                            <span>Max: <?= formatCurrency(max(array_column($bids, 'total_price'))) ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted">Average Score</label>
                        <div class="progress" style="height: 20px;">
                            <?php $avgScore = array_sum(array_column($bids, 'total_score')) / count($bids); ?>
                            <div class="progress-bar bg-info" style="width: <?= $avgScore ?>%">
                                <?= number_format($avgScore, 1) ?>%
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tender['status'] === 'open' && time() < strtotime($tender['deadline'])): ?>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        Bidding is still open. Deadline: <?= timeAgo($tender['deadline']) ?>
                    </div>
                    <?php elseif ($tender['status'] === 'closed'): ?>
                    <div class="alert alert-success small">
                        <i class="fas fa-check-circle me-2"></i>
                        Bidding has closed. Tender is under evaluation.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Tender deadline has passed.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bids Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-handshake me-2"></i>Submitted Bids
                <span class="badge bg-secondary ms-2"><?= count($bids) ?></span>
            </h5>
            <div class="btn-group" role="group">
                <button class="btn btn-outline-primary btn-sm" onclick="exportBids()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="printBids()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($bids)): ?>
            <div class="text-center py-5">
                <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No bids submitted yet</h5>
                <p class="text-muted">Vendors haven't submitted any bids for this tender.</p>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($bids as $bid): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($bid['company_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($bid['vendor_name']) ?></small>
                            </div>
                            <div class="text-end">
                                <?php if ($bid['total_score']): ?>
                                <div class="h5 mb-0 text-primary"><?= number_format($bid['total_score'], 1) ?></div>
                                <small class="text-muted">Score</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row small mb-3">
                                <div class="col-6">
                                    <div class="mb-2">
                                        <strong>Contact:</strong> <?= htmlspecialchars($bid['contact_person']) ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Phone:</strong> <?= htmlspecialchars($bid['phone']) ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2">
                                        <strong>Email:</strong> <?= htmlspecialchars($bid['email']) ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Price:</strong> <?= formatCurrency($bid['total_price']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($bid['total_score']): ?>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Score Breakdown</label>
                                <div class="row small">
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span>Price:</span>
                                            <span><?= number_format($bid['price_score'], 1) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Delivery:</span>
                                            <span><?= number_format($bid['delivery_score'], 1) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span>Rating:</span>
                                            <span><?= number_format($bid['vendor_rating_score'], 1) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Experience:</span>
                                            <span><?= number_format($bid['experience_score'], 1) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $bid['total_score'] ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($bid['proposal_text']): ?>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Proposal</label>
                                <div class="border rounded p-2 bg-light small">
                                    <?= nl2br(htmlspecialchars(substr($bid['proposal_text'], 0, 200))) ?>...
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> <?= timeAgo($bid['created_at']) ?>
                                </small>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewBidDetails(<?= $bid['id'] ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    <?php if ($tender['status'] === 'open' && hasRole('admin')): ?>
                                    <button class="btn btn-outline-success btn-sm" onclick="awardBid(<?= $bid['id'] ?>)">
                                        <i class="fas fa-award"></i> Award
                                    </button>
                                    <?php endif; ?>
                                </div>
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

<!-- Bid Details Modal -->
<div class="modal fade" id="bidDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bid Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bidDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function viewBidDetails(bidId) {
    $.ajax({
        url: '<?= BASE_URL ?>/api/bid-details.php',
        method: 'POST',
        data: {
            bid_id: bidId,
            csrf_token: '<?= generateCsrfToken() ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#bidDetailsContent').html(response.html);
                $('#bidDetailsModal').modal('show');
            } else {
                showAlert('error', response.error || 'Failed to load bid details');
            }
        },
        error: function() {
            showAlert('error', 'An error occurred while loading bid details');
        }
    });
}

function exportBids() {
    window.location.href = '<?= BASE_URL ?>/api/export-tender-bids.php?tender_id=<?= $tenderId ?>';
}

function printBids() {
    window.print();
}

function awardBid(bidId) {
    if (confirm('Are you sure you want to award this bid? This action cannot be undone.')) {
        $.ajax({
            url: '<?= BASE_URL ?>/api/award-bid.php',
            method: 'POST',
            data: {
                bid_id: bidId,
                csrf_token: '<?= generateCsrfToken() ?>'
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('error', response.error || 'Failed to award bid');
                }
            },
            error: function() {
                showAlert('error', 'An error occurred while awarding bid');
            }
        });
    }
}
</script>

<style>
@media print {
    .btn-group, .breadcrumb, .modal, .card-header .btn {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    
    .row {
        display: block !important;
    }
    
    .col-lg-6 {
        width: 100% !important;
        margin-bottom: 1rem !important;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
