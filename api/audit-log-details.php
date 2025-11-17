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

// Get log ID
$logId = (int)($_GET['id'] ?? 0);

if (!$logId) {
    echo json_encode(['success' => false, 'error' => 'Log ID is required']);
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get audit log details
$auditLog = $db->fetch("
    SELECT al.*, u.full_name, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.id = ?
", [$logId]);

if (!$auditLog) {
    echo json_encode(['success' => false, 'error' => 'Audit log not found']);
    exit;
}

// Parse details JSON
$details = json_decode($auditLog['details'] ?? '{}', true);

// Get related information based on target type
$relatedInfo = null;
if ($auditLog['target_type'] && $auditLog['target_id']) {
    switch ($auditLog['target_type']) {
        case 'tender':
            $relatedInfo = $db->fetch("
                SELECT id, title, tender_number, status, budget_min, budget_max
                FROM tenders
                WHERE id = ?
            ", [$auditLog['target_id']]);
            break;
            
        case 'bid':
            $relatedInfo = $db->fetch("
                SELECT b.id, b.amount, b.status, b.submitted_at,
                       t.title as tender_title, t.tender_number,
                       vp.company_name
                FROM bids b
                JOIN tenders t ON b.tender_id = t.id
                LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
                WHERE b.id = ?
            ", [$auditLog['target_id']]);
            break;
            
        case 'user':
            $relatedInfo = $db->fetch("
                SELECT id, full_name, email, role, created_at
                FROM users
                WHERE id = ?
            ", [$auditLog['target_id']]);
            break;
            
        case 'transport_request':
            $relatedInfo = $db->fetch("
                SELECT tr.id, tr.status, tr.pickup_location, tr.delivery_location,
                       tr.created_at, tr.fee
                FROM transport_requests tr
                WHERE tr.id = ?
            ", [$auditLog['target_id']]);
            break;
            
        case 'quality_report':
            $relatedInfo = $db->fetch("
                SELECT qr.id, qr.report_type, qr.severity, qr.status,
                       qr.created_at, qr.produce_category
                FROM quality_reports qr
                WHERE qr.id = ?
            ", [$auditLog['target_id']]);
            break;
    }
}

// Generate HTML content
ob_start();
?>

<div class="row">
    <div class="col-md-8">
        <h5 class="mb-3">Log Information</h5>
        
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Log ID</label>
                            <div class="fw-bold">#<?= $auditLog['id'] ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Date & Time</label>
                            <div class="fw-bold"><?= formatDate($auditLog['created_at']) ?></div>
                            <div class="text-muted"><?= formatTime($auditLog['created_at']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Action</label>
                            <div>
                                <span class="badge bg-<?= getActionColor($auditLog['action']) ?> fs-6">
                                    <?= ucfirst(str_replace('_', ' ', $auditLog['action'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Description</label>
                            <div class="fw-bold"><?= htmlspecialchars($auditLog['description']) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">IP Address</label>
                            <div class="fw-bold">
                                <code><?= $auditLog['ip_address'] ?></code>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">User Agent</label>
                            <div class="small text-muted" style="word-break: break-all;">
                                <?= htmlspecialchars($auditLog['user_agent'] ?? 'N/A') ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Target</label>
                            <div>
                                <?php if ($auditLog['target_type'] && $auditLog['target_id']): ?>
                                <span class="badge bg-secondary"><?= ucfirst($auditLog['target_type']) ?></span>
                                <span class="fw-bold ms-2">#<?= $auditLog['target_id'] ?></span>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Session ID</label>
                            <div class="small text-muted">
                                <?= $auditLog['session_id'] ? substr($auditLog['session_id'], 0, 8) . '...' : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Information -->
        <h5 class="mb-3">User Information</h5>
        <div class="card mb-3">
            <div class="card-body">
                <?php if ($auditLog['user_id']): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">User ID</label>
                            <div class="fw-bold">#<?= $auditLog['user_id'] ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Full Name</label>
                            <div class="fw-bold"><?= htmlspecialchars($auditLog['full_name'] ?? 'Unknown') ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Email</label>
                            <div class="fw-bold"><?= htmlspecialchars($auditLog['email'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Role</label>
                            <div>
                                <span class="badge bg-<?= getRoleColor($auditLog['role']) ?> fs-6">
                                    <?= ucfirst($auditLog['role'] ?? 'system') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted">
                    <i class="fas fa-robot fa-2x mb-2"></i>
                    <p>System action - no user associated</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Information -->
        <?php if ($relatedInfo): ?>
        <h5 class="mb-3">Related Information</h5>
        <div class="card mb-3">
            <div class="card-body">
                <?php if ($auditLog['target_type'] === 'tender'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Tender Title</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['title']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Tender Number</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['tender_number']) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge bg-<?= getStatusColor($relatedInfo['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $relatedInfo['status'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Budget Range</label>
                            <div class="fw-bold">
                                <?= formatCurrency($relatedInfo['budget_min']) ?> - <?= formatCurrency($relatedInfo['budget_max']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($auditLog['target_type'] === 'bid'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Bid Amount</label>
                            <div class="fw-bold"><?= formatCurrency($relatedInfo['amount']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Vendor</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['company_name'] ?? 'Unknown') ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge bg-<?= getStatusColor($relatedInfo['status']) ?>">
                                    <?= ucfirst($relatedInfo['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Submitted</label>
                            <div class="fw-bold"><?= formatDate($relatedInfo['submitted_at']) ?></div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($auditLog['target_type'] === 'user'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Full Name</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['full_name']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Email</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['email']) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Role</label>
                            <div>
                                <span class="badge bg-<?= getRoleColor($relatedInfo['role']) ?>">
                                    <?= ucfirst($relatedInfo['role']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Created</label>
                            <div class="fw-bold"><?= formatDate($relatedInfo['created_at']) ?></div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($auditLog['target_type'] === 'transport_request'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge bg-<?= getStatusColor($relatedInfo['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $relatedInfo['status'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Fee</label>
                            <div class="fw-bold"><?= formatCurrency($relatedInfo['fee']) ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Pickup Location</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['pickup_location']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Delivery Location</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['delivery_location']) ?></div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($auditLog['target_type'] === 'quality_report'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Report Type</label>
                            <div class="fw-bold"><?= ucfirst(str_replace('_', ' ', $relatedInfo['report_type'])) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Severity</label>
                            <div>
                                <span class="badge bg-<?= getSeverityColor($relatedInfo['severity']) ?>">
                                    <?= ucfirst($relatedInfo['severity']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge bg-<?= getStatusColor($relatedInfo['status']) ?>">
                                    <?= ucfirst($relatedInfo['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Category</label>
                            <div class="fw-bold"><?= htmlspecialchars($relatedInfo['produce_category']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Additional Details -->
        <?php if (!empty($details)): ?>
        <h5 class="mb-3">Additional Details</h5>
        <div class="card mb-3">
            <div class="card-body">
                <pre class="small text-muted" style="white-space: pre-wrap; word-break: break-word;"><?= htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)) ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Security Information -->
        <h5 class="mb-3">Security Information</h5>
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Geolocation</label>
                    <div class="small text-muted">
                        <?php if ($auditLog['latitude'] && $auditLog['longitude']): ?>
                        Lat: <?= $auditLog['latitude'] ?>, Lng: <?= $auditLog['longitude'] ?>
                        <br>
                        <a href="https://maps.google.com/?q=<?= $auditLog['latitude'] ?>,<?= $auditLog['longitude'] ?>" 
                           target="_blank" class="text-decoration-none">
                            <i class="fas fa-map-marker-alt me-1"></i>View on Map
                        </a>
                        <?php else: ?>
                        Not available
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Device Fingerprint</label>
                    <div class="small text-muted">
                        <?= $auditLog['device_fingerprint'] ? substr($auditLog['device_fingerprint'], 0, 16) . '...' : 'N/A' ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Risk Score</label>
                    <div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-<?= getRiskColor($auditLog['risk_score'] ?? 0) ?>" 
                                 style="width: <?= min($auditLog['risk_score'] ?? 0, 100) ?>%">
                                <?= $auditLog['risk_score'] ?? 0 ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <h5 class="mb-3">Quick Actions</h5>
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($auditLog['target_type'] === 'tender' && $relatedInfo): ?>
                    <a href="<?= BASE_URL ?>/public/admin/tender-details.php?id=<?= $relatedInfo['id'] ?>" 
                       class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="fas fa-eye me-2"></i>View Tender
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auditLog['target_type'] === 'bid' && $relatedInfo): ?>
                    <a href="<?= BASE_URL ?>/public/admin/bid-details.php?id=<?= $relatedInfo['id'] ?>" 
                       class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="fas fa-eye me-2"></i>View Bid
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auditLog['user_id']): ?>
                    <a href="<?= BASE_URL ?>/public/admin/user-details.php?id=<?= $auditLog['user_id'] ?>" 
                       class="btn btn-outline-info btn-sm" target="_blank">
                        <i class="fas fa-user me-2"></i>View User
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary btn-sm" onclick="copyLogInfo()">
                        <i class="fas fa-copy me-2"></i>Copy Info
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Copy log information to clipboard
function copyLogInfo() {
    const info = {
        id: <?= $auditLog['id'] ?>,
        action: '<?= $auditLog['action'] ?>',
        description: '<?= addslashes($auditLog['description']) ?>',
        user: '<?= addslashes($auditLog['full_name'] ?? 'Unknown') ?>',
        date: '<?= formatDate($auditLog['created_at']) ?>',
        ip: '<?= $auditLog['ip_address'] ?>',
        target: '<?= $auditLog['target_type'] ?> #<?= $auditLog['target_id'] ?>'
    };
    
    navigator.clipboard.writeText(JSON.stringify(info, null, 2))
        .then(() => showNotification('Log information copied to clipboard', 'success'))
        .catch(() => showNotification('Failed to copy information', 'danger'));
}
</script>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);

// Helper functions
function getActionColor($action) {
    $colors = [
        'login' => 'success',
        'logout' => 'secondary',
        'create' => 'primary',
        'update' => 'info',
        'delete' => 'danger',
        'award' => 'success',
        'submit' => 'primary',
        'reject' => 'danger',
        'approve' => 'success',
        'view' => 'info',
        'export' => 'warning',
        'print' => 'secondary'
    ];
    
    return $colors[$action] ?? 'secondary';
}

function getRoleColor($role) {
    $colors = [
        'admin' => 'danger',
        'vendor' => 'primary',
        'transporter' => 'success',
        'farmer' => 'warning',
        'system' => 'secondary'
    ];
    
    return $colors[$role] ?? 'secondary';
}

function getSeverityColor($severity) {
    $colors = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    
    return $colors[$severity] ?? 'secondary';
}

function getRiskColor($score) {
    if ($score < 30) return 'success';
    if ($score < 60) return 'warning';
    return 'danger';
}
?>
