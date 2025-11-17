<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
$verified = false;
$error = '';

if (!empty($token)) {
    $user = $db->fetch("SELECT * FROM users WHERE email_verification_token = ?", [$token]);
    
    if ($user) {
        // Check if token is not expired (24 hours)
        $tokenExpiry = date('Y-m-d H:i:s', strtotime($user['created_at']) + 86400);
        
        if (date('Y-m-d H:i:s') <= $tokenExpiry) {
            // Verify email
            $db->update('users', [
                'email_verified' => 1,
                'email_verification_token' => null,
                'status' => 'active'
            ], 'id = ?', [$user['id']]);
            
            $verified = true;
            
            // Log email verification
            logActivity($user['id'], 'email_verified', 'Email address verified successfully');
            
            // Send welcome notification
            addNotification($user['id'], 'Email Verified!', 'Your email has been successfully verified. You can now use all features of your account.', 'success');
        } else {
            $error = 'Verification token has expired. Please register again or contact support.';
        }
    } else {
        $error = 'Invalid verification token.';
    }
} else {
    $error = 'No verification token provided.';
}

$pageTitle = 'Email Verification - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5 text-center">
                    <?php if ($verified): ?>
                        <div class="mb-4">
                            <i class="fas fa-check-circle fa-5x text-success"></i>
                        </div>
                        <h2 class="fw-bold text-success mb-3">Email Verified!</h2>
                        <p class="text-muted mb-4">Your email address has been successfully verified. Your account is now active.</p>
                        
                        <div class="d-grid">
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Login to Your Account
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle fa-5x text-warning"></i>
                        </div>
                        <h2 class="fw-bold text-warning mb-3">Verification Failed</h2>
                        <p class="text-muted mb-4"><?= $error ?: 'An error occurred during email verification.' ?></p>
                        
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>
                                Register Again
                            </a>
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Go to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
