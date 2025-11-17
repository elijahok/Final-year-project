<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Get bid ID
$bidId = (int)($_GET['id'] ?? 0);

if (!$bidId) {
    echo json_encode(['success' => false, 'error' => 'Bid ID is required']);
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get bid details with related information
$bid = $db->fetch("
    SELECT b.*, t.title as tender_title, t.tender_number, t.description as tender_description,
           t.budget_min, t.budget_max, t.quantity, t.unit, t.deadline as tender_deadline,
           t.status as tender_status, t.category_id,
           vp.company_name, vp.rating as vendor_rating, vp.experience_years,
           vp.company_address, vp.company_phone, vp.company_email,
           u.full_name as vendor_name, u.email as vendor_email, u.phone as vendor_phone,
           pc.name as category_name
    FROM bids b
    JOIN tenders t ON b.tender_id = t.id
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
    LEFT JOIN users u ON b.vendor_id = u.id
    LEFT JOIN produce_categories pc ON t.category_id = pc.id
    WHERE b.id = ?
", [$bidId]);

if (!$bid) {
    echo json_encode(['success' => false, 'error' => 'Bid not found']);
    exit;
}

// Get bid score breakdown
$scoreBreakdown = json_decode($bid['score_breakdown'] ?? '{}', true);

// Get bid attachments
$attachments = $db->fetchAll("
    SELECT * FROM bid_attachments WHERE bid_id = ? ORDER BY uploaded_at DESC
", [$bidId]);

// Get vendor history (previous bids and awards)
$vendorHistory = $db->fetchAll("
    SELECT b.id, b.amount, b.status, b.submitted_at, b.bid_score,
           t.title as tender_title, t.tender_number, t.status as tender_status
    FROM bids b
    JOIN tenders t ON b.tender_id = t.id
    WHERE b.vendor_id = ? AND b.id != ?
    ORDER BY b.submitted_at DESC
    LIMIT 5
", [$bid['vendor_id'], $bidId]);

// Get audit logs for this bid
$auditLogs = $db->fetchAll("
    SELECT al.*, u.full_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.target_type = 'bid' AND al.target_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
", [$bidId]);

// Get tender comparison (other bids for same tender)
$otherBids = $db->fetchAll("
    SELECT b.id, b.amount, b.bid_score, b.status, b.submitted_at,
           vp.company_name
    FROM bids b
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
    WHERE b.tender_id = ? AND b.id != ?
    ORDER BY b.bid_score DESC, b.amount ASC
", [$bid['tender_id'], $bidId]);

// Generate HTML content
ob_start();
?>

<div class="row">
    <!-- Bid Summary -->
    <div class="col-md-8">
        <h5 class="mb-3">Bid Summary</h5>
        
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><?= htmlspecialchars($bid['tender_title']) ?></h6>
                        <p class="text-muted mb-2"><?= htmlspecialchars($bid['tender_description']) ?></p>
                        
                        <div class="row">
                            <div class="col-6">
                                <strong>Bid Amount:</strong><br>
                                <span class="h5 text-primary"><?= formatCurrency($bid['amount']) ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Delivery Timeline:</strong><br>
                                <span class="h5"><?= $bid['delivery_timeline'] ?> days</span>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-6">
                                <strong>Submitted:</strong><br>
                                <?= formatDate($bid['submitted_at']) ?>
                            </div>
                            <div class="col-6">
                                <strong>Status:</strong><br>
                                <span class="badge bg-<?= getStatusColor($bid['status']) ?>">
                                    <?= ucfirst($bid['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center">
                            <div class="bid-score-circle <?= getScoreColor($bid['bid_score']) ?>">
                                <div class="score-value"><?= number_format($bid['bid_score'], 1) ?></div>
                                <div class="score-label">Total Score</div>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-<?= getGradeColor($bid['bid_score']) ?> fs-6">
                                    <?= getScoreGrade($bid['bid_score']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Proposal -->
        <h5 class="mb-3">Proposal Details</h5>
        <div class="card mb-3">
            <div class="card-body">
                <div class="proposal-content">
                    <?= nl2br(htmlspecialchars($bid['proposal'])) ?>
                </div>
            </div>
        </div>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <h5 class="mb-3">Attachments</h5>
        <div class="card mb-3">
            <div class="card-body">
                <?php foreach ($attachments as $attachment): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <i class="fas fa-file-alt me-2"></i>
                        <a href="<?= BASE_URL ?>/uploads/bids/<?= $attachment['file_name'] ?>" target="_blank">
                            <?= htmlspecialchars($attachment['original_name']) ?>
                        </a>
                        <small class="text-muted">(<?= formatFileSize($attachment['file_size']) ?>)</small>
                    </div>
                    <small class="text-muted"><?= formatDate($attachment['uploaded_at']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Score Breakdown -->
        <h5 class="mb-3">Score Breakdown</h5>
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Price Competitiveness (40%)</span>
                                <strong><?= number_format($scoreBreakdown['price_competitiveness'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?= ($scoreBreakdown['price_competitiveness'] ?? 0) / 40 * 100 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Delivery Timeline (25%)</span>
                                <strong><?= number_format($scoreBreakdown['delivery_timeline'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: <?= ($scoreBreakdown['delivery_timeline'] ?? 0) / 25 * 100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Vendor Rating (20%)</span>
                                <strong><?= number_format($scoreBreakdown['vendor_rating'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= ($scoreBreakdown['vendor_rating'] ?? 0) / 20 * 100 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Experience (10%)</span>
                                <strong><?= number_format($scoreBreakdown['experience'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?= ($scoreBreakdown['experience'] ?? 0) / 10 * 100 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Proposal Quality (5%)</span>
                                <strong><?= number_format($scoreBreakdown['proposal_quality'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-secondary" style="width: <?= ($scoreBreakdown['proposal_quality'] ?? 0) / 5 * 100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Vendor Information -->
        <h5 class="mb-3">Vendor Information</h5>
        <div class="card mb-3">
            <div class="card-body">
                <h6><?= htmlspecialchars($bid['company_name']) ?></h6>
                <p class="text-muted small mb-2">
                    <i class="fas fa-user me-1"></i> <?= htmlspecialchars($bid['vendor_name']) ?>
                </p>
                
                <div class="mb-2">
                    <i class="fas fa-star text-warning me-1"></i>
                    <strong><?= number_format($bid['vendor_rating'], 1) ?></strong> Rating
                    <?php if ($bid['experience_years']): ?>
                    <span class="text-muted">| <?= $bid['experience_years'] ?> years experience</span>
                    <?php endif; ?>
                </div>
                
                <div class="mb-2">
                    <i class="fas fa-envelope me-1"></i>
                    <?= htmlspecialchars($bid['vendor_email']) ?>
                </div>
                
                <div class="mb-2">
                    <i class="fas fa-phone me-1"></i>
                    <?= htmlspecialchars($bid['vendor_phone']) ?>
                </div>
                
                <?php if ($bid['company_address']): ?>
                <div class="mb-2">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    <?= htmlspecialchars($bid['company_address']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tender Information -->
        <h5 class="mb-3">Tender Information</h5>
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-2">
                    <strong>Tender Number:</strong><br>
                    <span class="badge bg-secondary"><?= htmlspecialchars($bid['tender_number']) ?></span>
                </div>
                
                <div class="mb-2">
                    <strong>Category:</strong><br>
                    <?= htmlspecialchars($bid['category_name'] ?? 'N/A') ?>
                </div>
                
                <div class="mb-2">
                    <strong>Budget Range:</strong><br>
                    <?= formatCurrency($bid['budget_min']) ?> - <?= formatCurrency($bid['budget_max']) ?>
                </div>
                
                <div class="mb-2">
                    <strong>Quantity:</strong><br>
                    <?= $bid['quantity'] ?> <?= htmlspecialchars($bid['unit']) ?>
                </div>
                
                <div class="mb-2">
                    <strong>Deadline:</strong><br>
                    <?= formatDate($bid['tender_deadline']) ?>
                </div>
                
                <div class="mb-2">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?= getStatusColor($bid['tender_status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $bid['tender_status'])) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Vendor History -->
        <?php if (!empty($vendorHistory)): ?>
        <h5 class="mb-3">Recent Activity</h5>
        <div class="card mb-3">
            <div class="card-body">
                <?php foreach ($vendorHistory as $history): ?>
                <div class="mb-2 pb-2 border-bottom">
                    <div class="d-flex justify-content-between">
                        <small class="fw-bold"><?= htmlspecialchars($history['tender_title']) ?></small>
                        <span class="badge bg-<?= getStatusColor($history['status']) ?> badge-sm">
                            <?= ucfirst($history['status']) ?>
                        </span>
                    </div>
                    <small class="text-muted">
                        <?= formatCurrency($history['amount']) ?> 
                        <?php if ($history['bid_score']): ?>
                        | Score: <?= number_format($history['bid_score'], 1) ?>
                        <?php endif; ?>
                    </small>
                    <br>
                    <small class="text-muted"><?= formatDate($history['submitted_at']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Comparison -->
        <?php if (!empty($otherBids)): ?>
        <h5 class="mb-3">Other Bids</h5>
        <div class="card mb-3">
            <div class="card-body">
                <?php foreach ($otherBids as $otherBid): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($otherBid['company_name']) ?></div>
                        <small class="text-muted"><?= formatCurrency($otherBid['amount']) ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= getScoreColor($otherBid['bid_score']) ?>">
                            <?= number_format($otherBid['bid_score'], 1) ?>
                        </span>
                        <br>
                        <span class="badge bg-<?= getStatusColor($otherBid['status']) ?> badge-sm">
                            <?= ucfirst($otherBid['status']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.bid-score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: white;
    font-weight: bold;
}

.bid-score-circle.bg-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.bid-score-circle.bg-info { background: linear-gradient(135deg, #17a2b8, #117a8b); }
.bid-score-circle.bg-warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
.bid-score-circle.bg-danger { background: linear-gradient(135deg, #dc3545, #bd2130); }

.score-value {
    font-size: 2rem;
    line-height: 1;
}

.score-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

.proposal-content {
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.badge-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}
</style>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'tender_status' => $bid['tender_status'],
    'bid_status' => $bid['status'],
    'bid_score' => $bid['bid_score']
]);

// Helper functions
function getScoreColor($score) {
    if ($score >= 80) return 'bg-success';
    if ($score >= 60) return 'bg-info';
    if ($score >= 40) return 'bg-warning';
    return 'bg-danger';
}

function getGradeColor($score) {
    if ($score >= 80) return 'success';
    if ($score >= 60) return 'info';
    if ($score >= 40) return 'warning';
    return 'danger';
}

function getScoreGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B+';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C+';
    if ($score >= 40) return 'C';
    if ($score >= 30) return 'D';
    return 'F';
}
?>
