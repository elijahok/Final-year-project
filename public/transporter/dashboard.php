<?php
// Include configuration and functions
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is transporter
if (!isLoggedIn() || !hasRole('transporter')) {
    redirect(BASE_URL . '/public/login.php');
}

// Initialize database
$db = Database::getInstance();

// Get transporter profile
$transporter = $db->fetch("
    SELECT tp.*, u.full_name, u.email, u.phone
    FROM transporter_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.user_id = ?
", [getCurrentUser()['id']]);

if (!$transporter) {
    setFlashMessage('Transporter profile not found', 'danger');
    redirect(BASE_URL . '/public/profile.php');
}

// Get transporter statistics
$stats = [
    'active_requests' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE transporter_id = ? AND status IN ('pending', 'accepted')
    ", [$transporter['id']])['count'],
    
    'completed_today' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE transporter_id = ? AND status = 'completed' 
        AND DATE(completed_at) = CURDATE()
    ", [$transporter['id']])['count'],
    
    'total_completed' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests
        WHERE transporter_id = ? AND status = 'completed'
    ", [$transporter['id']])['count'],
    
    'total_earnings' => $db->fetch("
        SELECT COALESCE(SUM(fee), 0) as total
        FROM transport_requests
        WHERE transporter_id = ? AND status = 'completed'
    ", [$transporter['id']])['total'],
    
    'current_location' => $db->fetch("
        SELECT latitude, longitude, last_location_update
        FROM transporter_profiles
        WHERE user_id = ?
    ", [getCurrentUser()['id']])
];

// Get active transport requests
$activeRequests = $db->fetchAll("
    SELECT tr.*, u.full_name as farmer_name, u.phone as farmer_phone,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN users u ON tr.farmer_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.transporter_id = ? AND tr.status IN ('pending', 'accepted')
    ORDER BY tr.created_at DESC
    LIMIT 5
", [$transporter['id']]);

// Get recent completed requests
$recentCompleted = $db->fetchAll("
    SELECT tr.*, u.full_name as farmer_name,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN users u ON tr.farmer_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.transporter_id = ? AND tr.status = 'completed'
    ORDER BY tr.completed_at DESC
    LIMIT 5
", [$transporter['id']]);

// Get available requests (not assigned)
$availableRequests = $db->fetchAll("
    SELECT tr.*, u.full_name as farmer_name, u.phone as farmer_phone,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN users u ON tr.farmer_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.transporter_id IS NULL AND tr.status = 'pending'
    ORDER BY tr.created_at DESC
    LIMIT 10
");

// Get transporter rating
$rating = $db->fetch("
    SELECT AVG(rating) as average_rating, COUNT(*) as total_ratings
    FROM transporter_ratings
    WHERE transporter_id = ?
", [$transporter['id']]);

// Get wallet balance
$wallet = $db->fetch("
    SELECT balance, last_updated
    FROM user_wallets
    WHERE user_id = ?
", [getCurrentUser()['id']]);

$pageTitle = 'Transporter Dashboard';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Transporter Dashboard</h1>
                <div>
                    <button class="btn btn-primary me-2" onclick="updateLocation()">
                        <i class="fas fa-location-arrow me-2"></i>Update Location
                    </button>
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
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">Welcome back, <?= htmlspecialchars($transporter['full_name']) ?>!</h4>
                            <p class="mb-0">
                                <i class="fas fa-truck me-2"></i>
                                <?= $transporter['vehicle_type'] ?> • License: <?= htmlspecialchars($transporter['vehicle_license']) ?>
                                <?php if ($stats['current_location']['latitude']): ?>
                                • <i class="fas fa-map-marker-alt me-1"></i>Location updated
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="rating-display">
                                <i class="fas fa-star text-warning"></i>
                                <span class="h5"><?= number_format($rating['average_rating'] ?? 0, 1) ?></span>
                                <small class="ms-1">(<?= $rating['total_ratings'] ?? 0 ?> ratings)</small>
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
                            <h4 class="mb-0"><?= formatCurrency($wallet['balance'] ?? 0) ?></h4>
                            <p class="mb-0">Wallet Balance</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Live Tracking</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="centerMap()">
                            <i class="fas fa-crosshairs"></i> Center
                        </button>
                        <button class="btn btn-outline-primary" onclick="toggleTracking()">
                            <i class="fas fa-satellite-dish"></i> Tracking
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="transporterMap" style="height: 400px; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Requests -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Active Requests</h5>
                    <a href="<?= BASE_URL ?>/public/transporter/requests.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeRequests)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-truck fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No active requests</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activeRequests as $request): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($request['farmer_name']) ?>
                                        <br>
                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($request['pickup_location']) ?>
                                        → <?= htmlspecialchars($request['delivery_location']) ?>
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
                                        <?php if ($request['status'] === 'pending'): ?>
                                        <button class="btn btn-success btn-sm" onclick="acceptRequest(<?= $request['id'] ?>)">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="viewRequest(<?= $request['id'] ?>)">
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

        <!-- Available Requests -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Available Requests</h5>
                    <a href="<?= BASE_URL ?>/public/transporter/available-requests.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($availableRequests)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-search fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No available requests</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($availableRequests as $request): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($request['farmer_name']) ?>
                                        <br>
                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($request['pickup_location']) ?>
                                        → <?= htmlspecialchars($request['delivery_location']) ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-info">
                                            <?= formatCurrency($request['fee']) ?>
                                        </span>
                                        <span class="badge bg-secondary">
                                            <?= $request['distance'] ? round($request['distance'], 1) . ' km' : 'N/A' ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted"><?= formatDate($request['created_at']) ?></small>
                                    <div class="btn-group btn-group-sm mt-2">
                                        <button class="btn btn-success btn-sm" onclick="acceptRequest(<?= $request['id'] ?>)">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="btn btn-primary btn-sm" onclick="viewRequest(<?= $request['id'] ?>)">
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

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Completed Deliveries</h5>
                    <a href="<?= BASE_URL ?>/public/transporter/history.php" class="btn btn-sm btn-outline-primary">
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
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Farmer</th>
                                    <th>Category</th>
                                    <th>Route</th>
                                    <th>Fee</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCompleted as $request): ?>
                                <tr>
                                    <td>
                                        <div><?= formatDate($request['completed_at']) ?></div>
                                        <small class="text-muted"><?= formatTime($request['completed_at']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($request['farmer_name']) ?></td>
                                    <td><?= htmlspecialchars($request['produce_category'] ?? 'N/A') ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(substr($request['pickup_location'], 0, 20)) ?>...
                                            → <?= htmlspecialchars(substr($request['delivery_location'], 0, 20)) ?>...
                                        </small>
                                    </td>
                                    <td><?= formatCurrency($request['fee']) ?></td>
                                    <td>
                                        <?php if ($request['rating']): ?>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <?= number_format($request['rating'], 1) ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">Not rated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewRequest(<?= $request['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
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
    </div>
</main>

<!-- Request Details Modal -->
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
                <button type="button" class="btn btn-success" id="acceptRequestBtn" onclick="acceptRequestFromModal()">
                    <i class="fas fa-check me-2"></i>Accept Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.rating-display {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

#transporterMap {
    border-radius: 0.375rem;
}

.list-group-item {
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    border-left-color: #007bff;
    background-color: #f8f9fa;
}
</style>

<script>
let map;
let currentMarker;
let routePolyline;
let trackingInterval;
let isTracking = false;
let currentRequestId = null;

// Initialize map
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    
    // Check if geolocation is supported
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                updateMapCenter(lat, lng);
                updateLocationInDatabase(lat, lng);
            },
            error => {
                console.warn('Geolocation not available:', error);
                // Use default location or last known location
                <?php if ($stats['current_location']['latitude']): ?>
                updateMapCenter(<?= $stats['current_location']['latitude'] ?>, <?= $stats['current_location']['longitude'] ?>);
                <?php endif; ?>
            }
        );
    }
});

function initializeMap() {
    // Initialize Leaflet map
    map = L.map('transporterMap').setView([-1.2921, 36.8219], 13); // Default to Nairobi
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add current location marker
    currentMarker = L.marker([-1.2921, 36.8219], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: "<div style='background-color: #007bff; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);'></div>",
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map);
}

function updateMapCenter(lat, lng) {
    if (map) {
        map.setView([lat, lng], 15);
        if (currentMarker) {
            currentMarker.setLatLng([lat, lng]);
        }
    }
}

function updateLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                updateMapCenter(lat, lng);
                updateLocationInDatabase(lat, lng);
                showNotification('Location updated successfully', 'success');
            },
            error => {
                showNotification('Failed to get location: ' + error.message, 'danger');
            }
        );
    } else {
        showNotification('Geolocation is not supported by your browser', 'warning');
    }
}

