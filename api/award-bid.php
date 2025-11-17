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

// Get bid ID
$bidId = (int)($_POST['bid_id'] ?? 0);

if (!$bidId) {
    echo json_encode(['success' => false, 'error' => 'Bid ID is required']);
    exit;
}

// Check user authentication and role
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Initialize database
$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Get bid details
    $bid = $db->fetch("
        SELECT b.*, t.title as tender_title, t.deadline as tender_deadline,
               vp.company_name, u.full_name as vendor_name, u.email as vendor_email
        FROM bids b
        LEFT JOIN tenders t ON b.tender_id = t.id
        LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
        LEFT JOIN users u ON vp.user_id = u.id
        WHERE b.id = ?
    ", [$bidId]);
    
    if (!$bid) {
        throw new Exception('Bid not found');
    }
    
    // Check if tender is still open for awarding
    if ($bid['status'] === 'awarded') {
        throw new Exception('This bid has already been awarded');
    }
    
    if ($bid['status'] !== 'submitted') {
        throw new Exception('Bid cannot be awarded in current status: ' . $bid['status']);
    }
    
    // Update bid status to awarded
    $db->update('bids', [
        'status' => 'awarded',
        'awarded_at' => date('Y-m-d H:i:s'),
        'awarded_by' => $currentUser['id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$bidId]);
    
    // Update tender status to closed
    $db->update('tenders', [
        'status' => 'closed',
        'awarded_bid_id' => $bidId,
        'closed_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$bid['tender_id']]);
    
    // Mark all other bids for this tender as rejected
    $db->update('bids', [
        'status' => 'rejected',
        'updated_at' => date('Y-m-d H:i:s')
    ], 'tender_id = ? AND id != ?', [$bid['tender_id'], $bidId]);
    
    // Create notification for the winning vendor
    $notificationData = [
        'user_id' => $bid['vendor_name'] ? getUserIdByEmail($bid['vendor_email']) : null,
        'title' => 'Bid Awarded - Congratulations!',
        'message' => "Your bid for '{$bid['tender_title']}' has been awarded! Please contact the administrator for next steps.",
        'type' => 'success',
        'icon' => 'fa-trophy',
        'action_url' => BASE_URL . '/public/vendor/dashboard.php',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if ($notificationData['user_id']) {
        $db->insert('notifications', $notificationData);
    }
    
    // Create notifications for other vendors (rejected bids)
    $rejectedBids = $db->fetchAll("
        SELECT b.*, u.email as vendor_email, u.full_name as vendor_name
        FROM bids b
        LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
        LEFT JOIN users u ON vp.user_id = u.id
        WHERE b.tender_id = ? AND b.status = 'rejected'
    ", [$bid['tender_id']]);
    
    foreach ($rejectedBids as $rejectedBid) {
        $rejectedNotificationData = [
            'user_id' => getUserIdByEmail($rejectedBid['vendor_email']),
            'title' => 'Bid Not Selected',
            'message' => "Your bid for '{$bid['tender_title']}' was not selected. Thank you for your participation.",
            'type' => 'info',
            'icon' => 'fa-info-circle',
            'action_url' => BASE_URL . '/public/vendor/dashboard.php',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if ($rejectedNotificationData['user_id']) {
            $db->insert('notifications', $rejectedNotificationData);
        }
    }
    
    // Log activity
    logActivity(
        $currentUser['id'],
        'award_bid',
        'bid',
        $bidId,
        "Admin awarded bid #{$bidId} for tender '{$bid['tender_title']}' to {$bid['company_name']}",
        $_SERVER['REMOTE_ADDR']
    );
    
    // Update analytics data
    $analyticsData = [
        'event_type' => 'bid_awarded',
        'tender_id' => $bid['tender_id'],
        'bid_id' => $bidId,
        'vendor_id' => $bid['vendor_id'],
        'award_amount' => $bid['total_price'],
        'award_date' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('analytics_data', $analyticsData);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Bid awarded successfully to {$bid['company_name']}. All parties have been notified."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $db->rollback();
    
    // Log error
    error_log("Error awarding bid: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function to get user ID by email
function getUserIdByEmail($email) {
    global $db;
    $user = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    return $user ? $user['id'] : null;
}
?>
