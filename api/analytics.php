<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
$userRole = $currentUser['role'];
$userId = $currentUser['id'];

// Get action parameter
$action = $_GET['action'] ?? '';

// Validate action
if (!in_array($action, ['dashboard', 'stats', 'trends', 'reports', 'export', 'custom'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    switch ($action) {
        case 'dashboard':
            handleDashboard();
            break;
        case 'stats':
            handleStats();
            break;
        case 'trends':
            handleTrends();
            break;
        case 'reports':
            handleReports();
            break;
        case 'export':
            handleExport();
            break;
        case 'custom':
            handleCustom();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle dashboard analytics
 */
function handleDashboard() {
    global $db, $userRole, $userId;
    
    $timeframe = $_GET['timeframe'] ?? '30'; // days
    $timeframe = (int)$timeframe;
    
    $startDate = date('Y-m-d', strtotime("-{$timeframe} days"));
    $endDate = date('Y-m-d');
    
    $data = [];
    
    // Role-specific dashboard data
    switch ($userRole) {
        case 'admin':
            $data = getAdminDashboard($startDate, $endDate);
            break;
        case 'farmer':
            $data = getFarmerDashboard($startDate, $endDate);
            break;
        case 'transporter':
            $data = getTransporterDashboard($startDate, $endDate);
            break;
        case 'vendor':
            $data = getVendorDashboard($startDate, $endDate);
            break;
        default:
            $data = ['error' => 'Invalid user role'];
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Handle general statistics
 */
function handleStats() {
    global $db, $userRole, $userId;
    
    $type = $_GET['type'] ?? 'overview';
    $timeframe = $_GET['timeframe'] ?? '30';
    $timeframe = (int)$timeframe;
    
    $startDate = date('Y-m-d', strtotime("-{$timeframe} days"));
    $endDate = date('Y-m-d');
    
    $stats = [];
    
    switch ($type) {
        case 'overview':
            $stats = getOverviewStats($startDate, $endDate);
            break;
        case 'tenders':
            $stats = getTenderStats($startDate, $endDate);
            break;
        case 'transport':
            $stats = getTransportStats($startDate, $endDate);
            break;
        case 'payments':
            $stats = getPaymentStats($startDate, $endDate);
            break;
        case 'quality':
            $stats = getQualityStats($startDate, $endDate);
            break;
        case 'users':
            $stats = getUserStats($startDate, $endDate);
            break;
        default:
            $stats = ['error' => 'Invalid stats type'];
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

/**
 * Handle trend data
 */
function handleTrends() {
    global $db, $userRole, $userId;
    
    $type = $_GET['type'] ?? 'daily';
    $period = $_GET['period'] ?? '30';
    $period = (int)$period;
    
    $trends = [];
    
    switch ($type) {
        case 'daily':
            $trends = getDailyTrends($period);
            break;
        case 'weekly':
            $trends = getWeeklyTrends($period);
            break;
        case 'monthly':
            $trends = getMonthlyTrends($period);
            break;
        case 'revenue':
            $trends = getRevenueTrends($period);
            break;
        case 'activity':
            $trends = getActivityTrends($period);
            break;
        default:
            $trends = ['error' => 'Invalid trend type'];
    }
    
    echo json_encode(['success' => true, 'trends' => $trends]);
}

/**
 * Handle reports generation
 */
function handleReports() {
    global $db, $userRole, $userId;
    
    $reportType = $_GET['report_type'] ?? 'summary';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $report = [];
    
    switch ($reportType) {
        case 'summary':
            $report = generateSummaryReport($startDate, $endDate);
            break;
        case 'tenders':
            $report = generateTenderReport($startDate, $endDate);
            break;
        case 'transport':
            $report = generateTransportReport($startDate, $endDate);
            break;
        case 'payments':
            $report = generatePaymentReport($startDate, $endDate);
            break;
        case 'quality':
            $report = generateQualityReport($startDate, $endDate);
            break;
        case 'users':
            $report = generateUserReport($startDate, $endDate);
            break;
        default:
            $report = ['error' => 'Invalid report type'];
    }
    
    echo json_encode(['success' => true, 'report' => $report]);
}

/**
 * Handle data export
 */
function handleExport() {
    global $db, $userRole, $userId;
    
    $exportType = $_GET['export_type'] ?? 'summary';
    $format = $_GET['format'] ?? 'csv';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    if ($format !== 'csv' && $format !== 'json') {
        echo json_encode(['success' => false, 'error' => 'Invalid export format']);
        return;
    }
    
    $data = [];
    
    switch ($exportType) {
        case 'summary':
            $data = exportSummaryData($startDate, $endDate);
            break;
        case 'tenders':
            $data = exportTenderData($startDate, $endDate);
            break;
        case 'transport':
            $data = exportTransportData($startDate, $endDate);
            break;
        case 'payments':
            $data = exportPaymentData($startDate, $endDate);
            break;
        case 'quality':
            $data = exportQualityData($startDate, $endDate);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid export type']);
            return;
    }
    
    if ($format === 'csv') {
        exportToCSV($data, $exportType);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

/**
 * Handle custom queries
 */
function handleCustom() {
    global $db, $userRole, $userId;
    
    $query = $_GET['query'] ?? '';
    $params = $_GET['params'] ?? [];
    
    if (empty($query) || $userRole !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Invalid request or insufficient permissions']);
        return;
    }
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Query execution failed: ' . $e->getMessage()]);
    }
}

/**
 * Get admin dashboard data
 */
function getAdminDashboard($startDate, $endDate) {
    global $db;
    
    $dashboard = [
        'overview' => [
            'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
            'total_vendors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'vendor'")['count'],
            'total_transporters' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'transporter'")['count'],
            'total_farmers' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'")['count'],
            'active_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'open'")['count'],
            'total_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids")['count'],
            'total_transport_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests")['count'],
            'total_transactions' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions")['count']
        ],
        'period_stats' => [
            'new_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'new_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'new_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'completed_transports' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE status = 'completed' AND updated_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'total_revenue' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM mobile_money_transactions WHERE status = 'completed' AND created_at BETWEEN ? AND ?", [$startDate, $endDate])['total'],
            'quality_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count']
        ],
        'top_performers' => [
            'vendors' => $db->fetchAll("SELECT u.full_name, COUNT(b.id) as bid_count, AVG(b.total_amount) as avg_bid FROM users u LEFT JOIN bids b ON u.id = b.vendor_id WHERE u.role = 'vendor' GROUP BY u.id ORDER BY bid_count DESC LIMIT 5"),
            'transporters' => $db->fetchAll("SELECT u.full_name, COUNT(tr.id) as transport_count, AVG(tr.rating) as avg_rating FROM users u LEFT JOIN transport_requests tr ON u.id = tr.transporter_id WHERE u.role = 'transporter' AND tr.status = 'completed' GROUP BY u.id ORDER BY transport_count DESC LIMIT 5"),
            'farmers' => $db->fetchAll("SELECT u.full_name, COUNT(tr.id) as request_count, SUM(tr.transport_cost) as total_spent FROM users u LEFT JOIN transport_requests tr ON u.id = tr.farmer_id WHERE u.role = 'farmer' GROUP BY u.id ORDER BY request_count DESC LIMIT 5")
        ],
        'recent_activity' => $db->fetchAll("
            SELECT al.*, u.full_name, u.role
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= ?
            ORDER BY al.created_at DESC
            LIMIT 10
        ", [$startDate])
    ];
    
    return $dashboard;
}

/**
 * Get farmer dashboard data
 */
function getFarmerDashboard($startDate, $endDate) {
    global $db, $userId;
    
    $dashboard = [
        'overview' => [
            'total_transport_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ?", [$userId])['count'],
            'active_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status IN ('pending', 'accepted')", [$userId])['count'],
            'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'completed'", [$userId])['count'],
            'total_spent' => $db->fetch("SELECT COALESCE(SUM(transport_cost), 0) as total FROM transport_requests WHERE farmer_id = ? AND status = 'completed'", [$userId])['total'],
            'quality_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports WHERE reporter_id = ?", [$userId])['count'],
            'wallet_balance' => $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$userId])['balance'] ?? 0
        ],
        'period_stats' => [
            'new_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND created_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'completed' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'total_spent' => $db->fetch("SELECT COALESCE(SUM(transport_cost), 0) as total FROM transport_requests WHERE farmer_id = ? AND status = 'completed' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['total'],
            'quality_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports WHERE reporter_id = ? AND created_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count']
        ],
        'recent_requests' => $db->fetchAll("
            SELECT tr.*, u.full_name as transporter_name
            FROM transport_requests tr
            LEFT JOIN users u ON tr.transporter_id = u.id
            WHERE tr.farmer_id = ?
            ORDER BY tr.created_at DESC
            LIMIT 5
        ", [$userId]),
        'transport_trends' => getTransportTrendsForUser($userId, $startDate, $endDate)
    ];
    
    return $dashboard;
}

/**
 * Get transporter dashboard data
 */
function getTransporterDashboard($startDate, $endDate) {
    global $db, $userId;
    
    $dashboard = [
        'overview' => [
            'total_assignments' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ?", [$userId])['count'],
            'active_assignments' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status IN ('accepted', 'in_transit')", [$userId])['count'],
            'completed_assignments' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status = 'completed'", [$userId])['count'],
            'total_earned' => $db->fetch("SELECT COALESCE(SUM(transport_cost), 0) as total FROM transport_requests WHERE transporter_id = ? AND status = 'completed'", [$userId])['total'],
            'average_rating' => $db->fetch("SELECT COALESCE(AVG(rating), 0) as avg_rating FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND rating IS NOT NULL", [$userId])['avg_rating'],
            'wallet_balance' => $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$userId])['balance'] ?? 0
        ],
        'period_stats' => [
            'new_assignments' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND created_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'completed_assignments' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'total_earned' => $db->fetch("SELECT COALESCE(SUM(transport_cost), 0) as total FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['total'],
            'average_rating' => $db->fetch("SELECT COALESCE(AVG(rating), 0) as avg_rating FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND rating IS NOT NULL AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['avg_rating']
        ],
        'recent_assignments' => $db->fetchAll("
            SELECT tr.*, u.full_name as farmer_name
            FROM transport_requests tr
            LEFT JOIN users u ON tr.farmer_id = u.id
            WHERE tr.transporter_id = ?
            ORDER BY tr.created_at DESC
            LIMIT 5
        ", [$userId]),
        'performance_metrics' => [
            'on_time_delivery_rate' => $db->fetch("SELECT (COUNT(CASE WHEN delivered_at <= deadline THEN 1 END) * 100.0 / COUNT(*)) as rate FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND deadline IS NOT NULL", [$userId])['rate'] ?? 0,
            'average_response_time' => $db->fetch("SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)), 0) as avg_time FROM transport_requests WHERE transporter_id = ? AND status = 'completed' AND accepted_at IS NOT NULL", [$userId])['avg_time'] ?? 0
        ]
    ];
    
    return $dashboard;
}

/**
 * Get vendor dashboard data
 */
function getVendorDashboard($startDate, $endDate) {
    global $db, $userId;
    
    $dashboard = [
        'overview' => [
            'total_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ?", [$userId])['count'],
            'won_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND status = 'awarded'", [$userId])['count'],
            'pending_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND status = 'submitted'", [$userId])['count'],
            'total_value' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM bids WHERE vendor_id = ? AND status = 'awarded'", [$userId])['total'],
            'win_rate' => $db->fetch("SELECT (COUNT(CASE WHEN status = 'awarded' THEN 1 END) * 100.0 / COUNT(*)) as rate FROM bids WHERE vendor_id = ?", [$userId])['rate'] ?? 0,
            'wallet_balance' => $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$userId])['balance'] ?? 0
        ],
        'period_stats' => [
            'new_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND created_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'won_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND status = 'awarded' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['count'],
            'total_value' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM bids WHERE vendor_id = ? AND status = 'awarded' AND updated_at BETWEEN ? AND ?", [$userId, $startDate, $endDate])['total']
        ],
        'recent_bids' => $db->fetchAll("
            SELECT b.*, t.title as tender_title, t.deadline
            FROM bids b
            LEFT JOIN tenders t ON b.tender_id = t.id
            WHERE b.vendor_id = ?
            ORDER BY b.created_at DESC
            LIMIT 5
        ", [$userId]),
        'bidding_trends' => getBiddingTrendsForUser($userId, $startDate, $endDate)
    ];
    
    return $dashboard;
}

/**
 * Get overview statistics
 */
function getOverviewStats($startDate, $endDate) {
    global $db;
    
    return [
        'users' => [
            'total' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
            'new' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'by_role' => $db->fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role")
        ],
        'tenders' => [
            'total' => $db->fetch("SELECT COUNT(*) as count FROM tenders")['count'],
            'new' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'by_status' => $db->fetchAll("SELECT status, COUNT(*) as count FROM tenders GROUP BY status")
        ],
        'bids' => [
            'total' => $db->fetch("SELECT COUNT(*) as count FROM bids")['count'],
            'new' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'by_status' => $db->fetchAll("SELECT status, COUNT(*) as count FROM bids GROUP BY status")
        ],
        'transport' => [
            'total' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests")['count'],
            'new' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'by_status' => $db->fetchAll("SELECT status, COUNT(*) as count FROM transport_requests GROUP BY status")
        ],
        'payments' => [
            'total' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions")['count'],
            'total_amount' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM mobile_money_transactions WHERE status = 'completed'")['total'],
            'new' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'new_amount' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM mobile_money_transactions WHERE status = 'completed' AND created_at BETWEEN ? AND ?", [$startDate, $endDate])['total']
        ]
    ];
}

/**
 * Get daily trends
 */
function getDailyTrends($period) {
    global $db;
    
    $trends = [];
    
    for ($i = $period - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $trends[] = [
            'date' => $date,
            'new_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?", [$date])['count'],
            'new_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE DATE(created_at) = ?", [$date])['count'],
            'new_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE DATE(created_at) = ?", [$date])['count'],
            'new_transport_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE DATE(created_at) = ?", [$date])['count'],
            'completed_transports' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE DATE(updated_at) = ? AND status = 'completed'", [$date])['count'],
            'payments' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions WHERE DATE(created_at) = ? AND status = 'completed'", [$date])['count']
        ];
    }
    
    return $trends;
}

/**
 * Get revenue trends
 */
function getRevenueTrends($period) {
    global $db;
    
    $trends = [];
    
    for ($i = $period - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $trends[] = [
            'date' => $date,
            'revenue' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM mobile_money_transactions WHERE DATE(created_at) = ? AND status = 'completed'", [$date])['total'],
            'transport_revenue' => $db->fetch("SELECT COALESCE(SUM(transport_cost), 0) as total FROM transport_requests WHERE DATE(updated_at) = ? AND status = 'completed'", [$date])['total'],
            'bid_revenue' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM bids WHERE DATE(updated_at) = ? AND status = 'awarded'", [$date])['total']
        ];
    }
    
    return $trends;
}

/**
 * Get activity trends
 */
function getActivityTrends($period) {
    global $db;
    
    $trends = [];
    
    for ($i = $period - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $trends[] = [
            'date' => $date,
            'activities' => $db->fetch("SELECT COUNT(*) as count FROM audit_logs WHERE DATE(created_at) = ?", [$date])['count'],
            'logins' => $db->fetch("SELECT COUNT(*) as count FROM audit_logs WHERE DATE(created_at) = ? AND action = 'login'", [$date])['count'],
            'registrations' => $db->fetch("SELECT COUNT(*) as count FROM audit_logs WHERE DATE(created_at) = ? AND action = 'register'", [$date])['count']
        ];
    }
    
    return $trends;
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename) {
    $filename = $filename . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Header row
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Helper functions for user-specific trends
 */
function getTransportTrendsForUser($userId, $startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM transport_requests
        WHERE farmer_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$userId, $startDate, $endDate]);
}

function getBiddingTrendsForUser($userId, $startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM bids
        WHERE vendor_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$userId, $startDate, $endDate]);
}

// Additional helper functions for stats and reports
function getTenderStats($startDate, $endDate) {
    global $db;
    
    return [
        'total_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'by_category' => $db->fetchAll("
            SELECT pc.name, COUNT(t.id) as count
            FROM tenders t
            LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
            WHERE t.created_at BETWEEN ? AND ?
            GROUP BY pc.name
        ", [$startDate, $endDate]),
        'by_status' => $db->fetchAll("
            SELECT status, COUNT(*) as count
            FROM tenders
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ", [$startDate, $endDate]),
        'average_value' => $db->fetch("SELECT COALESCE(AVG(estimated_value), 0) as avg FROM tenders WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['avg']
    ];
}

function getTransportStats($startDate, $endDate) {
    global $db;
    
    return [
        'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'by_status' => $db->fetchAll("
            SELECT status, COUNT(*) as count
            FROM transport_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ", [$startDate, $endDate]),
        'average_cost' => $db->fetch("SELECT COALESCE(AVG(transport_cost), 0) as avg FROM transport_requests WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['avg'],
        'completion_rate' => $db->fetch("
            SELECT (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as rate
            FROM transport_requests
            WHERE created_at BETWEEN ? AND ?
        ", [$startDate, $endDate])['rate'] ?? 0
    ];
}

function getPaymentStats($startDate, $endDate) {
    global $db;
    
    return [
        'total_transactions' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'total_amount' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM mobile_money_transactions WHERE status = 'completed' AND created_at BETWEEN ? AND ?", [$startDate, $endDate])['total'],
        'by_provider' => $db->fetchAll("
            SELECT provider, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
            FROM mobile_money_transactions
            WHERE created_at BETWEEN ? AND ? AND status = 'completed'
            GROUP BY provider
        ", [$startDate, $endDate]),
        'by_status' => $db->fetchAll("
            SELECT status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
            FROM mobile_money_transactions
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ", [$startDate, $endDate])
    ];
}

function getQualityStats($startDate, $endDate) {
    global $db;
    
    return [
        'total_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'by_type' => $db->fetchAll("
            SELECT report_type, COUNT(*) as count
            FROM quality_reports
            WHERE created_at BETWEEN ? AND ?
            GROUP BY report_type
        ", [$startDate, $endDate]),
        'by_priority' => $db->fetchAll("
            SELECT priority, COUNT(*) as count
            FROM quality_reports
            WHERE created_at BETWEEN ? AND ?
            GROUP BY priority
        ", [$startDate, $endDate]),
        'by_status' => $db->fetchAll("
            SELECT status, COUNT(*) as count
            FROM quality_reports
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ", [$startDate, $endDate]),
        'resolution_rate' => $db->fetch("
            SELECT (COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) * 100.0 / COUNT(*)) as rate
            FROM quality_reports
            WHERE created_at BETWEEN ? AND ?
        ", [$startDate, $endDate])['rate'] ?? 0
    ];
}

function getUserStats($startDate, $endDate) {
    global $db;
    
    return [
        'new_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'by_role' => $db->fetchAll("
            SELECT role, COUNT(*) as count
            FROM users
            WHERE created_at BETWEEN ? AND ?
            GROUP BY role
        ", [$startDate, $endDate]),
        'active_users' => $db->fetch("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'],
        'registration_trends' => $db->fetchAll("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM users
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ", [$startDate, $endDate])
    ];
}

// Report generation functions
function generateSummaryReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'overview' => getOverviewStats($startDate, $endDate),
        'trends' => [
            'daily' => getDailyTrends(min(30, ceil((strtotime($endDate) - strtotime($startDate)) / 86400))),
            'revenue' => getRevenueTrends(min(30, ceil((strtotime($endDate) - strtotime($startDate)) / 86400)))
        ]
    ];
}

function generateTenderReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'stats' => getTenderStats($startDate, $endDate),
        'details' => $db->fetchAll("
            SELECT t.*, pc.name as category_name, u.full_name as created_by_name
            FROM tenders t
            LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.created_at BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ", [$startDate, $endDate])
    ];
}

function generateTransportReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'stats' => getTransportStats($startDate, $endDate),
        'details' => $db->fetchAll("
            SELECT tr.*, 
                   u1.full_name as farmer_name,
                   u2.full_name as transporter_name
            FROM transport_requests tr
            LEFT JOIN users u1 ON tr.farmer_id = u1.id
            LEFT JOIN users u2 ON tr.transporter_id = u2.id
            WHERE tr.created_at BETWEEN ? AND ?
            ORDER BY tr.created_at DESC
        ", [$startDate, $endDate])
    ];
}

function generatePaymentReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'stats' => getPaymentStats($startDate, $endDate),
        'details' => $db->fetchAll("
            SELECT mmt.*, u.full_name as user_name
            FROM mobile_money_transactions mmt
            LEFT JOIN users u ON mmt.user_id = u.id
            WHERE mmt.created_at BETWEEN ? AND ?
            ORDER BY mmt.created_at DESC
        ", [$startDate, $endDate])
    ];
}

function generateQualityReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'stats' => getQualityStats($startDate, $endDate),
        'details' => $db->fetchAll("
            SELECT qr.*, u.full_name as reporter_name
            FROM quality_reports qr
            LEFT JOIN users u ON qr.reporter_id = u.id
            WHERE qr.created_at BETWEEN ? AND ?
            ORDER BY qr.created_at DESC
        ", [$startDate, $endDate])
    ];
}

function generateUserReport($startDate, $endDate) {
    global $db;
    
    return [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'stats' => getUserStats($startDate, $endDate),
        'details' => $db->fetchAll("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM tenders WHERE created_by = u.id) as tender_count,
                   (SELECT COUNT(*) FROM bids WHERE vendor_id = u.id) as bid_count,
                   (SELECT COUNT(*) FROM transport_requests WHERE farmer_id = u.id) as farmer_request_count,
                   (SELECT COUNT(*) FROM transport_requests WHERE transporter_id = u.id) as transporter_request_count
            FROM users u
            WHERE u.created_at BETWEEN ? AND ?
            ORDER BY u.created_at DESC
        ", [$startDate, $endDate])
    ];
}

// Export data functions
function exportSummaryData($startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            DATE(u.created_at) as date,
            COUNT(CASE WHEN u.role = 'vendor' THEN 1 END) as new_vendors,
            COUNT(CASE WHEN u.role = 'transporter' THEN 1 END) as new_transporters,
            COUNT(CASE WHEN u.role = 'farmer' THEN 1 END) as new_farmers,
            COUNT(t.id) as new_tenders,
            COUNT(b.id) as new_bids,
            COUNT(tr.id) as new_transport_requests,
            COUNT(mmt.id) as completed_payments,
            COALESCE(SUM(mmt.amount), 0) as total_revenue
        FROM dates d
        LEFT JOIN users u ON DATE(u.created_at) = d.date
        LEFT JOIN tenders t ON DATE(t.created_at) = d.date
        LEFT JOIN bids b ON DATE(b.created_at) = d.date
        LEFT JOIN transport_requests tr ON DATE(tr.created_at) = d.date
        LEFT JOIN mobile_money_transactions mmt ON DATE(mmt.created_at) = d.date AND mmt.status = 'completed'
        WHERE d.date BETWEEN ? AND ?
        GROUP BY d.date
        ORDER BY d.date
    ", [$startDate, $endDate]);
}

function exportTenderData($startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            t.id,
            t.title,
            t.description,
            t.status,
            t.estimated_value,
            t.deadline,
            pc.name as category,
            u.full_name as created_by,
            t.created_at,
            COUNT(b.id) as bid_count,
            COALESCE(AVG(b.total_amount), 0) as avg_bid_amount
        FROM tenders t
        LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN bids b ON t.id = b.tender_id
        WHERE t.created_at BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ", [$startDate, $endDate]);
}

function exportTransportData($startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            tr.id,
            tr.pickup_location,
            tr.delivery_location,
            tr.status,
            tr.transport_cost,
            tr.deadline,
            tr.delivered_at,
            u1.full_name as farmer_name,
            u2.full_name as transporter_name,
            tr.rating,
            tr.created_at,
            tr.updated_at
        FROM transport_requests tr
        LEFT JOIN users u1 ON tr.farmer_id = u1.id
        LEFT JOIN users u2 ON tr.transporter_id = u2.id
        WHERE tr.created_at BETWEEN ? AND ?
        ORDER BY tr.created_at DESC
    ", [$startDate, $endDate]);
}

function exportPaymentData($startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            mmt.id,
            mmt.transaction_id,
            mmt.provider,
            mmt.phone_number,
            mmt.amount,
            mmt.status,
            mmt.transaction_type,
            u.full_name as user_name,
            mmt.created_at,
            mmt.updated_at
        FROM mobile_money_transactions mmt
        LEFT JOIN users u ON mmt.user_id = u.id
        WHERE mmt.created_at BETWEEN ? AND ?
        ORDER BY mmt.created_at DESC
    ", [$startDate, $endDate]);
}

function exportQualityData($startDate, $endDate) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            qr.id,
            qr.title,
            qr.report_type,
            qr.priority,
            qr.status,
            qr.description,
            u.full_name as reporter_name,
            qr.created_at,
            qr.updated_at,
            qr.resolved_at
        FROM quality_reports qr
        LEFT JOIN users u ON qr.reporter_id = u.id
        WHERE qr.created_at BETWEEN ? AND ?
        ORDER BY qr.created_at DESC
    ", [$startDate, $endDate]);
}

?>
