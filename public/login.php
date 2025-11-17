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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        global $db;
        
        // Get user by email
        $user = $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        
        if ($user) {
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $error = 'Account is temporarily locked. Please try again later.';
            } else {
                // Verify password
                if (verifyPassword($password, $user['password_hash'])) {
                    // Reset login attempts
                    $db->update('users', [
                        'login_attempts' => 0,
                        'locked_until' => null,
                        'last_login' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$user['id']]);
                    
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_language'] = $user['language'] ?? DEFAULT_LANGUAGE;
                    
                    // Log successful login
                    logActivity($user['id'], 'login', 'User logged in successfully');
                    
                    // Redirect based on role
                    $redirectUrl = BASE_URL . '/public/' . $user['role'] . '/dashboard.php';
                    header('Location: ' . $redirectUrl);
                    exit();
                } else {
                    // Increment login attempts
                    $attempts = $user['login_attempts'] + 1;
                    $lockUntil = null;
                    
                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                        $error = 'Account locked due to too many failed attempts. Please try again in 15 minutes.';
                    } else {
                        $error = 'Invalid email or password. Attempts remaining: ' . (MAX_LOGIN_ATTEMPTS - $attempts);
                    }
                    
                    $db->update('users', [
                        'login_attempts' => $attempts,
                        'locked_until' => $lockUntil
                    ], 'id = ?', [$user['id']]);
                    
                    // Log failed login attempt
                    logActivity($user['id'], 'login_failed', 'Failed login attempt: ' . $error);
                }
            }
        } else {
            $error = 'Invalid email or password.';
            
            // Log failed login attempt (without user ID)
            logActivity(null, 'login_failed', 'Failed login attempt for email: ' . $email);
        }
    }
}

$pageTitle = 'Login - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><?= APP_NAME ?></h2>
                        <p class="text-muted">Welcome back! Please login to your account.</p>
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
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Login
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Don't have an account? 
                            <a href="register.php" class="text-decoration-none">Register here</a>
                        </p>
                    </div>
                    
                    <div class="mt-4 border-top pt-3">
                        <div class="text-center">
                            <p class="text-muted small mb-2">Login as:</p>
                            <div class="d-flex justify-content-center gap-2">
                                <span class="badge bg-primary">Admin</span>
                                <span class="badge bg-success">Vendor</span>
                                <span class="badge bg-info">Transporter</span>
                                <span class="badge bg-warning">Farmer</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <small class="text-muted">
                    Default Admin: admin@example.com / Admin@123
                </small>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
