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
            handleListReports($currentUser, $db);
            break;
        case 'details':
            handleGetReportDetails($currentUser, $db);
            break;
        case 'stats':
            handleGetQualityStats($currentUser, $db);
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
        case 'create':
            handleCreateReport($currentUser, $db);
            break;
        case 'respond':
            handleRespondToReport($currentUser, $db);
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
        case 'update':
            handleUpdateReport($currentUser, $db);
            break;
        case 'resolve':
            handleResolveReport($currentUser, $db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDeleteRequest($currentUser, $db) {
    // Only admins can delete reports
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    $reportId = intval($_GET['id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report ID']);
        exit();
    }
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Check if report exists
    $report = $db->fetch("SELECT id FROM quality_reports WHERE id = ?", [$reportId]);
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Delete report
    $db->delete('quality_reports', 'id = ?', [$reportId]);
    
    // Log activity
    logActivity($currentUser['id'], 'delete_quality_report', "Deleted quality report #{$reportId}");
    
    echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
}

function handleListReports($currentUser, $db) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? '';
    $type = $_GET['type'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE clause
    $where = ['1=1'];
    $params = [];
    
    // Filter by user role
    if ($currentUser['role'] === 'farmer') {
        $where[] = "qr.reporter_id = ?";
        $params[] = $currentUser['id'];
    } elseif ($currentUser['role'] === 'transporter') {
        $where[] = "qr.related_transporter_id = ?";
        $params[] = $currentUser['id'];
    }
    // Admins can see all reports
    
    // Apply filters
    if ($status) {
        $where[] = "qr.status = ?";
        $params[] = $status;
    }
    
    if ($type) {
        $where[] = "qr.report_type = ?";
        $params[] = $type;
    }
    
    if ($priority) {
        $where[] = "qr.priority = ?";
        $params[] = $priority;
    }
    
    if ($search) {
        $where[] = "(qr.title LIKE ? OR qr.description LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $total = $db->fetch("
        SELECT COUNT(*) as count 
        FROM quality_reports qr
        LEFT JOIN users u ON qr.reporter_id = u.id
        WHERE {$whereClause}
    ", $params)['count'] ?? 0;
    
    // Get reports
    $reports = $db->fetchAll("
        SELECT qr.*, 
               u.full_name as reporter_name,
               u.email as reporter_email,
               rt.full_name as related_transporter_name,
               tr.id as transport_request_id
        FROM quality_reports qr
        LEFT JOIN users u ON qr.reporter_id = u.id
        LEFT JOIN users rt ON qr.related_transporter_id = rt.id
        LEFT JOIN transport_requests tr ON qr.transport_request_id = tr.id
        WHERE {$whereClause}
        ORDER BY qr.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);
    
    // Format reports
    foreach ($reports as &$report) {
        $report['created_at'] = formatDateTime($report['created_at']);
        $report['updated_at'] = formatDateTime($report['updated_at']);
        $report['priority_badge'] = getPriorityBadge($report['priority']);
        $report['status_badge'] = getStatusBadge($report['status']);
        $report['type_badge'] = getTypeBadge($report['report_type']);
    }
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'has_next' => $page < ceil($total / $limit),
            'has_prev' => $page > 1
        ]
    ]);
}

