<?php
// Include configuration and functions
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is farmer
if (!isLoggedIn() || !hasRole('farmer')) {
    redirect(BASE_URL . '/public/login.php');
}

// Initialize database
$db = Database::getInstance();

// Get farmer profile
$farmer = $db->fetch("
    SELECT fp.*, u.full_name, u.email, u.phone
    FROM farmer_profiles fp
    JOIN users u ON fp.user_id = u.id
    WHERE fp.user_id = ?
", [getCurrentUser()['id']]);

if (!$farmer) {
    setFlashMessage('Farmer profile not found', 'danger');
    redirect(BASE_URL . '/public/profile.php');
}

// Get produce categories
$categories = $db->fetchAll("
    SELECT * FROM produce_categories
    ORDER BY name ASC
");

// Get available transporters (active and with good ratings)
$transporters = $db->fetchAll("
    SELECT tp.*, u.full_name, u.phone, AVG(tr.rating) as average_rating, COUNT(tr.rating) as total_ratings
    FROM transporter_profiles tp
    JOIN users u ON tp.user_id = u.id
    LEFT JOIN transporter_ratings tr ON tp.id = tr.transporter_id
    WHERE tp.status = 'active'
    GROUP BY tp.id
    HAVING average_rating >= 3 OR average_rating IS NULL
    ORDER BY average_rating DESC, tp.created_at ASC
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
        redirect(BASE_URL . '/public/farmer/request-transport.php');
    }
    
    // Get form data
    $pickupLocation = sanitizeInput($_POST['pickup_location']);
    $deliveryLocation = sanitizeInput($_POST['delivery_location']);
    $produceCategoryId = (int)($_POST['produce_category_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $weight = (float)($_POST['weight'] ?? 0);
    $vehicleType = sanitizeInput($_POST['vehicle_type']);
    $urgency = sanitizeInput($_POST['urgency']);
    $specialInstructions = sanitizeInput($_POST['special_instructions'] ?? '');
    $preferredTransporterId = (int)($_POST['preferred_transporter_id'] ?? 0);
    $pickupDate = sanitizeInput($_POST['pickup_date']);
    $pickupTime = sanitizeInput($_POST['pickup_time']);
    $estimatedDistance = (float)($_POST['estimated_distance'] ?? 0);
    
    // Validate required fields
    $errors = [];
    
    if (empty($pickupLocation)) {
        $errors[] = 'Pickup location is required';
    }
    
    if (empty($deliveryLocation)) {
        $errors[] = 'Delivery location is required';
    }
    
    if ($produceCategoryId <= 0) {
        $errors[] = 'Produce category is required';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    if ($weight <= 0) {
        $errors[] = 'Weight must be greater than 0';
    }
    
    if (empty($vehicleType)) {
        $errors[] = 'Vehicle type is required';
    }
    
    if (empty($urgency)) {
        $errors[] = 'Urgency level is required';
    }
    
    if (empty($pickupDate)) {
        $errors[] = 'Pickup date is required';
    }
    
    if (empty($pickupTime)) {
        $errors[] = 'Pickup time is required';
    }
    
    // Validate date and time
    $pickupDateTime = $pickupDate . ' ' . $pickupTime;
    if (strtotime($pickupDateTime) <= strtotime(date('Y-m-d H:i:s'))) {
        $errors[] = 'Pickup date and time must be in the future';
    }
    
    // Calculate fee based on distance, weight, urgency, and vehicle type
    $baseFee = 500; // Base fee in KES
    $distanceFee = $estimatedDistance * 50; // 50 KES per km
    $weightFee = $weight * 10; // 10 KES per kg
    $urgencyMultiplier = [
        'low' => 1.0,
        'medium' => 1.2,
        'high' => 1.5,
        'emergency' => 2.0
    ];
    $vehicleMultiplier = [
        'motorcycle' => 1.0,
        'pickup' => 1.3,
        'truck_small' => 1.6,
        'truck_large' => 2.0
    ];
    
    $totalFee = $baseFee + $distanceFee + $weightFee;
    $totalFee *= $urgencyMultiplier[$urgency] ?? 1.0;
    $totalFee *= $vehicleMultiplier[$vehicleType] ?? 1.0;
    
    // Check wallet balance
    $wallet = $db->fetch("
        SELECT balance FROM user_wallets WHERE user_id = ?
    ", [getCurrentUser()['id']]);
    
    if ($wallet['balance'] < $totalFee) {
        $errors[] = 'Insufficient wallet balance. Please top up your wallet.';
    }
    
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'danger');
        redirect(BASE_URL . '/public/farmer/request-transport.php');
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Create transport request
        $requestId = $db->insert('transport_requests', [
            'farmer_id' => $farmer['id'],
            'transporter_id' => $preferredTransporterId > 0 ? $preferredTransporterId : null,
            'produce_category_id' => $produceCategoryId,
            'pickup_location' => $pickupLocation,
            'delivery_location' => $deliveryLocation,
            'pickup_date' => $pickupDate,
            'pickup_time' => $pickupTime,
            'quantity' => $quantity,
            'weight' => $weight,
            'vehicle_type' => $vehicleType,
            'urgency' => $urgency,
            'special_instructions' => $specialInstructions,
            'estimated_distance' => $estimatedDistance,
            'fee' => $totalFee,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Deduct fee from wallet
        $db->query("
            UPDATE user_wallets 
            SET balance = balance - ?, last_updated = NOW()
            WHERE user_id = ?
        ", [$totalFee, getCurrentUser()['id']]);
        
        // Create wallet transaction
        $db->insert('mobile_money_transactions', [
            'user_id' => getCurrentUser()['id'],
            'transaction_type' => 'transport_payment',
            'amount' => $totalFee,
            'transaction_id' => 'TR_' . time() . '_' . getCurrentUser()['id'],
            'status' => 'completed',
            'description' => 'Transport request payment for request #' . $requestId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        logActivity(getCurrentUser()['id'], 'create', 'transport_request', $requestId, 
                   "Created transport request from {$pickupLocation} to {$deliveryLocation}");
        
        // Notify preferred transporter if specified
        if ($preferredTransporterId > 0) {
            $transporterUser = $db->fetch("
                SELECT user_id FROM transporter_profiles WHERE id = ?
            ", [$preferredTransporterId]);
            
            if ($transporterUser) {
                createNotification(
                    $transporterUser['user_id'],
                    'New Transport Request',
                    "You have been preferred for a transport request from {$pickupLocation} to {$deliveryLocation}",
                    'transport_request',
                    $requestId
                );
            }
        }
        
        // Notify all active transporters if no preferred transporter
        if ($preferredTransporterId <= 0) {
            $activeTransporters = $db->fetchAll("
                SELECT user_id FROM transporter_profiles WHERE status = 'active'
            ");
            
            foreach ($activeTransporters as $transporter) {
                createNotification(
                    $transporter['user_id'],
                    'New Transport Request Available',
                    "A new transport request is available from {$pickupLocation} to {$deliveryLocation}",
                    'transport_request',
                    $requestId
                );
            }
        }
        
        // Commit transaction
        $db->commit();
        
        setFlashMessage('Transport request created successfully! Transporters will be notified.', 'success');
        redirect(BASE_URL . '/public/farmer/transport-requests.php');
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        
        error_log("Transport request creation error: " . $e->getMessage());
        setFlashMessage('Failed to create transport request. Please try again.', 'danger');
        redirect(BASE_URL . '/public/farmer/request-transport.php');
    }
}

$pageTitle = 'Request Transport';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Request Transport</h1>
                <a href="<?= BASE_URL ?>/public/farmer/dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Transport Request Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="transportRequestForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Location Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-map-marker-alt me-2"></i>Location Information
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="pickup_location" class="form-label">Pickup Location *</label>
                                <textarea class="form-control" id="pickup_location" name="pickup_location" 
                                          rows="2" required placeholder="Enter pickup address or location"></textarea>
                                <div class="form-text">Detailed pickup address for the transporter</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="delivery_location" class="form-label">Delivery Location *</label>
                                <textarea class="form-control" id="delivery_location" name="delivery_location" 
                                          rows="2" required placeholder="Enter delivery address or location"></textarea>
                                <div class="form-text">Where the produce should be delivered</div>
                            </div>
                        </div>
                        
                        <!-- Produce Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-seedling me-2"></i>Produce Information
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="produce_category_id" class="form-label">Produce Category *</label>
                                <select class="form-select" id="produce_category_id" name="produce_category_id" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           min="0.1" step="0.1" required placeholder="0.0">
                                    <select class="form-select" style="max-width: 100px;">
                                        <option>kg</option>
                                        <option>tons</option>
                                        <option>bags</option>
                                        <option>crates</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="weight" class="form-label">Weight (kg) *</label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       min="0.1" step="0.1" required placeholder="0.0">
                                <div class="form-text">Total weight of the produce</div>
                            </div>
                        </div>
                        
                        <!-- Transport Requirements -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-truck me-2"></i>Transport Requirements
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="vehicle_type" class="form-label">Vehicle Type *</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                    <option value="">Select vehicle type</option>
                                    <option value="motorcycle">Motorcycle</option>
                                    <option value="pickup">Pickup Truck</option>
                                    <option value="truck_small">Small Truck</option>
                                    <option value="truck_large">Large Truck</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="urgency" class="form-label">Urgency Level *</label>
                                <select class="form-select" id="urgency" name="urgency" required>
                                    <option value="">Select urgency</option>
                                    <option value="low">Low (Within 24 hours)</option>
                                    <option value="medium">Medium (Within 12 hours)</option>
                                    <option value="high">High (Within 6 hours)</option>
                                    <option value="emergency">Emergency (Within 2 hours)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="estimated_distance" class="form-label">Estimated Distance (km)</label>
                                <input type="number" class="form-control" id="estimated_distance" name="estimated_distance" 
                                       min="0.1" step="0.1" placeholder="0.0">
                                <div class="form-text">Optional - helps calculate accurate fee</div>
                            </div>
                        </div>
                        
                        <!-- Schedule -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-calendar me-2"></i>Pickup Schedule
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="pickup_date" class="form-label">Pickup Date *</label>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                       required min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="pickup_time" class="form-label">Pickup Time *</label>
                                <input type="time" class="form-control" id="pickup_time" name="pickup_time" required>
                            </div>
                        </div>
                        
                        <!-- Preferred Transporter -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>Preferred Transporter (Optional)
                                </h6>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="preferred_transporter_id" class="form-label">Select Transporter</label>
                                <select class="form-select" id="preferred_transporter_id" name="preferred_transporter_id">
                                    <option value="">No preference (Available to all)</option>
                                    <?php foreach ($transporters as $transporter): ?>
                                    <option value="<?= $transporter['id'] ?>">
                                        <?= htmlspecialchars($transporter['full_name']) ?> - 
                                        <?= htmlspecialchars($transporter['vehicle_type']) ?> -
                                        <?php if ($transporter['average_rating']): ?>
                                        <i class="fas fa-star text-warning"></i> <?= number_format($transporter['average_rating'], 1) ?>
                                        (<?= $transporter['total_ratings'] ?> reviews)
                                        <?php else: ?>
                                        No ratings yet
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select a preferred transporter or leave open for all transporters</div>
                            </div>
                        </div>
                        
                        <!-- Special Instructions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Additional Information
                                </h6>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="special_instructions" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="special_instructions" name="special_instructions" 
                                          rows="3" placeholder="Any special handling instructions or additional details..."></textarea>
                                <div class="form-text">Optional - Special requirements for handling the produce</div>
                            </div>
                        </div>
                        
                        <!-- Fee Calculation -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-calculator me-2"></i>Fee Calculation
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Base Fee:</span>
                                                    <span id="baseFee">KES 500</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Distance Fee:</span>
                                                    <span id="distanceFee">KES 0</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Weight Fee:</span>
                                                    <span id="weightFee">KES 0</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Urgency Multiplier:</span>
                                                    <span id="urgencyMultiplier">1.0x</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Vehicle Multiplier:</span>
                                                    <span id="vehicleMultiplier">1.0x</span>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between fw-bold">
                                                    <span>Total Fee:</span>
                                                    <span id="totalFee" class="text-primary">KES 500</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="<?= BASE_URL ?>/public/farmer/dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary me-2" onclick="saveDraft()">
                                            <i class="fas fa-save me-2"></i>Save Draft
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Wallet Balance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-wallet me-2"></i>Wallet Balance
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="h3 text-primary"><?= formatCurrency($wallet['balance'] ?? 0) ?></div>
                        <small class="text-muted">Available balance</small>
                    </div>
                    <div class="mt-3">
                        <a href="<?= BASE_URL ?>/public/farmer/wallet.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus me-2"></i>Top Up Wallet
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Tips
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Provide accurate pickup and delivery locations
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Specify correct weight for accurate pricing
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Choose appropriate urgency level
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Add special instructions for fragile items
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Preferred transporters respond faster
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Requests -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Requests
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $recentRequests = $db->fetchAll("
                        SELECT tr.*, pc.name as produce_category
                        FROM transport_requests tr
                        LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
                        WHERE tr.farmer_id = ?
                        ORDER BY tr.created_at DESC
                        LIMIT 3
                    ", [$farmer['id']]);
                    ?>
                    
                    <?php if (empty($recentRequests)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No recent requests</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentRequests as $request): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></div>
                                    <small class="text-muted"><?= formatDate($request['created_at']) ?></small>
                                </div>
                                <span class="badge bg-<?= getStatusColor($request['status']) ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.form-text {
    font-size: 0.875rem;
    color: #6c757d;
}

.card.bg-light {
    background-color: #f8f9fa !important;
}

.list-group-item {
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    border-left-color: #28a745;
    background-color: #f8f9fa;
}
</style>

<script>
// Fee calculation variables
const baseFee = 500;
const distanceRate = 50; // per km
const weightRate = 10; // per kg

const urgencyMultipliers = {
    'low': 1.0,
    'medium': 1.2,
    'high': 1.5,
    'emergency': 2.0
};

const vehicleMultipliers = {
    'motorcycle': 1.0,
    'pickup': 1.3,
    'truck_small': 1.6,
    'truck_large': 2.0
};

// Calculate fee on form changes
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('transportRequestForm');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        input.addEventListener('change', calculateFee);
        input.addEventListener('input', calculateFee);
    });
    
    // Initial calculation
    calculateFee();
});

function calculateFee() {
    const distance = parseFloat(document.getElementById('estimated_distance').value) || 0;
    const weight = parseFloat(document.getElementById('weight').value) || 0;
    const urgency = document.getElementById('urgency').value || 'low';
    const vehicle = document.getElementById('vehicle_type').value || 'motorcycle';
    
    // Calculate components
    const distanceFee = distance * distanceRate;
    const weightFee = weight * weightRate;
    const urgencyMultiplier = urgencyMultipliers[urgency] || 1.0;
    const vehicleMultiplier = vehicleMultipliers[vehicle] || 1.0;
    
    // Calculate total
    const subtotal = baseFee + distanceFee + weightFee;
    const total = subtotal * urgencyMultiplier * vehicleMultiplier;
    
    // Update display
    document.getElementById('baseFee').textContent = `KES ${baseFee}`;
    document.getElementById('distanceFee').textContent = `KES ${distanceFee.toFixed(2)}`;
    document.getElementById('weightFee').textContent = `KES ${weightFee.toFixed(2)}`;
    document.getElementById('urgencyMultiplier').textContent = `${urgencyMultiplier}x`;
    document.getElementById('vehicleMultiplier').textContent = `${vehicleMultiplier}x`;
    document.getElementById('totalFee').textContent = `KES ${total.toFixed(2)}`;
}

// Save draft to localStorage
function saveDraft() {
    const form = document.getElementById('transportRequestForm');
    const formData = new FormData(form);
    const draft = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'csrf_token') {
            draft[key] = value;
        }
    }
    
    localStorage.setItem('transportRequestDraft', JSON.stringify(draft));
    showNotification('Draft saved successfully', 'success');
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('transportRequestDraft');
    
    if (draft) {
        try {
            const draftData = JSON.parse(draft);
            const form = document.getElementById('transportRequestForm');
            
            // Fill form fields
            Object.keys(draftData).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = draftData[key];
                }
            });
            
            // Recalculate fee
            calculateFee();
            
            // Show notification
            showNotification('Draft loaded. You can continue from where you left off.', 'info');
            
        } catch (e) {
            console.error('Error loading draft:', e);
        }
    }
});

