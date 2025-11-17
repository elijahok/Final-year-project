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

// Get request ID
$requestId = (int)($_GET['id'] ?? 0);

if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'Request ID is required']);
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get transport request details
$request = $db->fetch("
    SELECT tr.*, 
           fp.farm_name, fp.farm_location,
           u_f.full_name as farmer_name, u_f.phone as farmer_phone,
           u_t.full_name as transporter_name, u_t.phone as transporter_phone,
           tp.vehicle_type as transporter_vehicle, tp.vehicle_license,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
    LEFT JOIN users u_f ON fp.user_id = u_f.id
    LEFT JOIN transporter_profiles tp ON tr.transporter_id = tp.id
    LEFT JOIN users u_t ON tp.user_id = u_t.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.id = ?
", [$requestId]);

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Transport request not found']);
    exit;
}

// Check user permissions
$currentUser = getCurrentUser();
if (!$currentUser) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Only allow farmer who created the request or assigned transporter to view
if ($currentUser['role'] === 'farmer' && $request['farmer_id'] !== $currentUser['id']) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($currentUser['role'] === 'transporter' && $request['transporter_id'] !== $currentUser['id']) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get tracking information
$tracking = $db->fetchAll("
    SELECT gt.*, 
           TIMESTAMPDIFF(MINUTE, gt.created_at, NOW()) as minutes_ago
    FROM gps_tracking gt
    WHERE gt.transport_request_id = ?
    ORDER BY gt.created_at DESC
    LIMIT 10
", [$requestId]);

// Get any quality reports related to this transport
$qualityReports = $db->fetchAll("
    SELECT qr.*, pc.name as produce_category
    FROM quality_reports qr
    LEFT JOIN produce_categories pc ON qr.produce_category_id = pc.id
    WHERE qr.transport_request_id = ?
    ORDER BY qr.created_at DESC
", [$requestId]);

// Generate HTML content
ob_start();
?>

<div class="row">
    <div class="col-md-8">
        <!-- Request Overview -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-truck me-2"></i>Transport Request #<?= $request['id'] ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                <span class="badge bg-<?= getStatusColor($request['status']) ?> fs-6">
                                    <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Produce Category</label>
                            <div class="fw-bold"><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Quantity & Weight</label>
                            <div class="fw-bold"><?= $request['quantity'] ?> units (<?= $request['weight'] ?> kg)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Vehicle Type</label>
                            <div class="fw-bold"><?= ucfirst(str_replace('_', ' ', $request['vehicle_type'])) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Urgency</label>
                            <div>
                                <span class="badge bg-<?= getUrgencyColor($request['urgency']) ?>">
                                    <?= ucfirst($request['urgency']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Fee</label>
                            <div class="fw-bold text-primary"><?= formatCurrency($request['fee']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Estimated Distance</label>
                            <div class="fw-bold"><?= $request['estimated_distance'] ? $request['estimated_distance'] . ' km' : 'N/A' ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Pickup Schedule</label>
                            <div class="fw-bold">
                                <?= formatDate($request['pickup_date']) ?> at <?= formatTime($request['pickup_time']) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Created</label>
                            <div class="fw-bold"><?= formatDate($request['created_at']) ?></div>
                        </div>
                        
                        <?php if ($request['completed_at']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted">Completed</label>
                            <div class="fw-bold"><?= formatDate($request['completed_at']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($request['special_instructions']): ?>
                <div class="mt-3">
                    <label class="form-label text-muted">Special Instructions</label>
                    <div class="alert alert-info">
                        <?= nl2br(htmlspecialchars($request['special_instructions'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Location Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>Location Details
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success mb-2">Pickup Location</h6>
                        <div class="mb-2">
                            <i class="fas fa-seedling me-2"></i>
                            <strong><?= htmlspecialchars($request['farm_name'] ?? 'Farm') ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-map-pin me-2"></i>
                            <?= nl2br(htmlspecialchars($request['pickup_location'])) ?>
                        </div>
                        <div>
                            <i class="fas fa-phone me-2"></i>
                            <?= htmlspecialchars($request['farmer_phone'] ?? 'N/A') ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-danger mb-2">Delivery Location</h6>
                        <div class="mb-2">
                            <i class="fas fa-warehouse me-2"></i>
                            <?= nl2br(htmlspecialchars($request['delivery_location'])) ?>
                        </div>
                        <?php if ($request['transporter_phone']): ?>
                        <div>
                            <i class="fas fa-phone me-2"></i>
                            <?= htmlspecialchars($request['transporter_phone']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Participants -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>Participants
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success mb-3">Farmer</h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-success text-white rounded-circle me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($request['farmer_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($request['farm_name'] ?? 'Farm') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($request['farm_location'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Transporter</h6>
                        <?php if ($request['transporter_name']): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-primary text-white rounded-circle me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($request['transporter_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($request['transporter_vehicle'] ?? 'N/A') ?></div>
                                <div class="text-muted small">License: <?= htmlspecialchars($request['vehicle_license'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-muted">
                            <i class="fas fa-user-slash me-2"></i>No transporter assigned yet
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quality Reports -->
        <?php if (!empty($qualityReports)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>Quality Reports
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($qualityReports as $report): ?>
                <div class="alert alert-<?= getSeverityColor($report['severity']) ?> mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold"><?= ucfirst(str_replace('_', ' ', $report['report_type'])) ?></div>
                            <div class="small"><?= htmlspecialchars($report['produce_category'] ?? 'N/A') ?></div>
                            <?php if ($report['description']): ?>
                            <div class="small mt-1"><?= htmlspecialchars(substr($report['description'], 0, 100)) ?>...</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge bg-<?= getStatusColor($report['status']) ?>">
                                <?= ucfirst($report['status']) ?>
                            </span>
                            <div class="small text-muted mt-1"><?= formatDate($report['created_at']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Live Tracking -->
        <?php if ($request['status'] === 'accepted' && $request['transporter_id']): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-satellite-dish me-2"></i>Live Tracking
                </h6>
            </div>
            <div class="card-body">
                <div id="liveTrackingMap" style="height: 200px; width: 100%; border-radius: 0.375rem;"></div>
                <?php if (!empty($tracking)): ?>
                <div class="mt-2">
                    <small class="text-muted">
                        Last update: <?= timeAgo($tracking[0]['created_at']) ?>
                    </small>
                </div>
                <?php else: ?>
                <div class="mt-2 text-center text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i>Waiting for location data...
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Timeline -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="fw-bold">Request Created</div>
                            <div class="small text-muted"><?= formatDate($request['created_at']) ?></div>
                        </div>
                    </div>
                    
                    <?php if ($request['transporter_id']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <div class="fw-bold">Transporter Assigned</div>
                            <div class="small text-muted"><?= htmlspecialchars($request['transporter_name']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['accepted_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <div class="fw-bold">Request Accepted</div>
                            <div class="small text-muted"><?= formatDate($request['accepted_at']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['picked_up_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <div class="fw-bold">Picked Up</div>
                            <div class="small text-muted"><?= formatDate($request['picked_up_at']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['completed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="fw-bold">Delivered</div>
                            <div class="small text-muted"><?= formatDate($request['completed_at']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cog me-2"></i>Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($currentUser['role'] === 'farmer'): ?>
                        <?php if ($request['status'] === 'completed' && !$request['rating']): ?>
                        <button class="btn btn-warning" onclick="rateTransporter()">
                            <i class="fas fa-star me-2"></i>Rate Transporter
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] === 'pending'): ?>
                        <button class="btn btn-danger" onclick="cancelRequest()">
                            <i class="fas fa-times me-2"></i>Cancel Request
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-primary" onclick="printRequest()">
                            <i class="fas fa-print me-2"></i>Print Details
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($currentUser['role'] === 'transporter'): ?>
                        <?php if ($request['status'] === 'pending'): ?>
                        <button class="btn btn-success" onclick="acceptRequest()">
                            <i class="fas fa-check me-2"></i>Accept Request
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] === 'accepted'): ?>
                        <button class="btn btn-info" onclick="markPickedUp()">
                            <i class="fas fa-box me-2"></i>Mark Picked Up
                        </button>
                        
                        <button class="btn btn-success" onclick="markDelivered()">
                            <i class="fas fa-check-circle me-2"></i>Mark Delivered
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-primary" onclick="contactFarmer()">
                            <i class="fas fa-phone me-2"></i>Contact Farmer
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
}

.avatar {
    font-size: 1.2rem;
}
</style>

<script>
// Initialize map for live tracking
<?php if ($request['status'] === 'accepted' && $request['transporter_id']): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Leaflet map
    const map = L.map('liveTrackingMap').setView([-1.2921, 36.8219], 13);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add markers if tracking data is available
    <?php if (!empty($tracking)): ?>
    <?php foreach ($tracking as $point): ?>
    L.marker([<?= $point['latitude'] ?>, <?= $point['longitude'] ?>]).addTo(map)
        .bindPopup('<?= formatDate($point['created_at']) ?>');
    <?php endforeach; ?>
    <?php endif; ?>
});
<?php endif; ?>

// Action functions
function rateTransporter() {
    // Implement rating functionality
    window.location.href = '<?= BASE_URL ?>/public/farmer/rate-transporter.php?request=<?= $request['id'] ?>';
}

function cancelRequest() {
    if (confirm('Are you sure you want to cancel this transport request?')) {
        // Implement cancel functionality
        window.location.href = '<?= BASE_URL ?>/api/cancel-transport-request.php?id=<?= $request['id'] ?>';
    }
}

function printRequest() {
    window.print();
}

function acceptRequest() {
    if (confirm('Are you sure you want to accept this transport request?')) {
        // Implement accept functionality
        window.location.href = '<?= BASE_URL ?>/api/accept-transport-request.php?id=<?= $request['id'] ?>';
    }
}

function markPickedUp() {
    if (confirm('Have you picked up the produce?')) {
        // Implement picked up functionality
        window.location.href = '<?= BASE_URL ?>/api/mark-picked-up.php?id=<?= $request['id'] ?>';
    }
}

function markDelivered() {
    if (confirm('Have you delivered the produce?')) {
        // Implement delivered functionality
        window.location.href = '<?= BASE_URL ?>/api/mark-delivered.php?id=<?= $request['id'] ?>';
    }
}

function contactFarmer() {
    // Implement contact functionality
    window.location.href = 'tel:<?= $request['farmer_phone'] ?>';
}
</script>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'request_status' => $request['status'],
    'transporter_id' => $request['transporter_id'],
    'rating_given' => $request['rating'] ? true : false,
    'pickup_lat' => $request['pickup_latitude'] ?? null,
    'pickup_lng' => $request['pickup_longitude'] ?? null,
    'delivery_lat' => $request['delivery_latitude'] ?? null,
    'delivery_lng' => $request['delivery_longitude'] ?? null
]);

// Helper functions
function getUrgencyColor($urgency) {
    $colors = [
        'low' => 'success',
        'medium' => 'info',
        'high' => 'warning',
        'emergency' => 'danger'
    ];
    
    return $colors[$urgency] ?? 'secondary';
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
?>
