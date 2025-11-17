<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verify token
if (empty($token)) {
    $error = 'Invalid reset token.';
} else {
    $user = $db->fetch("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token]);
    
    if (!$user) {
        $error = 'Invalid or expired reset token.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Hash new password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user record
        $db->update('users', [
            'password_hash' => $passwordHash,
            'reset_token' => null,
            'reset_expires' => null,
            'login_attempts' => 0,
            'locked_until' => null
        ], 'id = ?', [$user['id']]);
        
        $success = 'Password has been reset successfully. You can now login with your new password.';
        
        // Log password reset
        logActivity($user['id'], 'password_reset_completed', 'Password reset completed');
        
        // Redirect to login after 3 seconds
        header('refresh:3;url=' . BASE_URL . '/public/login.php');
    }
}

$pageTitle = 'Reset Password - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><?= APP_NAME ?></h2>
                        <p class="text-muted">Create a new password</p>
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
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Go to Login
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($error) && empty($success)): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Enter new password" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Password must be at least 8 characters long</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       placeholder="Confirm new password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-key me-2"></i>
                                Reset Password
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Login
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($error) && empty($success)): ?>
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
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