// Clear draft on successful submission
document.getElementById('transportRequestForm').addEventListener('submit', function() {
    localStorage.removeItem('transportRequestDraft');
});

// Auto-calculate distance if pickup and delivery locations are provided
function calculateDistance() {
    const pickup = document.getElementById('pickup_location').value;
    const delivery = document.getElementById('delivery_location').value;
    
    if (pickup && delivery) {
        // This is a placeholder for distance calculation
        // In a real implementation, you would use a geocoding API
        // For now, we'll estimate based on location names
        const distanceField = document.getElementById('estimated_distance');
        
        // Simple estimation logic (placeholder)
        if (pickup.toLowerCase().includes('nairobi') && delivery.toLowerCase().includes('nairobi')) {
            distanceField.value = Math.random() * 20 + 5; // 5-25 km within Nairobi
        } else {
            distanceField.value = Math.random() * 100 + 20; // 20-120 km for intercity
        }
        
        calculateFee();
    }
}

// Add event listeners for distance calculation
document.getElementById('pickup_location').addEventListener('blur', calculateDistance);
document.getElementById('delivery_location').addEventListener('blur', calculateDistance);

// Form validation
document.getElementById('transportRequestForm').addEventListener('submit', function(e) {
    const form = e.target;
    
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        
        // Show first error field
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            firstInvalid.focus();
            showNotification('Please fill in all required fields correctly', 'danger');
        }
    }
    
    form.classList.add('was-validated');
});

// Auto-save draft every 30 seconds
setInterval(function() {
    const form = document.getElementById('transportRequestForm');
    const formData = new FormData(form);
    const hasData = false;
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'csrf_token' && value.trim() !== '') {
            hasData = true;
            break;
        }
    }
    
    if (hasData) {
        saveDraft();
    }
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>
