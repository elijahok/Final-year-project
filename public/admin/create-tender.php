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

// Get produce categories
$categories = $db->fetchAll("SELECT * FROM produce_categories ORDER BY name");

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
    
    // Get form data
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity = (float)$_POST['quantity'];
    $unit = sanitizeInput($_POST['unit']);
    $budget_min = (float)$_POST['budget_min'];
    $budget_max = (float)$_POST['budget_max'];
    $deadline = sanitizeInput($_POST['deadline']);
    $delivery_location = sanitizeInput($_POST['delivery_location']);
    $delivery_date = sanitizeInput($_POST['delivery_date']);
    $requirements = sanitizeInput($_POST['requirements']);
    $evaluation_criteria = sanitizeInput($_POST['evaluation_criteria']);
    $special_instructions = sanitizeInput($_POST['special_instructions']);
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }
    
    if (empty($quantity) || $quantity <= 0) {
        $errors[] = "Valid quantity is required";
    }
    
    if (empty($unit)) {
        $errors[] = "Unit is required";
    }
    
    if (empty($budget_min) || $budget_min <= 0) {
        $errors[] = "Valid minimum budget is required";
    }
    
    if (empty($budget_max) || $budget_max <= 0) {
        $errors[] = "Valid maximum budget is required";
    }
    
    if ($budget_min >= $budget_max) {
        $errors[] = "Maximum budget must be greater than minimum budget";
    }
    
    if (empty($deadline)) {
        $errors[] = "Deadline is required";
    } elseif (strtotime($deadline) <= strtotime('+24 hours')) {
        $errors[] = "Deadline must be at least 24 hours from now";
    }
    
    if (empty($delivery_location)) {
        $errors[] = "Delivery location is required";
    }
    
    if (empty($delivery_date)) {
        $errors[] = "Delivery date is required";
    } elseif (strtotime($delivery_date) <= strtotime($deadline)) {
        $errors[] = "Delivery date must be after the deadline";
    }
    
    // If no errors, create tender
    if (empty($errors)) {
        try {
            // Generate unique tender number
            $tender_number = 'TEN' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Insert tender
            $tenderData = [
                'tender_number' => $tender_number,
                'title' => $title,
                'description' => $description,
                'category_id' => $category_id,
                'quantity' => $quantity,
                'unit' => $unit,
                'budget_min' => $budget_min,
                'budget_max' => $budget_max,
                'deadline' => $deadline,
                'delivery_location' => $delivery_location,
                'delivery_date' => $delivery_date,
                'requirements' => $requirements,
                'evaluation_criteria' => $evaluation_criteria,
                'special_instructions' => $special_instructions,
                'status' => 'open',
                'created_by' => getCurrentUser()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $tenderId = $db->insert('tenders', $tenderData);
            
            if ($tenderId) {
                // Log activity
                logActivity(getCurrentUser()['id'], 'create_tender', "Created tender: {$title}", ['tender_id' => $tenderId]);
                
                // Send notifications to all vendors
                $vendors = $db->fetchAll("SELECT id FROM users WHERE role = 'vendor' AND status = 'active'");
                foreach ($vendors as $vendor) {
                    createNotification(
                        $vendor['id'],
                        'New Tender Available',
                        "A new tender '{$title}' has been posted. Deadline: " . formatDate($deadline),
                        'info',
                        BASE_URL . "/public/vendor/tender-details.php?id={$tenderId}"
                    );
                }
                
                setFlashMessage('Tender created successfully! Vendors have been notified.', 'success');
                redirect(BASE_URL . '/public/admin/tenders.php');
            } else {
                $errors[] = "Failed to create tender. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If errors, set flash message and continue
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'danger');
    }
}

$pageTitle = 'Create Tender';
include '../../includes/header.php';
?>

<main class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Create New Tender</h1>
                <a href="<?= BASE_URL ?>/public/admin/tenders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Tenders
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tender Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Basic Information</h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Tender Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Please provide a tender title.
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a category.
                                </div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Provide a detailed description of the tender requirements.</div>
                                <div class="invalid-feedback">
                                    Please provide a description.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quantity and Budget -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Quantity and Budget</h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0.01" required
                                       value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Please provide a valid quantity.
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="unit" class="form-label">Unit *</label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="">Select Unit</option>
                                    <option value="kg" <?= (isset($_POST['unit']) && $_POST['unit'] == 'kg') ? 'selected' : '' ?>>Kilograms (kg)</option>
                                    <option value="tons" <?= (isset($_POST['unit']) && $_POST['unit'] == 'tons') ? 'selected' : '' ?>>Tons</option>
                                    <option value="liters" <?= (isset($_POST['unit']) && $_POST['unit'] == 'liters') ? 'selected' : '' ?>>Liters</option>
                                    <option value="pieces" <?= (isset($_POST['unit']) && $_POST['unit'] == 'pieces') ? 'selected' : '' ?>>Pieces</option>
                                    <option value="boxes" <?= (isset($_POST['unit']) && $_POST['unit'] == 'boxes') ? 'selected' : '' ?>>Boxes</option>
                                    <option value="crates" <?= (isset($_POST['unit']) && $_POST['unit'] == 'crates') ? 'selected' : '' ?>>Crates</option>
                                    <option value="bags" <?= (isset($_POST['unit']) && $_POST['unit'] == 'bags') ? 'selected' : '' ?>>Bags</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a unit.
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="budget_min" class="form-label">Min Budget (KES) *</label>
                                <input type="number" class="form-control" id="budget_min" name="budget_min" step="0.01" min="0.01" required
                                       value="<?= htmlspecialchars($_POST['budget_min'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Please provide a valid minimum budget.
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="budget_max" class="form-label">Max Budget (KES) *</label>
                                <input type="number" class="form-control" id="budget_max" name="budget_max" step="0.01" min="0.01" required
                                       value="<?= htmlspecialchars($_POST['budget_max'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Please provide a valid maximum budget.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timeline -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Timeline</h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="deadline" class="form-label">Bid Deadline *</label>
                                <input type="datetime-local" class="form-control" id="deadline" name="deadline" required
                                       value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">
                                <div class="form-text">Deadline must be at least 24 hours from now.</div>
                                <div class="invalid-feedback">
                                    Please provide a valid deadline.
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="delivery_date" class="form-label">Delivery Date *</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" required
                                       value="<?= htmlspecialchars($_POST['delivery_date'] ?? '') ?>">
                                <div class="form-text">Date must be after the bid deadline.</div>
                                <div class="invalid-feedback">
                                    Please provide a valid delivery date.
                                </div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="delivery_location" class="form-label">Delivery Location *</label>
                                <input type="text" class="form-control" id="delivery_location" name="delivery_location" required
                                       value="<?= htmlspecialchars($_POST['delivery_location'] ?? '') ?>"
                                       placeholder="e.g., Nairobi Central Market, Warehouse A">
                                <div class="invalid-feedback">
                                    Please provide a delivery location.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Requirements -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Requirements</h6>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="requirements" class="form-label">Technical Requirements</label>
                                <textarea class="form-control" id="requirements" name="requirements" rows="4"><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
                                <div class="form-text">Specify technical specifications, quality standards, certifications required, etc.</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="evaluation_criteria" class="form-label">Evaluation Criteria</label>
                                <textarea class="form-control" id="evaluation_criteria" name="evaluation_criteria" rows="4"><?= htmlspecialchars($_POST['evaluation_criteria'] ?? '') ?></textarea>
                                <div class="form-text">Specify how bids will be evaluated (price: 40%, quality: 30%, delivery: 20%, experience: 10%, etc.)</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="special_instructions" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"><?= htmlspecialchars($_POST['special_instructions'] ?? '') ?></textarea>
                                <div class="form-text">Any special instructions or conditions for vendors.</div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="<?= BASE_URL ?>/public/admin/tenders.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Create Tender
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Guidelines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tender Guidelines</h5>
                </div>
                <div class="card-body">
                    <h6>Before creating a tender:</h6>
                    <ul class="small">
                        <li>Ensure all required information is available</li>
                        <li>Set realistic budget ranges</li>
                        <li>Provide detailed requirements</li>
                        <li>Allow sufficient time for bidding</li>
                        <li>Specify clear evaluation criteria</li>
                    </ul>
                    
                    <h6 class="mt-3">Best Practices:</h6>
                    <ul class="small">
                        <li>Use clear and concise language</li>
                        <li>Include all technical specifications</li>
                        <li>Set fair delivery timelines</li>
                        <li>Consider market rates for budget</li>
                        <li>Provide contact information for queries</li>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Tender Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recentTenders = $db->fetchAll("
                        SELECT tender_number, title, status, created_at 
                        FROM tenders 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    ?>
                    
                    <?php if (empty($recentTenders)): ?>
                    <p class="text-muted small">No recent tenders</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentTenders as $tender): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 small"><?= htmlspecialchars($tender['title']) ?></h6>
                                    <small class="text-muted"><?= $tender['tender_number'] ?></small>
                                </div>
                                <span class="badge bg-<?= getStatusColor($tender['status']) ?>"><?= ucfirst($tender['status']) ?></span>
                            </div>
                            <small class="text-muted"><?= timeAgo($tender['created_at']) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Form validation
(function () {
    'use strict';
    
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Budget validation
document.getElementById('budget_min').addEventListener('input', validateBudget);
document.getElementById('budget_max').addEventListener('input', validateBudget);

function validateBudget() {
    const minBudget = parseFloat(document.getElementById('budget_min').value);
    const maxBudget = parseFloat(document.getElementById('budget_max').value);
    
    if (minBudget && maxBudget && minBudget >= maxBudget) {
        document.getElementById('budget_max').setCustomValidity('Maximum budget must be greater than minimum budget');
    } else {
        document.getElementById('budget_max').setCustomValidity('');
    }
}

// Date validation
document.getElementById('deadline').addEventListener('change', validateDates);
document.getElementById('delivery_date').addEventListener('change', validateDates);

function validateDates() {
    const deadline = new Date(document.getElementById('deadline').value);
    const deliveryDate = new Date(document.getElementById('delivery_date').value);
    const now = new Date();
    const minDeadline = new Date(now.getTime() + 24 * 60 * 60 * 1000); // 24 hours from now
    
    if (deadline && deadline < minDeadline) {
        document.getElementById('deadline').setCustomValidity('Deadline must be at least 24 hours from now');
    } else {
        document.getElementById('deadline').setCustomValidity('');
    }
    
    if (deadline && deliveryDate && deliveryDate <= deadline) {
        document.getElementById('delivery_date').setCustomValidity('Delivery date must be after the deadline');
    } else {
        document.getElementById('delivery_date').setCustomValidity('');
    }
}

// Auto-save draft (optional)
let autoSaveTimer;
const form = document.querySelector('form');

form.addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(saveDraft, 30000); // Save after 30 seconds of inactivity
});

function saveDraft() {
    const formData = new FormData(form);
    const draft = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'csrf_token') {
            draft[key] = value;
        }
    }
    
    localStorage.setItem('tender_draft', JSON.stringify(draft));
    showNotification('Draft saved automatically', 'info');
}

// Load draft on page load
window.addEventListener('load', function() {
    const draft = localStorage.getItem('tender_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        
        // Fill form fields with draft data
        Object.keys(draftData).forEach(key => {
            const field = document.getElementById(key);
            if (field) {
                field.value = draftData[key];
            }
        });
        
        showNotification('Draft loaded. You can continue editing.', 'info');
    }
});

// Clear draft on successful submission
form.addEventListener('submit', function() {
    localStorage.removeItem('tender_draft');
});
</script>

<?php include '../../includes/footer.php'; ?>
