<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a transporter
if (!isLoggedIn() || getCurrentUser()['role'] !== 'transporter') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$requestId = intval($_GET['id'] ?? 0);

if ($requestId <= 0) {
    redirect(BASE_URL . '/public/transporter/transport-requests.php');
}

// Get transport request details
$transportRequest = $db->fetch("
    SELECT tr.*, 
           u.full_name as client_name, u.phone as client_phone, u.email as client_email,
           pc.name as produce_category, p.name as produce_name,
           tp.full_name as transporter_name, tp.phone as transporter_phone
    FROM transport_requests tr
    JOIN users u ON tr.client_id = u.id
    LEFT JOIN users tp ON tr.transporter_id = tp.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    LEFT JOIN produce p ON tr.produce_id = p.id
    WHERE tr.id = ? AND tr.transporter_id = ?
", [$requestId, $currentUser['id']]);

if (!$transportRequest) {
    $_SESSION['error'] = 'Transport request not found or access denied';
    redirect(BASE_URL . '/public/transporter/transport-requests.php');
}

// Get location history
$locationHistory = $db->fetchAll("
    SELECT * FROM transport_locations
    WHERE transport_request_id = ?
    ORDER BY timestamp DESC
    LIMIT 20
", [$requestId]);

// Get any ratings for this request
$rating = $db->fetch("
    SELECT * FROM ratings
    WHERE transport_request_id = ? AND client_id = ?
", [$requestId, $transportRequest['client_id']]);

$pageTitle = 'Transport Details #' . $requestId . ' - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-truck me-2"></i>
                    Transport Details #<?= $requestId ?>
                </h1>
                <div class="btn-group">
                    <a href="transport-requests.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Requests
                    </a>
                    <a href="tracking.php" class="btn btn-primary">
                        <i class="fas fa-map-marked-alt me-1"></i>
                        Live Tracking
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Badge -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-<?= getStatusColor($transportRequest['status']) ?> text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?= getStatusText($transportRequest['status']) ?></h5>
                            <small class="text-white-50">Last updated: <?= formatDateTime($transportRequest['updated_at']) ?></small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-<?= getStatusIcon($transportRequest['status']) ?> fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Transport Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Transport Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Client Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td width="30%">Name:</td>
                                    <td><?= htmlspecialchars($transportRequest['client_name']) ?></td>
                                </tr>
                                <tr>
                                    <td>Phone:</td>
                                    <td>
                                        <a href="tel:<?= htmlspecialchars($transportRequest['client_phone']) ?>">
                                            <?= htmlspecialchars($transportRequest['client_phone']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Email:</td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($transportRequest['client_email']) ?>">
                                            <?= htmlspecialchars($transportRequest['client_email']) ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Transport Details</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td width="30%">Amount:</td>
                                    <td class="fw-bold text-success">KES <?= number_format($transportRequest['amount'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Weight:</td>
                                    <td><?= $transportRequest['weight'] ?> kg</td>
                                </tr>
                                <tr>
                                    <td>Volume:</td>
                                    <td><?= $transportRequest['volume'] ?> m³</td>
                                </tr>
                                <tr>
                                    <td>Payment:</td>
                                    <td>
                                        <span class="badge bg-<?= getPaymentStatusColor($transportRequest['payment_status']) ?>">
                                            <?= ucfirst($transportRequest['payment_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Pickup Information</h6>
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($transportRequest['pickup_location']) ?></div>
                                    <small class="text-muted">
                                        <?= $transportRequest['pickup_latitude'] ?>, <?= $transportRequest['pickup_longitude'] ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Delivery Information</h6>
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-flag-checkered fa-2x text-success"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($transportRequest['delivery_location']) ?></div>
                                    <small class="text-muted">
                                        <?= $transportRequest['delivery_latitude'] ?>, <?= $transportRequest['delivery_longitude'] ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($transportRequest['special_instructions']): ?>
                        <hr>
                        <h6 class="text-muted mb-2">Special Instructions</h6>
                        <div class="alert alert-info">
                            <?= nl2br(htmlspecialchars($transportRequest['special_instructions'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($transportRequest['notes']): ?>
                        <hr>
                        <h6 class="text-muted mb-2">Notes</h6>
                        <div class="alert alert-warning">
                            <?= nl2br(htmlspecialchars($transportRequest['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Produce Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-apple-alt me-2"></i>
                        Produce Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-box fa-3x text-primary mb-2"></i>
                                <h6>Category</h6>
                                <p class="mb-0"><?= htmlspecialchars($transportRequest['produce_category'] ?: 'Mixed') ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-leaf fa-3x text-success mb-2"></i>
                                <h6>Produce Type</h6>
                                <p class="mb-0"><?= htmlspecialchars($transportRequest['produce_name'] ?: 'Mixed Produce') ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-weight fa-3x text-warning mb-2"></i>
                                <h6>Total Weight</h6>
                                <p class="mb-0"><?= $transportRequest['weight'] ?> kg</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-route me-2"></i>
                        Location History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($locationHistory)): ?>
                        <p class="text-muted text-center">No location history available</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Latitude</th>
                                        <th>Longitude</th>
                                        <th>Accuracy</th>
                                        <th>Speed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locationHistory as $location): ?>
                                        <tr>
                                            <td><?= formatDateTime($location['timestamp']) ?></td>
                                            <td><?= $location['latitude'] ?></td>
                                            <td><?= $location['longitude'] ?></td>
                                            <td><?= $location['accuracy'] ?>m</td>
                                            <td><?= $location['speed'] ?>m/s</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                    <?php if ($transportRequest['status'] === 'assigned'): ?>
                        <button type="button" class="btn btn-success w-100 mb-2" 
                                onclick="updateStatus('picked_up')">
                            <i class="fas fa-play me-2"></i>
                            Start Transport
                        </button>
                    <?php elseif ($transportRequest['status'] === 'picked_up'): ?>
                        <button type="button" class="btn btn-primary w-100 mb-2" 
                                onclick="updateStatus('in_transit')">
                            <i class="fas fa-truck me-2"></i>
                            In Transit
                        </button>
                    <?php elseif ($transportRequest['status'] === 'in_transit'): ?>
                        <button type="button" class="btn btn-success w-100 mb-2" 
                                onclick="updateStatus('delivered')">
                            <i class="fas fa-flag-checkered me-2"></i>
                            Mark as Delivered
                        </button>
                    <?php elseif ($transportRequest['status'] === 'delivered'): ?>
                        <button type="button" class="btn btn-success w-100 mb-2" 
                                onclick="updateStatus('completed')">
                            <i class="fas fa-check me-2"></i>
                            Complete Transport
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($transportRequest['status'], ['assigned', 'picked_up', 'in_transit'])): ?>
                        <button type="button" class="btn btn-danger w-100 mb-2" 
                                onclick="updateStatus('cancelled')">
                            <i class="fas fa-times me-2"></i>
                            Cancel Transport
                        </button>
                    <?php endif; ?>
                    
                    <a href="tel:<?= htmlspecialchars($transportRequest['client_phone']) ?>" 
                       class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-phone me-2"></i>
                        Call Client
                    </a>
                    
                    <a href="mailto:<?= htmlspecialchars($transportRequest['client_email']) ?>" 
                       class="btn btn-outline-secondary w-100">
                        <i class="fas fa-envelope me-2"></i>
                        Email Client
                    </a>
                </div>
            </div>

            <!-- Schedule Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        Schedule Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Scheduled Date</label>
                        <div class="fw-bold"><?= formatDate($transportRequest['scheduled_date']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Scheduled Time</label>
                        <div class="fw-bold"><?= formatTime($transportRequest['scheduled_date']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Created On</label>
                        <div><?= formatDateTime($transportRequest['created_at']) ?></div>
                    </div>
                    <div>
                        <label class="form-label text-muted">Last Updated</label>
                        <div><?= formatDateTime($transportRequest['updated_at']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Rating -->
            <?php if ($rating): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i>
                            Client Rating
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star fa-2x <?= $i <= $rating['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="h4"><?= $rating['rating'] ?>/5</div>
                        <?php if ($rating['review']): ?>
                            <div class="alert alert-info">
                                <?= nl2br(htmlspecialchars($rating['review'])) ?>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted">Rated on <?= formatDate($rating['created_at']) ?></small>
                    </div>
                </div>
            <?php elseif ($transportRequest['status'] === 'completed'): ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Awaiting client rating</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Map Preview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map me-2"></i>
                        Route Map
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="routeMap" style="height: 250px; position: relative; background: #f0f0f0;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt fa-2x text-muted mb-2"></i>
                                <p class="text-muted small">Route map preview</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Transport Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateStatusForm">
                <div class="modal-body">
                    <input type="hidden" name="request_id" value="<?= $requestId ?>">
                    <input type="hidden" name="status" id="statusField">
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add any notes about this status update..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Location</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="number" class="form-control" id="latitude" name="latitude" 
                                       step="0.000001" placeholder="Latitude" readonly>
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" id="longitude" name="longitude" 
                                       step="0.000001" placeholder="Longitude" readonly>
                            </div>
                        </div>
                        <small class="form-text text-muted">Location will be automatically detected</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize
document.addEventListener('DOMContentLoaded', function() {
    getCurrentLocation();
});

// Get current location
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
            },
            function(error) {
                console.error('Error getting location:', error);
            }
        );
    }
}

// Update status
function updateStatus(status) {
    const statusMessages = {
        'picked_up': 'Are you sure you want to start this transport?',
        'in_transit': 'Are you sure you want to mark this transport as in transit?',
        'delivered': 'Are you sure you want to mark this transport as delivered?',
        'completed': 'Are you sure you want to complete this transport?',
        'cancelled': 'Are you sure you want to cancel this transport?'
    };
    
    if (!confirm(statusMessages[status])) {
        return;
    }
    
    document.getElementById('statusField').value = status;
    
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

// Handle form submission
document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/update-transport-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully!');
            bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
            location.reload();
        } else {
            alert(data.error || 'Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update status');
    });
});

// Helper functions
function getStatusColor(status) {
    const colors = {
        'assigned': 'info',
        'picked_up': 'primary',
        'in_transit': 'warning',
        'delivered': 'success',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'assigned': 'Assigned',
        'picked_up': 'Picked Up',
        'in_transit': 'In Transit',
        'delivered': 'Delivered',
        'completed': 'Completed',
        'cancelled': 'Cancelled'
    };
    return texts[status] || status;
}

function getStatusIcon(status) {
    const icons = {
        'assigned': 'clipboard-list',
        'picked_up': 'truck',
        'in_transit': 'route',
        'delivered': 'flag-checkered',
        'completed': 'check-circle',
        'cancelled': 'times-circle'
    };
    return icons[status] || 'circle';
}

function getPaymentStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'paid': 'success',
        'refunded' => 'info',
        'failed' => 'danger'
    };
    return colors[status] || 'secondary';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
