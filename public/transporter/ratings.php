<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a transporter
if (!isLoggedIn() || !hasRole('transporter')) {
    redirect(BASE_URL . '/public/login.php');
    exit;
}

// Get transporter profile
$transporterProfile = getTransporterProfile(getCurrentUserId());
if (!$transporterProfile) {
    $_SESSION['flash_error'] = 'Transporter profile not found';
    redirect(BASE_URL . '/public/dashboard.php');
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get filters
$rating = (int)($_GET['rating'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query conditions
$whereConditions = ["tr.transporter_id = ?"];
$params = [$transporterProfile['id']];

if ($rating > 0 && $rating <= 5) {
    $whereConditions[] = "tr.overall_rating = ?";
    $params[] = $rating;
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);

// Get total count for pagination
$totalCount = $db->fetch("
    SELECT COUNT(*) as total
    FROM transporter_ratings tr
    {$whereClause}
", $params);

$totalPages = ceil($totalCount['total'] / $perPage);

// Get ratings
$ratings = $db->fetchAll("
    SELECT tr.*, 
           t_req.fee,
           t_req.quantity,
           t_req.weight,
           pc.name as produce_category,
           fp.farm_name,
           u_f.full_name as farmer_name,
           u_f.phone as farmer_phone,
           fp.farm_location
    FROM transporter_ratings tr
    LEFT JOIN transport_requests t_req ON tr.transport_request_id = t_req.id
    LEFT JOIN produce_categories pc ON t_req.produce_category_id = pc.id
    LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
    LEFT JOIN users u_f ON fp.user_id = u_f.id
    {$whereClause}
    ORDER BY tr.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Get rating statistics
$ratingStats = $db->fetchAll("
    SELECT 
        overall_rating,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transporter_ratings WHERE transporter_id = ?), 2) as percentage
    FROM transporter_ratings
    WHERE transporter_id = ?
    GROUP BY overall_rating
    ORDER BY overall_rating DESC
", [$transporterProfile['id'], $transporterProfile['id']]);

// Get average ratings by category
$categoryAverages = $db->fetch("
    SELECT 
        AVG(punctuality_rating) as avg_punctuality,
        AVG(professionalism_rating) as avg_professionalism,
        AVG(vehicle_condition_rating) as avg_vehicle_condition,
        AVG(communication_rating) as avg_communication
    FROM transporter_ratings
    WHERE transporter_id = ?
", [$transporterProfile['id']]);

// Get recent rating trend (last 30 days)
$recentTrend = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        AVG(overall_rating) as avg_rating,
        COUNT(*) as count
    FROM transporter_ratings
    WHERE transporter_id = ? 
    AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
", [$transporterProfile['id']]);

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">My Ratings & Reviews</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/transporter/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Ratings</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="display-4 mb-2"><?= number_format($transporterProfile['average_rating'] ?? 0, 1) ?></div>
                    <div class="h5 mb-0">Overall Rating</div>
                    <div class="small opacity-75">
                        <i class="fas fa-star"></i>
                        <?= $transporterProfile['total_ratings'] ?? 0 ?> total ratings
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <div class="display-4 mb-2"><?= number_format($categoryAverages['avg_punctuality'] ?? 0, 1) ?></div>
                    <div class="h5 mb-0">Punctuality</div>
                    <div class="small opacity-75">
                        <i class="fas fa-clock"></i> Average rating
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <div class="display-4 mb-2"><?= number_format($categoryAverages['avg_professionalism'] ?? 0, 1) ?></div>
                    <div class="h5 mb-0">Professionalism</div>
                    <div class="small opacity-75">
                        <i class="fas fa-user-tie"></i> Average rating
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <div class="display-4 mb-2"><?= number_format($categoryAverages['avg_vehicle_condition'] ?? 0, 1) ?></div>
                    <div class="h5 mb-0">Vehicle Condition</div>
                    <div class="small opacity-75">
                        <i class="fas fa-truck"></i> Average rating
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rating Distribution -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Rating Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                    foreach ($ratingStats as $stat) {
                        $distribution[$stat['overall_rating']] = $stat['count'];
                    }
                    $totalRatings = array_sum($distribution);
                    ?>
                    
                    <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <?= $star ?> 
                                <i class="fas fa-star text-warning"></i>
                                <?php if ($star === 5): ?><span class="text-muted small">(Excellent)</span><?php endif; ?>
                                <?php if ($star === 4): ?><span class="text-muted small">(Very Good)</span><?php endif; ?>
                                <?php if ($star === 3): ?><span class="text-muted small">(Good)</span><?php endif; ?>
                                <?php if ($star === 2): ?><span class="text-muted small">(Fair)</span><?php endif; ?>
                                <?php if ($star === 1): ?><span class="text-muted small">(Poor)</span><?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                <?= $distribution[$star] ?> ratings
                            </div>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= getStarColor($star) ?>" 
                                 style="width: <?= $totalRatings > 0 ? ($distribution[$star] / $totalRatings * 100) : 0 ?>%">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>30-Day Rating Trend
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentTrend)): ?>
                    <canvas id="ratingTrendChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <p>No ratings in the last 30 days</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex align-items-center">
                        <label class="me-2 mb-0">Filter by Rating:</label>
                        <select name="rating" class="form-select me-2" onchange="this.form.submit()">
                            <option value="0" <?= $rating === 0 ? 'selected' : '' ?>>All Ratings</option>
                            <option value="5" <?= $rating === 5 ? 'selected' : '' ?>>5 Stars</option>
                            <option value="4" <?= $rating === 4 ? 'selected' : '' ?>>4 Stars</option>
                            <option value="3" <?= $rating === 3 ? 'selected' : '' ?>>3 Stars</option>
                            <option value="2" <?= $rating === 2 ? 'selected' : '' ?>>2 Stars</option>
                            <option value="1" <?= $rating === 1 ? 'selected' : '' ?>>1 Star</option>
                        </select>
                        <?php if ($rating > 0): ?>
                        <a href="<?= BASE_URL ?>/public/transporter/ratings.php" class="btn btn-outline-secondary btn-sm">
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
                    <button class="btn btn-outline-primary btn-sm" onclick="exportRatings()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="printRatings()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ratings List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Recent Ratings & Reviews
                <?php if ($rating > 0): ?>
                <span class="badge bg-secondary ms-2"><?= $rating ?> Stars</span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($ratings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No ratings found</h5>
                <p class="text-muted">
                    <?php if ($rating > 0): ?>
                    You haven't received any <?= $rating ?>-star ratings yet.
                    <?php else: ?>
                    You haven't received any ratings yet. Complete deliveries to get rated!
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($ratings as $ratingItem): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <!-- Rating Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="me-3">
                                            <div class="avatar bg-success text-white rounded-circle" 
                                                 style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($ratingItem['farmer_name']) ?></h6>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($ratingItem['farm_name']) ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?= formatDate($ratingItem['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="h4 mb-0">
                                        <?= $ratingItem['overall_rating'] ?>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transport Request Details -->
                            <div class="alert alert-light mb-3">
                                <div class="row small">
                                    <div class="col-6">
                                        <div class="mb-1">
                                            <strong>Produce:</strong> <?= htmlspecialchars($ratingItem['produce_category'] ?? 'General') ?>
                                        </div>
                                        <div class="mb-1">
                                            <strong>Quantity:</strong> <?= $ratingItem['quantity'] ?> units (<?= $ratingItem['weight'] ?> kg)
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-1">
                                            <strong>Farm Location:</strong> <?= htmlspecialchars($ratingItem['farm_location'] ?? 'N/A') ?>
                                        </div>
                                        <div class="mb-1">
                                            <strong>Fee:</strong> <?= formatCurrency($ratingItem['fee']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detailed Ratings -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">Punctuality</span>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star fa-xs <?= $i <= $ratingItem['punctuality_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">Professionalism</span>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star fa-xs <?= $i <= $ratingItem['professionalism_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">Vehicle Condition</span>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star fa-xs <?= $i <= $ratingItem['vehicle_condition_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">Communication</span>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star fa-xs <?= $i <= $ratingItem['communication_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Review Text -->
                            <?php if ($ratingItem['review']): ?>
                            <div class="border-top pt-3">
                                <h6 class="small text-muted mb-2">Review:</h6>
                                <p class="mb-0 small"><?= nl2br(htmlspecialchars($ratingItem['review'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Ratings pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?rating=<?= $rating ?>&page=<?= $page - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?rating=<?= $rating ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?rating=<?= $rating ?>&page=<?= $page + 1 ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize rating trend chart if data available
    <?php if (!empty($recentTrend)): ?>
    const ctx = document.getElementById('ratingTrendChart').getContext('2d');
    const trendData = <?= json_encode(array_reverse($recentTrend)) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(item => new Date(item.date).toLocaleDateString()),
            datasets: [{
                label: 'Average Rating',
                data: trendData.map(item => parseFloat(item.avg_rating)),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>
});

// Export ratings
function exportRatings() {
    const rating = '<?= $rating ?>';
    window.location.href = '<?= BASE_URL ?>/api/export-ratings.php?rating=' + rating;
}

// Print ratings
function printRatings() {
    window.print();
}

// Helper function for star colors
function getStarColor(star) {
    const colors = {
        5: 'success',
        4: 'info',
        3: 'warning',
        2: 'secondary',
        1: 'danger'
    };
    return colors[star] || 'secondary';
}
</script>

<style>
.avatar {
    font-size: 1.2rem;
}

@media print {
    .btn-group, .breadcrumb, .card-header .btn, .pagination, .chart-container {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    
    .row {
        display: block !important;
    }
    
    .col-lg-6 {
        width: 100% !important;
        margin-bottom: 1rem !important;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
