<?php
// Include configuration and functions
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/public/login.php');
}

// Initialize database
$db = Database::getInstance();

// Get tender ID from URL
$tenderId = isset($_GET['tender']) ? (int)$_GET['tender'] : 0;

if (!$tenderId) {
    setFlashMessage('Invalid tender specified', 'danger');
    redirect(BASE_URL . '/public/admin/tenders.php');
}

// Get tender details
$tender = $db->fetch("
    SELECT t.*, pc.name as category_name, u.full_name as created_by_name
    FROM tenders t
    LEFT JOIN produce_categories pc ON t.category_id = pc.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.id = ?
", [$tenderId]);

if (!$tender) {
    setFlashMessage('Tender not found', 'danger');
    redirect(BASE_URL . '/public/admin/tenders.php');
}

// Get all bids for the tender
$bids = $db->fetchAll("
    SELECT b.*, vp.company_name, vp.rating as vendor_rating, vp.experience_years,
           u.email as vendor_email, u.phone as vendor_phone
    FROM bids b
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
    LEFT JOIN users u ON b.vendor_id = u.id
    WHERE b.tender_id = ?
    ORDER BY b.bid_score DESC, b.submitted_at ASC
", [$tenderId]);

// Check if tender can be evaluated
if ($tender['status'] !== 'closed' && $tender['deadline'] > date('Y-m-d H:i:s')) {
    setFlashMessage('Tender is still open for bidding. Evaluation can only be done after the deadline.', 'warning');
}

// Calculate scores for bids that don't have scores yet
foreach ($bids as &$bid) {
    if (!$bid['bid_score']) {
        // Calculate score components
        $scores = calculateBidScoreComponents($bid, $tender);
        $totalScore = array_sum($scores);
        
        // Update bid in database
        $db->update('bids', [
            'bid_score' => $totalScore,
            'score_breakdown' => json_encode($scores),
            'score_updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$bid['id']]);
        
        $bid['bid_score'] = $totalScore;
        $bid['score_breakdown'] = $scores;
    } else {
        $bid['score_breakdown'] = json_decode($bid['score_breakdown'] ?? '{}', true);
    }
}

// Sort bids by score
usort($bids, function($a, $b) {
    return $b['bid_score'] <=> $a['bid_score'];
});

// Handle award action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_bid'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
        redirect($_SERVER['PHP_SELF'] . '?tender=' . $tenderId);
    }
    
    $bidId = (int)$_POST['bid_id'];
    $awardNotes = sanitizeInput($_POST['award_notes'] ?? '');
    
    // Get bid details
    $selectedBid = null;
    foreach ($bids as $bid) {
        if ($bid['id'] == $bidId) {
            $selectedBid = $bid;
            break;
        }
    }
    
    if (!$selectedBid) {
        setFlashMessage('Bid not found', 'danger');
        redirect($_SERVER['PHP_SELF'] . '?tender=' . $tenderId);
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Update tender status
        $db->update('tenders', [
            'status' => 'awarded',
            'awarded_to' => $selectedBid['vendor_id'],
            'awarded_bid_id' => $bidId,
            'awarded_amount' => $selectedBid['amount'],
            'award_notes' => $awardNotes,
            'awarded_at' => date('Y-m-d H:i:s'),
            'awarded_by' => getCurrentUser()['id']
        ], 'id = ?', [$tenderId]);
        
        // Update selected bid status
        $db->update('bids', [
            'status' => 'awarded',
            'award_notes' => $awardNotes,
            'awarded_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$bidId]);
        
        // Update other bids status
        foreach ($bids as $bid) {
            if ($bid['id'] != $bidId) {
                $db->update('bids', ['status' => 'rejected'], 'id = ?', [$bid['id']]);
            }
        }
        
        // Create notification for winning vendor
        createNotification(
            $selectedBid['vendor_id'],
            'Congratulations! Your Bid Has Been Awarded',
            "Your bid of KES " . number_format($selectedBid['amount'], 2) . " for tender '{$tender['title']}' has been awarded!",
            'success',
            BASE_URL . "/public/vendor/bid-details.php?id={$bidId}"
        );
        
        // Create notifications for other vendors
        foreach ($bids as $bid) {
            if ($bid['id'] != $bidId) {
                createNotification(
                    $bid['vendor_id'],
                    'Bid Status Update',
                    "The tender '{$tender['title']}' has been awarded to another vendor. Thank you for your participation.",
                    'info',
                    BASE_URL . "/public/vendor/bid-details.php?id={$bid['id']}"
                );
            }
        }
        
        // Log activity
        logActivity(getCurrentUser()['id'], 'award_bid', "Awarded tender '{$tender['title']}' to {$selectedBid['company_name']}", [
            'tender_id' => $tenderId,
            'bid_id' => $bidId,
            'vendor_id' => $selectedBid['vendor_id'],
            'amount' => $selectedBid['amount']
        ]);
        
        // Commit transaction
        $db->commit();
        
        setFlashMessage('Tender awarded successfully! Vendor has been notified.', 'success');
        redirect(BASE_URL . '/public/admin/tender-details.php?id=' . $tenderId);
        
    } catch (Exception $e) {
        $db->rollback();
        setFlashMessage('Error awarding tender: ' . $e->getMessage(), 'danger');
    }
}

$pageTitle = 'Bid Evaluation - ' . htmlspecialchars($tender['title']);
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Bid Evaluation</h1>
                <div>
                    <a href="<?= BASE_URL ?>/public/admin/tender-details.php?id=<?= $tenderId ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-eye me-2"></i>Tender Details
                    </a>
                    <a href="<?= BASE_URL ?>/public/admin/tenders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Tenders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tender Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Tender Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h6><?= htmlspecialchars($tender['title']) ?></h6>
                    <p class="text-muted mb-2"><?= htmlspecialchars($tender['description']) ?></p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Tender Number:</strong><br>
                            <span class="badge bg-secondary"><?= htmlspecialchars($tender['tender_number']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Category:</strong><br>
                            <?= htmlspecialchars($tender['category_name'] ?? 'N/A') ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?= getStatusColor($tender['status']) ?>"><?= ucfirst(str_replace('_', ' ', $tender['status'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <strong>Budget Range:</strong><br>
                            <?= formatCurrency($tender['budget_min']) ?> - <?= formatCurrency($tender['budget_max']) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Deadline:</strong><br>
                            <?= formatDate($tender['deadline']) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Total Bids:</strong><br>
                            <span class="badge bg-primary"><?= count($bids) ?> bids received</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-<?= $tender['status'] === 'awarded' ? 'success' : ($tender['status'] === 'closed' ? 'info' : 'warning') ?>">
                        <h6 class="alert-heading mb-2">Evaluation Status</h6>
                        <?php if ($tender['status'] === 'awarded'): ?>
                            <p class="mb-0">This tender has been awarded to <strong><?= getVendorName($tender['awarded_to']) ?></strong></p>
                        <?php elseif ($tender['status'] === 'closed'): ?>
                            <p class="mb-0">Tender is closed and ready for evaluation.</p>
                        <?php else: ?>
                            <p class="mb-0">Tender is still open. Evaluation available after deadline.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scoring Criteria -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Scoring Criteria</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle primary">
                            <span>40%</span>
                        </div>
                        <small class="text-muted">Price Competitiveness</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle info">
                            <span>25%</span>
                        </div>
                        <small class="text-muted">Delivery Timeline</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle success">
                            <span>20%</span>
                        </div>
                        <small class="text-muted">Vendor Rating</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle warning">
                            <span>10%</span>
                        </div>
                        <small class="text-muted">Experience</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle secondary">
                            <span>5%</span>
                        </div>
                        <small class="text-muted">Proposal Quality</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-circle dark">
                            <span>100%</span>
                        </div>
                        <small class="text-muted">Total Score</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bids Evaluation -->
    <?php if (empty($bids)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Bids Received</h5>
            <p class="text-muted">No bids have been submitted for this tender yet.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Bid Evaluation Results</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="exportEvaluation()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-primary" onclick="printEvaluation()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <button class="btn btn-outline-info" onclick="recalculateScores()">
                    <i class="fas fa-calculator me-1"></i>Recalculate
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="evaluationTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Vendor</th>
                            <th>Bid Amount</th>
                            <th>Delivery</th>
                            <th>Total Score</th>
                            <th>Price (40%)</th>
                            <th>Timeline (25%)</th>
                            <th>Rating (20%)</th>
                            <th>Experience (10%)</th>
                            <th>Proposal (5%)</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($bids as $bid): ?>
                        <tr class="<?= $bid['status'] === 'awarded' ? 'table-success' : '' ?>">
                            <td>
                                <span class="badge bg-<?= $rank === 1 ? 'success' : ($rank === 2 ? 'info' : ($rank === 3 ? 'warning' : 'secondary')) ?>">
                                    #<?= $rank++ ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($bid['company_name'] ?? 'Unknown Vendor') ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-star text-warning"></i> <?= number_format($bid['vendor_rating'], 1) ?>
                                        <?php if ($bid['experience_years']): ?>
                                        | <?= $bid['experience_years'] ?> years exp.
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <strong><?= formatCurrency($bid['amount']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php
                                    $budgetPosition = ($tender['budget_max'] - $bid['amount']) / ($tender['budget_max'] - $tender['budget_min']) * 100;
                                    echo round($budgetPosition) . '% of budget range';
                                    ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= $bid['delivery_timeline'] ?> days</strong>
                                <br>
                                <small class="text-muted">Timeline</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                        <div class="progress-bar <?= getScoreColor($bid['bid_score']) ?>" 
                                             style="width: <?= $bid['bid_score'] ?>%">
                                            <?= number_format($bid['bid_score'], 1) ?>
                                        </div>
                                    </div>
                                    <strong><?= number_format($bid['bid_score'], 1) ?></strong>
                                </div>
                            </td>
                            <td><?= number_format($bid['score_breakdown']['price_competitiveness'] ?? 0, 1) ?></td>
                            <td><?= number_format($bid['score_breakdown']['delivery_timeline'] ?? 0, 1) ?></td>
                            <td><?= number_format($bid['score_breakdown']['vendor_rating'] ?? 0, 1) ?></td>
                            <td><?= number_format($bid['score_breakdown']['experience'] ?? 0, 1) ?></td>
                            <td><?= number_format($bid['score_breakdown']['proposal_quality'] ?? 0, 1) ?></td>
                            <td>
                                <span class="badge bg-<?= getGradeColor($bid['bid_score']) ?>">
                                    <?= getScoreGrade($bid['bid_score']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusColor($bid['status']) ?>">
                                    <?= ucfirst($bid['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewBidDetails(<?= $bid['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="viewProposal(<?= $bid['id'] ?>)" title="View Proposal">
                                        <i class="fas fa-file-alt"></i>
                                    </button>
                                    <?php if ($tender['status'] === 'closed' && $bid['status'] !== 'awarded'): ?>
                                    <button class="btn btn-success" onclick="awardBid(<?= $bid['id'] ?>)" title="Award Bid">
                                        <i class="fas fa-trophy"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Bid Details Modal -->
<div class="modal fade" id="bidDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bid Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bidDetailsBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="awardBidBtn" onclick="awardBidFromModal()">Award Bid</button>
            </div>
        </div>
    </div>
</div>

<!-- Award Bid Modal -->
<div class="modal fade" id="awardBidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Award Bid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="awardBidForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="bid_id" id="awardBidId">
                <input type="hidden" name="award_bid" value="1">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Confirm Award</h6>
                        <p class="mb-0">You are about to award this tender to the selected vendor. This action cannot be undone.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="award_notes" class="form-label">Award Notes (Optional)</label>
                        <textarea class="form-control" id="award_notes" name="award_notes" rows="3"
                                  placeholder="Add any notes about this award decision..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-trophy me-2"></i>Confirm Award
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.score-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-weight: bold;
    color: white;
}

.score-circle.primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.score-circle.info { background: linear-gradient(135deg, #17a2b8, #117a8b); }
.score-circle.success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.score-circle.warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
.score-circle.secondary { background: linear-gradient(135deg, #6c757d, #545b62); }
.score-circle.dark { background: linear-gradient(135deg, #343a40, #1d2124); }
</style>

<script>
let currentBidId = null;

// View bid details
function viewBidDetails(bidId) {
    currentBidId = bidId;
    const modal = new bootstrap.Modal(document.getElementById('bidDetailsModal'));
    const modalBody = document.getElementById('bidDetailsBody');
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch(`<?= BASE_URL ?>/api/bid-details.php?id=${bidId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = data.html;
            
            // Show/hide award button based on tender status
            const awardBtn = document.getElementById('awardBidBtn');
            if (data.tender_status === 'closed' && data.bid_status !== 'awarded') {
                awardBtn.style.display = 'inline-block';
            } else {
                awardBtn.style.display = 'none';
            }
            
            modal.show();
        } else {
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load bid details</div>';
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading bid details</div>';
    });
}

// View proposal
function viewProposal(bidId) {
    window.open(`<?= BASE_URL ?>/public/vendor/proposal-view.php?id=${bidId}`, '_blank');
}

// Award bid
function awardBid(bidId) {
    currentBidId = bidId;
    document.getElementById('awardBidId').value = bidId;
    document.getElementById('awardBidForm').reset();
    
    const modal = new bootstrap.Modal(document.getElementById('awardBidModal'));
    modal.show();
}

// Award bid from modal
function awardBidFromModal() {
    if (currentBidId) {
        awardBid(currentBidId);
    }
}

// Export evaluation
function exportEvaluation() {
    const url = new URL(window.location);
    url.searchParams.set('export', 'csv');
    window.open(url.toString());
}

// Print evaluation
function printEvaluation() {
    window.print();
}

// Recalculate scores
function recalculateScores() {
    if (confirm('This will recalculate all bid scores based on current criteria. Continue?')) {
        fetch(`<?= BASE_URL ?>/api/bid-scoring.php?action=evaluate_bids&tender_id=<?= $tenderId ?>`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Scores recalculated successfully', 'success');
                location.reload();
            } else {
                showNotification(data.error || 'Failed to recalculate scores', 'danger');
            }
        })
        .catch(error => {
            showNotification('Error recalculating scores', 'danger');
        });
    }
}

// Handle award bid form submission
document.getElementById('awardBidForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Set loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Check if redirect happened (success)
        if (html.includes('setFlashMessage')) {
            showNotification('Bid awarded successfully!', 'success');
            setTimeout(() => {
                window.location.href = '<?= BASE_URL ?>/public/admin/tender-details.php?id=<?= $tenderId ?>';
            }, 1000);
        } else {
            showNotification('Error awarding bid', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-trophy me-2"></i>Confirm Award';
        }
    })
    .catch(error => {
        showNotification('Error awarding bid', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-trophy me-2"></i>Confirm Award';
    });
});

// Sort table by score
function sortTableByScore() {
    const table = document.getElementById('evaluationTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const scoreA = parseFloat(a.cells[4].textContent.trim());
        const scoreB = parseFloat(b.cells[4].textContent.trim());
        return scoreB - scoreA;
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    sortTableByScore();
});
</script>

<?php
// Helper functions
function calculateBidScoreComponents($bid, $tender) {
    $scores = [];
    
    // Price competitiveness (40%)
    if ($bid['amount'] >= $tender['budget_min'] && $bid['amount'] <= $tender['budget_max']) {
        $priceRange = $tender['budget_max'] - $tender['budget_min'];
        $pricePosition = ($tender['budget_max'] - $bid['amount']) / $priceRange;
        $scores['price_competitiveness'] = round($pricePosition * 40, 2);
    } else {
        $scores['price_competitiveness'] = 0;
    }
    
    // Delivery timeline (25%)
    $optimalDelivery = 7;
    if ($bid['delivery_timeline'] > 0) {
        $deliveryScore = max(0, ($optimalDelivery - min($bid['delivery_timeline'], 30)) / $optimalDelivery * 25);
        $scores['delivery_timeline'] = round($deliveryScore, 2);
    } else {
        $scores['delivery_timeline'] = 0;
    }
    
    // Vendor rating (20%)
    $vendorRating = $bid['vendor_rating'] ?? 0;
    $scores['vendor_rating'] = round(($vendorRating / 5) * 20, 2);
    
    // Experience (10%)
    $experienceYears = $bid['experience_years'] ?? 0;
    $experienceScore = min($experienceYears / 10 * 10, 10);
    $scores['experience'] = round($experienceScore, 2);
    
    // Proposal quality (5%)
    $proposalLength = strlen($bid['proposal'] ?? '');
    if ($proposalLength > 100) {
        $proposalScore = min($proposalLength / 1000 * 5, 5);
    } else {
        $proposalScore = 0;
    }
    $scores['proposal_quality'] = round($proposalScore, 2);
    
    return $scores;
}

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

function getVendorName($vendorId) {
    global $db;
    $vendor = $db->fetch("SELECT company_name FROM vendor_profiles WHERE user_id = ?", [$vendorId]);
    return $vendor['company_name'] ?? 'Unknown';
}
?>

<?php include '../../includes/footer.php'; ?>
