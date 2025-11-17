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

// Get request ID
$requestId = (int)($_GET['request'] ?? 0);

if (!$requestId) {
    $_SESSION['flash_error'] = 'Transport request ID is required';
    redirect(BASE_URL . '/public/farmer/dashboard.php');
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get transport request details
$request = $db->fetch("
    SELECT tr.*, 
           fp.farm_name,
           u_f.full_name as farmer_name,
           tp.user_id as transporter_user_id,
           u_t.full_name as transporter_name,
           tp.vehicle_type,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
    LEFT JOIN users u_f ON fp.user_id = u_f.id
    LEFT JOIN transporter_profiles tp ON tr.transporter_id = tp.id
    LEFT JOIN users u_t ON tp.user_id = u_t.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    WHERE tr.id = ?
", [$requestId]);

if (!$request) {
    $_SESSION['flash_error'] = 'Transport request not found';
    redirect(BASE_URL . '/public/farmer/dashboard.php');
    exit;
}

// Check if the current farmer owns this request
$farmerProfile = getFarmerProfile(getCurrentUserId());
if (!$farmerProfile || $farmerProfile['id'] != $request['farmer_id']) {
    $_SESSION['flash_error'] = 'Access denied';
    redirect(BASE_URL . '/public/farmer/dashboard.php');
    exit;
}

// Check if request is completed
if ($request['status'] !== 'completed') {
    $_SESSION['flash_error'] = 'This transport request has not been completed yet';
    redirect(BASE_URL . '/public/farmer/transport-requests.php?id=' . $requestId);
    exit;
}

// Check if rating has already been given
if ($request['rating']) {
    $_SESSION['flash_info'] = 'You have already rated this transport request';
    redirect(BASE_URL . '/public/farmer/transport-requests.php?id=' . $requestId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request';
        redirect(BASE_URL . '/public/farmer/rate-transporter.php?request=' . $requestId);
        exit;
    }
    
    // Get form data
    $rating = (int)($_POST['rating'] ?? 0);
    $review = sanitizeInput($_POST['review'] ?? '');
    $punctuality = (int)($_POST['punctuality'] ?? 0);
    $professionalism = (int)($_POST['professionalism'] ?? 0);
    $vehicle_condition = (int)($_POST['vehicle_condition'] ?? 0);
    $communication = (int)($_POST['communication'] ?? 0);
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['flash_error'] = 'Rating must be between 1 and 5 stars';
        redirect(BASE_URL . '/public/farmer/rate-transporter.php?request=' . $requestId);
        exit;
    }
    
    // Validate sub-ratings
    $subRatings = [$punctuality, $professionalism, $vehicle_condition, $communication];
    foreach ($subRatings as $subRating) {
        if ($subRating < 1 || $subRating > 5) {
            $_SESSION['flash_error'] = 'All ratings must be between 1 and 5';
            redirect(BASE_URL . '/public/farmer/rate-transporter.php?request=' . $requestId);
            exit;
        }
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Update transport request with rating
        $db->update('transport_requests', [
            'rating' => $rating,
            'review' => $review,
            'rated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$requestId]);
        
        // Insert detailed rating record
        $ratingData = [
            'transport_request_id' => $requestId,
            'transporter_id' => $request['transporter_id'],
            'farmer_id' => $farmerProfile['id'],
            'overall_rating' => $rating,
            'punctuality_rating' => $punctuality,
            'professionalism_rating' => $professionalism,
            'vehicle_condition_rating' => $vehicle_condition,
            'communication_rating' => $communication,
            'review' => $review,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('transporter_ratings', $ratingData);
        
        // Update transporter's average rating
        $avgRating = $db->fetch("
            SELECT AVG(overall_rating) as avg_rating, COUNT(*) as total_ratings
            FROM transporter_ratings
            WHERE transporter_id = ?
        ", [$request['transporter_id']]);
        
        if ($avgRating && $avgRating['total_ratings'] > 0) {
            $db->update('transporter_profiles', [
                'average_rating' => round($avgRating['avg_rating'], 2),
                'total_ratings' => $avgRating['total_ratings'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$request['transporter_id']]);
        }
        
        // Log activity
        logActivity(
            getCurrentUserId(),
            'rate_transporter',
            'transport_request',
            $requestId,
            "Farmer rated transporter {$rating} stars for request #{$requestId}",
            $_SERVER['REMOTE_ADDR']
        );
        
        // Create notification for transporter
        $notificationData = [
            'user_id' => $request['transporter_user_id'],
            'title' => 'New Rating Received',
            'message' => "You received a {$rating}-star rating from {$farmerProfile['farm_name']} for transport request #{$requestId}.",
            'type' => 'info',
            'icon' => 'fa-star',
            'action_url' => BASE_URL . '/public/transporter/ratings.php',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('notifications', $notificationData);
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['flash_success'] = 'Thank you for rating the transporter! Your feedback helps improve our service.';
        redirect(BASE_URL . '/public/farmer/transport-requests.php?id=' . $requestId);
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        
        // Log error
        error_log("Error submitting transporter rating: " . $e->getMessage());
        
        $_SESSION['flash_error'] = 'An error occurred while submitting your rating. Please try again.';
        redirect(BASE_URL . '/public/farmer/rate-transporter.php?request=' . $requestId);
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Rate Transporter</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/farmer/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/farmer/transport-requests.php">Transport Requests</a></li>
                        <li class="breadcrumb-item active">Rate Transporter</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-star text-warning me-2"></i>
                        Rate Transport Service
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Transport Request Summary -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading mb-2">Transport Request #<?= $request['id'] ?></h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>Transporter:</strong> <?= htmlspecialchars($request['transporter_name']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Vehicle:</strong> <?= ucfirst(str_replace('_', ' ', $request['vehicle_type'])) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Produce:</strong> <?= htmlspecialchars($request['produce_category']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>Quantity:</strong> <?= $request['quantity'] ?> units (<?= $request['weight'] ?> kg)
                                </div>
                                <div class="mb-2">
                                    <strong>Completed:</strong> <?= formatDate($request['completed_at']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Fee:</strong> <?= formatCurrency($request['fee']) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rating Form -->
                    <form method="POST" id="ratingForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <!-- Overall Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Overall Rating *</label>
                            <div class="d-flex align-items-center mb-2">
                                <div class="rating-stars" id="overallRating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="far fa-star fa-2x text-warning" data-rating="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="ms-3 fs-5" id="ratingText">Select rating</span>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" required>
                            <div class="form-text">Click on the stars to rate the overall service</div>
                        </div>

                        <!-- Detailed Ratings -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Punctuality *</label>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars-small" id="punctualityRating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star text-warning" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="ms-2" id="punctualityText">-</span>
                                    </div>
                                    <input type="hidden" name="punctuality" id="punctualityValue" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Professionalism *</label>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars-small" id="professionalismRating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star text-warning" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="ms-2" id="professionalismText">-</span>
                                    </div>
                                    <input type="hidden" name="professionalism" id="professionalismValue" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Condition *</label>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars-small" id="vehicleRating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star text-warning" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="ms-2" id="vehicleText">-</span>
                                    </div>
                                    <input type="hidden" name="vehicle_condition" id="vehicleValue" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Communication *</label>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars-small" id="communicationRating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star text-warning" data-rating="<?= $i ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="ms-2" id="communicationText">-</span>
                                    </div>
                                    <input type="hidden" name="communication" id="communicationValue" required>
                                </div>
                            </div>
                        </div>

                        <!-- Review -->
                        <div class="mb-4">
                            <label for="review" class="form-label">Review (Optional)</label>
                            <textarea class="form-control" id="review" name="review" rows="4" 
                                      placeholder="Share your experience with this transporter..."></textarea>
                            <div class="form-text">Tell us about your experience (maximum 500 characters)</div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/public/farmer/transport-requests.php?id=<?= $requestId ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Request
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Submit Rating
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Rating Guidelines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Rating Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-success me-2">5</span>
                            <span>Excellent - Exceeded expectations</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-info me-2">4</span>
                            <span>Very Good - Met expectations</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-warning me-2">3</span>
                            <span>Good - Acceptable service</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-secondary me-2">2</span>
                            <span>Fair - Below expectations</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-2">1</span>
                            <span>Poor - Unacceptable service</span>
                        </div>
                    </div>
                    <div class="alert alert-light">
                        <small class="text-muted">
                            Your honest feedback helps us maintain high service quality and assists other farmers in making informed decisions.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Transporter Info -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2"></i>Transporter Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar bg-primary text-white rounded-circle mx-auto mb-2" 
                             style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h6 class="mb-1"><?= htmlspecialchars($request['transporter_name']) ?></h6>
                        <div class="text-muted small">
                            <?php if ($request['average_rating']): ?>
                            <i class="fas fa-star text-warning"></i>
                            <?= number_format($request['average_rating'], 1) ?> 
                            (<?= $request['total_ratings'] ?> ratings)
                            <?php else: ?>
                            <span class="text-muted">No ratings yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="small">
                        <div class="mb-2">
                            <strong>Vehicle Type:</strong><br>
                            <?= ucfirst(str_replace('_', ' ', $request['vehicle_type'])) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Completed Deliveries:</strong><br>
                            <?php
                            $completedCount = $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE transporter_id = ? AND status = 'completed'", [$request['transporter_id']]);
                            echo $completedCount['count'] ?? 0;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-stars i {
    cursor: pointer;
    transition: all 0.2s;
}

.rating-stars i:hover,
.rating-stars i.active {
    transform: scale(1.1);
}

.rating-stars-small i {
    cursor: pointer;
    font-size: 1.2rem;
    margin-right: 2px;
    transition: all 0.2s;
}

.rating-stars-small i:hover,
.rating-stars-small i.active {
    transform: scale(1.1);
}

.avatar {
    font-size: 2rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Overall rating
    const overallStars = document.querySelectorAll('#overallRating i');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    
    const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    
    overallStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingValue.value = rating;
            ratingText.textContent = ratingTexts[rating];
            
            overallStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            overallStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });
    
    document.getElementById('overallRating').addEventListener('mouseleave', function() {
        const currentRating = parseInt(ratingValue.value) || 0;
        overallStars.forEach((s, index) => {
            if (index < currentRating) {
                s.classList.remove('far');
                s.classList.add('fas', 'active');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    });
    
    // Sub-ratings
    const subRatings = [
        { id: 'punctuality', valueId: 'punctualityValue', textId: 'punctualityText' },
        { id: 'professionalism', valueId: 'professionalismValue', textId: 'professionalismText' },
        { id: 'vehicle', valueId: 'vehicleValue', textId: 'vehicleText' },
        { id: 'communication', valueId: 'communicationValue', textId: 'communicationText' }
    ];
    
    subRatings.forEach(rating => {
        const stars = document.querySelectorAll(`#${rating.id}Rating i`);
        const valueInput = document.getElementById(rating.valueId);
        const textSpan = document.getElementById(rating.textId);
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const ratingValue = parseInt(this.dataset.rating);
                valueInput.value = ratingValue;
                textSpan.textContent = ratingValue;
                
                stars.forEach((s, index) => {
                    if (index < ratingValue) {
                        s.classList.remove('far');
                        s.classList.add('fas', 'active');
                    } else {
                        s.classList.remove('fas', 'active');
                        s.classList.add('far');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const ratingValue = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < ratingValue) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
        });
        
        document.getElementById(`${rating.id}Rating`).addEventListener('mouseleave', function() {
            const currentRating = parseInt(valueInput.value) || 0;
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });
    });
    
    // Form validation
    document.getElementById('ratingForm').addEventListener('submit', function(e) {
        const overallRating = parseInt(document.getElementById('ratingValue').value);
        const punctuality = parseInt(document.getElementById('punctualityValue').value);
        const professionalism = parseInt(document.getElementById('professionalismValue').value);
        const vehicle = parseInt(document.getElementById('vehicleValue').value);
        const communication = parseInt(document.getElementById('communicationValue').value);
        
        if (!overallRating || !punctuality || !professionalism || !vehicle || !communication) {
            e.preventDefault();
            alert('Please provide all required ratings');
            return;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
    
    // Character counter for review
    const reviewTextarea = document.getElementById('review');
    const maxLength = 500;
    
    reviewTextarea.addEventListener('input', function() {
        const remaining = maxLength - this.value.length;
        if (remaining < 0) {
            this.value = this.value.substring(0, maxLength);
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
