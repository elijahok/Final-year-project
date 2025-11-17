<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || getCurrentUser()['role'] !== 'farmer') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$requestId = intval($_GET['id'] ?? 0);

if ($requestId <= 0) {
    redirect(BASE_URL . '/public/farmer/transport-requests.php');
}

// Get transport request details
$request = $db->fetch("
    SELECT tr.*, u.full_name as transporter_name, u.phone as transporter_phone,
           tp.vehicle_type, tp.vehicle_number
    FROM transport_requests tr
    JOIN users u ON tr.transporter_id = u.id
    JOIN transporter_profiles tp ON u.id = tp.user_id
    WHERE tr.id = ? AND tr.client_id = ?
", [$requestId, $currentUser['id']]);

if (!$request) {
    redirect(BASE_URL . '/public/farmer/transport-requests.php');
}

// Only allow tracking for active requests
if (!in_array($request['status'], ['assigned', 'picked_up'])) {
    redirect(BASE_URL . '/public/farmer/transport-details.php?id=' . $requestId);
}

$pageTitle = 'Track Transport - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Track Transport
                </h1>
                <div>
                    <a href="transport-details.php?id=<?= $requestId ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Details
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map me-2"></i>
                        Live Tracking
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 500px; position: relative; background: #f0f0f0;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Loading map...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-route me-2"></i>
                        Route Progress
                    </h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 20%">
                            20% Complete
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="step-item <?= $request['status'] === 'assigned' ? 'active' : 'completed' ?>">
                                <div class="step-icon">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <small class="d-block mt-2">Assigned</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="step-item <?= $request['status'] === 'picked_up' ? 'active' : ($request['status'] === 'in_transit' ? 'completed' : '') ?>">
                                <div class="step-icon">
                                    <i class="fas fa-truck-loading"></i>
                                </div>
                                <small class="d-block mt-2">Picked Up</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="step-item <?= $request['status'] === 'in_transit' ? 'active' : ($request['status'] === 'delivered' ? 'completed' : '') ?>">
                                <div class="step-icon">
                                    <i class="fas fa-shipping-fast"></i>
                                </div>
                                <small class="d-block mt-2">In Transit</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="step-item <?= $request['status'] === 'delivered' ? 'completed' : '' ?>">
                                <div class="step-icon">
                                    <i class="fas fa-flag-checkered"></i>
                                </div>
                                <small class="d-block mt-2">Delivered</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Transport Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Transporter</label>
                        <div class="fw-bold"><?= htmlspecialchars($request['transporter_name']) ?></div>
                        <div class="text-muted"><?= htmlspecialchars($request['transporter_phone']) ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Vehicle</label>
                        <div class="fw-bold"><?= htmlspecialchars($request['vehicle_type']) ?></div>
                        <div class="text-muted"><?= htmlspecialchars($request['vehicle_number']) ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Pickup Location</label>
                        <div class="fw-bold"><?= htmlspecialchars($request['pickup_location']) ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Delivery Location</label>
                        <div class="fw-bold"><?= htmlspecialchars($request['delivery_location']) ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Scheduled Date</label>
                        <div class="fw-bold"><?= formatDate($request['scheduled_date']) ?></div>
                    </div>
                    
                    <div>
                        <label class="text-muted small">Status</label>
                        <div>
                            <span class="badge bg-<?= getRequestStatusColor($request['status']) ?> fs-6">
                                <?= ucfirst($request['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-location-arrow me-2"></i>
                        Current Location
                    </h5>
                </div>
                <div class="card-body">
                    <div id="transporterLocation">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">Getting location...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Estimated Arrival
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="h4 text-primary mb-2" id="etaTime">--:--</div>
                        <small class="text-muted">Estimated time of arrival</small>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Distance remaining:</span>
                            <strong id="distanceRemaining">-- km</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted">Time remaining:</span>
                            <strong id="timeRemaining">-- min</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-phone me-2"></i>
                        Contact Transporter
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="tel:<?= htmlspecialchars($request['transporter_phone']) ?>" class="btn btn-primary">
                            <i class="fas fa-phone me-2"></i>
                            Call Transporter
                        </a>
                        <button type="button" class="btn btn-outline-primary" id="sendMessage">
                            <i class="fas fa-comment me-2"></i>
                            Send Message
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.step-item {
    opacity: 0.5;
    transition: opacity 0.3s;
}
.step-item.active {
    opacity: 1;
}
.step-item.completed {
    opacity: 1;
}
.step-item.completed .step-icon {
    background-color: #28a745;
    color: white;
}
.step-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 1.2rem;
}
.step-item.active .step-icon {
    background-color: #007bff;
    color: white;
}
</style>

<script>
let updateInterval = null;
let map = null;

// Initialize tracking
function initTracking() {
    // Load transporter location
    loadTransporterLocation();
    
    // Update every 30 seconds
    updateInterval = setInterval(loadTransporterLocation, 30000);
    
    // Initialize map (placeholder)
    initMap();
}

// Load transporter location
function loadTransporterLocation() {
    fetch(`<?= BASE_URL ?>/api/gps-tracking.php?action=get_location&transporter_id=<?= $request['transporter_id'] }`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateTransporterLocation(data.transporter);
            updateMap(data.transporter);
            updateETA(data.transporter);
        } else {
            console.error('Failed to load location:', data.error);
        }
    })
    .catch(error => {
        console.error('Error loading location:', error);
    });
}

