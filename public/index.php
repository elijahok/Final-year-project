<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
    exit;
}

// Get current user
$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

// Initialize database
$db = Database::getInstance();

// Get marketplace statistics for all users
$marketplaceStats = [
    'total_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'open'")['count'] ?? 0,
    'total_vendors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'vendor'")['count'] ?? 0,
    'total_bidders' => $db->fetch("SELECT COUNT(DISTINCT vendor_id) as count FROM bids")['count'] ?? 0,
    'active_transporters' => $db->fetch("SELECT COUNT(*) as count FROM transporter_profiles WHERE is_available = 1")['count'] ?? 0
];

// Get recent marketplace activity
$recentActivity = $db->fetchAll("
    SELECT 
        al.*,
        u.full_name,
        CASE 
            WHEN al.target_type = 'tender' THEN t.title
            WHEN al.target_type = 'bid' THEN CONCAT('Bid for ', t.title)
            WHEN al.target_type = 'transport_request' THEN CONCAT('Transport Request #', al.target_id)
            ELSE al.description
        END as item_title
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN tenders t ON (al.target_type = 'tender' AND al.target_id = t.id) OR (al.target_type = 'bid' AND al.target_id = t.id)
    WHERE al.action IN ('create_tender', 'submit_bid', 'accept_transport_request', 'create_transport_request')
    ORDER BY al.created_at DESC
    LIMIT 10
");

// Get featured tenders for marketplace display
$featuredTenders = $db->fetchAll("
    SELECT t.*, 
           COUNT(b.id) as bid_count,
           pc.name as category_name
    FROM tenders t
    LEFT JOIN bids b ON t.id = b.tender_id
    LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
    WHERE t.status = 'open'
    AND t.deadline >= DATE(NOW())
    ORDER BY t.created_at DESC
    LIMIT 5
");

// Get dashboard statistics based on user role
$stats = [];
$recentActivities = [];
$upcomingTasks = [];

switch ($userRole) {
    case 'admin':
        // Admin statistics
        $stats = [
            'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
            'total_vendors' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'vendor'")['count'],
            'total_transporters' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'transporter'")['count'],
            'total_farmers' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'")['count'],
            'active_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'open'")['count'],
            'total_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids")['count'],
            'pending_approvals' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'pending_approval'")['count'],
            'total_transactions' => $db->fetch("SELECT COUNT(*) as count FROM mobile_money_transactions")['count']
        ];
        
        // Recent activities
        $recentActivities = $db->fetchAll("
            SELECT al.*, u.full_name, u.role 
            FROM audit_logs al 
            JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        
        // Upcoming tasks
        $upcomingTasks = $db->fetchAll("
            SELECT * FROM tenders 
            WHERE status = 'open' AND deadline > NOW() 
            ORDER BY deadline ASC 
            LIMIT 5
        ");
        break;
        
    case 'vendor':
        // Vendor statistics
        $vendorId = $currentUser['id'];
        $stats = [
            'active_tenders' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'open'")['count'],
            'my_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ?", [$vendorId])['count'],
            'won_bids' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND status = 'awarded'", [$vendorId])['count'],
            'pending_payments' => $db->fetch("SELECT COUNT(*) as count FROM bids WHERE vendor_id = ? AND status = 'awarded' AND payment_status = 'pending'", [$vendorId])['count'],
            'wallet_balance' => $db->fetch("SELECT balance FROM user_wallets WHERE user_id = ?", [$vendorId])['balance'] ?? 0
        ];
        
        // Recent bids
        $recentActivities = $db->fetchAll("
            SELECT b.*, t.title as tender_title, t.deadline 
            FROM bids b 
            JOIN tenders t ON b.tender_id = t.id 
            WHERE b.vendor_id = ? 
            ORDER BY b.created_at DESC 
            LIMIT 10
        ", [$vendorId]);
        
        // Available tenders
        $upcomingTasks = $db->fetchAll("
            SELECT * FROM tenders 
            WHERE status = 'open' AND deadline > NOW() 
            ORDER BY deadline ASC 
            LIMIT 5
        ");
        break;
        
    case 'transporter':
        // Transporter statistics
        $transporterId = $currentUser['id'];
        $stats = [
            'active_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status = 'pending'", [$transporterId])['count'],
            'completed_trips' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status = 'completed'", [$transporterId])['count'],
            'total_earnings' => $db->fetch("SELECT COALESCE(SUM(cost), 0) as total FROM transport_requests WHERE transporter_id = ? AND status = 'completed'", [$transporterId])['total'],
            'average_rating' => $db->fetch("SELECT COALESCE(AVG(rating), 0) as avg_rating FROM transporter_ratings WHERE transporter_id = ?", [$transporterId])['avg_rating'],
            'wallet_balance' => $db->fetch("SELECT balance FROM user_wallets WHERE user_id = ?", [$transporterId])['balance'] ?? 0
        ];
        
        // Recent transport requests
        $recentActivities = $db->fetchAll("
            SELECT tr.*, u.full_name as client_name, u.role as client_role 
            FROM transport_requests tr 
            JOIN users u ON tr.client_id = u.id 
            WHERE tr.transporter_id = ? 
            ORDER BY tr.created_at DESC 
            LIMIT 10
        ", [$transporterId]);
        
        // Available requests
        $upcomingTasks = $db->fetchAll("
            SELECT tr.*, u.full_name as client_name, u.role as client_role 
            FROM transport_requests tr 
            JOIN users u ON tr.client_id = u.id 
            WHERE tr.transporter_id IS NULL AND tr.status = 'pending' 
            ORDER BY tr.created_at DESC 
            LIMIT 5
        ");
        break;
        
    case 'farmer':
        // Farmer statistics
        $farmerId = $currentUser['id'];
        $stats = [
            'active_produce' => $db->fetch("SELECT COUNT(*) as count FROM farmer_produce WHERE farmer_id = ? AND status = 'available'", [$farmerId])['count'],
            'transport_requests' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE client_id = ? AND status != 'completed'", [$farmerId])['count'],
            'quality_reports' => $db->fetch("SELECT COUNT(*) as count FROM quality_reports WHERE farmer_id = ?", [$farmerId])['count'],
            'total_earnings' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM farmer_produce WHERE farmer_id = ? AND status = 'sold'", [$farmerId])['total'],
            'wallet_balance' => $db->fetch("SELECT balance FROM user_wallets WHERE user_id = ?", [$farmerId])['balance'] ?? 0
        ];
        
        // Recent activities
        $recentActivities = $db->fetchAll("
            SELECT * FROM farmer_produce 
            WHERE farmer_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ", [$farmerId]);
        
        // Upcoming tasks
        $upcomingTasks = $db->fetchAll("
            SELECT tr.*, u.full_name as transporter_name 
            FROM transport_requests tr 
            JOIN users u ON tr.transporter_id = u.id 
            WHERE tr.client_id = ? AND tr.status = 'assigned' 
            ORDER BY tr.scheduled_date ASC 
            LIMIT 5
        ", [$farmerId]);
        break;
}

// Get recent notifications
$notifications = $db->fetchAll("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
", [$currentUser['id']]);

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>

<main class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-2">Welcome back, <?= htmlspecialchars($currentUser['full_name']) ?>!</h1>
                            <p class="mb-0">Here's what's happening with your <?= ucfirst($userRole) ?> dashboard today.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex justify-content-md-end gap-3">
                                <div class="text-center">
                                    <h4 class="mb-0"><?= date('d') ?></h4>
                                    <small><?= date('F') ?></small>
                                </div>
                                <div class="text-center">
                                    <h4 class="mb-0"><?= date('H:i') ?></h4>
                                    <small>Local Time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Marketplace Overview (for all users) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-store me-2"></i>Marketplace Overview
                    </h5>
                    <div class="btn-group" role="group">
                        <?php if ($userRole === 'farmer'): ?>
                        <a href="<?= BASE_URL ?>/public/farmer/dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-seedling me-1"></i>Buyer Portal
                        </a>
                        <?php elseif ($userRole === 'vendor'): ?>
                        <a href="<?= BASE_URL ?>/public/vendor/dashboard.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-store me-1"></i>Seller Portal
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="text-center">
                                <div class="h3 text-primary mb-1"><?= $marketplaceStats['total_tenders'] ?></div>
                                <div class="text-muted small">Active Tenders</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="text-center">
                                <div class="h3 text-success mb-1"><?= $marketplaceStats['total_vendors'] ?></div>
                                <div class="text-muted small">Registered Sellers</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="text-center">
                                <div class="h3 text-info mb-1"><?= $marketplaceStats['total_bidders'] ?></div>
                                <div class="text-muted small">Active Bidders</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="text-center">
                                <div class="h3 text-warning mb-1"><?= $marketplaceStats['active_transporters'] ?></div>
                                <div class="text-muted small">Available Transporters</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Tenders (Marketplace Section) -->
    <?php if ($userRole === 'farmer' || $userRole === 'vendor' || $userRole === 'admin'): ?>
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-gavel me-2"></i>Featured Tenders
                    </h5>
                    <a href="<?= BASE_URL ?>/public/vendor/tenders.php" class="btn btn-outline-primary btn-sm">
                        View All Tenders
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($featuredTenders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No active tenders at the moment</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($featuredTenders as $tender): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($tender['title']) ?></h6>
                                        <span class="badge bg-primary"><?= htmlspecialchars($tender['category_name'] ?? 'General') ?></span>
                                    </div>
                                    <p class="card-text small text-muted"><?= htmlspecialchars(substr($tender['description'], 0, 100)) ?>...</p>
                                    <div class="row small text-muted mb-2">
                                        <div class="col-6">
                                            <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($tender['budget_range_min']) ?> - <?= formatCurrency($tender['budget_range_max']) ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-calendar"></i> <?= formatDate($tender['deadline']) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-users"></i> <?= $tender['bid_count'] ?> bids
                                        </small>
                                        <?php if ($userRole === 'vendor'): ?>
                                        <a href="<?= BASE_URL ?>/public/vendor/bid-submission.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-primary">
                                            Place Bid
                                        </a>
                                        <?php elseif ($userRole === 'farmer'): ?>
                                        <a href="<?= BASE_URL ?>/public/farmer/tender-details.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Marketplace Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivity)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent activity</p>
                    </div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?= getActivityColor($activity['action']) ?>"></div>
                            <div class="timeline-content">
                                <div class="small text-muted"><?= timeAgo($activity['created_at']) ?></div>
                                <div class="small">
                                    <strong><?= htmlspecialchars($activity['full_name']) ?></strong>
                                    <?= getActivityDescription($activity['action']) ?>
                                </div>
                                <?php if ($activity['item_title']): ?>
                                <div class="small text-muted"><?= htmlspecialchars($activity['item_title']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Role-specific Statistics Cards -->
    <div class="row mb-4">
        <?php foreach ($stats as $key => $value): ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card <?= getStatCardClass($key) ?>">
                <div class="d-flex align-items-center">
                    <div class="stat-icon <?= getStatIconClass($key) ?>">
                        <i class="fas <?= getStatIcon($key) ?>"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0"><?= formatNumber($value) ?></h5>
                        <small class="text-muted"><?= formatStatLabel($key) ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Recent Activities -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Activities</h5>
                    <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivities)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent activities</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas <?= getActivityIcon($activity, $role) ?> text-primary me-2"></i>
                                            <div>
                                                <div class="fw-bold"><?= getActivityTitle($activity, $role) ?></div>
                                                <small class="text-muted"><?= getActivityDescription($activity, $role) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?= formatDate($activity['created_at'] ?? $activity['date']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($activity['status'] ?? 'pending') ?>">
                                            <?= ucfirst($activity['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDetails('<?= $activity['id'] ?>', '<?= $role ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($role === 'admin'): ?>
                                            <button class="btn btn-outline-secondary" onclick="viewAudit('<?= $activity['id'] ?>')">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications & Quick Actions -->
        <div class="col-lg-4">
            <!-- Notifications -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted small">No new notifications</p>
                    </div>
                    <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <i class="fas <?= getNotificationIcon($notification['type']) ?> text-<?= getNotificationColor($notification['type']) ?>"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-1 small"><?= htmlspecialchars($notification['title']) ?></h6>
                                <p class="mb-1 small text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                                <small class="text-muted"><?= timeAgo($notification['created_at']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/public/notifications.php" class="btn btn-sm btn-outline-primary">View All Notifications</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($role === 'admin'): ?>
                        <a href="<?= BASE_URL ?>/public/admin/create-tender.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Tender
                        </a>
                        <a href="<?= BASE_URL ?>/public/admin/users.php" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                        <a href="<?= BASE_URL ?>/public/admin/analytics.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar me-2"></i>View Analytics
                        </a>
                        <?php elseif ($role === 'vendor'): ?>
                        <a href="<?= BASE_URL ?>/public/vendor/tenders.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>View Tenders
                        </a>
                        <a href="<?= BASE_URL ?>/public/vendor/my-bids.php" class="btn btn-outline-primary">
                            <i class="fas fa-file-contract me-2"></i>My Bids
                        </a>
                        <a href="<?= BASE_URL ?>/public/vendor/profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i>Update Profile
                        </a>
                        <?php elseif ($role === 'transporter'): ?>
                        <a href="<?= BASE_URL ?>/public/transporter/requests.php" class="btn btn-primary">
                            <i class="fas fa-truck me-2"></i>View Requests
                        </a>
                        <a href="<?= BASE_URL ?>/public/transporter/my-trips.php" class="btn btn-outline-primary">
                            <i class="fas fa-route me-2"></i>My Trips
                        </a>
                        <a href="<?= BASE_URL ?>/public/transporter/profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i>Update Profile
                        </a>
                        <?php elseif ($role === 'farmer'): ?>
                        <a href="<?= BASE_URL ?>/public/farmer/produce.php" class="btn btn-primary">
                            <i class="fas fa-seedling me-2"></i>Add Produce
                        </a>
                        <a href="<?= BASE_URL ?>/public/farmer/transport.php" class="btn btn-outline-primary">
                            <i class="fas fa-truck me-2"></i>Request Transport
                        </a>
                        <a href="<?= BASE_URL ?>/public/farmer/quality.php" class="btn btn-outline-primary">
                            <i class="fas fa-clipboard-check me-2"></i>Quality Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Tasks/Events -->
    <?php if (!empty($upcomingTasks)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= getUpcomingTasksTitle($role) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($upcomingTasks as $task): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?= getTaskTitle($task, $role) ?></h6>
                                    <p class="card-text small text-muted"><?= getTaskDescription($task, $role) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= getTaskTime($task, $role) ?>
                                        </small>
                                        <span class="badge bg-<?= getTaskPriorityColor($task) ?>">
                                            <?= getTaskPriority($task) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="<?= getTaskActionLink($task, $role) ?>" class="btn btn-sm btn-primary">
                                        <?= getTaskActionText($task, $role) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php
// Helper functions for dashboard
function getStatCardClass($key) {
    $classes = [
        'total_users' => 'info',
        'active_tenders' => 'primary',
        'my_bids' => 'success',
        'won_bids' => 'success',
        'pending_payments' => 'warning',
        'wallet_balance' => 'success',
        'active_requests' => 'primary',
        'completed_trips' => 'success',
        'total_earnings' => 'success',
        'average_rating' => 'info',
        'active_produce' => 'success',
        'transport_requests' => 'primary',
        'quality_reports' => 'warning'
    ];
    return $classes[$key] ?? 'primary';
}

function getStatIconClass($key) {
    return getStatCardClass($key);
}

function getStatIcon($key) {
    $icons = [
        'total_users' => 'fa-users',
        'total_vendors' => 'fa-store',
        'total_transporters' => 'fa-truck',
        'total_farmers' => 'fa-seedling',
        'active_tenders' => 'fa-file-contract',
        'total_bids' => 'fa-gavel',
        'pending_approvals' => 'fa-clock',
        'total_transactions' => 'fa-exchange-alt',
        'my_bids' => 'fa-gavel',
        'won_bids' => 'fa-trophy',
        'pending_payments' => 'fa-credit-card',
        'wallet_balance' => 'fa-wallet',
        'active_requests' => 'fa-truck-loading',
        'completed_trips' => 'fa-check-circle',
        'total_earnings' => 'fa-money-bill-wave',
        'average_rating' => 'fa-star',
        'active_produce' => 'fa-apple-alt',
        'transport_requests' => 'fa-truck',
        'quality_reports' => 'fa-clipboard-check'
    ];
    return $icons[$key] ?? 'fa-chart-bar';
}

function formatStatLabel($key) {
    $labels = [
        'total_users' => 'Total Users',
        'total_vendors' => 'Vendors',
        'total_transporters' => 'Transporters',
        'total_farmers' => 'Farmers',
        'active_tenders' => 'Active Tenders',
        'total_bids' => 'Total Bids',
        'pending_approvals' => 'Pending Approvals',
        'total_transactions' => 'Transactions',
        'my_bids' => 'My Bids',
        'won_bids' => 'Won Bids',
        'pending_payments' => 'Pending Payments',
        'wallet_balance' => 'Wallet Balance',
        'active_requests' => 'Active Requests',
        'completed_trips' => 'Completed Trips',
        'total_earnings' => 'Total Earnings',
        'average_rating' => 'Avg Rating',
        'active_produce' => 'Active Produce',
        'transport_requests' => 'Transport Requests',
        'quality_reports' => 'Quality Reports'
    ];
    return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

function getActivityIcon($activity, $role) {
    if ($role === 'admin') {
        return 'fa-history';
    } elseif ($role === 'vendor') {
        return 'fa-gavel';
    } elseif ($role === 'transporter') {
        return 'fa-truck';
    } elseif ($role === 'farmer') {
        return 'fa-seedling';
    }
    return 'fa-circle';
}

function getActivityTitle($activity, $role) {
    if ($role === 'admin') {
        return $activity['action'] ?? 'System Activity';
    } elseif ($role === 'vendor') {
        return $activity['tender_title'] ?? 'Bid Activity';
    } elseif ($role === 'transporter') {
        return 'Transport Request';
    } elseif ($role === 'farmer') {
        return $activity['produce_name'] ?? 'Produce Activity';
    }
    return 'Activity';
}

function getActivityDescription($activity, $role) {
    if ($role === 'admin') {
        return $activity['details'] ?? 'System action performed';
    } elseif ($role === 'vendor') {
        return "Bid for {$activity['tender_title']} - Amount: " . formatCurrency($activity['amount'] ?? 0);
    } elseif ($role === 'transporter') {
        return "Request from {$activity['client_name']} ({$activity['client_role']})";
    } elseif ($role === 'farmer') {
        return "{$activity['produce_name']} - {$activity['quantity']} {$activity['unit']}";
    }
    return 'No description available';
}

function getUpcomingTasksTitle($role) {
    $titles = [
        'admin' => 'Upcoming Tender Deadlines',
        'vendor' => 'Available Tenders',
        'transporter' => 'Available Transport Requests',
        'farmer' => 'Scheduled Pickups'
    ];
    return $titles[$role] ?? 'Upcoming Tasks';
}

function getTaskTitle($task, $role) {
    if ($role === 'admin') {
        return $task['title'];
    } elseif ($role === 'vendor') {
        return $task['title'];
    } elseif ($role === 'transporter') {
        return "Request from {$task['client_name']}";
    } elseif ($role === 'farmer') {
        return "Pickup with {$task['transporter_name']}";
    }
    return 'Task';
}

function getTaskDescription($task, $role) {
    if ($role === 'admin') {
        return $task['description'];
    } elseif ($role === 'vendor') {
        return $task['description'];
    } elseif ($role === 'transporter') {
        return "From: {$task['pickup_location']} To: {$task['delivery_location']}";
    } elseif ($role === 'farmer') {
        return "Pickup location: {$task['pickup_location']}";
    }
    return 'Task description';
}

function getTaskTime($task, $role) {
    if ($role === 'admin' || $role === 'vendor') {
        return 'Deadline: ' . formatDate($task['deadline']);
    } elseif ($role === 'transporter' || $role === 'farmer') {
        return 'Scheduled: ' . formatDate($task['scheduled_date']);
    }
    return 'No time specified';
}

function getTaskPriorityColor($task) {
    $priority = $task['priority'] ?? 'medium';
    $colors = [
        'low' => 'success',
        'medium' => 'info',
        'high' => 'warning',
        'urgent' => 'danger'
    ];
    return $colors[$priority] ?? 'info';
}

function getTaskPriority($task) {
    return ucfirst($task['priority'] ?? 'medium');
}

function getTaskActionLink($task, $role) {
    if ($role === 'admin') {
        return BASE_URL . "/public/admin/tender-details.php?id={$task['id']}";
    } elseif ($role === 'vendor') {
        return BASE_URL . "/public/vendor/tender-details.php?id={$task['id']}";
    } elseif ($role === 'transporter') {
        return BASE_URL . "/public/transporter/request-details.php?id={$task['id']}";
    } elseif ($role === 'farmer') {
        return BASE_URL . "/public/farmer/transport-details.php?id={$task['id']}";
    }
    return '#';
}

function getTaskActionText($task, $role) {
    if ($role === 'admin') {
        return 'View Details';
    } elseif ($role === 'vendor') {
        return 'Place Bid';
    } elseif ($role === 'transporter') {
        return 'Accept Request';
    } elseif ($role === 'farmer') {
        return 'View Details';
    }
    return 'View';
}
?>

<script>
// View details function
function viewDetails(id, role) {
    // This would typically open a modal or navigate to details page
    console.log('View details:', id, role);
    showNotification('Opening details...', 'info');
}

// View audit function (admin only)
function viewAudit(id) {
    // This would open audit trail
    console.log('View audit:', id);
    showNotification('Loading audit trail...', 'info');
}
</script>

<?php include '../includes/footer.php'; ?>
