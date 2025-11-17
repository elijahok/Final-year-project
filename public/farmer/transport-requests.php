<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || !hasRole('farmer')) {
    redirect(BASE_URL . '/public/login.php');
    exit;
}

// Get farmer profile
$farmerProfile = getFarmerProfile(getCurrentUserId());
if (!$farmerProfile) {
    $_SESSION['flash_error'] = 'Farmer profile not found';
    redirect(BASE_URL . '/public/dashboard.php');
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get filters
$status = sanitizeInput($_GET['status'] ?? 'all');
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query conditions
$whereConditions = ["tr.farmer_id = ?"];
$params = [$farmerProfile['id']];

if ($status !== 'all') {
    $whereConditions[] = "tr.status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);

// Get total count for pagination
$totalCount = $db->fetch("
    SELECT COUNT(*) as total
    FROM transport_requests tr
    {$whereClause}
", $params);

$totalPages = ceil($totalCount['total'] / $perPage);

// Get transport requests
$requests = $db->fetchAll("
    SELECT tr.*, 
           tp.user_id as transporter_user_id,
           u_t.full_name as transporter_name,
           tp.vehicle_type as transporter_vehicle,
           tp.average_rating as transporter_rating,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN transporter_profiles tp ON tr.transporter_id = tp.id
    LEFT JOIN users u_t ON tp.user_id = u_t.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    {$whereClause}
    ORDER BY tr.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ?", [$farmerProfile['id']])['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'pending'", [$farmerProfile['id']])['count'],
    'accepted' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'accepted'", [$farmerProfile['id']])['count'],
    'in_transit' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'in_transit'", [$farmerProfile['id']])['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'completed'", [$farmerProfile['id']])['count'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE farmer_id = ? AND status = 'cancelled'", [$farmerProfile['id']])['count']
];

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Transport Requests</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/farmer/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Transport Requests</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['total'] ?></h4>
                            <div class="small">Total Requests</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-truck fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['pending'] ?></h4>
                            <div class="small">Pending</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['accepted'] ?></h4>
                            <div class="small">Accepted</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['in_transit'] ?></h4>
                            <div class="small">In Transit</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shipping-fast fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['completed'] ?></h4>
                            <div class="small">Completed</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['cancelled'] ?></h4>
                            <div class="small">Cancelled</div>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex align-items-center">
                        <label class="me-2 mb-0">Filter:</label>
                        <select name="status" class="form-select me-2" onchange="this.form.submit()">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="in_transit" <?= $status === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <?php if ($status !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/public/farmer/transport-requests.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-end">
                    <a href="<?= BASE_URL ?>/public/farmer/request-transport.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Transport Request
                    </a>
                    <button class="btn btn-outline-primary btn-sm ms-2" onclick="exportRequests()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="printRequests()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transport Requests List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Transport Requests
                <?php if ($status !== 'all'): ?>
                <span class="badge bg-secondary ms-2"><?= ucfirst(str_replace('_', ' ', $status)) ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No transport requests found</h5>
                <p class="text-muted">
                    <?php if ($status === 'all'): ?>
                    You haven't created any transport requests yet.
                    <?php else: ?>
                    No transport requests with status "<?= ucfirst(str_replace('_', ' ', $status)) ?>" found.
                    <?php endif; ?>
                </p>
                <a href="<?= BASE_URL ?>/public/farmer/request-transport.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Your First Request
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="requestsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produce</th>
                            <th>Quantity</th>
                            <th>Pickup</th>
                            <th>Delivery</th>
                            <th>Transporter</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary">#<?= $request['id'] ?></span>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($request['produce_category'] ?? 'General') ?></strong>
                                    <?php if ($request['urgency']): ?>
                                    <div class="small">
                                        <span class="badge bg-<?= getUrgencyColor($request['urgency']) ?>">
                                            <?= ucfirst($request['urgency']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?= $request['quantity'] ?></strong> units
                                    <div class="small text-muted"><?= $request['weight'] ?> kg</div>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <div><strong><?= formatDate($request['pickup_date']) ?></strong></div>
                                    <div class="text-muted"><?= formatTime($request['pickup_time']) ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="small text-truncate" style="max-width: 150px;">
                                    <?= nl2br(htmlspecialchars(substr($request['delivery_location'], 0, 30))) ?>
                                    <?php if (strlen($request['delivery_location']) > 30): ?>
                                    ...
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($request['transporter_name']): ?>
                                <div>
                                    <strong><?= htmlspecialchars($request['transporter_name']) ?></strong>
                                    <?php if ($request['transporter_rating']): ?>
                                    <div class="small">
                                        <i class="fas fa-star text-warning"></i>
                                        <?= number_format($request['transporter_rating'], 1) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= formatCurrency($request['fee']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusColor($request['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                </span>
                                <?php if ($request['rating']): ?>
                                <div class="small mt-1">
                                    <i class="fas fa-star text-warning"></i>
                                    <?= $request['rating'] ?>/5
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    <div><?= formatDate($request['created_at']) ?></div>
                                    <div class="text-muted"><?= timeAgo($request['created_at']) ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewDetails(<?= $request['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($request['status'] === 'completed' && !$request['rating']): ?>
                                    <a href="<?= BASE_URL ?>/public/farmer/rate-transporter.php?request=<?= $request['id'] ?>" 
                                       class="btn btn-outline-warning" title="Rate Transporter">
                                        <i class="fas fa-star"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($request['status'] === 'pending'): ?>
                                    <button class="btn btn-outline-danger" onclick="cancelRequest(<?= $request['id'] ?>)" title="Cancel Request">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Transport requests pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?status=<?= $status ?>&page=<?= $page - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?status=<?= $status ?>&page=<?= $page + 1 ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transport Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Transport Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this transport request?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> If the request has already been accepted, you may still be charged a cancellation fee.
                </div>
                <form id="cancelForm">
                    <input type="hidden" name="request_id" id="cancelRequestId">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Cancellation Reason</label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Request</button>
                <button type="button" class="btn btn-danger" onclick="confirmCancel()">Yes, Cancel Request</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// View request details
function viewDetails(requestId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const contentDiv = document.getElementById('detailsContent');
    
    // Show loading state
    contentDiv.innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading details...</p>
        </div>
    `;
    
    modal.show();
    
    // Fetch details
    fetch('<?= BASE_URL ?>/api/transport-request-details.php?id=' + requestId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            contentDiv.innerHTML = data.html;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error || 'Failed to load details'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        contentDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Failed to load request details. Please try again.
            </div>
        `;
    });
}

// Cancel request
function cancelRequest(requestId) {
    document.getElementById('cancelRequestId').value = requestId;
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}

function confirmCancel() {
    const form = document.getElementById('cancelForm');
    const formData = new FormData(form);
    
    fetch('<?= BASE_URL ?>/api/cancel-transport-request.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
            location.reload();
        } else {
            alert(data.error || 'Failed to cancel request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to cancel request. Please try again.');
    });
}

// Export requests
function exportRequests() {
    const status = '<?= $status ?>';
    window.location.href = '<?= BASE_URL ?>/api/export-transport-requests.php?status=' + status;
}

// Print requests
function printRequests() {
    window.print();
}

// Helper function for urgency color
function getUrgencyColor(urgency) {
    const colors = {
        'low': 'success',
        'medium': 'info',
        'high': 'warning',
        'emergency': 'danger'
    };
    return colors[urgency] || 'secondary';
}
</script>

<style>
@media print {
    .btn-group, .breadcrumb, .card-header .btn, .pagination {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 12px;
    }
    
    .badge {
        border: 1px solid #000 !important;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
