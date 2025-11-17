<?php
// Include configuration and functions
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/public/login.php');
}

// Initialize database
$db = Database::getInstance();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$targetType = $_GET['target_type'] ?? '';

// Build WHERE conditions
$whereConditions = [];
$params = [];

if ($actionFilter) {
    $whereConditions[] = "al.action = ?";
    $params[] = $actionFilter;
}

if ($userFilter) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userFilter;
}

if ($targetType) {
    $whereConditions[] = "al.target_type = ?";
    $params[] = $targetType;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total records
$totalRecords = $db->fetch("
    SELECT COUNT(*) as total
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
", $params)['total'];

// Get audit logs with pagination
$auditLogs = $db->fetchAll("
    SELECT al.*, u.full_name, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Get unique actions for filter
$actions = $db->fetchAll("
    SELECT DISTINCT action, COUNT(*) as count
    FROM audit_logs
    GROUP BY action
    ORDER BY count DESC
");

// Get users for filter
$users = $db->fetchAll("
    SELECT DISTINCT u.id, u.full_name, u.role, COUNT(al.id) as log_count
    FROM users u
    LEFT JOIN audit_logs al ON u.id = al.user_id
    WHERE u.role IN ('admin', 'vendor', 'transporter', 'farmer')
    GROUP BY u.id
    ORDER BY log_count DESC
");

// Get target types for filter
$targetTypes = $db->fetchAll("
    SELECT DISTINCT target_type, COUNT(*) as count
    FROM audit_logs
    WHERE target_type IS NOT NULL
    GROUP BY target_type
    ORDER BY count DESC
");

// Calculate pagination
$totalPages = ceil($totalRecords / $perPage);

// Handle export
if (isset($_GET['export'])) {
    exportAuditLogs();
    exit;
}

$pageTitle = 'Audit Logs';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Audit Logs</h1>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="exportLogs()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-outline-primary me-2" onclick="printLogs()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button class="btn btn-outline-danger" onclick="clearLogs()">
                        <i class="fas fa-trash me-2"></i>Clear Old Logs
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($totalRecords) ?></h4>
                            <p class="mb-0">Total Logs</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-list fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= count($actions) ?></h4>
                            <p class="mb-0">Unique Actions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-tasks fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= count($users) ?></h4>
                            <p class="mb-0">Active Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $db->fetch("SELECT COUNT(*) as count FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'] ?></h4>
                            <p class="mb-0">Last 24 Hours</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="action" class="form-label">Action</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?= $action['action'] ?>" <?= $actionFilter === $action['action'] ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $action['action'])) ?> (<?= $action['count'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="user" class="form-label">User</label>
                    <select class="form-select" id="user" name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $userFilter == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="target_type" class="form-label">Target Type</label>
                    <select class="form-select" id="target_type" name="target_type">
                        <option value="">All Types</option>
                        <?php foreach ($targetTypes as $type): ?>
                        <option value="<?= $type['target_type'] ?>" <?= $targetType === $type['target_type'] ? 'selected' : '' ?>>
                            <?= ucfirst($type['target_type']) ?> (<?= $type['count'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $dateFrom ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $dateTo ?>">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Audit Log Entries</h5>
            <span class="badge bg-secondary"><?= number_format($totalRecords) ?> records</span>
        </div>
        <div class="card-body">
            <?php if (empty($auditLogs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No audit logs found</h5>
                <p class="text-muted">Try adjusting your filters or check back later.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="auditTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Target</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td>
                                <div><?= formatDate($log['created_at']) ?></div>
                                <small class="text-muted"><?= formatTime($log['created_at']) ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                        <?= strtoupper(substr($log['full_name'] ?? 'Unknown', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></div>
                                        <small class="text-muted"><?= ucfirst($log['role'] ?? 'system') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?= getActionColor($log['action']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($log['description']) ?></div>
                                <?php if ($log['details']): ?>
                                <small class="text-muted">
                                    <a href="#" onclick="showDetails(<?= $log['id'] ?>)" class="text-decoration-none">
                                        View details <i class="fas fa-chevron-right"></i>
                                    </a>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['target_type'] && $log['target_id']): ?>
                                <div>
                                    <small class="text-muted"><?= ucfirst($log['target_type']) ?></small><br>
                                    #<?= $log['target_id'] ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="small"><?= $log['ip_address'] ?></code>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="showLogDetails(<?= $log['id'] ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= buildQueryString() ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= buildQueryString() ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= buildQueryString() ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Old Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="clearLogsForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="clear_logs" value="1">
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">Warning!</h6>
                        <p class="mb-0">This will permanently delete audit logs older than the specified date. This action cannot be undone.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="clear_date" class="form-label">Delete logs older than</label>
                        <input type="date" class="form-control" id="clear_date" name="clear_date" 
                               value="<?= date('Y-m-d', strtotime('-90 days')) ?>" required>
                        <small class="text-muted">Logs older than this date will be permanently deleted</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show log details
function showLogDetails(logId) {
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    const modalBody = document.getElementById('logDetailsBody');
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch(`<?= BASE_URL ?>/api/audit-log-details.php?id=${logId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = data.html;
            modal.show();
        } else {
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load log details</div>';
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading log details</div>';
    });
}

// Show details inline
function showDetails(logId) {
    // This could expand a row to show details inline
    showLogDetails(logId);
}

// Export logs
function exportLogs() {
    const url = new URL(window.location);
    url.searchParams.set('export', 'csv');
    window.open(url.toString());
}

// Print logs
function printLogs() {
    window.print();
}

// Clear old logs
function clearLogs() {
    const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
    modal.show();
}

// Handle clear logs form
document.getElementById('clearLogsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Set loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        showNotification('Old logs deleted successfully', 'success');
        location.reload();
    })
    .catch(error => {
        showNotification('Error deleting logs', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Delete Logs';
    });
});

// Auto-refresh logs every 30 seconds
setInterval(() => {
    if (!document.hidden) {
        location.reload();
    }
}, 30000);

// Handle export
<?php if (isset($_GET['export']) && $_GET['export'] === 'csv'): ?>
function exportAuditLogs() {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit-logs-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Date', 'Time', 'User', 'Role', 'Action', 'Description', 
        'Target Type', 'Target ID', 'IP Address', 'User Agent'
    ]);
    
    // CSV data
    foreach ($auditLogs as $log) {
        fputcsv($output, [
            formatDate($log['created_at'], 'Y-m-d'),
            formatTime($log['created_at']),
            $log['full_name'] ?? 'Unknown',
            $log['role'] ?? 'system',
            $log['action'],
            $log['description'],
            $log['target_type'],
            $log['target_id'],
            $log['ip_address'],
            $log['user_agent']
        ]);
    }
    
    fclose($output);
    exit;
}
<?php endif; ?>

// Build query string for pagination
function buildQueryString() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    const queryString = params.toString();
    return queryString ? '&' + queryString : '';
}
</script>

<?php
// Helper functions
function getActionColor($action) {
    $colors = [
        'login' => 'success',
        'logout' => 'secondary',
        'create' => 'primary',
        'update' => 'info',
        'delete' => 'danger',
        'award' => 'success',
        'submit' => 'primary',
        'reject' => 'danger',
        'approve' => 'success',
        'view' => 'info',
        'export' => 'warning',
        'print' => 'secondary'
    ];
    
    return $colors[$action] ?? 'secondary';
}

// Handle clear logs POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request', 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
    
    $clearDate = $_POST['clear_date'] ?? '';
    
    if (!$clearDate) {
        setFlashMessage('Please specify a date', 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
    
    try {
        $deleted = $db->delete('audit_logs', 'created_at < ?', [$clearDate]);
        
        logActivity(getCurrentUser()['id'], 'clear_audit_logs', "Cleared audit logs older than {$clearDate}", [
            'deleted_count' => $deleted,
            'clear_date' => $clearDate
        ]);
        
        setFlashMessage("Successfully deleted {$deleted} old audit logs", 'success');
        redirect($_SERVER['PHP_SELF']);
        
    } catch (Exception $e) {
        setFlashMessage('Error clearing logs: ' . $e->getMessage(), 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
}
?>

<?php include '../../includes/footer.php'; ?>
