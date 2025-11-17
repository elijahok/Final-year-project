<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a transporter
if (!isLoggedIn() || getCurrentUser()['role'] !== 'transporter') {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();

// Get assigned transport requests
$assignedRequests = $db->fetchAll("
    SELECT tr.*, u.full_name as client_name, u.phone as client_phone,
           pc.name as produce_category, p.name as produce_name
    FROM transport_requests tr
    JOIN users u ON tr.client_id = u.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    LEFT JOIN produce p ON tr.produce_id = p.id
    WHERE tr.transporter_id = ? AND tr.status = 'assigned'
    ORDER BY tr.scheduled_date ASC
", [$currentUser['id']]);

// Get today's completed requests for analytics
$completedToday = $db->fetchAll("
    SELECT tr.*, COUNT(tl.id) as location_points
    FROM transport_requests tr
    LEFT JOIN transport_locations tl ON tr.id = tl.transport_request_id
    WHERE tr.transporter_id = ? AND tr.status = 'completed'
    AND DATE(tr.updated_at) = CURDATE()
    GROUP BY tr.id
", [$currentUser['id']]);

$pageTitle = 'Route Planning - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-route me-2"></i>
                    Route Planning
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="refreshRequests">
                        <i class="fas fa-sync me-1"></i>
                        Refresh
                    </button>
                    <a href="tracking.php" class="btn btn-primary">
                        <i class="fas fa-map-marked-alt me-1"></i>
                        Live Tracking
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= count($assignedRequests) ?></h4>
                            <small>Pending Routes</small>
                        </div>
                        <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= count($completedToday) ?></h4>
                            <small>Completed Today</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="totalDistance">0</h4>
                            <small>km Today</small>
                        </div>
                        <i class="fas fa-road fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="activeTime">0</h4>
                            <small>Hours Active</small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Route Optimization -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-route me-2"></i>
                        Route Optimization
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($assignedRequests) > 1): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="optimizationType" class="form-label">Optimization Method</label>
                                <select class="form-select" id="optimizationType">
                                    <option value="nearest_neighbor">Nearest Neighbor</option>
                                    <option value="shortest_path">Shortest Path</option>
                                    <option value="time_based">Time-based</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary w-100" id="optimizeRoute">
                                    <i class="fas fa-magic me-2"></i>
                                    Optimize Route
                                </button>
                            </div>
                        </div>
                        
                        <div id="optimizationResults" style="display: none;">
                            <div class="alert alert-success">
                                <h6>Optimization Results:</h6>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <strong>Total Distance:</strong> <span id="optDistance">--</span> km
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Est. Time:</strong> <span id="optTime">--</span> min
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Total Weight:</strong> <span id="optWeight">--</span> kg
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Completion:</strong> <span id="optCompletion">--</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <h6>Optimized Sequence:</h6>
                                <ol id="optimizedSequence" class="mt-2"></ol>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Route optimization is available when you have 2 or more pending requests.
                            <?php if (count($assignedRequests) === 0): ?>
                                You have no pending requests at the moment.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Pending Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assignedRequests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No pending transport requests</p>
                            <a href="transport-requests.php" class="btn btn-outline-primary">
                                <i class="fas fa-search me-1"></i>
                                Browse Requests
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Client</th>
                                        <th>Pickup</th>
                                        <th>Delivery</th>
                                        <th>Produce</th>
                                        <th>Weight</th>
                                        <th>Scheduled</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedRequests as $request): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input request-checkbox" 
                                                       value="<?= $request['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($request['client_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['client_phone']) ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($request['pickup_location']) ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($request['delivery_location']) ?></small>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div><?= htmlspecialchars($request['produce_name'] ?: 'Mixed') ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($request['produce_category'] ?: '') ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $request['weight'] ?> kg</span>
                                            </td>
                                            <td>
                                                <small><?= formatDate($request['scheduled_date']) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="transport-details.php?id=<?= $request['id'] ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-success start-transport" 
                                                            data-id="<?= $request['id'] ?>" title="Start Transport">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" id="startSelected" disabled>
                                <i class="fas fa-play me-2"></i>
                                Start Selected Route
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="exportRoute">
                                <i class="fas fa-download me-2"></i>
                                Export Route
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Route Map -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map me-2"></i>
                        Route Map
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="routeMap" style="height: 300px; position: relative; background: #f0f0f0;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt fa-2x text-muted mb-2"></i>
                                <p class="text-muted small">Route map will be displayed here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Route Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Route Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div id="routeStats">
                        <div class="text-center py-3">
                            <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">Select requests to view statistics</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $recentActivity = $db->fetchAll("
                        SELECT al.*, tr.pickup_location, tr.delivery_location
                        FROM audit_logs al
                        LEFT JOIN transport_requests tr ON al.target_id = tr.id
                        WHERE al.user_id = ? AND al.action IN ('accept_transport_request', 'start_transport', 'complete_transport')
                        ORDER BY al.created_at DESC
                        LIMIT 5
                    ", [$currentUser['id']]);
                    ?>
                    
                    <?php if (empty($recentActivity)): ?>
                        <p class="text-muted text-center">No recent activity</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <i class="fas fa-circle fa-xs text-primary"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <small class="text-muted"><?= formatDateTime($activity['created_at']) ?></small>
                                        <div class="small"><?= htmlspecialchars($activity['description']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    margin-bottom: 15px;
}
.timeline-marker {
    position: absolute;
    left: -20px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-content {
    margin-left: 0;
}
</style>

<script>
let selectedRequests = [];
let optimizedRoute = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadTodayStats();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.request-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedRequests();
    });
    
    // Individual checkboxes
    document.querySelectorAll('.request-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedRequests);
    });
    
    // Optimize route button
    document.getElementById('optimizeRoute').addEventListener('click', optimizeRoute);
    
    // Start selected button
    document.getElementById('startSelected').addEventListener('click', startSelectedRoute);
    
    // Export route button
    document.getElementById('exportRoute').addEventListener('click', exportRoute);
    
    // Start transport buttons
    document.querySelectorAll('.start-transport').forEach(button => {
        button.addEventListener('click', function() {
            startTransport(this.dataset.id);
        });
    });
    
    // Refresh button
    document.getElementById('refreshRequests').addEventListener('click', function() {
        location.reload();
    });
}

