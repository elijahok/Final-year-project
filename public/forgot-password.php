<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            // Don't reveal if email exists or not for security
            $success = 'If an account with that email exists, a password reset link has been sent.';
        } else {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
            
            // Update user record
            $db->update('users', [
                'reset_token' => $resetToken,
                'reset_expires' => $resetExpires
            ], 'id = ?', [$user['id']]);
            
            // Create reset link
            $resetLink = BASE_URL . '/public/reset-password.php?token=' . $resetToken;
            
            // In a real application, you would send this email
            // For demo purposes, we'll just show the link
            $success = "Password reset link: <a href='{$resetLink}'>{$resetLink}</a>";
            
            // Log password reset request
            logActivity($user['id'], 'password_reset_requested', 'Password reset requested for email: ' . $email);
        }
    }
}

$pageTitle = 'Forgot Password - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><?= APP_NAME ?></h2>
                        <p class="text-muted">Reset your password</p>
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

                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="Enter your email address" required>
                            </div>
                            <small class="text-muted">Enter the email address associated with your account.</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Reset Link
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

                    <div class="mt-4 border-top pt-3">
                        <div class="text-center">
                            <p class="text-muted small mb-2">Password Reset Process:</p>
                            <ol class="text-start small text-muted">
                                <li>Enter your email address</li>
                                <li>Check your email for reset link</li>
                                <li>Click the link to reset password</li>
                                <li>Create a new password</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