function updateLocationInDatabase(lat, lng) {
    fetch('<?= BASE_URL ?>/api/update-transporter-location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            latitude: lat,
            longitude: lng,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Location updated in database');
        }
    })
    .catch(error => {
        console.error('Error updating location:', error);
    });
}

function centerMap() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                updateMapCenter(position.coords.latitude, position.coords.longitude);
            },
            error => {
                showNotification('Failed to get current location', 'danger');
            }
        );
    }
}

function toggleTracking() {
    isTracking = !isTracking;
    
    if (isTracking) {
        startTracking();
        showNotification('Location tracking enabled', 'success');
    } else {
        stopTracking();
        showNotification('Location tracking disabled', 'info');
    }
}

function startTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
    }
    
    trackingInterval = setInterval(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    updateMapCenter(lat, lng);
                    updateLocationInDatabase(lat, lng);
                },
                error => {
                    console.warn('Tracking error:', error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
    }, 30000); // Update every 30 seconds
}

function stopTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
    }
}

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
            
            // Show/hide accept button based on request status
            const acceptBtn = document.getElementById('acceptRequestBtn');
            if (data.request_status === 'pending' && !data.transporter_id) {
                acceptBtn.style.display = 'inline-block';
            } else {
                acceptBtn.style.display = 'none';
            }
            
            // Show route on map if coordinates are available
            if (data.pickup_lat && data.pickup_lng && data.delivery_lat && data.delivery_lng) {
                showRoute(data.pickup_lat, data.pickup_lng, data.delivery_lat, data.delivery_lng);
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

function showRoute(pickupLat, pickupLng, deliveryLat, deliveryLng) {
    if (!map) return;
    
    // Remove existing route
    if (routePolyline) {
        map.removeLayer(routePolyline);
    }
    
    // Add route polyline
    routePolyline = L.polyline([
        [pickupLat, pickupLng],
        [deliveryLat, deliveryLng]
    ], {
        color: '#007bff',
        weight: 4,
        opacity: 0.7,
        dashArray: '10, 10'
    }).addTo(map);
    
    // Add markers for pickup and delivery
    L.marker([pickupLat, pickupLng], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: "<div style='background-color: #28a745; width: 16px; height: 16px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);'></div>",
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        })
    }).addTo(map).bindPopup('Pickup Location');
    
    L.marker([deliveryLat, deliveryLng], {
        icon: L.divIcon({
            className: 'custom-div-icon',
            html: "<div style='background-color: #dc3545; width: 16px; height: 16px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);'></div>",
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        })
    }).addTo(map).bindPopup('Delivery Location');
    
    // Fit map to show route
    map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
}

function acceptRequest(requestId) {
    if (!confirm('Are you sure you want to accept this transport request?')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>/api/accept-transport-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            request_id: requestId,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Request accepted successfully!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.error || 'Failed to accept request', 'danger');
        }
    })
    .catch(error => {
        showNotification('Error accepting request', 'danger');
    });
}

function acceptRequestFromModal() {
    if (currentRequestId) {
        acceptRequest(currentRequestId);
    }
}

function refreshDashboard() {
    location.reload();
}

// Auto-refresh dashboard every 2 minutes
setInterval(() => {
    if (!document.hidden) {
        fetch('<?= BASE_URL ?>/api/transporter-dashboard-stats.php', {
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
                formatCurrency(stats.wallet_balance)
            ];
            element.textContent = values[index];
        }
    });
}

// Cleanup tracking on page unload
window.addEventListener('beforeunload', function() {
    stopTracking();
});
</script>

<?php include '../../includes/footer.php'; ?>
