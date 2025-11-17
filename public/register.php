<?php
require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $userRole = $_SESSION['user_role'];
    $redirectUrl = BASE_URL . '/public/' . $userRole . '/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit();
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password) || empty($confirmPassword) || empty($fullName) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['vendor', 'transporter', 'farmer'])) {
        $error = 'Invalid role selected.';
    } else {
        global $db;
        
        // Check if email already exists
        $existingUser = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existingUser) {
            $error = 'An account with this email already exists.';
        } else {
            // Create new user
            $passwordHash = hashPassword($password);
            
            $userData = [
                'email' => $email,
                'password_hash' => $passwordHash,
                'full_name' => $fullName,
                'phone' => $phone,
                'role' => $role,
                'status' => 'active',
                'language' => DEFAULT_LANGUAGE
            ];
            
            $userId = $db->insert('users', $userData);
            
            if ($userId) {
                // Create profile based on role
                if ($role === 'vendor') {
                    $vendorData = [
                        'user_id' => $userId,
                        'company_name' => $fullName . ' Company',
                        'company_address' => '',
                        'contact_person' => $fullName,
                        'contact_phone' => $phone
                    ];
                    $db->insert('vendor_profiles', $vendorData);
                } elseif ($role === 'transporter') {
                    $transporterData = [
                        'user_id' => $userId,
                        'company_name' => $fullName . ' Transport',
                        'vehicle_type' => 'truck',
                        'vehicle_capacity' => 1000.00,
                        'base_location' => 'Nairobi'
                    ];
                    $db->insert('transporter_profiles', $transporterData);
                } elseif ($role === 'farmer') {
                    $farmerData = [
                        'user_id' => $userId,
                        'farm_name' => $fullName . ' Farm',
                        'farm_size' => 1.00,
                        'farming_type' => 'conventional'
                    ];
                    $db->insert('farmer_profiles', $farmerData);
                }
                
                // Create wallet for user
                $walletData = [
                    'user_id' => $userId,
                    'balance' => 0.00,
                    'available_balance' => 0.00,
                    'frozen_balance' => 0.00,
                    'currency' => 'KES'
                ];
                $db->insert('user_wallets', $walletData);
                
                // Log registration
                logActivity($userId, 'register', "User registered as $role");
                
                // Send welcome notification
                addNotification($userId, 'Welcome to ' . APP_NAME, 'Your account has been created successfully. Please complete your profile.', 'success');
                
                $success = 'Registration successful! You can now login.';
                
                // Redirect to login after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>";
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">Create Account</h2>
                        <p class="text-muted">Join <?= APP_NAME ?> and start your journey</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                                           placeholder="Enter your full name" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="Enter your email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                           placeholder="Enter phone number">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Account Type *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Account Type</option>
                                        <option value="vendor" <?= ($_POST['role'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                                        <option value="transporter" <?= ($_POST['role'] ?? '') === 'transporter' ? 'selected' : '' ?>>Transporter</option>
                                        <option value="farmer" <?= ($_POST['role'] ?? '') === 'farmer' ? 'selected' : '' ?>>Farmer</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Password must be at least 8 characters long</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" class="text-decoration-none" target="_blank">Terms and Conditions</a>
                                    and <a href="privacy.php" class="text-decoration-none" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>
                                Create Account
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account? 
                            <a href="login.php" class="text-decoration-none">Login here</a>
                        </p>
                    </div>
                    
                    <div class="mt-4 border-top pt-3">
                        <div class="row text-center">
                            <div class="col-md-4 mb-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-store text-success me-2"></i>
                                    <div>
                                        <h6 class="mb-0">Vendor</h6>
                                        <small class="text-muted">Submit bids on tenders</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-truck text-info me-2"></i>
                                    <div>
                                        <h6 class="mb-0">Transporter</h6>
                                        <small class="text-muted">Provide transport services</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-seedling text-warning me-2"></i>
                                    <div>
                                        <h6 class="mb-0">Farmer</h6>
                                        <small class="text-muted">Sell farm produce</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('confirm_password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
