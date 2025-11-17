<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($currentUser, $db);
            break;
        case 'POST':
            handlePostRequest($currentUser, $db);
            break;
        case 'PUT':
            handlePutRequest($currentUser, $db);
            break;
        case 'DELETE':
            handleDeleteRequest($currentUser, $db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function handleGetRequest($currentUser, $db) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            handleListNotifications($currentUser, $db);
            break;
        case 'unread':
            handleUnreadNotifications($currentUser, $db);
            break;
        case 'count':
            handleNotificationCount($currentUser, $db);
            break;
        case 'settings':
            handleNotificationSettings($currentUser, $db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequest($currentUser, $db) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'send':
            handleSendNotification($currentUser, $db);
            break;
        case 'bulk':
            handleBulkNotifications($currentUser, $db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePutRequest($currentUser, $db) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'read':
            handleMarkAsRead($currentUser, $db);
            break;
        case 'unread':
            handleMarkAsUnread($currentUser, $db);
            break;
        case 'all':
            handleMarkAllAsRead($currentUser, $db);
            break;
        case 'settings':
            handleUpdateSettings($currentUser, $db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDeleteRequest($currentUser, $db) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'delete':
            handleDeleteNotification($currentUser, $db);
            break;
        case 'clear':
            handleClearNotifications($currentUser, $db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleListNotifications($currentUser, $db) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE clause
    $where = ['n.user_id = ?'];
    $params = [$currentUser['id']];
    
    // Apply filters
    if ($type) {
        $where[] = "n.type = ?";
        $params[] = $type;
    }
    
    if ($status === 'read') {
        $where[] = "n.is_read = 1";
    } elseif ($status === 'unread') {
        $where[] = "n.is_read = 0";
    }
    
    if ($search) {
        $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $total = $db->fetch("
        SELECT COUNT(*) as count 
        FROM notifications n
        WHERE {$whereClause}
    ", $params)['count'] ?? 0;
    
    // Get notifications
    $notifications = $db->fetchAll("
        SELECT n.*, 
               u.full_name as sender_name,
               u.role as sender_role
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);
    
    // Format notifications
    foreach ($notifications as &$notification) {
        $notification['created_at'] = formatDateTime($notification['created_at']);
        $notification['time_ago'] = timeAgo($notification['created_at']);
        $notification['type_badge'] = getTypeBadge($notification['type']);
        $notification['is_read_badge'] = $notification['is_read'] ? 
            '<span class="badge bg-secondary">Read</span>' : 
            '<span class="badge bg-primary">Unread</span>';
        $notification['sender_role_badge'] = getRoleBadge($notification['sender_role']);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'has_next' => $page < ceil($total / $limit),
            'has_prev' => $page > 1
        ]
    ]);
}

function handleUnreadNotifications($currentUser, $db) {
    $limit = min(50, intval($_GET['limit'] ?? 10));
    
    $notifications = $db->fetchAll("
        SELECT n.*, 
               u.full_name as sender_name,
               u.role as sender_role
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.user_id = ? AND n.is_read = 0
        ORDER BY n.created_at DESC
        LIMIT {$limit}
    ", [$currentUser['id']]);
    
    // Format notifications
    foreach ($notifications as &$notification) {
        $notification['created_at'] = formatDateTime($notification['created_at']);
        $notification['time_ago'] = timeAgo($notification['created_at']);
        $notification['type_badge'] = getTypeBadge($notification['type']);
        $notification['sender_role_badge'] = getRoleBadge($notification['sender_role']);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

function handleNotificationCount($currentUser, $db) {
    $total = $db->fetch("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ", [$currentUser['id']])['count'] ?? 0;
    
    // Get counts by type
    $typeCounts = $db->fetchAll("
        SELECT type, COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
        GROUP BY type
    ", [$currentUser['id']]);
    
    $counts = ['total' => $total];
    foreach ($typeCounts as $typeCount) {
        $counts[$typeCount['type']] = $typeCount['count'];
    }
    
    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);
}

function handleNotificationSettings($currentUser, $db) {
    $settings = $db->fetch("
        SELECT * FROM notification_settings 
        WHERE user_id = ?
    ", [$currentUser['id']]);
    
    if (!$settings) {
        // Create default settings
        $defaultSettings = [
            'user_id' => $currentUser['id'],
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'push_notifications' => 1,
            'tender_notifications' => 1,
            'bid_notifications' => 1,
            'transport_notifications' => 1,
            'payment_notifications' => 1,
            'quality_notifications' => 1,
            'system_notifications' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $settingsId = $db->insert('notification_settings', $defaultSettings);
        $settings = $db->fetch("SELECT * FROM notification_settings WHERE id = ?", [$settingsId]);
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function handleSendNotification($currentUser, $db) {
    // Only admins can send notifications
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Validate required fields
    $required = ['recipient_id', 'title', 'message', 'type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            exit();
        }
    }
    
    // Validate recipient
    $recipient = $db->fetch("SELECT id FROM users WHERE id = ?", [intval($_POST['recipient_id'])]);
    if (!$recipient) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid recipient']);
        exit();
    }
    
    // Validate notification type
    $validTypes = ['tender', 'bid', 'transport', 'payment', 'quality', 'system', 'alert'];
    if (!in_array($_POST['type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification type']);
        exit();
    }
    
    // Create notification
    $notificationData = [
        'user_id' => intval($_POST['recipient_id']),
        'sender_id' => $currentUser['id'],
        'type' => $_POST['type'],
        'title' => sanitizeInput($_POST['title']),
        'message' => sanitizeInput($_POST['message']),
        'link' => sanitizeInput($_POST['link'] ?? ''),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $notificationId = $db->insert('notifications', $notificationData);
    
    if (!$notificationId) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send notification']);
        exit();
    }
    
    // Check recipient's notification settings
    $settings = $db->fetch("
        SELECT * FROM notification_settings 
        WHERE user_id = ?
    ", [intval($_POST['recipient_id'])]);
    
    if ($settings) {
        // Send email notification if enabled
        if ($settings['email_notifications']) {
            sendEmailNotification(intval($_POST['recipient_id']), $_POST['title'], $_POST['message']);
        }
        
        // Send SMS notification if enabled (placeholder)
        if ($settings['sms_notifications']) {
            // sendSMSNotification(intval($_POST['recipient_id']), $_POST['message']);
        }
    }
    
    // Log activity
    logActivity($currentUser['id'], 'send_notification', "Sent notification: " . $_POST['title']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully',
        'notification_id' => $notificationId
    ]);
}

function handleBulkNotifications($currentUser, $db) {
    // Only admins can send bulk notifications
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Validate required fields
    $required = ['title', 'message', 'type', 'recipients'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            exit();
        }
    }
    
    $recipients = json_decode($_POST['recipients'], true);
    if (!is_array($recipients) || empty($recipients)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid recipients']);
        exit();
    }
    
    // Validate notification type
    $validTypes = ['tender', 'bid', 'transport', 'payment', 'quality', 'system', 'alert'];
    if (!in_array($_POST['type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification type']);
        exit();
    }
    
    $sentCount = 0;
    $errors = [];
    
    foreach ($recipients as $recipientId) {
        $recipientId = intval($recipientId);
        if ($recipientId <= 0) continue;
        
        // Verify recipient exists
        $recipient = $db->fetch("SELECT id FROM users WHERE id = ?", [$recipientId]);
        if (!$recipient) {
            $errors[] = "Invalid recipient ID: {$recipientId}";
            continue;
        }
        
        // Create notification
        $notificationData = [
            'user_id' => $recipientId,
            'sender_id' => $currentUser['id'],
            'type' => $_POST['type'],
            'title' => sanitizeInput($_POST['title']),
            'message' => sanitizeInput($_POST['message']),
            'link' => sanitizeInput($_POST['link'] ?? ''),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $notificationId = $db->insert('notifications', $notificationData);
        
        if ($notificationId) {
            $sentCount++;
            
            // Check recipient's notification settings
            $settings = $db->fetch("
                SELECT * FROM notification_settings 
                WHERE user_id = ?
            ", [$recipientId]);
            
            if ($settings && $settings['email_notifications']) {
                sendEmailNotification($recipientId, $_POST['title'], $_POST['message']);
            }
        } else {
            $errors[] = "Failed to send to recipient ID: {$recipientId}";
        }
    }
    
    // Log activity
    logActivity($currentUser['id'], 'send_bulk_notifications', "Sent bulk notification to {$sentCount} users");
    
    echo json_encode([
        'success' => true,
        'message' => "Notifications sent to {$sentCount} recipients",
        'sent_count' => $sentCount,
        'errors' => $errors
    ]);
}

function handleMarkAsRead($currentUser, $db) {
    $notificationId = intval($_GET['id'] ?? 0);
    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID']);
        exit();
    }
    
    // Verify notification belongs to user
    $notification = $db->fetch("
        SELECT id FROM notifications 
        WHERE id = ? AND user_id = ?
    ", [$notificationId, $currentUser['id']]);
    
    if (!$notification) {
        http_response_code(404);
        echo json_encode(['error' => 'Notification not found']);
        exit();
    }
    
    // Mark as read
    $result = $db->update('notifications', 
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$notificationId]
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notification as read']);
    }
}

function handleMarkAsUnread($currentUser, $db) {
    $notificationId = intval($_GET['id'] ?? 0);
    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID']);
        exit();
    }
    
    // Verify notification belongs to user
    $notification = $db->fetch("
        SELECT id FROM notifications 
        WHERE id = ? AND user_id = ?
    ", [$notificationId, $currentUser['id']]);
    
    if (!$notification) {
        http_response_code(404);
        echo json_encode(['error' => 'Notification not found']);
        exit();
    }
    
    // Mark as unread
    $result = $db->update('notifications', 
        ['is_read' => 0, 'read_at' => null],
        'id = ?',
        [$notificationId]
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as unread']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notification as unread']);
    }
}

function handleMarkAllAsRead($currentUser, $db) {
    // Verify CSRF token for bulk action
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    $type = $_POST['type'] ?? '';
    $where = "user_id = ?";
    $params = [$currentUser['id']];
    
    if ($type) {
        $where .= " AND type = ?";
        $params[] = $type;
    }
    
    // Mark all as read
    $result = $db->update('notifications', 
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
        $where,
        $params
    );
    
    if ($result !== false) {
        echo json_encode([
            'success' => true, 
            'message' => 'All notifications marked as read',
            'marked_count' => $result
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notifications as read']);
    }
}

function handleUpdateSettings($currentUser, $db) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Get current settings
    $settings = $db->fetch("
        SELECT * FROM notification_settings 
        WHERE user_id = ?
    ", [$currentUser['id']]);
    
    $updateData = [
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Update notification preferences
    $preferences = [
        'email_notifications',
        'sms_notifications', 
        'push_notifications',
        'tender_notifications',
        'bid_notifications',
        'transport_notifications',
        'payment_notifications',
        'quality_notifications',
        'system_notifications'
    ];
    
    foreach ($preferences as $pref) {
        $updateData[$pref] = isset($_POST[$pref]) ? 1 : 0;
    }
    
    if ($settings) {
        // Update existing settings
        $result = $db->update('notification_settings', $updateData, 'user_id = ?', [$currentUser['id']]);
    } else {
        // Create new settings
        $updateData['user_id'] = $currentUser['id'];
        $updateData['created_at'] = date('Y-m-d H:i:s');
        $result = $db->insert('notification_settings', $updateData);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification settings updated']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update settings']);
    }
}

function handleDeleteNotification($currentUser, $db) {
    $notificationId = intval($_GET['id'] ?? 0);
    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification ID']);
        exit();
    }
    
    // Verify notification belongs to user
    $notification = $db->fetch("
        SELECT id FROM notifications 
        WHERE id = ? AND user_id = ?
    ", [$notificationId, $currentUser['id']]);
    
    if (!$notification) {
        http_response_code(404);
        echo json_encode(['error' => 'Notification not found']);
        exit();
    }
    
    // Delete notification
    $result = $db->delete('notifications', 'id = ?', [$notificationId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete notification']);
    }
}

function handleClearNotifications($currentUser, $db) {
    // Verify CSRF token for bulk action
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    $type = $_POST['type'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $where = "user_id = ?";
    $params = [$currentUser['id']];
    
    if ($type) {
        $where .= " AND type = ?";
        $params[] = $type;
    }
    
    if ($status === 'read') {
        $where .= " AND is_read = 1";
    } elseif ($status === 'unread') {
        $where .= " AND is_read = 0";
    }
    
    // Delete notifications
    $result = $db->delete('notifications', $where, $params);
    
    if ($result !== false) {
        echo json_encode([
            'success' => true, 
            'message' => 'Notifications cleared',
            'deleted_count' => $result
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to clear notifications']);
    }
}

// Helper functions
function getTypeBadge($type) {
    $badges = [
        'tender' => '<span class="badge bg-primary">Tender</span>',
        'bid' => '<span class="badge bg-info">Bid</span>',
        'transport' => '<span class="badge bg-success">Transport</span>',
        'payment' => '<span class="badge bg-warning">Payment</span>',
        'quality' => '<span class="badge bg-danger">Quality</span>',
        'system' => '<span class="badge bg-secondary">System</span>',
        'alert' => '<span class="badge bg-danger">Alert</span>'
    ];
    return $badges[$type] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function getRoleBadge($role) {
    $badges = [
        'admin' => '<span class="badge bg-danger">Admin</span>',
        'farmer' => '<span class="badge bg-success">Farmer</span>',
        'transporter' => '<span class="badge bg-primary">Transporter</span>',
        'vendor' => '<span class="badge bg-info">Vendor</span>'
    ];
    return $badges[$role] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $time);
    }
}

function sendEmailNotification($userId, $title, $message) {
    // Placeholder for email notification
    // In a real implementation, this would use PHPMailer or similar
    error_log("Email notification sent to user {$userId}: {$title}");
}

function sendSMSNotification($userId, $message) {
    // Placeholder for SMS notification
    // In a real implementation, this would use an SMS gateway API
    error_log("SMS notification sent to user {$userId}: {$message}");
}
?>