// Update selected requests
function updateSelectedRequests() {
    const checkboxes = document.querySelectorAll('.request-checkbox:checked');
    selectedRequests = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    document.getElementById('startSelected').disabled = selectedRequests.length === 0;
    updateRouteStats();
}

// Optimize route
function optimizeRoute() {
    if (selectedRequests.length < 2) {
        alert('Please select at least 2 requests for route optimization');
        return;
    }
    
    const optimizationType = document.getElementById('optimizationType').value;
    
    fetch('<?= BASE_URL ?>/api/route-optimization.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            request_ids: selectedRequests,
            type: optimizationType,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOptimizationResults(data);
            optimizedRoute = data.route;
            updateRouteMap(data.route);
        } else {
            alert(data.error || 'Failed to optimize route');
        }
    })
    .catch(error => {
        console.error('Error optimizing route:', error);
        alert('Failed to optimize route');
    });
}

// Display optimization results
function displayOptimizationResults(data) {
    document.getElementById('optDistance').textContent = data.statistics.total_distance_km;
    document.getElementById('optTime').textContent = data.statistics.total_time_minutes;
    document.getElementById('optWeight').textContent = data.statistics.total_weight_kg;
    document.getElementById('optCompletion').textContent = data.statistics.estimated_completion_time;
    
    const sequenceDiv = document.getElementById('optimizedSequence');
    sequenceDiv.innerHTML = '';
    
    data.route.forEach((request, index) => {
        const li = document.createElement('li');
        li.className = 'mb-2';
        li.innerHTML = `
            <strong>${request.client_name}</strong><br>
            <small class="text-muted">
                ${request.pickup_location} → ${request.delivery_location}<br>
                Distance: ${request.distance_from_previous.toFixed(1)} km
            </small>
        `;
        sequenceDiv.appendChild(li);
    });
    
    document.getElementById('optimizationResults').style.display = 'block';
}

// Update route map
function updateRouteMap(route) {
    // Placeholder for map updates
    // In production, integrate with Google Maps API
    console.log('Updating map with route:', route);
}

// Update route statistics
function updateRouteStats() {
    if (selectedRequests.length === 0) {
        document.getElementById('routeStats').innerHTML = `
            <div class="text-center py-3">
                <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                <p class="text-muted small">Select requests to view statistics</p>
            </div>
        `;
        return;
    }
    
    // Calculate basic statistics
    const totalWeight = selectedRequests.length * 100; // Placeholder
    const totalDistance = selectedRequests.length * 15; // Placeholder
    
    document.getElementById('routeStats').innerHTML = `
        <div class="row text-center">
            <div class="col-6 mb-3">
                <div class="h5 text-primary mb-1">${selectedRequests.length}</div>
                <small class="text-muted">Requests</small>
            </div>
            <div class="col-6 mb-3">
                <div class="h5 text-success mb-1">${totalWeight} kg</div>
                <small class="text-muted">Total Weight</small>
            </div>
            <div class="col-6">
                <div class="h5 text-info mb-1">${totalDistance} km</div>
                <small class="text-muted">Est. Distance</small>
            </div>
            <div class="col-6">
                <div class="h5 text-warning mb-1">${selectedRequests.length * 30} min</div>
                <small class="text-muted">Est. Time</small>
            </div>
        </div>
    `;
}

// Start selected route
function startSelectedRoute() {
    if (selectedRequests.length === 0) {
        alert('No requests selected');
        return;
    }
    
    if (!confirm(`Start transport for ${selectedRequests.length} selected requests?`)) {
        return;
    }
    
    // Start the first request and navigate to tracking
    window.location.href = 'tracking.php';
}

// Start single transport
function startTransport(requestId) {
    if (!confirm('Start this transport request?')) {
        return;
    }
    
    // Update request status
    fetch('<?= BASE_URL ?>/api/update-transport-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'request_id': requestId,
            'status': 'picked_up',
            'csrf_token': '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Transport started successfully');
            window.location.href = 'tracking.php';
        } else {
            alert(data.error || 'Failed to start transport');
        }
    })
    .catch(error => {
        console.error('Error starting transport:', error);
        alert('Failed to start transport');
    });
}

// Export route
function exportRoute() {
    if (selectedRequests.length === 0) {
        alert('No requests selected');
        return;
    }
    
    const url = '<?= BASE_URL ?>/api/export-transport-route.php?ids=' + selectedRequests.join(',');
    window.open(url, '_blank');
}

// Load today's statistics
function loadTodayStats() {
    fetch('<?= BASE_URL ?>/api/transporter-stats.php?stat=total_distance_today')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalDistance').textContent = data.distance;
        }
    })
    .catch(error => console.error('Error loading distance stats:', error));
    
    fetch('<?= BASE_URL ?>/api/transporter-stats.php?stat=active_time_today')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('activeTime').textContent = data.hours;
        }
    })
    .catch(error => console.error('Error loading time stats:', error));
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
