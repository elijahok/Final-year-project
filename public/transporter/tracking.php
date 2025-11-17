<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a transporter
if (!isLoggedIn() || getCurrentUser()['role'] !== 'transporter') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$activeRequests = $db->fetchAll("
    SELECT tr.*, u.full_name as client_name, u.phone as client_phone
    FROM transport_requests tr
    JOIN users u ON tr.client_id = u.id
    WHERE tr.transporter_id = ? AND tr.status IN ('assigned', 'picked_up')
    ORDER BY tr.scheduled_date ASC
", [$currentUser['id']]);

$pageTitle = 'GPS Tracking - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    GPS Tracking
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="startTracking">
                        <i class="fas fa-play me-1"></i>
                        Start Tracking
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="stopTracking" disabled>
                        <i class="fas fa-stop me-1"></i>
                        Stop Tracking
                    </button>
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
                        Live Map
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 500px; position: relative; background: #f0f0f0;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Click "Start Tracking" to begin GPS tracking</p>
                                <small class="text-muted">Make sure location services are enabled on your device</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-route me-2"></i>
                        Route Optimization
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($activeRequests) > 1): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You have multiple active requests. Use route optimization to find the most efficient delivery sequence.
                        </div>
                        <button type="button" class="btn btn-primary" id="optimizeRoute">
                            <i class="fas fa-route me-2"></i>
                            Optimize Route
                        </button>
                        <div id="optimizedRoute" class="mt-3" style="display: none;">
                            <h6>Optimized Route Sequence:</h6>
                            <ol id="routeSequence" class="mt-2"></ol>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Route optimization is available when you have multiple active requests.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Active Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activeRequests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No active transport requests</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activeRequests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($request['client_name']) ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= htmlspecialchars($request['pickup_location']) ?>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <i class="fas fa-flag-checkered me-1"></i>
                                                <?= htmlspecialchars($request['delivery_location']) ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-<?= getRequestStatusColor($request['status']) ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Scheduled: <?= formatDate($request['scheduled_date']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                    <div id="currentLocation">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">Waiting for location data...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Today's Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h4 text-primary mb-1" id="distanceTraveled">0</div>
                            <small class="text-muted">km traveled</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h4 text-success mb-1" id="deliveriesCompleted">0</div>
                            <small class="text-muted">deliveries</small>
                        </div>
                        <div class="col-6">
                            <div class="h4 text-info mb-1" id="averageSpeed">0</div>
                            <small class="text-muted">avg km/h</small>
                        </div>
                        <div class="col-6">
                            <div class="h4 text-warning mb-1" id="activeTime">0</div>
                            <small class="text-muted">hours active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for location updates -->
<form id="locationForm" style="display: none;">
    <input type="hidden" name="latitude" id="latitude">
    <input type="hidden" name="longitude" id="longitude">
    <input type="hidden" name="accuracy" id="accuracy">
    <input type="hidden" name="speed" id="speed">
    <input type="hidden" name="heading" id="heading">
    <input type="hidden" name="timestamp" id="timestamp">
</form>

<script>
let trackingInterval = null;
let watchPositionId = null;
let totalDistance = 0;
let lastPosition = null;
let trackingStartTime = null;

// Initialize map (placeholder - would integrate with Google Maps or similar)
function initMap() {
    // This is a placeholder for map initialization
    // In production, integrate with Google Maps API or similar
    console.log('Map initialized');
}

// Start GPS tracking
document.getElementById('startTracking').addEventListener('click', function() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    this.disabled = true;
    document.getElementById('stopTracking').disabled = false;
    trackingStartTime = new Date();
    
    // Start watching position
    watchPositionId = navigator.geolocation.watchPosition(
        function(position) {
            updateLocation(position);
        },
        function(error) {
            console.error('Geolocation error:', error);
            showError('Unable to get your location. Please check location permissions.');
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
    
    // Update statistics every 30 seconds
    trackingInterval = setInterval(updateStatistics, 30000);
    
    showSuccess('GPS tracking started');
});

// Stop GPS tracking
document.getElementById('stopTracking').addEventListener('click', function() {
    if (watchPositionId) {
        navigator.geolocation.clearWatch(watchPositionId);
        watchPositionId = null;
    }
    
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
    }
    
    this.disabled = true;
    document.getElementById('startTracking').disabled = false;
    
    showSuccess('GPS tracking stopped');
});

// Update location
function updateLocation(position) {
    const coords = position.coords;
    
    // Update form fields
    document.getElementById('latitude').value = coords.latitude;
    document.getElementById('longitude').value = coords.longitude;
    document.getElementById('accuracy').value = coords.accuracy;
    document.getElementById('speed').value = coords.speed || 0;
    document.getElementById('heading').value = coords.heading || 0;
    document.getElementById('timestamp').value = new Date().toISOString();
    
    // Calculate distance if we have previous position
    if (lastPosition) {
        const distance = calculateDistance(
            lastPosition.latitude, lastPosition.longitude,
            coords.latitude, coords.longitude
        );
        totalDistance += distance;
    }
    
    lastPosition = {
        latitude: coords.latitude,
        longitude: coords.longitude,
        accuracy: coords.accuracy,
        speed: coords.speed,
        heading: coords.heading,
        timestamp: position.timestamp
    };
    
    // Update current location display
    updateLocationDisplay(lastPosition);
    
    // Send location to server
    const formData = new FormData(document.getElementById('locationForm'));
    formData.append('action', 'update_location');
    
    fetch('<?= BASE_URL ?>/api/gps-tracking.php?action=update_location', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to update location:', data.error);
        }
    })
    .catch(error => {
        console.error('Error updating location:', error);
    });
}