// Update transporter location display
function updateTransporterLocation(transporter) {
    const locationDiv = document.getElementById('transporterLocation');
    const location = transporter.current_location;
    
    if (location.latitude && location.longitude) {
        locationDiv.innerHTML = `
            <div class="small">
                <div class="mb-2">
                    <strong>Coordinates:</strong><br>
                    ${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}
                </div>
                <div class="mb-2">
                    <strong>Accuracy:</strong> ±${location.accuracy.toFixed(0)}m
                </div>
                ${location.speed ? `<div class="mb-2"><strong>Speed:</strong> ${(location.speed * 3.6).toFixed(1)} km/h</div>` : ''}
                <div class="text-muted">
                    Last update: ${new Date(location.last_update).toLocaleString()}
                </div>
            </div>
        `;
    } else {
        locationDiv.innerHTML = `
            <div class="text-center py-3">
                <i class="fas fa-question-circle fa-2x text-muted mb-2"></i>
                <p class="text-muted small">Location not available</p>
            </div>
        `;
    }
}

// Update ETA
function updateETA(transporter) {
    const location = transporter.current_location;
    
    if (location.latitude && location.longitude) {
        // Calculate distance from transporter to delivery location
        const distance = calculateDistance(
            location.latitude, location.longitude,
            <?= $request['delivery_latitude'] ?>, <?= $request['delivery_longitude'] ?>
        );
        
        // Estimate time based on average speed
        const avgSpeed = location.speed * 3.6 || 40; // km/h, fallback to 40 if no speed data
        const timeMinutes = (distance / avgSpeed) * 60;
        
        // Calculate ETA
        const now = new Date();
        const eta = new Date(now.getTime() + timeMinutes * 60000);
        
        document.getElementById('etaTime').textContent = eta.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        document.getElementById('distanceRemaining').textContent = distance.toFixed(1) + ' km';
        document.getElementById('timeRemaining').textContent = Math.round(timeMinutes) + ' min';
        
        // Update progress bar
        const totalDistance = calculateDistance(
            <?= $request['pickup_latitude'] ?>, <?= $request['pickup_longitude'] ?>,
            <?= $request['delivery_latitude'] ?>, <?= $request['delivery_longitude'] ?>
        );
        const distanceCovered = totalDistance - distance;
        const progressPercentage = Math.min(Math.round((distanceCovered / totalDistance) * 100), 95);
        
        const progressBar = document.getElementById('progressBar');
        progressBar.style.width = progressPercentage + '%';
        progressBar.textContent = progressPercentage + '% Complete';
    }
}

// Calculate distance between two points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Initialize map (placeholder)
function initMap() {
    // This is a placeholder for map initialization
    // In production, integrate with Google Maps API or similar
    const mapDiv = document.getElementById('map');
    mapDiv.innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="text-center">
                <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">Live tracking map would be displayed here</p>
                <small class="text-muted">Integrate with Google Maps API for full functionality</small>
            </div>
        </div>
    `;
}

// Update map with location data
function updateMap(transporter) {
    // Placeholder for map updates
    // In production, update map markers and route line
    console.log('Updating map with transporter location:', transporter.current_location);
}

// Send message functionality
document.getElementById('sendMessage').addEventListener('click', function() {
    // This would open a messaging interface
    // For now, just show an alert
    alert('Messaging feature would be implemented here. You can call the transporter directly using the button above.');
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTracking();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
