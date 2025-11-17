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
$requestId = (int)($_POST['request_id'] ?? 0);

if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'Request ID is required']);
    exit;
}

// Get cancellation reason
$cancelReason = sanitizeInput($_POST['cancel_reason'] ?? '');

if (empty($cancelReason)) {
    echo json_encode(['success' => false, 'error' => 'Cancellation reason is required']);
    exit;
}

// Check user authentication and role
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'farmer') {
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
    
    // Check if the current farmer owns this request
    if ($request['farmer_user_id'] != $currentUser['id']) {
        throw new Exception('Access denied');
    }
    
    // Check if request can be cancelled
    if (!in_array($request['status'], ['pending', 'accepted'])) {
        throw new Exception('This request cannot be cancelled. Current status: ' . $request['status']);
    }
    
    // Calculate refund amount based on status
    $refundAmount = 0;
    if ($request['status'] === 'pending') {
        // Full refund for pending requests
        $refundAmount = $request['fee'];
    } elseif ($request['status'] === 'accepted') {
        // 50% refund for accepted requests (cancellation fee)
        $refundAmount = $request['fee'] * 0.5;
    }
    
    // Update transport request
    $db->update('transport_requests', [
        'status' => 'cancelled',
        'cancel_reason' => $cancelReason,
        'cancelled_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$requestId]);
    
    // Process refund if applicable
    if ($refundAmount > 0) {
        // Update farmer wallet (add refund)
        $db->update('user_wallets', [
            'balance' => 'balance + ' . $refundAmount,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$currentUser['id']]);
        
        // Create wallet transaction record for refund
        $refundTransactionData = [
            'user_id' => $currentUser['id'],
            'transaction_type' => 'refund',
            'amount' => $refundAmount,
            'reference_id' => $requestId,
            'reference_type' => 'transport_request',
            'description' => "Refund for cancelled transport request #{$requestId}",
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('mobile_money_transactions', $refundTransactionData);
    }
    
    // Log activity
    logActivity(
        $currentUser['id'],
        'cancel_transport_request',
        'transport_request',
        $requestId,
        "Farmer cancelled transport request #{$requestId}. Reason: {$cancelReason}",
        $_SERVER['REMOTE_ADDR']
    );
    
    // Create notification for transporter if assigned
    if ($request['transporter_user_id']) {
        $notificationData = [
            'user_id' => $request['transporter_user_id'],
            'title' => 'Transport Request Cancelled',
            'message' => "Transport request #{$requestId} has been cancelled by the farmer. Reason: {$cancelReason}",
            'type' => 'warning',
            'icon' => 'fa-times-circle',
            'action_url' => BASE_URL . '/public/transporter/dashboard.php',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('notifications', $notificationData);
    }
    
    // Commit transaction
    $db->commit();
    
    $message = 'Transport request cancelled successfully';
    if ($refundAmount > 0) {
        $message .= '. A refund of ' . formatCurrency($refundAmount) . ' has been added to your wallet';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'refund_amount' => $refundAmount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $db->rollback();
    
    // Log error
    error_log("Error cancelling transport request: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