// Update location display
function updateLocationDisplay(location) {
    const locationDiv = document.getElementById('currentLocation');
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
            ${location.heading ? `<div class="mb-2"><strong>Heading:</strong> ${location.heading.toFixed(0)}°</div>` : ''}
            <div class="text-muted">
                Last update: ${new Date(location.timestamp).toLocaleTimeString()}
            </div>
        </div>
    `;
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

// Update statistics
function updateStatistics() {
    document.getElementById('distanceTraveled').textContent = totalDistance.toFixed(1);
    
    if (trackingStartTime) {
        const hoursActive = (new Date() - trackingStartTime) / (1000 * 60 * 60);
        document.getElementById('activeTime').textContent = hoursActive.toFixed(1);
        
        if (hoursActive > 0) {
            const avgSpeed = totalDistance / hoursActive;
            document.getElementById('averageSpeed').textContent = avgSpeed.toFixed(1);
        }
    }
    
    // Get completed deliveries count
    fetch('<?= BASE_URL ?>/api/transporter-stats.php?stat=completed_today')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('deliveriesCompleted').textContent = data.count;
        }
    })
    .catch(error => console.error('Error fetching stats:', error));
}

// Optimize route
document.getElementById('optimizeRoute').addEventListener('click', function() {
    const requestIds = <?= json_encode(array_column($activeRequests, 'id')) ?>;
    
    fetch('<?= BASE_URL ?>/api/gps-tracking.php?action=optimize_route', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'request_ids': JSON.stringify(requestIds),
            'csrf_token': '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOptimizedRoute(data.optimized_route);
            showSuccess('Route optimized successfully');
        } else {
            showError(data.error || 'Failed to optimize route');
        }
    })
    .catch(error => {
        console.error('Error optimizing route:', error);
        showError('Failed to optimize route');
    });
});

// Display optimized route
function displayOptimizedRoute(route) {
    const routeDiv = document.getElementById('optimizedRoute');
    const sequenceDiv = document.getElementById('routeSequence');
    
    sequenceDiv.innerHTML = '';
    route.forEach((request, index) => {
        const li = document.createElement('li');
        li.className = 'mb-2';
        li.innerHTML = `
            <strong>${request.client_name}</strong><br>
            <small class="text-muted">
                ${request.pickup_location} → ${request.delivery_location}<br>
                Distance from previous: ${request.distance_from_previous.toFixed(1)} km
            </small>
        `;
        sequenceDiv.appendChild(li);
    });
    
    routeDiv.style.display = 'block';
}

// Utility functions
function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

function showError(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initMap();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