function handleGetReportDetails($currentUser, $db) {
    $reportId = intval($_GET['id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report ID']);
        exit();
    }
    
    // Get report details
    $report = $db->fetch("
        SELECT qr.*, 
               u.full_name as reporter_name,
               u.email as reporter_email,
               u.phone as reporter_phone,
               rt.full_name as related_transporter_name,
               rt.phone as related_transporter_phone,
               tr.id as transport_request_id,
               tr.pickup_location,
               tr.delivery_location,
               tr.amount
        FROM quality_reports qr
        LEFT JOIN users u ON qr.reporter_id = u.id
        LEFT JOIN users rt ON qr.related_transporter_id = rt.id
        LEFT JOIN transport_requests tr ON qr.transport_request_id = tr.id
        WHERE qr.id = ?
    ", [$reportId]);
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Check permissions
    if ($currentUser['role'] === 'farmer' && $report['reporter_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    if ($currentUser['role'] === 'transporter' && $report['related_transporter_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    // Get responses
    $responses = $db->fetchAll("
        SELECT qr.*, u.full_name as responder_name, u.role as responder_role
        FROM quality_report_responses qr
        JOIN users u ON qr.responder_id = u.id
        WHERE qr.report_id = ?
        ORDER BY qr.created_at ASC
    ", [$reportId]);
    
    // Format data
    $report['created_at'] = formatDateTime($report['created_at']);
    $report['updated_at'] = formatDateTime($report['updated_at']);
    $report['priority_badge'] = getPriorityBadge($report['priority']);
    $report['status_badge'] = getStatusBadge($report['status']);
    $report['type_badge'] = getTypeBadge($report['report_type']);
    
    foreach ($responses as &$response) {
        $response['created_at'] = formatDateTime($response['created_at']);
        $response['responder_role_badge'] = getRoleBadge($response['responder_role']);
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report,
        'responses' => $responses
    ]);
}

function handleCreateReport($currentUser, $db) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    // Validate required fields
    $required = ['title', 'description', 'report_type', 'priority'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            exit();
        }
    }
    
    // Validate report type
    $validTypes = ['produce_quality', 'delivery_delay', 'service_issue', 'payment_issue', 'safety_concern', 'other'];
    if (!in_array($_POST['report_type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type']);
        exit();
    }
    
    // Validate priority
    $validPriorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($_POST['priority'], $validPriorities)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid priority']);
        exit();
    }
    
    // Get transport request ID if provided
    $transportRequestId = intval($_POST['transport_request_id'] ?? 0);
    $relatedTransporterId = null;
    
    if ($transportRequestId > 0) {
        // Verify transport request exists and get transporter
        $transportRequest = $db->fetch("
            SELECT transporter_id, client_id FROM transport_requests 
            WHERE id = ? AND client_id = ?
        ", [$transportRequestId, $currentUser['id']]);
        
        if (!$transportRequest) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid transport request']);
            exit();
        }
        
        $relatedTransporterId = $transportRequest['transporter_id'];
    }
    
    // Create report
    $reportData = [
        'reporter_id' => $currentUser['id'],
        'transport_request_id' => $transportRequestId ?: null,
        'related_transporter_id' => $relatedTransporterId,
        'title' => sanitizeInput($_POST['title']),
        'description' => sanitizeInput($_POST['description']),
        'report_type' => $_POST['report_type'],
        'priority' => $_POST['priority'],
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $reportId = $db->insert('quality_reports', $reportData);
    
    if (!$reportId) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create report']);
        exit();
    }
    
    // Send notifications to admin
    $admins = $db->fetchAll("SELECT id FROM users WHERE role = 'admin'");
    foreach ($admins as $admin) {
        createNotification(
            $admin['id'],
            'quality_report',
            "New quality report: " . $_POST['title'],
            "/admin/quality-reports.php?id={$reportId}"
        );
    }
    
    // Notify related transporter if applicable
    if ($relatedTransporterId) {
        createNotification(
            $relatedTransporterId,
            'quality_report',
            "Quality report filed regarding your transport",
            "/transporter/quality-reports.php?id={$reportId}"
        );
    }
    
    // Log activity
    logActivity($currentUser['id'], 'create_quality_report', "Created quality report #{$reportId}: " . $_POST['title']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quality report submitted successfully',
        'report_id' => $reportId
    ]);
}

