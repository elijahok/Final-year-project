<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $requestId = intval($_POST['request_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    
    // Validate input
    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        exit();
    }
    
    if (!in_array($status, ['assigned', 'picked_up', 'in_transit', 'delivered', 'completed', 'cancelled'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit();
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Get transport request details
    $transportRequest = $db->fetch("
        SELECT tr.*, u.full_name as client_name, u.phone as client_phone
        FROM transport_requests tr
        JOIN users u ON tr.client_id = u.id
        WHERE tr.id = ?
    ", [$requestId]);
    
    if (!$transportRequest) {
        http_response_code(404);
        echo json_encode(['error' => 'Transport request not found']);
        exit();
    }
    
    // Check permissions
    if ($currentUser['role'] === 'transporter') {
        // Transporters can only update their assigned requests
        if ($transportRequest['transporter_id'] !== $currentUser['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
    } elseif ($currentUser['role'] === 'admin') {
        // Admins can update any request
    } elseif ($currentUser['role'] === 'farmer') {
        // Farmers can only cancel their own requests
        if ($transportRequest['client_id'] !== $currentUser['id'] || $status !== 'cancelled') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    // Validate status transitions
    $validTransitions = [
        'assigned' => ['picked_up', 'cancelled'],
        'picked_up' => ['in_transit', 'cancelled'],
        'in_transit' => ['delivered', 'cancelled'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => []
    ];
    
    if (!in_array($status, $validTransitions[$transportRequest['status']])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status transition']);
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        // Update transport request status
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($notes) {
            $updateData['notes'] = $notes;
        }
        
        $db->update('transport_requests', $updateData, 'id = ?', [$requestId]);
        
        // Add location update if coordinates provided
        if ($latitude && $longitude) {
            $db->insert('transport_locations', [
                'transport_request_id' => $requestId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Update transporter profile location if transporter is updating
        if ($currentUser['role'] === 'transporter' && $latitude && $longitude) {
            $db->update('transporter_profiles', [
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
                'last_location_update' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$currentUser['id']]);
        }
        
        // Process status-specific actions
        if ($status === 'completed') {
            // Mark as completed and update transporter availability
            $db->update('transporter_profiles', [
                'is_available' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$transportRequest['transporter_id']]);
            
            // Process payment if not already paid
            if ($transportRequest['payment_status'] !== 'paid') {
                // Deduct from wallet if wallet payment
                if ($transportRequest['payment_method'] === 'wallet') {
                    $clientWallet = $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$transportRequest['client_id']]);
                    if ($clientWallet && $clientWallet['balance'] >= $transportRequest['amount']) {
                        $db->update('wallets', [
                            'balance' => $clientWallet['balance'] - $transportRequest['amount'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'user_id = ?', [$transportRequest['client_id']]);
                        
                        // Add to transporter wallet
                        $transporterWallet = $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$transportRequest['transporter_id']]);
                        if ($transporterWallet) {
                            $db->update('wallets', [
                                'balance' => $transporterWallet['balance'] + $transportRequest['amount'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ], 'user_id = ?', [$transportRequest['transporter_id']]);
                        }
                        
                        // Create wallet transactions
                        $db->insert('wallet_transactions', [
                            'user_id' => $transportRequest['client_id'],
                            'amount' => $transportRequest['amount'],
                            'transaction_type' => 'debit',
                            'description' => "Payment for transport request #{$requestId}",
                            'reference_id' => $requestId,
                            'related_user_id' => $transportRequest['transporter_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $db->insert('wallet_transactions', [
                            'user_id' => $transportRequest['transporter_id'],
                            'amount' => $transportRequest['amount'],
                            'transaction_type' => 'credit',
                            'description' => "Payment received for transport request #{$requestId}",
                            'reference_id' => $requestId,
                            'related_user_id' => $transportRequest['client_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Update payment status
                        $db->update('transport_requests', [
                            'payment_status' => 'paid',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$requestId]);
                    }
                }
            }
            
            // Create rating request for client
            $db->insert('ratings', [
                'transporter_id' => $transportRequest['transporter_id'],
                'client_id' => $transportRequest['client_id'],
                'transport_request_id' => $requestId,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send notification to client to rate
            addNotification($transportRequest['client_id'], 'Rate Transporter', 
                           "Please rate your experience with the transporter for request #{$requestId}", 'info');
        } elseif ($status === 'cancelled') {
            // Update transporter availability
            $db->update('transporter_profiles', [
                'is_available' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$transportRequest['transporter_id']]);
            
            // Process refund if payment was made
            if ($transportRequest['payment_status'] === 'paid') {
                // Refund to client wallet
                $clientWallet = $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$transportRequest['client_id']]);
                if ($clientWallet) {
                    $db->update('wallets', [
                        'balance' => $clientWallet['balance'] + $transportRequest['amount'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'user_id = ?', [$transportRequest['client_id']]);
                    
                    // Create refund transaction
                    $db->insert('wallet_transactions', [
                        'user_id' => $transportRequest['client_id'],
                        'amount' => $transportRequest['amount'],
                        'transaction_type' => 'credit',
                        'description' => "Refund for cancelled transport request #{$requestId}",
                        'reference_id' => $requestId,
                        'related_user_id' => $transportRequest['transporter_id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                // Update payment status
                $db->update('transport_requests', [
                    'payment_status' => 'refunded',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$requestId]);
            }
        }
        
        $db->commit();
        
        // Log activity
        $activityDescription = "Transport request #{$requestId} status updated to {$status}";
        if ($notes) {
            $activityDescription .= " - Notes: {$notes}";
        }
        logActivity($currentUser['id'], 'update_transport_status', $activityDescription);
        
        // Send notifications
        if ($status === 'picked_up') {
            addNotification($transportRequest['client_id'], 'Transport Started', 
                           "Your transport request #{$requestId} has been picked up", 'success');
        } elseif ($status === 'in_transit') {
            addNotification($transportRequest['client_id'], 'Transport In Transit', 
                           "Your transport request #{$requestId} is now in transit", 'info');
        } elseif ($status === 'delivered') {
            addNotification($transportRequest['client_id'], 'Transport Delivered', 
                           "Your transport request #{$requestId} has been delivered", 'success');
        } elseif ($status === 'completed') {
            addNotification($transportRequest['client_id'], 'Transport Completed', 
                           "Your transport request #{$requestId} has been completed", 'success');
        } elseif ($status === 'cancelled') {
            addNotification($transportRequest['transporter_id'], 'Transport Cancelled', 
                           "Transport request #{$requestId} has been cancelled", 'warning');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Transport request status updated successfully',
            'new_status' => $status
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
