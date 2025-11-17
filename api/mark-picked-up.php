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

// Check CSRF token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get request ID
$requestId = (int)($_POST['id'] ?? 0);

if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'Request ID is required']);
    exit;
}

// Check user authentication and role
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'transporter') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Initialize database
$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Get transport request details
    $request = $db->fetch("
        SELECT tr.*, 
               fp.user_id as farmer_user_id,
               tp.user_id as transporter_user_id
        FROM transport_requests tr
        LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
        LEFT JOIN transporter_profiles tp ON tr.transporter_id = tp.id
        WHERE tr.id = ?
    ", [$requestId]);
    
    if (!$request) {
        throw new Exception('Transport request not found');
    }
    
    // Check if request is in accepted status
    if ($request['status'] !== 'accepted') {
        throw new Exception('This request cannot be marked as picked up. Current status: ' . $request['status']);
    }
    
    // Check if the current transporter is assigned to this request
    if ($request['transporter_user_id'] != $currentUser['id']) {
        throw new Exception('You are not assigned to this transport request');
    }
    
    // Get transporter's current location (if available)
    $currentLocation = $db->fetch("
        SELECT latitude, longitude 
        FROM gps_tracking 
        WHERE transporter_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ", [$request['transporter_id']]);
    
    // Update transport request
    $updateData = [
        'status' => 'in_transit',
        'picked_up_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Add pickup location if available
    if ($currentLocation) {
        $updateData['pickup_latitude'] = $currentLocation['latitude'];
        $updateData['pickup_longitude'] = $currentLocation['longitude'];
    }
    
    $db->update('transport_requests', $updateData, 'id = ?', [$requestId]);
    
    // Log activity
    logActivity(
        $currentUser['id'],
        'mark_picked_up',
        'transport_request',
        $requestId,
        "Transporter marked request #{$requestId} as picked up",
        $_SERVER['REMOTE_ADDR']
    );
    
    // Create notification for farmer
    $notificationData = [
        'user_id' => $request['farmer_user_id'],
        'title' => 'Produce Picked Up',
        'message' => "Your produce for transport request #{$requestId} has been picked up by {$currentUser['full_name']}. The delivery is now in transit.",
        'type' => 'info',
        'icon' => 'fa-truck',
        'action_url' => BASE_URL . '/public/farmer/transport-requests.php?id=' . $requestId,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('notifications', $notificationData);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Transport request marked as picked up successfully! The farmer has been notified.',
        'next_action' => 'mark_delivered'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $db->rollback();
    
    // Log error
    error_log("Error marking transport request as picked up: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