function handleRespondToReport($currentUser, $db) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
    
    $reportId = intval($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report ID']);
        exit();
    }
    
    $response = sanitizeInput($_POST['response'] ?? '');
    if (empty($response)) {
        http_response_code(400);
        echo json_encode(['error' => 'Response cannot be empty']);
        exit();
    }
    
    // Get report details
    $report = $db->fetch("SELECT * FROM quality_reports WHERE id = ?", [$reportId]);
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Check permissions
    if ($currentUser['role'] === 'farmer' && $report['reporter_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    if ($currentUser['role'] === 'transporter' && $report['related_transporter_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    // Add response
    $responseData = [
        'report_id' => $reportId,
        'responder_id' => $currentUser['id'],
        'response' => $response,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $responseId = $db->insert('quality_report_responses', $responseData);
    
    if (!$responseId) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add response']);
        exit();
    }
    
    // Update report timestamp
    $db->update('quality_reports', 
        ['updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$reportId]
    );
    
    // Notify other parties
    if ($currentUser['role'] === 'admin') {
        // Notify reporter and transporter
        if ($report['reporter_id']) {
            createNotification(
                $report['reporter_id'],
                'quality_report_response',
                "Admin responded to your quality report",
                "/farmer/quality-reports.php?id={$reportId}"
            );
        }
        if ($report['related_transporter_id']) {
            createNotification(
                $report['related_transporter_id'],
                'quality_report_response',
                "Admin responded to quality report",
                "/transporter/quality-reports.php?id={$reportId}"
            );
        }
    } else {
        // Notify admins
        $admins = $db->fetchAll("SELECT id FROM users WHERE role = 'admin'");
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'quality_report_response',
                ucfirst($currentUser['role']) . " responded to quality report",
                "/admin/quality-reports.php?id={$reportId}"
            );
        }
    }
    
    // Log activity
    logActivity($currentUser['id'], 'respond_quality_report', "Responded to quality report #{$reportId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Response added successfully',
        'response_id' => $responseId
    ]);
}

function handleUpdateReport($currentUser, $db) {
    // Only admins can update reports
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
    
    $reportId = intval($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report ID']);
        exit();
    }
    
    // Check if report exists
    $report = $db->fetch("SELECT * FROM quality_reports WHERE id = ?", [$reportId]);
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Update fields
    $updateData = ['updated_at' => date('Y-m-d H:i:s')];
    
    if (!empty($_POST['priority'])) {
        $validPriorities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($_POST['priority'], $validPriorities)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid priority']);
            exit();
        }
        $updateData['priority'] = $_POST['priority'];
    }
    
    if (!empty($_POST['status'])) {
        $validStatuses = ['open', 'investigating', 'resolved', 'closed'];
        if (!in_array($_POST['status'], $validStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            exit();
        }
        $updateData['status'] = $_POST['status'];
    }
    
    if (!empty($_POST['title'])) {
        $updateData['title'] = sanitizeInput($_POST['title']);
    }
    
    if (!empty($_POST['description'])) {
        $updateData['description'] = sanitizeInput($_POST['description']);
    }
    
    // Update report
    $result = $db->update('quality_reports', $updateData, 'id = ?', [$reportId]);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update report']);
        exit();
    }
    
    // Log activity
    logActivity($currentUser['id'], 'update_quality_report', "Updated quality report #{$reportId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Report updated successfully'
    ]);
}

function handleResolveReport($currentUser, $db) {
    // Only admins can resolve reports
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
    
    $reportId = intval($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report ID']);
        exit();
    }
    
    $resolution = sanitizeInput($_POST['resolution'] ?? '');
    if (empty($resolution)) {
        http_response_code(400);
        echo json_encode(['error' => 'Resolution details are required']);
        exit();
    }
    
    // Check if report exists
    $report = $db->fetch("SELECT * FROM quality_reports WHERE id = ?", [$reportId]);
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Update report status and resolution
    $updateData = [
        'status' => 'resolved',
        'resolution' => $resolution,
        'resolved_at' => date('Y-m-d H:i:s'),
        'resolved_by' => $currentUser['id'],
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->update('quality_reports', $updateData, 'id = ?', [$reportId]);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to resolve report']);
        exit();
    }
    
    // Notify reporter and transporter
    if ($report['reporter_id']) {
        createNotification(
            $report['reporter_id'],
            'quality_report_resolved',
            "Your quality report has been resolved",
            "/farmer/quality-reports.php?id={$reportId}"
        );
    }
    if ($report['related_transporter_id']) {
        createNotification(
            $report['related_transporter_id'],
            'quality_report_resolved',
            "Quality report has been resolved",
            "/transporter/quality-reports.php?id={$reportId}"
        );
    }
    
    // Log activity
    logActivity($currentUser['id'], 'resolve_quality_report', "Resolved quality report #{$reportId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Report resolved successfully'
    ]);
}

