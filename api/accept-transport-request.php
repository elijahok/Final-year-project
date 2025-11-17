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
    
    // Check if request is in pending status
    if ($request['status'] !== 'pending') {
        throw new Exception('This request cannot be accepted. Current status: ' . $request['status']);
    }
    
    // Check if transporter is already assigned (preferred transporter)
    if ($request['transporter_id'] && $request['transporter_id'] != $currentUser['id']) {
        throw new Exception('This request has already been assigned to another transporter');
    }
    
    // Get transporter profile
    $transporterProfile = $db->fetch("
        SELECT * FROM transporter_profiles 
        WHERE user_id = ? AND is_active = 1
    ", [$currentUser['id']]);
    
    if (!$transporterProfile) {
        throw new Exception('Transporter profile not found or inactive');
    }
    
    // Update transport request
    $db->update('transport_requests', [
        'transporter_id' => $transporterProfile['id'],
        'status' => 'accepted',
        'accepted_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$requestId]);
    
    // Log activity
    logActivity(
        $currentUser['id'],
        'accept_transport_request',
        'transport_request',
        $requestId,
        "Transporter accepted transport request #{$requestId}",
        $_SERVER['REMOTE_ADDR']
    );
    
    // Create notification for farmer
    $notificationData = [
        'user_id' => $request['farmer_user_id'],
        'title' => 'Transport Request Accepted',
        'message' => "Your transport request #{$requestId} has been accepted by {$currentUser['full_name']}. Pickup scheduled for " . formatDate($request['pickup_date']) . " at " . formatTime($request['pickup_time']),
        'type' => 'success',
        'icon' => 'fa-check-circle',
        'action_url' => BASE_URL . '/public/farmer/transport-requests.php?id=' . $requestId,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('notifications', $notificationData);
    
    // Commit transaction
    $db->commit();
    
    // Send real-time notification if WebSocket is available
    // This would be implemented with WebSocket or Server-Sent Events
    
    echo json_encode([
        'success' => true,
        'message' => 'Transport request accepted successfully! The farmer has been notified.',
        'redirect_url' => BASE_URL . '/public/transporter/dashboard.php'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $db->rollback();
    
    // Log error
    error_log("Error accepting transport request: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
