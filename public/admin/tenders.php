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
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalTenders = $db->fetch("SELECT COUNT(*) as count FROM tenders")['count'];
$totalPages = ceil($totalTenders / $perPage);

// Filters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build query
$whereConditions = [];
$params = [];

if ($status) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status;
}

if ($category) {
    $whereConditions[] = "t.category_id = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.tender_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Valid sort columns
$validSorts = ['created_at', 'deadline', 'title', 'budget_max', 'status'];
if (!in_array($sortBy, $validSorts)) {
    $sortBy = 'created_at';
}

// Fetch tenders
$tenders = $db->fetchAll("
    SELECT t.*, pc.name as category_name, u.full_name as created_by_name,
           COUNT(b.id) as bid_count
    FROM tenders t
    LEFT JOIN produce_categories pc ON t.category_id = pc.id
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN bids b ON t.id = b.tender_id
    $whereClause
    GROUP BY t.id
    ORDER BY t.$sortBy $sortOrder
    LIMIT $perPage OFFSET $offset
", $params);

// Get categories for filter
$categories = $db->fetchAll("SELECT * FROM produce_categories ORDER BY name");

// Statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM tenders")['count'],
    'open' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'open'")['count'],
    'closed' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'closed'")['count'],
    'awarded' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'awarded'")['count'],
    'pending_approval' => $db->fetch("SELECT COUNT(*) as count FROM tenders WHERE status = 'pending_approval'")['count']
];

$pageTitle = 'Manage Tenders';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3">Manage Tenders</h1>
                <a href="<?= BASE_URL ?>/public/admin/create-tender.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New Tender
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card primary">
                <div class="d-flex align-items-center">
                    <div class="stat-icon primary">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0"><?= $stats['total'] ?></h5>
                        <small class="text-muted">Total Tenders</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card success">
                <div class="d-flex align-items-center">
                    <div class="stat-icon success">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0"><?= $stats['open'] ?></h5>
                        <small class="text-muted">Open Tenders</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card warning">
                <div class="d-flex align-items-center">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0"><?= $stats['pending_approval'] ?></h5>
                        <small class="text-muted">Pending Approval</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card info">
                <div class="d-flex align-items-center">
                    <div class="stat-icon info">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0"><?= $stats['awarded'] ?></h5>
                        <small class="text-muted">Awarded</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search tenders...">
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="awarded" <?= $status === 'awarded' ? 'selected' : '' ?>>Awarded</option>
                        <option value="pending_approval" <?= $status === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Created Date</option>
                        <option value="deadline" <?= $sortBy === 'deadline' ? 'selected' : '' ?>>Deadline</option>
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="budget_max" <?= $sortBy === 'budget_max' ? 'selected' : '' ?>>Budget</option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="order" class="form-label">Order</label>
                    <select class="form-select" id="order" name="order">
                        <option value="desc" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="asc" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <a href="<?= BASE_URL ?>/public/admin/tenders.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tenders Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Tenders (<?= count($tenders) ?> of <?= $totalTenders ?>)</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="exportTenders()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-primary" onclick="printTenders()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($tenders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No tenders found</h5>
                <p class="text-muted">No tenders match your current filters.</p>
                <a href="<?= BASE_URL ?>/public/admin/create-tender.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Your First Tender
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="tendersTable">
                    <thead>
                        <tr>
                            <th>Tender Number</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Budget Range</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Bids</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenders as $tender): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($tender['tender_number']) ?></span>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($tender['title']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars(substr($tender['description'], 0, 100)) ?>...</small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($tender['category_name'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <?= formatCurrency($tender['budget_min']) ?> - <?= formatCurrency($tender['budget_max']) ?>
                            </td>
                            <td>
                                <div>
                                    <?= formatDate($tender['deadline']) ?>
                                    <br>
                                    <?php
                                    $deadline = new DateTime($tender['deadline']);
                                    $now = new DateTime();
                                    $interval = $now->diff($deadline);
                                    if ($deadline > $now) {
                                        echo '<small class="text-success">' . $interval->days . ' days left</small>';
                                    } else {
                                        echo '<small class="text-danger">Expired</small>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatusColor($tender['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $tender['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= $tender['bid_count'] ?></span>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($tender['created_by_name'] ?? 'Unknown') ?></small>
                                <br>
                                <small class="text-muted"><?= formatDate($tender['created_at']) ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="viewTender(<?= $tender['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="editTender(<?= $tender['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewBids(<?= $tender['id'] ?>)" title="View Bids">
                                        <i class="fas fa-gavel"></i>
                                    </button>
                                    <?php if ($tender['status'] === 'open'): ?>
                                    <button class="btn btn-outline-warning" onclick="closeTender(<?= $tender['id'] ?>)" title="Close Tender">
                                        <i class="fas fa-door-closed"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-danger" onclick="deleteTender(<?= $tender['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Tenders pagination">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>">Previous</a>
            </li>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</main>

<!-- Tender Details Modal -->
<div class="modal fade" id="tenderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tender Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="tenderModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bids Modal -->
<div class="modal fade" id="bidsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tender Bids</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bidsModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// View tender details
function viewTender(tenderId) {
    const modal = new bootstrap.Modal(document.getElementById('tenderModal'));
    const modalBody = document.getElementById('tenderModalBody');
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch(`<?= BASE_URL ?>/api/tender-details.php?id=${tenderId}`, {
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
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load tender details</div>';
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading tender details</div>';
    });
}

// Edit tender
function editTender(tenderId) {
    window.location.href = `<?= BASE_URL ?>/public/admin/edit-tender.php?id=${tenderId}`;
}

// View bids
function viewBids(tenderId) {
    const modal = new bootstrap.Modal(document.getElementById('bidsModal'));
    const modalBody = document.getElementById('bidsModalBody');
    
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch(`<?= BASE_URL ?>/api/tender-bids.php?id=${tenderId}`, {
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
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load bids</div>';
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading bids</div>';
    });
}

// Close tender
function closeTender(tenderId) {
    if (confirm('Are you sure you want to close this tender? No further bids will be accepted.')) {
        fetch(`<?= BASE_URL ?>/api/close-tender.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                tender_id: tenderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Tender closed successfully', 'success');
                location.reload();
            } else {
                showNotification(data.error || 'Failed to close tender', 'danger');
            }
        })
        .catch(error => {
            showNotification('Error closing tender', 'danger');
        });
    }
}

// Delete tender
function deleteTender(tenderId) {
    if (confirm('Are you sure you want to delete this tender? This action cannot be undone.')) {
        fetch(`<?= BASE_URL ?>/api/delete-tender.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                tender_id: tenderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Tender deleted successfully', 'success');
                location.reload();
            } else {
                showNotification(data.error || 'Failed to delete tender', 'danger');
            }
        })
        .catch(error => {
            showNotification('Error deleting tender', 'danger');
        });
    }
}

// Export tenders
function exportTenders() {
    const url = new URL(window.location);
    url.searchParams.set('export', 'csv');
    window.open(url.toString());
}

// Print tenders
function printTenders() {
    window.print();
}

// Build pagination URL
function buildPaginationUrl(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    return url.toString();
}

// Auto-refresh for expiring tenders
setInterval(() => {
    const deadlineCells = document.querySelectorAll('td:nth-child(5)');
    deadlineCells.forEach(cell => {
        const deadlineText = cell.textContent.trim();
        if (deadlineText.includes('days left')) {
            // Update countdown
            const daysMatch = deadlineText.match(/(\d+) days left/);
            if (daysMatch) {
                const days = parseInt(daysMatch[1]);
                if (days > 0) {
                    const newDays = days - 1;
                    cell.innerHTML = cell.innerHTML.replace(/\d+ days left/, `${newDays} days left`);
                    if (newDays <= 2) {
                        cell.querySelector('small').className = 'text-warning';
                    }
                    if (newDays === 0) {
                        cell.innerHTML = cell.innerHTML.replace(/<small class="[^"]*">.*?<\/small>/, '<small class="text-danger">Expires today</small>');
                    }
                }
            }
        }
    });
}, 60000); // Update every minute
</script>

<?php
// Helper function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return BASE_URL . '/public/admin/tenders.php?' . http_build_query($params);
}
?>

<?php include '../../includes/footer.php'; ?>