function handleGetQualityStats($currentUser, $db) {
    $timeframe = $_GET['timeframe'] ?? '30';
    $days = min(365, max(1, intval($timeframe)));
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    
    $where = "qr.created_at >= ?";
    $params = [$startDate];
    
    // Filter by user role
    if ($currentUser['role'] === 'farmer') {
        $where .= " AND qr.reporter_id = ?";
        $params[] = $currentUser['id'];
    } elseif ($currentUser['role'] === 'transporter') {
        $where .= " AND qr.related_transporter_id = ?";
        $params[] = $currentUser['id'];
    }
    
    // Get stats
    $stats = [
        'total_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports qr WHERE {$where}", $params)['count'] ?? 0,
        'open_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports qr WHERE {$where} AND qr.status = 'open'", $params)['count'] ?? 0,
        'resolved_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports qr WHERE {$where} AND qr.status = 'resolved'", $params)['count'] ?? 0,
        'critical_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports qr WHERE {$where} AND qr.priority = 'critical'", $params)['count'] ?? 0
    ];
    
    // Get reports by type
    $reportsByType = $db->fetchAll("
        SELECT report_type, COUNT(*) as count 
        FROM quality_reports qr 
        WHERE {$where}
        GROUP BY report_type
        ORDER BY count DESC
    ", $params);
    
    // Get reports by priority
    $reportsByPriority = $db->fetchAll("
        SELECT priority, COUNT(*) as count 
        FROM quality_reports qr 
        WHERE {$where}
        GROUP BY priority
        ORDER BY 
            CASE priority 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END
    ", $params);
    
    // Get daily trends
    $dailyTrends = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as reports,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM quality_reports qr
        WHERE {$where}
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ", $params);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'reports_by_type' => $reportsByType,
        'reports_by_priority' => $reportsByPriority,
        'daily_trends' => $dailyTrends,
        'timeframe' => $days
    ]);
}

// Helper functions
function getPriorityBadge($priority) {
    $badges = [
        'low' => '<span class="badge bg-success">Low</span>',
        'medium' => '<span class="badge bg-warning">Medium</span>',
        'high' => '<span class="badge bg-danger">High</span>',
        'critical' => '<span class="badge bg-dark">Critical</span>'
    ];
    return $badges[$priority] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function getStatusBadge($status) {
    $badges = [
        'open' => '<span class="badge bg-primary">Open</span>',
        'investigating' => '<span class="badge bg-info">Investigating</span>',
        'resolved' => '<span class="badge bg-success">Resolved</span>',
        'closed' => '<span class="badge bg-secondary">Closed</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function getTypeBadge($type) {
    $badges = [
        'produce_quality' => '<span class="badge bg-outline-primary">Produce Quality</span>',
        'delivery_delay' => '<span class="badge bg-outline-warning">Delivery Delay</span>',
        'service_issue' => '<span class="badge bg-outline-danger">Service Issue</span>',
        'payment_issue' => '<span class="badge bg-outline-info">Payment Issue</span>',
        'safety_concern' => '<span class="badge bg-outline-dark">Safety Concern</span>',
        'other' => '<span class="badge bg-outline-secondary">Other</span>'
    ];
    return $badges[$type] ?? '<span class="badge bg-outline-secondary">Unknown</span>';
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
?>
